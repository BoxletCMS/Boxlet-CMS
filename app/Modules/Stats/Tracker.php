<?php

namespace App\Modules\Stats;

use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Support\ClientIp;
use App\Support\Dates;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;

/**
 * Counts a page view on the site's own server (PLAN.md D-051, SPEC §5.7): no cookie, no
 * script on the page, no third party, and nothing stored that names a visitor.
 *
 * Called from public/index.php after the page has been sent, so a visitor never waits for
 * it and never sees it fail.
 *
 * A visitor is a key made fresh each day: HMAC-SHA256 over the address, the User-Agent and
 * the host, under a salt kept in settings and replaced by the first request of each new
 * day. The address is read for that one hash and never stored. The same request deletes
 * the previous day's keys, and with them any way of linking a visitor across two days, and
 * the counts older than the retention period: there is no cron to do it (D-051). What that
 * first view of a day does is NewDay's.
 */
final class Tracker
{
    /** Months of counts kept, as the Settings panel offers them. */
    public const RETENTION = [6, 12, 24, 36];

    private const RETENTION_DEFAULT = 24;

    /**
     * How many months a place keeps its city before the row is collapsed into its region
     * (D-055); 0 keeps it as long as the counts themselves. A city is the sharpest thing
     * these tables hold, and it is the one worth forgetting first.
     */
    public const CITY_MONTHS = [1, 3, 6, 12, 0];

    private const CITY_MONTHS_DEFAULT = 3;

    /** First path segments that are never a visitor's page. */
    private const SKIPPED = ['admin', 'form', 'sitemap', 'download', 'install', 'install.php', '_boxlet'];

    /**
     * The module's settings, with their defaults: on, honouring DNT and GPC, two years,
     * and the country as the only thing said about where a visitor is.
     *
     * @return array{enabled: bool, dnt: bool, retention: int, missing: bool, group: bool, location: string, cityMonths: int, cityMin: int}
     */
    public static function settings(Db $db): array
    {
        $stored = Settings::many($db, ['stats_enabled', 'stats_dnt', 'stats_retention', 'stats_missing', 'stats_group', 'stats_location', 'stats_city_months', 'stats_city_min']);
        $retention = $stored['stats_retention'];
        $location = $stored['stats_location'];
        $cityMonths = $stored['stats_city_months'];
        $cityMin = $stored['stats_city_min'];

        return [
            'enabled' => $stored['stats_enabled'] !== false,
            'dnt' => $stored['stats_dnt'] !== false,
            'retention' => is_int($retention) && in_array($retention, self::RETENTION, true) ? $retention : self::RETENTION_DEFAULT,
            // Addresses that are not there: off unless asked for (O-20). A small site's 404s
            // are mostly other people's broken links and bots guessing at addresses.
            'missing' => $stored['stats_missing'] === true,
            // Rows of one or two visitors gathered into "Other" (O-20): on a small site a
            // table is otherwise a list of ones, and one visitor from one country on one
            // page is close to naming somebody.
            'group' => $stored['stats_group'] === true,
            // How much of where a visitor is (D-055). The country unless asked otherwise:
            // the city is the point at which a count starts to be about a person.
            'location' => is_string($location) && in_array($location, Place::LEVELS, true) ? $location : 'country',
            'cityMonths' => is_int($cityMonths) && in_array($cityMonths, self::CITY_MONTHS, true) ? $cityMonths : self::CITY_MONTHS_DEFAULT,
            // How many visitors a city needs before it is named (D-109). Five unless the
            // owner says otherwise, which is the specification's own privacy rule; one is
            // allowed and changes what the site tells its visitors (PrivacyText).
            'cityMin' => is_int($cityMin) && in_array($cityMin, PlaceQuery::MIN_CHOICES, true) ? $cityMin : PlaceQuery::DEFAULT_MIN,
        ];
    }

