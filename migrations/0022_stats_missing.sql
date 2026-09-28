-- Addresses visitors asked for and the site does not have (PLAN.md O-20): the 404s, counted
-- only while the owner asks for them in Settings.
--
-- Its own table, not a row in stats_views: a 404 is not a page view, and counting it as one
-- would put addresses that do not exist into the pages people read. The source is kept for
-- the reason the count is: it says WHO is linking to the address that is missing.
--
-- One row per day, address and source; pruned with everything else by the retention period.
CREATE TABLE stats_missing (
    day VARCHAR(10) NOT NULL,
    path VARCHAR(255) NOT NULL,
    source VARCHAR(190) NOT NULL,
    views INTEGER NOT NULL DEFAULT 0,
    CONSTRAINT stats_missing_unique UNIQUE (day, path, source)
);
