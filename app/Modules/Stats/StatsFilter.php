<?php

namespace App\Modules\Stats;

use DateTimeImmutable;

/**
 * What the Statistics screen is showing (PLAN.md O-20): which days, and which page, source,
 * country, device, browser or system the figures are narrowed to.
 *
 * All of it comes from the address and goes back into every link on the screen, so a view
 * can be kept, shared and stepped back out of with the browser's own Back — and so the whole
 * screen works without a script.
 */
final class StatsFilter
{
    /** The dimensions that can be narrowed, and the column each one is. */
    public const DIMENSIONS = [
        'path' => 'path',
        'source' => 'source',
        'country' => 'country',
        'device' => 'device',
        'browser' => 'browser',
        'os' => 'os',
    ];

    /**
     * @param array<string, string> $narrowed dimension => the value it is narrowed to
     */
    private function __construct(
        public readonly string $period,
        public readonly string $from,
        public readonly string $to,
        public readonly array $narrowed,
    ) {
    }

    /**
     * The filter an address asks for. Anything unknown falls back to the last 7 days, so a
     * hand-typed or truncated address shows a screen rather than an error.
     *
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query, DateTimeImmutable $today): self
    {
        $period = is_string($query['period'] ?? null) ? $query['period'] : '7d';
        $from = self::day($query['from'] ?? null);
        $to = self::day($query['to'] ?? null);

        if ($period === 'custom' && $from !== null && $to !== null) {
            [$from, $to] = $from <= $to ? [$from, $to] : [$to, $from];
        } else {
            $period = isset(StatsQuery::PERIODS[$period]) ? $period : '7d';
            $range = StatsQuery::range($period, $today);
            [$from, $to] = [$range['from'], $range['to']];
        }

        $narrowed = [];
        foreach (array_keys(self::DIMENSIONS) as $dimension) {
            $value = $query[$dimension] ?? null;
            if (is_string($value)) {
                // '-' is how an address says "the ones with none": no source, no country.
                $narrowed[$dimension] = $value === '-' ? '' : mb_substr($value, 0, 255);
            }
        }

        return new self($period, $from, $to, $narrowed);
    }

    /** The only dimension stats_places has of its own, and so the only one it answers. */
    public const PLACE_DIMENSIONS = ['country'];

    /**
     * Whether the screen is narrowed to something the places table does not hold — a page
     * above all, which it deliberately never will (D-055). The regions and the cities are
     * left off the screen then, with a line saying why, rather than answering a different
     * question from the one that was asked.
     */
    public function narrowedBeyondPlaces(): bool
    {
        foreach (array_keys($this->narrowed) as $dimension) {
            if (!in_array($dimension, self::PLACE_DIMENSIONS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The WHERE for stats_places: the days, and the country when the screen is narrowed to
     * one. Anything else it cannot be narrowed by is simply not there — the caller asks
     * narrowedBeyondPlaces() first.
     *
     * @return array{string, list<string>}
     */
    public function placeWhere(): array
    {
        $sql = 'day >= ? AND day <= ?';
        $params = [$this->from, $this->to];
        foreach (self::PLACE_DIMENSIONS as $dimension) {
            if (array_key_exists($dimension, $this->narrowed)) {
                $sql .= ' AND ' . $dimension . ' = ?';
                $params[] = $this->narrowed[$dimension];
            }
        }

        return [$sql, $params];
    }

    /** Whether any dimension is narrowed, which is what the page visitors cannot answer. */
    public function isNarrowed(): bool
    {
        return $this->narrowed !== [];
    }

    /**
     * The period of the same length immediately before this one, for the comparison.
     *
     * @return array{from: string, to: string}
     */
    public function previous(): array
    {
        $from = new DateTimeImmutable($this->from);
        $days = max(1, (int) $from->diff(new DateTimeImmutable($this->to))->format('%a') + 1);

        return [
            'from' => $from->modify("-{$days} days")->format('Y-m-d'),
            'to' => $from->modify('-1 day')->format('Y-m-d'),
        ];
    }

    /** How many days it covers; the chart draws a year by week rather than by day. */
    public function days(): int
    {
        return max(1, (int) (new DateTimeImmutable($this->from))->diff(new DateTimeImmutable($this->to))->format('%a') + 1);
    }

    /**
     * The WHERE for stats_views: the days, and every dimension narrowed.
     *
     * @return array{string, list<string>}
     */
    public function where(?string $fromDay = null, ?string $toDay = null): array
    {
        $sql = 'day >= ? AND day <= ?';
        $params = [$fromDay ?? $this->from, $toDay ?? $this->to];
        foreach ($this->narrowed as $dimension => $value) {
            $sql .= ' AND ' . self::DIMENSIONS[$dimension] . ' = ?';
            $params[] = $value;
        }

        return [$sql, $params];
    }

    /**
     * This filter as an address's query, with $add put in and $without taken out — which is
     * what every link on the screen is built from.
     *
     * @param array<string, string> $add
     * @param array<int|string, string> $without a docblock's list<string> does not reach an
     *        arrow function assigned to a variable, which is how the views build addresses
     * @return array<string, string>
     */
    public function asQuery(array $add = [], array $without = []): array
    {
        $query = $this->period === 'custom'
            ? ['period' => 'custom', 'from' => $this->from, 'to' => $this->to]
            : ['period' => $this->period];
        foreach ($this->narrowed as $dimension => $value) {
            $query[$dimension] = $value === '' ? '-' : $value;
        }
        foreach ($add as $key => $value) {
            $query[$key] = $value;
        }
        foreach ($without as $key) {
            unset($query[$key]);
        }

        return $query;
    }

    /** A Y-m-d day from the address, or null when it is not one. */
    private static function day(mixed $value): ?string
    {
        if (!is_string($value) || preg_match('~^\d{4}-\d{2}-\d{2}$~', $value) !== 1) {
            return null;
        }
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $day !== false && $day->format('Y-m-d') === $value ? $value : null;
    }
}