    /**
     * Whether this request and response could be a view to count, from what they carry
     * alone: no database, so that the admin's own requests and every asset, redirect and
     * error cost nothing. What needs the settings is decided in record().
     */
    public static function wanted(Request $request, Response $response): bool
    {
        $segment = strtolower(explode('/', ltrim($request->path, '/'))[0]);
        $purpose = strtolower(($request->header('sec-purpose') ?? '') . ' ' . ($request->header('purpose') ?? '') . ' ' . ($request->header('x-moz') ?? ''));

        return $request->method === 'GET'
            // A 404 is counted too, in its own table, where the owner asks for it (O-20);
            // record() decides which of the two it is.
            && ($response->status === 200 || $response->status === 404)
            && str_contains(strtolower((string) ($response->headers['Content-Type'] ?? '')), 'text/html')
            && !in_array($segment, self::SKIPPED, true)
            && !str_contains($purpose, 'prefetch')
            && !str_contains($purpose, 'prerender')
            // The logged-in admin, told by the session cookie without starting a session:
            // a visitor never receives one, since nothing on the public site starts one.
            && preg_match('~(?:^|;)\s*boxlet_session=~', $request->header('cookie') ?? '') !== 1
            && !Bots::is($request->header('user-agent') ?? '');
    }

    /**
     * Counts the view if it is one to count. Returns whether it did. $storagePath is where
     * the country database is looked for (Geo); '' counts every country as unknown.
     */
    public static function record(Db $db, Request $request, Response $response, ?DateTimeImmutable $now = null, string $storagePath = ''): bool
    {
        if (!self::wanted($request, $response)) {
            return false;
        }
        $settings = self::settings($db);
        if (!$settings['enabled']
            || ($settings['dnt'] && ($request->header('dnt') === '1' || $request->header('sec-gpc') === '1'))) {
            return false;
        }

        $zone = new DateTimeZone(Dates::zone($db));
        $today = ($now ?? new DateTimeImmutable())->setTimezone($zone);
        $day = $today->format('Y-m-d');
        $userAgent = $request->header('user-agent') ?? '';
        $host = self::host($request->header('host') ?? '');
        $path = mb_substr($request->path, 0, 255);
        // The visitor's own address where the site sits behind a proxy (O-20); the server's
        // own report of it otherwise. Read for the day's key and the country, never stored.
        $ip = ClientIp::of($request, Settings::text($db, 'trusted_proxies'));

        // An address that is not there is not a page view: it goes in its own table, and
        // only while the owner wants it counted (O-20). Before the salt and the visitor's
        // key, which a 404 has no use for — and bots asking for addresses that do not exist
        // are most of what lands here.
        if ($response->status === 404) {
            if ($settings['missing']) {
                self::increase(
                    $db,
                    'stats_missing',
                    ['day' => $day, 'path' => $path, 'source' => self::source($request->header('referer') ?? '', $host)],
                    'views = views + 1',
                    ['views' => 1],
                );
            }

            return $settings['missing'];
        }

        // Where the visitor is, as far as the owner asked (D-055). The address itself is
        // gone by the end of this method; what stays is a country, and at most a city.
        $place = $storagePath === '' ? new Place() : Geo::place($storagePath, $ip, $settings['location']);

        $salt = NewDay::salt($db, $day, $today, $settings);
        $visitor = bin2hex(substr(hash_hmac('sha256', $ip . "\n" . $userAgent . "\n" . $host, $salt, true), 0, 16));
        $agent = Agent::parse($userAgent);

        $newToSite = self::firstSeen($db, $day, $visitor, '');
        if (self::firstSeen($db, $day, $visitor, $path)) {
            self::increase($db, 'stats_page_visitors', ['day' => $day, 'path' => $path], 'visitors = visitors + 1', ['visitors' => 1]);
        }
        self::increase(
            $db,
            'stats_views',
            [
                'day' => $day,
                'path' => $path,
                'source' => self::source($request->header('referer') ?? '', $host),
                'country' => $place->country,
                'device' => $agent['device'],
                'browser' => $agent['browser'],
                'os' => $agent['os'],
            ],
            'views = views + 1' . ($newToSite ? ', visitors = visitors + 1' : ''),
            ['views' => 1, 'visitors' => $newToSite ? 1 : 0],
        );

        // And where they were, in a table of its own (D-055), which the path never enters.
        // Written whenever anything at all is known, so the country level fills it too and
        // turning the level up later adds detail to the days after it rather than rewriting
        // the days before.
        if ($place->isKnown()) {
            self::increase(
                $db,
                'stats_places',
                ['day' => $day, 'country' => $place->country, 'region' => $place->region, 'city' => $place->city],
                'views = views + 1' . ($newToSite ? ', visitors = visitors + 1' : ''),
                [
                    'views' => 1,
                    'visitors' => $newToSite ? 1 : 0,
                    'latitude' => $place->latitude,
                    'longitude' => $place->longitude,
                ],
            );
        }

        return true;
    }

