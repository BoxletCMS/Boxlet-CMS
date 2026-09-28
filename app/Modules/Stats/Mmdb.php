<?php

namespace App\Modules\Stats;

use RuntimeException;

/**
 * A reader for MaxMind DB files (PLAN.md D-051), the format DB-IP's country database comes
 * in. Boxlet's own, because the dependency list is closed: the format is published
 * (https://maxmind.github.io/MaxMind-DB/) and a lookup needs only part of it.
 *
 * The file is a binary search tree over the bits of an address, then a data section. A
 * lookup walks the tree one bit at a time until a record points into the data, and decodes
 * the value there. The file is read where it lies, a few bytes at a time, never loaded
 * whole: it is eight megabytes and a lookup touches well under one of its kilobytes.
 */
final class Mmdb
{
    private const METADATA_MARKER = "\xAB\xCD\xEFMaxMind.com";

    /** @var resource */
    private $file;

    private int $nodeCount;

    private int $recordSize;

    private int $ipVersion;

    private int $treeSize;

    /** @var array<string, mixed> */
    private array $metadata;

    public function __construct(string $path)
    {
        $file = is_file($path) ? @fopen($path, 'rb') : false;
        if ($file === false) {
            throw new RuntimeException("Cannot open {$path}.");
        }
        $this->file = $file;

        $size = (int) filesize($path);
        $tail = min($size, 128 * 1024);
        if ($tail < 1) {
            throw new RuntimeException('Not a MaxMind DB file: it is empty.');
        }
        fseek($file, $size - $tail);
        $end = (string) fread($file, $tail);
        $marker = strrpos($end, self::METADATA_MARKER);
        if ($marker === false) {
            throw new RuntimeException('Not a MaxMind DB file: no metadata.');
        }
        $start = $size - $tail + $marker + strlen(self::METADATA_MARKER);
        [$metadata] = $this->decode($start, $start);
        if (!is_array($metadata)) {
            throw new RuntimeException('Not a MaxMind DB file: the metadata is not a map.');
        }

        $this->metadata = $metadata;
        $this->nodeCount = is_int($metadata['node_count'] ?? null) ? $metadata['node_count'] : 0;
        $this->recordSize = is_int($metadata['record_size'] ?? null) ? $metadata['record_size'] : 0;
        $this->ipVersion = is_int($metadata['ip_version'] ?? null) ? $metadata['ip_version'] : 0;
        if ($this->nodeCount < 1 || !in_array($this->recordSize, [24, 28, 32], true) || !in_array($this->ipVersion, [4, 6], true)) {
            throw new RuntimeException('Not a MaxMind DB file this reader knows.');
        }
        $this->treeSize = intdiv($this->nodeCount * $this->recordSize, 4);
    }

    public function __destruct()
    {
        fclose($this->file);
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * The value stored for an address, or null when the file has none. An IPv4 address in
     * an IPv6 file is looked up where the format puts it, after 96 zero bits.
     */
    public function get(string $ip): mixed
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return null;
        }
        if (strlen($packed) === 16 && $this->ipVersion === 4) {
            return null;
        }
        if (strlen($packed) === 4 && $this->ipVersion === 6) {
            $packed = str_repeat("\0", 12) . $packed;
        }

        $node = 0;
        $bits = strlen($packed) * 8;
        for ($i = 0; $i < $bits && $node < $this->nodeCount; $i++) {
            $bit = (ord($packed[$i >> 3]) >> (7 - ($i & 7))) & 1;
            $node = $this->record($node, $bit);
        }
        if ($node <= $this->nodeCount) {
            return null;
        }

        // A record past the node count points into the data section, which starts 16 bytes
        // after the tree.
        $base = $this->treeSize + 16;
        [$value] = $this->decode($base + $node - $this->nodeCount - 16, $base);

