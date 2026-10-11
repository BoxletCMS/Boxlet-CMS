-- A CHARACTER KEPT AS LARGE AS A SET MAY BE (PLAN.md D-200). DesignSet::parse() reads a file
-- of up to 64 KB, and the row keeps it as DesignSet::export() writes it — pretty-printed,
-- measured a third larger than the files it came from (Workshop 8,048 bytes in, 11,107
-- out). MySQL's TEXT stops at 64 KB, so a set near the limit could be read and then not
-- kept. MEDIUMTEXT, as page_drafts and page_revisions (0035, 0039); SQLite reads it as text.
--
-- Made again and copied, ids kept: a column's type is not changed the same way on both.
CREATE TABLE design_characters_wide (
    id {{pk}},
    slug VARCHAR(32) NOT NULL,
    set_json MEDIUMTEXT NOT NULL,
    source VARCHAR(16) NOT NULL DEFAULT 'import',
    created_at VARCHAR(19) NOT NULL,
    updated_at VARCHAR(19) NOT NULL,
    CONSTRAINT design_characters_wide_slug UNIQUE (slug)
);

INSERT INTO design_characters_wide (id, slug, set_json, source, created_at, updated_at)
    SELECT id, slug, set_json, source, created_at, updated_at FROM design_characters;

DROP TABLE design_characters;

ALTER TABLE design_characters_wide RENAME TO design_characters;