    /**
     * Every count and key gone, for the owner who asks from Settings. The salt goes too:
     * the next view starts a new day's keys.
     */
    public static function erase(Db $db): void
    {
        foreach (['stats_views', 'stats_page_visitors', 'stats_seen', 'stats_missing', 'stats_places'] as $table) {
            $db->query("DELETE FROM {$table}");
        }
        $db->query('DELETE FROM settings WHERE `key` = ?', ['stats_salt']);
    }

    /**
     * Where the visitor came from, as a bare domain; '' for a direct visit, which is also
     * what a link from the site itself is. Never the address that linked here: a query
     * string can carry anything, a search or an e-mail address included.
     */
    public static function source(string $referer, string $ownHost): string
    {
        $host = self::host((string) parse_url($referer, PHP_URL_HOST));

        return $host === $ownHost ? '' : mb_substr($host, 0, 190);
    }

    /** A host name as compared and stored: lower case, no port, no leading www. */
    private static function host(string $host): string
    {
        $host = strtolower(trim(explode(':', $host)[0], '.'));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /** Marks the visitor seen today on $path ('' for the site); true the first time. */
    private static function firstSeen(Db $db, string $day, string $visitor, string $path): bool
    {
        try {
            $db->query('INSERT INTO stats_seen (day, hash, path) VALUES (?, ?, ?)', [$day, $visitor, $path]);

            return true;
        } catch (PDOException $e) {
            if (self::duplicate($e)) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Adds to a row's counts, making the row if there is none (SPEC §5.0 has no upsert
     * that both databases accept). A row made at the same moment by another view is found
     * by the second UPDATE. Public because the day's housekeeping (NewDay) writes into the
     * same rows in the same way when it collapses a place's city into its region.
     *
     * @param array<string, string> $key
     * @param array<string, int|float|null> $first what a new row carries besides its key:
     *        its counts, and for a place its coordinates, which are set once and never
     *        added to
     */
    public static function increase(Db $db, string $table, array $key, string $increment, array $first): void
    {
        $where = implode(' AND ', array_map(static fn (string $column): string => "{$column} = ?", array_keys($key)));
        $update = "UPDATE {$table} SET {$increment} WHERE {$where}";
        if ($db->query($update, array_values($key))->rowCount() > 0) {
            return;
        }

        $row = $key + $first;
        $columns = implode(', ', array_keys($row));
        $marks = implode(', ', array_fill(0, count($row), '?'));
        try {
            $db->query("INSERT INTO {$table} ({$columns}) VALUES ({$marks})", array_values($row));
        } catch (PDOException $e) {
            if (!self::duplicate($e)) {
                throw $e;
            }
            $db->query($update, array_values($key));
        }
    }

    /** A unique constraint refused the row: SQLSTATE 23000 on MySQL and SQLite alike. */
    private static function duplicate(PDOException $e): bool
    {
        return (string) $e->getCode() === '23000';
    }
}
