<?php

namespace App\Modules\Stats;

use App\Core\Db;
use DateTimeImmutable;

/**
 * What the Statistics screen and the dashboard card read (PLAN.md D-051): totals for a
 * period and the one before it, a series for the chart, and the top rows of a dimension.
 *
 * Visitors over a period are the sum of each day's visitors. A visitor's key lives for one
 * day (SPEC §5.7), so someone who came on Monday and Tuesday is two; that is the price of
 * forgetting them, and the screen says so.
 */
final class StatsQuery
{
    /** Fewer than this and a row is gathered into "other", where that is switched on. */
    public const SMALL = 3;

    /** The value that stands for the gathered rows; no real value is ever this. */
    public const OTHER = "\x00other";

    /** The periods the screen offers, and how many days each covers. */
    public const PERIODS = ['today' => 1, '7d' => 7, '30d' => 30, '12m' => 365];

    /** The dimensions a table can show: its key, and the column it reads. */
    public const DIMENSIONS = [
        'pages' => 'path',
        'sources' => 'source',
        'countries' => 'country',
        'devices' => 'device',
        'browsers' => 'browser',
        'os' => 'os',
        // Where visitors are, past the country (D-055). Their rows come from stats_places,
        // which PlaceQuery reads; everything else about them — the table, "show all", the
        // CSV — is the same machinery as the six above.
        'regions' => 'region',
        'cities' => 'city',
    ];

    /** The two that are not in stats_views at all. */
    public const PLACES = ['regions' => 'region', 'cities' => 'city'];

    /**
     * @param int|null $cityMin the owner's floor under the cities (D-109); null is the
     *        default five. Held here rather than passed to top(), because every caller that
     *        asks for a dimension would otherwise have to carry a number that concerns two
     *        of them.
     */
    public function __construct(private readonly Db $db, private readonly ?int $cityMin = null)
    {
    }

