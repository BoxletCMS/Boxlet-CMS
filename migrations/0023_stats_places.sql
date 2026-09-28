-- Where visitors are, past the country (PLAN.md D-055, SPEC §5.7).
--
-- One row per day and place: the country, the region and the city, with the city's own
-- coordinates so the map can put a marker on it. The coordinates come from the location
-- database, never from anything a visitor sent, and are rounded to two decimal places —
-- a marker on the city, not a marker on a street.
--
-- THE PATH IS NOT HERE, AND WILL NOT BE. A count that says which pages somebody in a small
-- town read is a description of a person, not of an audience. The country stays in
-- stats_views, where it is safe to combine because a country holds millions; the region and
-- the city live here alone. It also keeps the rows bounded: a day cannot hold more rows
-- than it had views, whatever the site's shape.
--
-- Empty is "not known" in both name columns, as everywhere else in these tables, and the
-- words for it are the admin's. A row is written at whatever level the owner asked for, so
-- a site counting countries has rows with a country and two empty names.
CREATE TABLE stats_places (
    day VARCHAR(10) NOT NULL,
    country VARCHAR(2) NOT NULL,
    region VARCHAR(120) NOT NULL,
    city VARCHAR(120) NOT NULL,
    -- Nullable, because a place can be known without a coordinate for it.
    latitude DECIMAL(6, 2) NULL,
    longitude DECIMAL(6, 2) NULL,
    views INTEGER NOT NULL DEFAULT 0,
    visitors INTEGER NOT NULL DEFAULT 0,
    CONSTRAINT stats_places_unique UNIQUE (day, country, region, city)
);
