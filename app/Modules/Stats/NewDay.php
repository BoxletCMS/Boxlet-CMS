<?php

namespace App\Modules\Stats;

use App\Core\Db;
use App\Core\Settings;
use DateTimeImmutable;

/**
 * What the first counted view of a new day does (PLAN.md D-051, D-055).
 *
 * Boxlet asks for no cron, so the day's housekeeping rides on the first view there is: a new
 * salt, yesterday's visitor keys gone, counts past the retention period gone, and the city
 * dropped from places old enough that keeping it would be keeping more than an audience.
 *
 * Split from Tracker when that passed the size a file is kept to; the seam is the one the
 * module already had — what happens on every view, and what happens once a day.
 */
final class NewDay
{
    /**
     * Today's salt. The first request of a new day makes one, and deletes the earlier
     * days' visitor keys and the counts older than the retention period.
     *
     * Two first requests at once can both make one; whichever the settings table keeps is
     * read back and used, so at most the loser's own view is keyed with a salt nobody
     * keeps — one visitor, once, possibly counted twice.
     *
     * @param array{retention: int, cityMonths: int} $settings
     */
    public static function salt(Db $db, string $day, DateTimeImmutable $today, array $settings): string
    {
        $stored = Settings::get($db, 'stats_salt');
        if (is_array($stored) && ($stored['day'] ?? null) === $day && is_string($stored['salt'] ?? null)) {
            return $stored['salt'];
        }

        Settings::set($db, 'stats_salt', ['day' => $day, 'salt' => bin2hex(random_bytes(32))]);
        $db->query('DELETE FROM stats_seen WHERE day <> ?', [$day]);
        $retention = $settings['retention'];
        $oldest = $today->modify("-{$retention} months")->format('Y-m-d');
        $db->query('DELETE FROM stats_views WHERE day < ?', [$oldest]);
        $db->query('DELETE FROM stats_page_visitors WHERE day < ?', [$oldest]);
        $db->query('DELETE FROM stats_missing WHERE day < ?', [$oldest]);
        $db->query('DELETE FROM stats_places WHERE day < ?', [$oldest]);
        // The city goes before the rest does (D-055): after a few months a place keeps only
        // its region, which is no longer sharp enough to be about a person.
        if ($settings['cityMonths'] > 0) {
            self::forgetCities($db, $today->modify('-' . $settings['cityMonths'] . ' months')->format('Y-m-d'));
        }

        $kept = Settings::get($db, 'stats_salt');

        return is_array($kept) && is_string($kept['salt'] ?? null) ? $kept['salt'] : '';
    }

    /**
     * Collapses every place older than $before into its region: the counts are kept, the
     * city and its coordinates are not. In one transaction, because a crash between the
     * gathering and the deleting would either lose a day's counts or count them twice.
     */
    private static function forgetCities(Db $db, string $before): void
    {
        $groups = $db->all(
            "SELECT day, country, region, SUM(views) AS views, SUM(visitors) AS visitors
             FROM stats_places WHERE day < ? AND city <> '' GROUP BY day, country, region",
            [$before],
        );
        if ($groups === []) {
            return;
        }

        $db->transaction(static function () use ($db, $groups, $before): void {
            $db->query("DELETE FROM stats_places WHERE day < ? AND city <> ''", [$before]);
            foreach ($groups as $group) {
                $views = (int) $group['views'];
                $visitors = (int) $group['visitors'];
                Tracker::increase(
                    $db,
                    'stats_places',
                    ['day' => (string) $group['day'], 'country' => (string) $group['country'], 'region' => (string) $group['region'], 'city' => ''],
                    "views = views + {$views}, visitors = visitors + {$visitors}",
                    ['views' => $views, 'visitors' => $visitors, 'latitude' => null, 'longitude' => null],
                );
            }
        });
    }
}
