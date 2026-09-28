<?php

namespace App\Modules\Stats;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Where a visitor's address is (PLAN.md D-051, D-055), from one of DB-IP's Lite databases:
 * CC BY 4.0, attributed on the Statistics screen, updated monthly by DB-IP.
 *
 * Two files, and the owner chooses: IP-to-Country, 3.9 MB to fetch and 9.6 on disk, or
 * IP-to-City, 57.5 MB to fetch and 121.4 on disk — measured, where the specification said
 * 19. The big one is what makes the region and the city possible, and it is not a thing to
 * put on a shared host without being asked; GeoDownload fetches it a few megabytes at a
 * time.
 *
 * The database lives in storage/geo/, outside the web root, and is optional: without it
 * every place is unknown and nothing else changes. The owner fetches it from Settings,
 * which is the only request Boxlet makes to anyone for statistics, and carries nothing
 * about a visitor; or uploads the file where the server cannot reach DB-IP.
 *
 * A file is checked before it replaces the one in use — it must open as a MaxMind DB file
 * and know a country for a well-known address — and replaces it in one rename, so a view
 * counted meanwhile reads either the old file or the new one.
 */
final class Geo
{
    /** DB-IP's monthly country file; %s is the year and month. */
    public const SOURCE = 'https://download.db-ip.com/free/dbip-country-lite-%s.mmdb.gz';

    /** And the city one, which GeoDownload fetches in pieces. */
    public const CITY_SOURCE = 'https://download.db-ip.com/free/dbip-city-lite-%s.mmdb.gz';

    private const FILE = 'geo/dbip-country-lite.mmdb';

    /** Refused above this, uncompressed: the city file is 121 MB, and nothing DB-IP
        publishes is anywhere near this. */
    private const MAX_BYTES = 256 * 1024 * 1024;

    /** An address every country database places, to prove a file works before it is used. */
    private const KNOWN_ADDRESS = '8.8.8.8';

    public static function path(string $storagePath): string
    {
        return $storagePath . '/' . self::FILE;
    }

    /**
     * Where an address is, at most as far as $level asks: the country as ISO writes it
     * (HR), and with a city database the region and the city too. Everything empty when
     * the address is unknown, or there is no database.
     */
    public static function place(string $storagePath, string $ip, string $level = 'country'): Place
    {
        $path = self::path($storagePath);
        if ($ip === '' || !is_file($path)) {
            return new Place();
        }
        try {
            return Place::of((new Mmdb($path))->get($ip), $level);
        } catch (Throwable) {
            return new Place();
        }
    }

    /**
     * The database in use, for the Settings panel: when it was built and by whom. Null
     * when there is none, or it cannot be read.
     *
     * Whether it holds cities is asked of the file first — does the address every database
     * knows come back with a city — and of its name only as a fallback, for the file whose
     * one known row happens to have none. A name alone would be no promise at all.
     *
     * @return array{built: string, type: string, cities: bool}|null
     */
    public static function status(string $storagePath): ?array
    {
        $path = self::path($storagePath);
        if (!is_file($path)) {
            return null;
        }
        try {
            $metadata = (new Mmdb($path))->metadata();
        } catch (Throwable) {
            return null;
        }
        $epoch = $metadata['build_epoch'] ?? null;

        return [
            'built' => is_int($epoch) ? gmdate('Y-m-d', $epoch) : '',
            'type' => is_string($metadata['database_type'] ?? null) ? $metadata['database_type'] : '',
            'cities' => self::place($storagePath, self::KNOWN_ADDRESS, 'city')->city !== ''
                || stripos(is_string($metadata['database_type'] ?? null) ? $metadata['database_type'] : '', 'city') !== false,
        ];
    }

