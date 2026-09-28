-- Visit statistics (PLAN.md D-051, SPEC §5.7): what was viewed, per day and combination.
--
-- One row per day (in the site's time zone, Y-m-d) and combination of the page's path, the
-- source domain, the country, and the device, browser and system families. Nothing in a row
-- names a visitor: views and visitors are counts. visitors counts a visitor once a day, in
-- the row of their first view that day, so summing it over any dimension gives that day's
-- visitors without double counting.
--
-- An empty string is "none" or "unknown" in every dimension: source '' is a direct visit,
-- country '' a country not known, browser '' one not recognised. The words are the admin's.
--
-- The unique constraint is what the counting upsert relies on (UPDATE, then INSERT, then
-- UPDATE again when two views race to create the same row). Its width fits MySQL's
-- 3072-byte index limit with utf8mb4: path 255 and source 190 characters are the largest.
CREATE TABLE stats_views (
    day VARCHAR(10) NOT NULL,
    path VARCHAR(255) NOT NULL,
    source VARCHAR(190) NOT NULL,
    country VARCHAR(2) NOT NULL,
    device VARCHAR(16) NOT NULL,
    browser VARCHAR(32) NOT NULL,
    os VARCHAR(32) NOT NULL,
    views INTEGER NOT NULL DEFAULT 0,
    visitors INTEGER NOT NULL DEFAULT 0,
    CONSTRAINT stats_views_unique UNIQUE (day, path, source, country, device, browser, os)
);
