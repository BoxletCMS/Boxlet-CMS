<?php

namespace App\Support;

use Throwable;

/**
 * One range of a file over HTTPS, written to disk (PLAN.md D-215): curl where PHP has it, PHP's
 * own streams where it has not — a shared host that turns one off often keeps the other.
 *
 * From GeoDownload, which fetched the country database in pieces this way (D-174), now that
 * the installer fetches the demo's package the same way: a second caller, so one copy.
 */
final class RangeDownload
{
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
            'error' => $total < 1 ? t('download.no_size') : '',
        ];
    }

    /**
     * @return array<string, string>|string the response's headers, or what went wrong
     */
    private static function curl(string $url, int $from, int $to, string $etag, string $target): array|string
    {
        $out = @fopen($target, 'wb');
        if ($out === false) {
            return t('download.not_saved');
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

        return $copied === false ? t('download.not_saved') : $headers;
    }
}
