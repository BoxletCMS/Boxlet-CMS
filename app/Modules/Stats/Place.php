<?php

namespace App\Modules\Stats;

/**
 * Where a visitor's address is (PLAN.md D-055), as far as the owner has asked to know.
 *
 * A site that serves one country learns nothing from a map saying everybody is in that
 * country, which is why the region and the city exist at all. They are also the point at
 * which a count starts to be about a person rather than an audience, so the level is the
 * owner's choice, the city is never put beside the path, and small cities are shown
 * together rather than named.
 *
 * NAMES ARE NORMALISED HERE, and both rules come from what DB-IP's own data looks like
 * rather than from a preference:
 *   - Two Croatian addresses, 161.53.1.1 and 31.147.200.1, are in "City of Zagreb" and in
 *     "Zagreb". Without the leading administrative words one place is one row. It costs the
 *     distinction between a city-region and the county around it, where a country has both.
 *   - Nearly half the city names in a sample of four thousand addresses carry a
 *     neighbourhood in brackets — "Vienna (Leopoldstadt)", "Copenhagen (Valby)". Left
 *     alone, one city arrives as a dozen rows. Cutting the bracket also stops the count
 *     saying which part of a city somebody was in.
 */
final class Place
{
    /** Country, then region, then city: each level is the one before it, with more detail. */
    public const LEVELS = ['country', 'region', 'city'];

    /** The leading words DB-IP puts in front of a region's name. */
    private const ADMINISTRATIVE = 'City|County|State|Province|Region|District|Department|Governorate|Prefecture|Municipality|Canton|Republic|Territory|Emirate|Autonomous Region';

    public function __construct(
        public readonly string $country = '',
        public readonly string $region = '',
        public readonly string $city = '',
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
    ) {
    }

    /**
     * What a database record says, cut to $level. A country database answers the country
     * whatever the level, because that is all it holds.
     */
    public static function of(mixed $record, string $level = 'city'): self
    {
        if (!is_array($record)) {
            return new self();
        }
        $country = self::text($record['country']['iso_code'] ?? null);
        $country = preg_match('~^[A-Za-z]{2}$~', $country) === 1 ? strtoupper($country) : '';
        if ($country === '' || $level === 'country') {
            return new self($country);
        }

        $region = self::region(self::text($record['subdivisions'][0]['names']['en'] ?? null));
        if ($level === 'region') {
            return new self($country, $region);
        }

        $city = self::city(self::text($record['city']['names']['en'] ?? null));
        $location = is_array($record['location'] ?? null) ? $record['location'] : [];

        // Two decimal places: enough to put a marker on the city, and the coordinate is the
        // city's own, from the database, never anything the visitor sent.
        return new self($country, $region, $city, self::degrees($location['latitude'] ?? null), self::degrees($location['longitude'] ?? null));
    }

    /** Whether anything at all was found. */
    public function isKnown(): bool
    {
        return $this->country !== '';
    }

    private static function region(string $name): string
    {
        return (string) preg_replace('~^(?:' . self::ADMINISTRATIVE . ') of ~u', '', $name);
    }

    private static function city(string $name): string
    {
        $bracket = mb_strpos($name, '(');

        return trim($bracket === false ? $name : mb_substr($name, 0, $bracket));
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim(mb_substr($value, 0, 120)) : '';
    }

    private static function degrees(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? round((float) $value, 2) : null;
    }
}
