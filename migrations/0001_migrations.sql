-- Applied migrations, one row per file. The first migration creates it so the Migrator
-- records this file like any other.
CREATE TABLE IF NOT EXISTS migrations (
    filename VARCHAR(190) NOT NULL PRIMARY KEY,
    applied_at VARCHAR(19) NOT NULL
);
