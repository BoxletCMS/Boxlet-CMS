<?php

namespace App\Support;

use RuntimeException;

/**
 * Writes a ZIP one entry at a time, across as many requests as it takes (PLAN.md D-139).
 *
 * WHY NOT ZipArchive. libzip writes an archive whole when it is closed: add ten files to an
 * archive of 500 MB and close it, and all 500 MB are copied to a new file. A backup made in
 * thirty-second steps on a shared host would copy itself once per step. This appends: each
 * entry is written at the end of the file and never touched again, and the central
 * directory, which a ZIP keeps at its end, is gathered in a file beside it and written once,
 * by finish().
 *
 * ACROSS REQUESTS. checkpoint() says how far the archive is whole. The caller keeps that
 * beside its own progress, in one write, and hands it back to the next request's
 * constructor, which cuts off anything written after it. A request killed half-way through
 * an entry therefore costs that entry, never the archive.
 *
 * ZIP64 where the classic format runs out: more than 65,535 entries, or an entry that starts
 * past 4 GB. A single entry may not be 4 GB or more, which nothing Boxlet keeps comes near.
 *
 * Pictures and archives are stored as they are, because they are compressed already and
 * deflating them again costs time for nothing. Everything else is deflated.
 */
final class ZipWriter
{
    private const STORED = ['avif', 'webp', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'gz', 'mp4', 'webm', 'mp3', 'pdf', 'mmdb', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp'];
    private const CHUNK = 1048576;
    private const MAX32 = 0xFFFFFFFF;

    /** @var resource */
    private $archive;
    /** @var resource */
    private $directory;
    private int $length;
    private int $directoryLength;
    private int $count;

    /**
     * @param array{length: int, directory: int, count: int}|null $checkpoint where a
     *        previous request left the archive whole; null starts a new one
     */
    public function __construct(private readonly string $path, ?array $checkpoint = null)
    {
        if ($checkpoint === null) {
            foreach ([$path, $path . '.directory'] as $old) {
                if (is_file($old)) {
                    unlink($old);
                }
            }
        }
        $this->archive = self::open($path);
        $this->directory = self::open($path . '.directory');
        $this->length = $checkpoint['length'] ?? 0;
        $this->directoryLength = $checkpoint['directory'] ?? 0;
        $this->count = $checkpoint['count'] ?? 0;
        // Whatever a killed request wrote past the checkpoint is not part of the archive.
        ftruncate($this->archive, max(0, $this->length));
        ftruncate($this->directory, max(0, $this->directoryLength));
        fseek($this->archive, $this->length);
        fseek($this->directory, $this->directoryLength);
    }

    /**
     * @return array{length: int, directory: int, count: int}
     */
    public function checkpoint(): array
    {
        fflush($this->archive);
        fflush($this->directory);

        return ['length' => $this->length, 'directory' => $this->directoryLength, 'count' => $this->count];
    }

    /** Adds the file at $source under $name. */
    public function addFile(string $name, string $source): void
    {
        $size = is_file($source) ? filesize($source) : false;
        $in = is_readable($source) ? fopen($source, 'rb') : false;
        if ($size === false || $in === false) {
            throw new RuntimeException("Cannot read {$source}.");
        }
        if ($size >= self::MAX32) {
            fclose($in);
            throw new RuntimeException("{$source} is 4 GB or more, which a backup does not take.");
        }
        try {
            $this->add($name, static fn () => ($chunk = fread($in, self::CHUNK)) === false || $chunk === '' ? null : $chunk, (int) filemtime($source));
        } finally {
            fclose($in);
        }
    }

    /** Adds $contents under $name. */
    public function addString(string $name, string $contents): void
    {
        $given = false;
        $this->add($name, static function () use (&$given, $contents): ?string {
            if ($given) {
                return null;
            }
            $given = true;

            return $contents;
        }, time());
    }

    /**
     * Writes the central directory and the end records, and removes the file that held
     * the directory. The archive is a ZIP from here on; nothing more can be added.
     *
     * @return int the archive's size in bytes
     */
    public function finish(): int
    {
        $start = $this->length;
        fseek($this->directory, 0);
        $size = (int) stream_copy_to_stream($this->directory, $this->archive);
        fclose($this->directory);
        unlink($this->path . '.directory');
        $this->length += $size;

        if ($this->count > 0xFFFF || $start > self::MAX32 || $size > self::MAX32) {
            $record = $this->length;
            $this->write(pack('VPvvVVPPPP', 0x06064b50, 44, 45, 45, 0, 0, $this->count, $this->count, $size, $start));
            $this->write(pack('VVPV', 0x07064b50, 0, $record, 1));
        }
        $this->write(pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            min($this->count, 0xFFFF),
            min($this->count, 0xFFFF),
            min($size, self::MAX32),
            min($start, self::MAX32),
            0,
        ));
        fclose($this->archive);

        return $this->length;
    }

