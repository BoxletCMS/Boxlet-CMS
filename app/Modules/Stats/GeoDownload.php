<?php

namespace App\Modules\Stats;

use DateTimeImmutable;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Fetching a location database a few megabytes at a time (PLAN.md D-055).
 *
 * The country file is 3.9 MB and comes down in one request (Geo::download). The city file
 * is 57.5 MB, which on a shared host will not: `max_execution_time` is often thirty
 * seconds, and a slow line makes that a certainty rather than a risk. So it arrives in
 * pieces, the way the media remake makes pictures (D-048) — Start, then a Continue that a
 * script presses by itself and the owner can press by hand.
 *
 * NOTHING IS KEPT IN THE DATABASE. Where to carry on from is the size of the part file
 * itself, so there is no counter to drift out of step with what is on disk. Beside it sits
 * a small JSON file holding which address is being fetched, how big it is and the ETag the
 * server gave, and that ETag goes back with every piece: a file that is replaced mid-way
 * through the month answers with the whole of itself instead of the piece asked for, which
 * is how we know to start again rather than glue two months together.
 *
 * $fetch is how a piece is asked for, and it is a parameter for one reason: the arithmetic
 * of carrying on — where to resume, when it is finished, what a file that changed underneath
 * looks like — is the part worth testing, and none of it can be reached through a real
 * download. The tests pass their own; nothing else ever does.
 */
final class GeoDownload
{
    /** What one Continue fetches. Eight megabytes is seven or eight presses for the city file. */
    public const CHUNK = 8 * 1024 * 1024;

    private const STATE = 'geo/download.json';

    private const PART = 'geo/download.mmdb.gz.part';

    /** One piece, while it is being checked, before it is added to the part file. */
    private const PIECE = 'geo/download.piece';

    /**
     * Begins a download: finds the month DB-IP has published and clears whatever a previous
     * attempt left. Nothing is fetched here beyond one byte, so the button answers at once.
     *
     * @throws RuntimeException with a message for the owner
     */
    public static function start(string $storagePath, DateTimeImmutable $now, string $source = Geo::CITY_SOURCE, ?callable $fetch = null): void
    {
        $fetch ??= self::piece(...);
        self::cancel($storagePath);
        $failure = '';
        // This month's file appears on the first, in DB-IP's own time zone; before that,
        // last month's is the current one.
        foreach ([$now, $now->modify('first day of last month')] as $month) {
            $url = sprintf($source, $month->format('Y-m'));
            $answer = $fetch($url, 0, 0, '', self::path($storagePath, self::PIECE));
            self::remove(self::path($storagePath, self::PIECE));
            if ($answer['error'] === '') {
                self::write($storagePath, ['url' => $url, 'total' => $answer['total'], 'etag' => $answer['etag']]);
                touch(self::path($storagePath, self::PART));

                return;
            }
            $failure = $answer['error'];
        }

        throw new RuntimeException(t('stats.geo_download_failed', ['reason' => $failure]));
    }

    /**
     * Fetches the next piece, and installs the database once the last one is in.
     *
     * @return array{done: int, total: int, finished: bool}
     *
     * @throws RuntimeException with a message for the owner
     */
    public static function step(string $storagePath, ?callable $fetch = null): array
    {
        $fetch ??= self::piece(...);
        $state = self::read($storagePath);
        if ($state === null) {
            throw new RuntimeException(t('stats.geo_download_none'));
        }
        $part = self::path($storagePath, self::PART);
        $done = is_file($part) ? (int) filesize($part) : 0;
        $piece = self::path($storagePath, self::PIECE);

        $answer = $fetch($state['url'], $done, $done + self::CHUNK - 1, $state['etag'], $piece);
        if ($answer['error'] !== '') {
            self::remove($piece);

            throw new RuntimeException(t('stats.geo_download_failed', ['reason' => $answer['error']]));
        }
        // The server sent the whole file rather than the piece asked for: it is a different
        // file from the one we started, so what is on disk is worth nothing. Begin again.
        if ($answer['whole'] && $done > 0) {
            self::remove($piece);
            file_put_contents($part, '');

            return ['done' => 0, 'total' => $state['total'], 'finished' => false];
        }

        self::append($part, $piece);
        self::remove($piece);
        $done = (int) filesize($part);
        if ($done < $state['total']) {
            return ['done' => $done, 'total' => $state['total'], 'finished' => false];
        }

        try {
            Geo::install($storagePath, $part);
        } finally {
            self::cancel($storagePath);
        }

        return ['done' => $done, 'total' => $state['total'], 'finished' => true];
    }

    /**
     * How far a download has got, for the panel to draw; null when none is running.
     *
     * @return array{done: int, total: int}|null
     */
    public static function progress(string $storagePath): ?array
    {
        $state = self::read($storagePath);
        if ($state === null) {
            return null;
        }
        $part = self::path($storagePath, self::PART);

        return ['done' => is_file($part) ? (int) filesize($part) : 0, 'total' => $state['total']];
    }

    /** Forgets a download, finished or abandoned, and takes its files with it. */
    public static function cancel(string $storagePath): void
    {
        foreach ([self::STATE, self::PART, self::PIECE] as $name) {
            self::remove(self::path($storagePath, $name));
        }
    }