    /**
     * Fetches DB-IP's file for this month, or last month's when this month's is not out
     * yet (it appears on the first, in DB-IP's time), and puts it in use.
     *
     * @throws RuntimeException with a message for the owner
     */
    public static function download(string $storagePath, DateTimeImmutable $now, string $source = self::SOURCE): void
    {
        $directory = self::directory($storagePath);
        $part = $directory . '/download.mmdb.gz.part';
        $failure = '';
        foreach ([$now, $now->modify('first day of last month')] as $month) {
            $failure = self::fetch(sprintf($source, $month->format('Y-m')), $part);
            if ($failure === '') {
                try {
                    self::install($storagePath, $part);
                } finally {
                    self::remove($part);
                }

                return;
            }
        }
        self::remove($part);

        throw new RuntimeException(t('stats.geo_download_failed', ['reason' => $failure]));
    }

    /**
     * Puts a database in use: $source is a .mmdb file, or one gzipped as DB-IP sends it.
     * $source is left where it is; the caller removes it.
     *
     * @throws RuntimeException with a message for the owner
     */
    public static function install(string $storagePath, string $source): void
    {
        $directory = self::directory($storagePath);
        $unpacked = $directory . '/install.mmdb.part';
        try {
            self::unpack($source, $unpacked);
            try {
                $known = Place::of((new Mmdb($unpacked))->get(self::KNOWN_ADDRESS))->country;
            } catch (Throwable) {
                $known = '';
            }
            if ($known === '') {
                throw new RuntimeException(t('stats.geo_not_a_database'));
            }
            if (!@rename($unpacked, self::path($storagePath))) {
                throw new RuntimeException(t('stats.geo_not_saved'));
            }
        } finally {
            self::remove($unpacked);
        }
    }

    /** Copies $source to $target, unpacking it if it is gzipped, up to MAX_BYTES. */
    private static function unpack(string $source, string $target): void
    {
        $head = (string) @file_get_contents($source, false, null, 0, 2);
        $in = $head === "\x1f\x8b" ? @gzopen($source, 'rb') : @fopen($source, 'rb');
        $out = @fopen($target, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException(t('stats.geo_not_saved'));
        }
        $written = 0;
        $gz = $head === "\x1f\x8b";
        while (!($gz ? gzeof($in) : feof($in))) {
            $chunk = $gz ? gzread($in, 1 << 20) : fread($in, 1 << 20);
            if ($chunk === false) {
                break;
            }
            $written += strlen($chunk);
            if ($written > self::MAX_BYTES) {
                break;
            }
            fwrite($out, $chunk);
        }
        $gz ? gzclose($in) : fclose($in);
        fclose($out);
        if ($written > self::MAX_BYTES) {
            throw new RuntimeException(t('stats.geo_too_big'));
        }
    }

    /**
     * Downloads $url to $target. Returns '' on success, else what went wrong in a few
     * words. curl where PHP has it, since a shared host that turns off allow_url_fopen
     * usually still has curl; PHP's own streams otherwise.
     */
    private static function fetch(string $url, string $target): string
    {
        if (function_exists('curl_init') && str_starts_with($url, 'https://')) {
            $out = @fopen($target, 'wb');
            if ($out === false) {
                return t('stats.geo_not_saved');
            }
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_FILE => $out,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_FAILONERROR => true,
                CURLOPT_USERAGENT => 'Boxlet',
            ]);
            $done = curl_exec($curl);
            $error = $done === true ? '' : (curl_error($curl) !== '' ? curl_error($curl) : 'HTTP ' . curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
            curl_close($curl);
            fclose($out);

            return $error;
        }

        try {
            $in = @fopen($url, 'rb', false, stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'Boxlet']]));
        } catch (Throwable $e) {
            // An error handler that ignores @ turns the warning into this.
            return $e->getMessage();
        }
        if ($in === false) {
            return error_get_last()['message'] ?? 'no connection';
        }
        $copied = @file_put_contents($target, $in);
        fclose($in);

        return $copied === false ? t('stats.geo_not_saved') : '';
    }

    private static function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    private static function directory(string $storagePath): string
    {
        $directory = dirname(self::path($storagePath));
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException(t('stats.geo_not_saved'));
        }

        return $directory;
    }
}