    /**
     * One entry: its local header with the sizes still unknown, the data streamed from
     * $next, then the header patched with the sizes and checksum now known. Patched rather
     * than followed by a data descriptor, which some readers handle badly.
     *
     * @param callable(): ?string $next the next chunk of data, or null when there is no more
     */
    private function add(string $name, callable $next, int $modified): void
    {
        $name = ltrim(str_replace('\\', '/', $name), '/');
        if ($name === '' || str_contains('/' . $name . '/', '/../')) {
            throw new RuntimeException("Not a name an archive may hold: {$name}");
        }
        $method = in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::STORED, true) ? 0 : 8;
        [$time, $date] = self::dosTime($modified);
        $offset = $this->length;

        $this->write(pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $time, $date, 0, 0, 0, strlen($name), 0) . $name);
        $dataStart = $this->length;

        $crc = hash_init('crc32b');
        $deflate = $method === 8 ? (deflate_init(ZLIB_ENCODING_RAW, ['level' => 6]) ?: throw new RuntimeException('zlib cannot deflate here.')) : null;
        $original = 0;
        while (($chunk = $next()) !== null) {
            $original += strlen($chunk);
            hash_update($crc, $chunk);
            $this->write($deflate === null ? $chunk : (string) deflate_add($deflate, $chunk, ZLIB_NO_FLUSH));
        }
        if ($deflate !== null) {
            $this->write((string) deflate_add($deflate, '', ZLIB_FINISH));
        }
        if ($original >= self::MAX32) {
            throw new RuntimeException("{$name} is 4 GB or more, which a backup does not take.");
        }
        $checksum = (int) hexdec(hash_final($crc));
        $compressed = $this->length - $dataStart;

        // The sizes and checksum go into the local header, at bytes 14 to 25.
        fseek($this->archive, $offset + 14);
        fwrite($this->archive, pack('VVV', $checksum, $compressed, $original));
        fseek($this->archive, $this->length);

        $extra = $offset > self::MAX32 ? pack('vvP', 0x0001, 8, $offset) : '';
        $entry = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            (3 << 8) | 45,
            $extra === '' ? 20 : 45,
            0x0800,
            $method,
            $time,
            $date,
            $checksum,
            $compressed,
            $original,
            strlen($name),
            strlen($extra),
            0,
            0,
            0,
            0100644 << 16,
            min($offset, self::MAX32),
        ) . $name . $extra;
        if (fwrite($this->directory, $entry) !== strlen($entry)) {
            throw new RuntimeException("Cannot write beside {$this->path}.");
        }
        $this->directoryLength += strlen($entry);
        $this->count++;
    }

    private function write(string $bytes): void
    {
        if ($bytes === '') {
            return;
        }
        if (fwrite($this->archive, $bytes) !== strlen($bytes)) {
            throw new RuntimeException("Cannot write {$this->path}: is the disk full?");
        }
        $this->length += strlen($bytes);
    }

    /**
     * @return resource
     */
    private static function open(string $path)
    {
        $handle = is_dir(dirname($path)) ? fopen($path, 'c+b') : false;
        if ($handle === false) {
            throw new RuntimeException("Cannot open {$path} for writing.");
        }

        return $handle;
    }

    /**
     * @return array{int, int} MS-DOS time and date, which a ZIP entry carries
     */
    private static function dosTime(int $timestamp): array
    {
        $parts = getdate(max($timestamp, 315532800)); // 1980, where DOS time begins

        return [
            ($parts['hours'] << 11) | ($parts['minutes'] << 5) | intdiv($parts['seconds'], 2),
            (($parts['year'] - 1980) << 9) | ($parts['mon'] << 5) | $parts['mday'],
        ];
    }
}
