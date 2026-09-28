-- Which visitor keys were seen today, and where (PLAN.md D-051, SPEC §5.7).
--
-- hash is 16 bytes of HMAC-SHA256 over the visitor's address, browser and the site's host,
-- keyed by a salt made fresh each day, written as 32 hex characters. path '' marks the
-- visitor as seen on the site at all; a path marks them seen on that page. The first
-- request of a new day deletes every earlier row with the old salt, so no key outlives the
-- day it was made on, and nothing here can link a visitor across two days.
CREATE TABLE stats_seen (
    day VARCHAR(10) NOT NULL,
    hash VARCHAR(32) NOT NULL,
    path VARCHAR(255) NOT NULL,
    CONSTRAINT stats_seen_unique UNIQUE (day, hash, path)
);
