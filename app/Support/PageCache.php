<?php

namespace App\Support;

/**
 * Visitors' pages kept as files and handed back without the database (PLAN.md D-053).
 *
 * Checked in public/index.php before the container, the router or the database exist, so a
 * hit costs a file check and a read. What may be kept, and handed back:
 *   - a GET with no query string
 *   - from someone with no boxlet_session cookie: the admin never gets or leaves a kept page,
 *     and nothing on the public site gives a visitor a cookie, so no page is anyone's own
 *   - outside /admin, /form, /download and the installer
 *   - while maintenance is off
 *   - kept only when it was answered 200 with HTML, and the owner has the cache on
 *
 * EMPTIED BY ANY POST TO THE ADMIN but logging in and out, not by a list of the changes that
 * alter a page. D-053
 * counted on the activity log, and a check of every route found changes that write none: a
 * block inserted, a picture's sizes made again, the languages reordered. Emptying on every
 * admin POST also covers what is built after this. A kept page also goes stale after a day,
 * for whatever a page shows that changes with the date.
 */
final class PageCache
{
    public const MAX_AGE = 86400;

    private const SKIPPED = ['admin', 'form', 'download', 'install.php', '_boxlet', 'sitemap'];

    /** POSTs to the admin that change nothing a visitor sees: logging in and out, the admin's theme. */
    private const NOT_CHANGES = ['login', 'logout', 'forgot', 'reset', 'theme'];

    private static string $directory = '';

    /** Where pages are kept: set in bootstrap, beside the compiled stylesheet. */
    public static function use(string $cacheDirectory): void
    {
        self::$directory = rtrim($cacheDirectory, '/') . '/pages';
    }

    /**
     * Sends the kept page for this request, if there is one and it may be sent. Returns
     * whether it did; when it did, the request is answered.
     *
     * @param array<string, mixed> $server
     */
    public static function serve(array $server, string $storage): bool
    {
        if (!self::eligible($server, $storage)) {
            return false;
        }
        $file = self::file($server);
        $modified = is_file($file) ? (int) filemtime($file) : 0;
        if ($modified === 0 || $modified < time() - self::MAX_AGE || self::newYearSince($modified, time())) {
            return false;
        }
        // As Response::send(): headers only while they can still be sent.
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: text/html; charset=utf-8');
            header('X-Boxlet-Cache: hit');
        }
        readfile($file);

        return true;
    }

    /**
     * Whether a new year may have begun on the site since $kept: a page's {{year}} (D-201) is
     * the year it was drawn in. Asked before the site's time zone is known, which is not read
     * here, so of every zone there is, UTC-12 to UTC+14: a page drawn in the last hours of a
     * year is drawn again a few hours sooner than it had to be, and never shown a year late.
     */
    public static function newYearSince(int $kept, int $now): bool
    {
        return gmdate('Y', $kept - 12 * 3600) !== gmdate('Y', $now + 14 * 3600);
    }

    /**
     * After a request has been answered: kept, when it is a page that may be; or every kept
     * page emptied, when it was a POST to the admin.
     *
     * @param array<string, mixed> $server
     * @param \Closure(): bool $enabled whether the owner has the cache on, asked only when a
     *        page could be kept, since asking reads the database
     */
    public static function after(array $server, string $storage, int $status, string $contentType, string $body, \Closure $enabled): void
    {
        if (self::$directory === '') {
            return;
        }
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST' && self::segment($server) === 'admin' && !in_array(self::segment($server, 1), self::NOT_CHANGES, true) && !self::drafting($server)) {
            self::clear();

            return;
        }
        if ($status !== 200 || !str_contains(strtolower($contentType), 'text/html') || !self::eligible($server, $storage) || !$enabled()) {
            return;
        }
        if (!is_dir(self::$directory) && !@mkdir(self::$directory, 0775, true) && !is_dir(self::$directory)) {
            return; // an unwritable cache is no cache, never an error for a visitor
        }
        $file = self::file($server);
        if (@file_put_contents($file . '.' . getmypid(), $body) !== false) {
            @rename($file . '.' . getmypid(), $file);
        }
    }

    /** Every kept page removed. */
    public static function clear(): void
    {
        // Never told where the pages are, there are none: '' would make this the disk's root.
        if (self::$directory === '') {
            return;
        }
        foreach (glob(self::$directory . '/*.html') ?: [] as $file) {
            @unlink($file);
        }
    }

    /** How many pages are kept now. */
    public static function count(): int
    {
        return count(glob(self::$directory . '/*.html') ?: []);
    }

    /**
     * @param array<string, mixed> $server
     */
    private static function eligible(array $server, string $storage): bool
    {
        $uri = (string) ($server['REQUEST_URI'] ?? '/');

        return self::$directory !== ''
            && strtoupper((string) ($server['REQUEST_METHOD'] ?? '')) === 'GET'
            && !str_contains($uri, '?')
            && preg_match('~(?:^|;)\s*boxlet_session=~', (string) ($server['HTTP_COOKIE'] ?? '')) !== 1
            && !in_array(self::segment($server), self::SKIPPED, true)
            && !is_file($storage . '/maintenance.flag');
    }

    /**
     * A POST that touches only what the editors show (D-163, D-173, D-175): the draft's
     * autosave and a revision put into it, a band or an inspector drawn for the builder, a
     * block's fields cleaned, a pattern kept. Emptying every kept page on each of them would
     * make the cache useless while anyone edits.
     *
     * @param array<string, mixed> $server
     */
    private static function drafting(array $server): bool
    {
        return (self::segment($server, 1) === 'pages' && in_array(self::segment($server, 3), ['draft', 'render', 'inspect', 'fields', 'restore'], true))
            || self::segment($server, 1) === 'patterns';
    }

    /**
     * A segment of the path the site answers, the first unless told, lower-cased.
     *
     * @param array<string, mixed> $server
     */
    private static function segment(array $server, int $which = 0): string
    {
        $path = (string) parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return strtolower(explode('/', ltrim($path, '/'))[$which] ?? '');
    }

    /**
     * One file per scheme, host and path: a site answering on two names, or on http and
     * https, keeps each apart, since its pages carry their own address.
     *
     * @param array<string, mixed> $server
     */
    private static function file(array $server): string
    {
        $https = strtolower((string) ($server['HTTPS'] ?? 'off')) !== 'off' && ($server['HTTPS'] ?? '') !== '';
        $key = ($https ? 'https' : 'http') . '://' . strtolower((string) ($server['HTTP_HOST'] ?? ''))
            . (string) parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return self::$directory . '/' . sha1($key) . '.html';
    }
}
