-- Site settings, one JSON value per key. `key` is quoted because KEY is reserved in
-- MySQL; SQLite accepts the same backticks.
CREATE TABLE settings (
    `key` VARCHAR(190) NOT NULL PRIMARY KEY,
    value_json TEXT NOT NULL
);