    /**
     * One range of $url, written to $target. `whole` says the server answered with the
     * entire file instead of the range, which is what an ETag that no longer matches, or a
     * server that does not do ranges, looks like.
     *
     * @return array{bytes: int, total: int, etag: string, whole: bool, error: string}
     */
    public static function piece(string $url, int $from, int $to, string $etag, string $target): array
    {
        $nothing = ['bytes' => 0, 'total' => 0, 'etag' => '', 'whole' => false];
        if (!str_starts_with($url, 'https://')) {
            return $nothing + ['error' => 'not an address we fetch from'];
        }
        $headers = function_exists('curl_init')
            ? self::curl($url, $from, $to, $etag, $target)
            : self::streams($url, $from, $to, $etag, $target);
        if (is_string($headers)) {
            return $nothing + ['error' => $headers];
        }

        // "bytes 0-8388607/60287600" — the number after the slash is the file's own size.
        // No Content-Range at all means the server sent the whole file rather than a range.
        $range = preg_match('~/(\d+)\s*$~', $headers['content-range'] ?? '', $found) === 1;
        $total = $range ? (int) $found[1] : (int) ($headers['content-length'] ?? 0);

        return [
            'bytes' => is_file($target) ? (int) filesize($target) : 0,
            'total' => $total,
            'etag' => $headers['etag'] ?? $etag,
            'whole' => !$range,
            'error' => $total < 1 ? t('stats.geo_download_no_size') : '',
        ];
    }

    /**
     * @return array<string, string>|string the response's headers, or what went wrong
     */
    private static function curl(string $url, int $from, int $to, string $etag, string $target): array|string
    {
        $out = @fopen($target, 'wb');
        if ($out === false) {
            return t('stats.geo_not_saved');
        }
        $headers = [];
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_FILE => $out,
            CURLOPT_RANGE => $from . '-' . $to,
            CURLOPT_HTTPHEADER => $etag === '' ? [] : ['If-Range: ' . $etag],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_FAILONERROR => true,
            CURLOPT_USERAGENT => 'Boxlet',
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                $colon = strpos($line, ':');
                if ($colon !== false) {
                    $headers[strtolower(substr($line, 0, $colon))] = trim(substr($line, $colon + 1));
                }

                return strlen($line);
            },
        ]);
        $done = curl_exec($curl);
        $error = $done === true ? '' : (curl_error($curl) !== '' ? curl_error($curl) : 'HTTP ' . curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
        curl_close($curl);
        fclose($out);

        return $error === '' ? $headers : $error;
    }

    /**
     * @return array<string, string>|string the response's headers, or what went wrong
     */
    private static function streams(string $url, int $from, int $to, string $etag, string $target): array|string
    {
        $request = ['timeout' => 90, 'user_agent' => 'Boxlet', 'header' => ['Range: bytes=' . $from . '-' . $to]];
        if ($etag !== '') {
            $request['header'][] = 'If-Range: ' . $etag;
        }
        try {
            $in = @fopen($url, 'rb', false, stream_context_create(['http' => $request]));
        } catch (Throwable $e) {
            // An error handler that ignores @ turns the warning into this.
            return $e->getMessage();
        }
        if ($in === false) {
            return error_get_last()['message'] ?? 'no connection';
        }
        // PHP puts the response's headers in this variable, in the scope of the fopen.
        $headers = [];
        foreach ($http_response_header as $line) {
            $colon = strpos($line, ':');
            if ($colon !== false) {
                $headers[strtolower(substr($line, 0, $colon))] = trim(substr($line, $colon + 1));
            }
        }
        $copied = @file_put_contents($target, $in);
        fclose($in);

        return $copied === false ? t('stats.geo_not_saved') : $headers;
    }

    /** Adds a piece to the end of the part file. */
    private static function append(string $part, string $piece): void
    {
        $out = @fopen($part, 'ab');
        $in = @fopen($piece, 'rb');
        if ($out === false || $in === false) {
            throw new RuntimeException(t('stats.geo_not_saved'));
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
    }

    /**
     * @return array{url: string, total: int, etag: string}|null
     */
    private static function read(string $storagePath): ?array
    {
        $file = self::path($storagePath, self::STATE);
        if (!is_file($file)) {
            return null;
        }
        try {
            $state = json_decode((string) file_get_contents($file), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array($state) || !is_string($state['url'] ?? null) || !is_int($state['total'] ?? null)) {
            return null;
        }

        return ['url' => $state['url'], 'total' => $state['total'], 'etag' => is_string($state['etag'] ?? null) ? $state['etag'] : ''];
    }

    /**
     * @param array{url: string, total: int, etag: string} $state
     */
    private static function write(string $storagePath, array $state): void
    {
        file_put_contents(self::path($storagePath, self::STATE), (string) json_encode($state));
    }

    private static function path(string $storagePath, string $name): string
    {
        $directory = $storagePath . '/geo';
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException(t('stats.geo_not_saved'));
        }

        return $storagePath . '/' . $name;
    }

    private static function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