        return $value;
    }

    /** One of a node's two records: where the tree goes next for a 0 or a 1. */
    private function record(int $node, int $bit): int
    {
        $bytes = $this->read(intdiv($node * $this->recordSize, 4), intdiv($this->recordSize, 4));

        switch ($this->recordSize) {
            case 24:
                return self::number(substr($bytes, $bit * 3, 3));
            case 28:
                $middle = ord($bytes[3]);
                $high = $bit === 0 ? $middle >> 4 : $middle & 0x0F;

                return ($high << 24) | self::number($bit === 0 ? substr($bytes, 0, 3) : substr($bytes, 4, 3));
            default:
                return self::number(substr($bytes, $bit * 4, 4));
        }
    }

    /**
     * The value at $offset, and the offset just after it. $base is where pointers count
     * from: the data section, or the metadata's own start.
     *
     * @return array{mixed, int}
     */
    private function decode(int $offset, int $base): array
    {
        $control = ord($this->read($offset++, 1));
        $type = $control >> 5;

        if ($type === 1) {
            $size = ($control >> 3) & 0x3;
            $bytes = $this->read($offset, $size + 1);
            $offset += $size + 1;
            $low = $control & 0x7;
            $pointer = match ($size) {
                0 => ($low << 8) | ord($bytes[0]),
                1 => (($low << 16) | self::number($bytes)) + 2048,
                2 => (($low << 24) | self::number($bytes)) + 526336,
                default => self::number($bytes),
            };
            [$value] = $this->decode($base + $pointer, $base);

            return [$value, $offset];
        }

        if ($type === 0) {
            $type = 7 + ord($this->read($offset++, 1));
        }
        $size = $control & 0x1F;
        if ($size >= 29) {
            $extra = $size - 28;
            $size = [29 => 29, 30 => 285, 31 => 65821][$size] + self::number($this->read($offset, $extra));
            $offset += $extra;
        }

        switch ($type) {
            case 2:
            case 4:
                return [$size === 0 ? '' : $this->read($offset, $size), $offset + $size];
            case 3:
                return [unpack('E', $this->read($offset, 8))[1] ?? 0.0, $offset + 8];
            case 15:
                return [unpack('G', $this->read($offset, 4))[1] ?? 0.0, $offset + 4];
            case 5:
            case 6:
            case 9:
            case 10:
                return [$size === 0 ? 0 : self::number($this->read($offset, $size)), $offset + $size];
            case 8:
                $number = $size === 0 ? 0 : self::number($this->read($offset, $size));

                return [$size === 4 && $number >= 0x80000000 ? $number - 0x100000000 : $number, $offset + $size];
            case 7:
                $map = [];
                for ($i = 0; $i < $size; $i++) {
                    [$key, $offset] = $this->decode($offset, $base);
                    [$value, $offset] = $this->decode($offset, $base);
                    $map[(string) (is_scalar($key) ? $key : '')] = $value;
                }

                return [$map, $offset];
            case 11:
                $list = [];
                for ($i = 0; $i < $size; $i++) {
                    [$list[], $offset] = $this->decode($offset, $base);
                }

                return [$list, $offset];
            case 14:
                return [$size !== 0, $offset];
            default:
                throw new RuntimeException("Unknown MaxMind DB data type {$type}.");
        }
    }

    private function read(int $offset, int $length): string
    {
        if ($length < 1) {
            return '';
        }
        fseek($this->file, $offset);
        $bytes = fread($this->file, $length);
        if ($bytes === false || strlen($bytes) !== $length) {
            throw new RuntimeException('The MaxMind DB file ends too soon.');
        }

        return $bytes;
    }

    /**
     * Big-endian bytes as a number. A 128-bit value does not fit, and nothing Boxlet reads
     * is one; it would come out wrong rather than fail.
     */
    private static function number(string $bytes): int
    {
        $number = 0;
        foreach (str_split($bytes) as $byte) {
            $number = ($number << 8) | ord($byte);
        }

        return $number;
    }
}
