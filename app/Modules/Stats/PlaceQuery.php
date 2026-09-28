<?php

namespace App\Modules\Stats;

use App\Core\Db;

/**
 * The regions and cities visitors came from (PLAN.md D-055), read from stats_places.
 *
 * A table of its own, because the answers are: it is the one table the path never enters,
 * so a question that narrows by page cannot be asked of it at all, and the screen says so
 * rather than showing a number that means something else.
 *
 * A FLOOR UNDER THE CITIES. A city with one or two visitors in a period is close to naming
 * somebody, so cities under the floor are gathered into one row and the row says how many
 * they were.
 *
 * IT IS THE OWNER'S NUMBER SINCE D-109, WHICH IT WAS NOT BEFORE. It was fixed at five, from
 * the specification's own privacy rule, and on a site with twenty visitors a month that left
 * the Cities panel showing nothing but "Other" — the owner's words: "nema neke logike da se
 * ne prikazuju najposjećeniji gradovi". Five is still the default. One is offered and is a
 * real choice with a real cost: at one a row describes a PERSON, not a place, and the site's
 * own privacy text drops the sentence promising otherwise (PrivacyText). A setting that
 * quietly made that sentence false would be the worst kind of wrong.
 */
final class PlaceQuery
{
    /** What the owner may choose. One is in the list deliberately; see the note above. */
    public const MIN_CHOICES = [1, 2, 3, 5, 10];

    /** Fewer visitors than this in the period, and a city is shown with the others. */
    public const DEFAULT_MIN = 5;

    private readonly int $cityMin;

    public function __construct(private readonly Db $db, ?int $cityMin = null)
    {
        $this->cityMin = in_array($cityMin, self::MIN_CHOICES, true) ? $cityMin : self::DEFAULT_MIN;
    }

    /**
     * The places of one kind — 'region' or 'city' — busiest first.
     *
     * @return list<array{value: string, visitors: int|null, views: int}>
     */
    public function top(string $column, StatsFilter $filter, ?int $limit = 10, bool $group = false): array
    {
        [$where, $params] = $filter->placeWhere();
        // A region with no name and a city with no name are one row, not two: the place is
        // simply not known that far, and the table says "not known" once.
        $rows = array_values(array_map(static fn (array $row): array => [
            'value' => (string) $row['value'],
            'visitors' => (int) $row['total_visitors'],
            'views' => (int) $row['total_views'],
        ], $this->db->all(
            "SELECT {$column} AS value, SUM(visitors) AS total_visitors, SUM(views) AS total_views
             FROM stats_places WHERE {$where}
             GROUP BY {$column} ORDER BY total_visitors DESC, total_views DESC, {$column}",
            $params,
        )));

        return $column === 'city'
            ? self::gathered($rows, $this->cityMin, $limit)
            : StatsQuery::gathered($rows, $group, $limit, 'visitors');
    }

    /**
     * Every city with a coordinate, for the markers on the map. The floor applies here
     * too: a city too small to be named in the table is too small to be a dot on a map.
     *
     * @return list<array{city: string, country: string, visitors: int, latitude: float, longitude: float}>
     */
    public function markers(StatsFilter $filter): array
    {
        [$where, $params] = $filter->placeWhere();
        $rows = $this->db->all(
            "SELECT city, country, MIN(latitude) AS latitude, MIN(longitude) AS longitude, SUM(visitors) AS visitors
             FROM stats_places
             WHERE {$where} AND city <> '' AND latitude IS NOT NULL
             GROUP BY city, country HAVING SUM(visitors) >= " . $this->cityMin . '
             ORDER BY visitors DESC',
            $params,
        );

        return array_values(array_map(static fn (array $row): array => [
            'city' => (string) $row['city'],
            'country' => (string) $row['country'],
            'visitors' => (int) $row['visitors'],
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
        ], $rows));
    }

    /** The floor this query is reading with, for the row that has to say the number. */
    public function floor(): int
    {
        return $this->cityMin;
    }

    /**
     * The rows with everything under $floor gathered into one, whatever the "gather small
     * rows" setting says: that setting is about tidiness, this is about a person. At a floor
     * of one nothing is gathered, which is the point of offering one.
     *
     * @param list<array{value: string, visitors: int|null, views: int}> $rows
     * @return list<array{value: string, visitors: int|null, views: int}>
     */
    private static function gathered(array $rows, int $floor, ?int $limit): array
    {
        $kept = [];
        $other = ['value' => StatsQuery::OTHER, 'visitors' => 0, 'views' => 0];
        $small = 0;
        foreach ($rows as $row) {
            // A city with no name is already every unnamed place together, so it is not
            // one of the small ones: it goes in with them all the same, since "not known"
            // and "too few to name" are the same row to a reader.
            if ((int) $row['visitors'] >= $floor && $row['value'] !== '') {
                $kept[] = $row;
                continue;
            }
            $small++;
            $other['visitors'] = (int) $other['visitors'] + (int) $row['visitors'];
            $other['views'] += $row['views'];
        }
        if ($limit !== null) {
            $kept = array_slice($kept, 0, max(1, $limit));
        }

        return $small === 0 ? $kept : [...$kept, $other];
    }
}