    /**
     * The first and last day of a period ending today, and of the period of the same
     * length just before it.
     *
     * @return array{from: string, to: string, prevFrom: string, prevTo: string}
     */
    public static function range(string $period, DateTimeImmutable $today): array
    {
        $days = self::PERIODS[$period] ?? 7;
        $from = $today->modify('-' . ($days - 1) . ' days');

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $today->format('Y-m-d'),
            'prevFrom' => $from->modify("-{$days} days")->format('Y-m-d'),
            'prevTo' => $from->modify('-1 day')->format('Y-m-d'),
        ];
    }

    /**
     * Visitors, views, views per visitor and the share of visitors on a phone, for what the
     * filter is showing. $from and $to override its days, which is how the period before is
     * asked for.
     *
     * @return array{visitors: int, views: int, perVisitor: float, mobile: float}
     */
    public function totals(StatsFilter $filter, ?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $filter->where($from, $to);
        $row = $this->db->one(
            "SELECT SUM(visitors) AS visitors, SUM(views) AS views,
                    SUM(CASE WHEN device = 'mobile' THEN visitors ELSE 0 END) AS mobile
             FROM stats_views WHERE {$where}",
            $params,
        );
        $visitors = (int) ($row['visitors'] ?? 0);
        $views = (int) ($row['views'] ?? 0);

        return [
            'visitors' => $visitors,
            'views' => $views,
            'perVisitor' => $visitors > 0 ? $views / $visitors : 0.0,
            'mobile' => $visitors > 0 ? (int) ($row['mobile'] ?? 0) / $visitors : 0.0,
        ];
    }

    /**
     * Visitors and views for every day from $from to $to, days with none included, so the
     * chart's line does not skip a quiet day. $week sums them by week (Monday first), for a
     * year's chart that would otherwise be 365 points wide.
     *
     * @return list<array{day: string, visitors: int, views: int}>
     */
    public function series(StatsFilter $filter, bool $week = false): array
    {
        [$where, $params] = $filter->where();
        [$from, $to] = [$filter->from, $filter->to];
        $found = [];
        foreach ($this->db->all(
            "SELECT day, SUM(visitors) AS visitors, SUM(views) AS views FROM stats_views
             WHERE {$where} GROUP BY day",
            $params,
        ) as $row) {
            $found[(string) $row['day']] = ['visitors' => (int) $row['visitors'], 'views' => (int) $row['views']];
        }

        $series = [];
        $day = new DateTimeImmutable($from);
        $last = new DateTimeImmutable($to);
        while ($day <= $last) {
            $key = $day->format('Y-m-d');
            $bucket = $week ? $day->modify('monday this week')->format('Y-m-d') : $key;
            $series[$bucket] ??= ['day' => $bucket, 'visitors' => 0, 'views' => 0];
            $series[$bucket]['visitors'] += $found[$key]['visitors'] ?? 0;
            $series[$bucket]['views'] += $found[$key]['views'] ?? 0;
            $day = $day->modify('+1 day');
        }

        return array_values($series);
    }

    /**
     * The rows of one dimension, most visitors first — for pages, most views, since a
     * page's visitors come from their own table (migration 0019) and a page is judged by
     * how often it is read.
     *
     * A page's visitors are null while anything else is narrowed: stats_page_visitors knows
     * a day and a path and nothing more, so it cannot say how many visitors a page had FROM
     * ONE COUNTRY. The screen says so rather than showing a number that does not answer the
     * question on the screen (O-20).
     *
     * @return list<array{value: string, visitors: int|null, views: int}>
     */
    public function top(string $dimension, StatsFilter $filter, ?int $limit = 10, bool $group = false): array
    {
        if (isset(self::PLACES[$dimension])) {
            return (new PlaceQuery($this->db, $this->cityMin))->top(self::PLACES[$dimension], $filter, $limit, $group);
        }
        $column = self::DIMENSIONS[$dimension] ?? 'path';
        // Gathering the small rows needs all of them: the limit is applied afterwards.
        $cap = $limit === null || $group ? '' : ' LIMIT ' . max(1, $limit);
        [$where, $params] = $filter->where();

        if ($column === 'path') {
            $rows = $this->db->all(
                "SELECT path AS value, SUM(views) AS total_views FROM stats_views
                 WHERE {$where} GROUP BY path ORDER BY total_views DESC, path{$cap}",
                $params,
            );
            $visitors = [];
            if (!$filter->isNarrowed()) {
                foreach ($this->db->all(
                    'SELECT path, SUM(visitors) AS visitors FROM stats_page_visitors
                     WHERE day >= ? AND day <= ? GROUP BY path',
                    [$filter->from, $filter->to],
                ) as $row) {
                    $visitors[(string) $row['path']] = (int) $row['visitors'];
                }
            }

            return self::gathered(array_values(array_map(static fn (array $row): array => [
                'value' => (string) $row['value'],
                'visitors' => $filter->isNarrowed() ? null : ($visitors[(string) $row['value']] ?? 0),
                'views' => (int) $row['total_views'],
            ], $rows)), $group, $limit, 'views');
        }

        return self::gathered(array_values(array_map(static fn (array $row): array => [
            'value' => (string) $row['value'],
            'visitors' => (int) $row['total_visitors'],
            'views' => (int) $row['total_views'],
        ], $this->db->all(
            // Aliases that are not column names, so ORDER BY cannot be read as the column
            // rather than its sum.
            "SELECT {$column} AS value, SUM(visitors) AS total_visitors, SUM(views) AS total_views FROM stats_views
             WHERE {$where} GROUP BY {$column} ORDER BY total_visitors DESC, total_views DESC, {$column}{$cap}",
            $params,
        ))), $group, $limit, 'visitors');
    }

    /**
     * The rows as the table shows them: unchanged, or with everything under SMALL gathered
     * into one "other" row at the end (O-20), and then cut to the limit.
     *
     * @param list<array{value: string, visitors: int|null, views: int}> $rows
     * @param 'visitors'|'views' $by which count decides that a row is small
     * @return list<array{value: string, visitors: int|null, views: int}>
     */
    public static function gathered(array $rows, bool $group, ?int $limit, string $by): array
    {
        if (!$group) {
            return $rows;
        }

        $kept = [];
        $other = ['value' => self::OTHER, 'visitors' => 0, 'views' => 0];
        $small = 0;
        foreach ($rows as $row) {
            if ((int) $row[$by] >= self::SMALL) {
                $kept[] = $row;
                continue;
            }
            $small++;
            $other['visitors'] = $row['visitors'] === null ? null : (int) $other['visitors'] + (int) $row['visitors'];
            $other['views'] += $row['views'];
        }
        if ($limit !== null) {
            $kept = array_slice($kept, 0, max(1, $limit));
        }

        return $small === 0 ? $kept : [...$kept, $other];
    }

    /**
     * Addresses visitors asked for and the site does not have, most asked first (O-20).
     *
     * stats_missing knows a day, an address and where the visitor came from, so it can be
     * narrowed by those two and by nothing else; null says the screen is narrowed to
     * something it cannot answer, and the screen says so rather than showing a wrong list.
     *
     * @return list<array{path: string, source: string, views: int}>|null
     */
    public function missing(StatsFilter $filter, ?int $limit = 10): ?array
    {
        foreach ($filter->narrowed as $dimension => $value) {
            if ($dimension !== 'path' && $dimension !== 'source') {
                return null;
            }
        }
        [$where, $params] = $filter->where();
        $cap = $limit === null ? '' : ' LIMIT ' . max(1, $limit);

        return array_values(array_map(static fn (array $row): array => [
            'path' => (string) $row['path'],
            'source' => (string) $row['source'],
            'views' => (int) $row['total_views'],
        ], $this->db->all(
            "SELECT path, source, SUM(views) AS total_views FROM stats_missing
             WHERE {$where} GROUP BY path, source ORDER BY total_views DESC, path{$cap}",
            $params,
        )));
    }

    /**
     * The change from $before to $now as a fraction (0.25 for a quarter more), or null when
     * there was nothing before to compare with.
     */
    public static function change(float $now, float $before): ?float
    {
        return $before > 0 ? ($now - $before) / $before : null;
    }
}
