-- Enabled locales. Exactly one row has is_primary = 1: chosen at install, never changed.
CREATE TABLE locales (
    code VARCHAR(12) NOT NULL PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    is_primary SMALLINT NOT NULL DEFAULT 0,
    fallback VARCHAR(12) NULL,
    sort INTEGER NOT NULL DEFAULT 0,
    enabled SMALLINT NOT NULL DEFAULT 1
);
