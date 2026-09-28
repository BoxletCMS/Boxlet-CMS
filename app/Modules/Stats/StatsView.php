<?php

namespace App\Modules\Stats;

use DateTimeImmutable;

/**
 * How the Statistics screen and the dashboard card write what they read (PLAN.md D-051):
 * a stored value as the owner reads it, a day, a change against the period before.
 */
final class StatsView
{
    /** Table => the address's name for it. */
    private const KEYS = [
        'pages' => 'path',
        'sources' => 'source',
        'countries' => 'country',
        'devices' => 'device',
        'browsers' => 'browser',
        'os' => 'os',
    ];

    /**
     * A stored value in words: '' is "direct", "unknown" or "other" by dimension.
     *
     * @param int|null $cityMin the owner's floor under the cities (D-109), for the row that
     *        stands for the small ones and has to say the right number. The cities have a
     *        floor of their own and it is not the same as everything else's (D-055).
     */
    public static function label(string $dimension, string $value, ?int $cityMin = null): string
    {
        return match (true) {
            $value === StatsQuery::OTHER => t('stats.other_small', ['count' => (string) ($dimension === 'cities'
                ? (in_array($cityMin, PlaceQuery::MIN_CHOICES, true) ? $cityMin : PlaceQuery::DEFAULT_MIN)
                : StatsQuery::SMALL)]),
            $dimension === 'devices' => t('stats.device.' . ($value !== '' ? $value : 'desktop')),
            $value !== '' => $dimension === 'countries' ? strtoupper($value) : $value,
            $dimension === 'sources' => t('stats.direct'),
            $dimension === 'countries', $dimension === 'regions', $dimension === 'cities' => t('stats.unknown'),
            default => t('stats.other'),
        };
    }

    /**
     * The table a filter's key belongs to, and the key a table narrows by (O-20): the
     * tables are named in the plural ("countries") and the columns in the singular
     * ("country"), and the address uses the column's name.
     */
    public static function table(string $filterKey): string
    {
        return array_search($filterKey, self::KEYS, true) ?: 'pages';
    }

    public static function filterKey(string $table): string
    {
        return self::KEYS[$table] ?? 'path';
    }

    /** A Y-m-d day as the chart and the range show it: "19 Sep", or "19 Sep 2026". */
    public static function day(string $day, bool $year = false): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day);

        return $date === false ? $day : $date->format($year ? 'j M Y' : 'j M');
    }

    public static function percent(float $fraction): string
    {
        return number_format($fraction * 100) . '%';
    }

    /**
     * A change against the period before, as a marked-up figure: up, down, or a dash when
     * there was nothing before. The arrow is words for a screen reader.
     */
    public static function change(?float $change): string
    {
        if ($change === null) {
            return '<span class="stats-change">' . e(t('stats.no_change')) . '</span>';
        }
        $rounded = (int) round($change * 100);
        [$class, $said] = match (true) {
            $rounded > 0 => ['stats-change stats-up', t('stats.up', ['percent' => (string) $rounded])],
            $rounded < 0 => ['stats-change stats-down', t('stats.down', ['percent' => (string) -$rounded])],
            default => ['stats-change', t('stats.same')],
        };

        return '<span class="' . $class . '">' . e($said) . '</span>';
    }
}
