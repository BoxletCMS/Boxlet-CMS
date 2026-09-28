-- A page becomes a list of SECTIONS, and a section owns the layer-2 style (PLAN.md D-093,
-- reversing D-008 and SPEC §5.3 deliberately).
--
-- WHY THE STYLE MOVES. surface, rhythm, width, align, divider and the background picture
-- have always been section language applied per block, because there was nothing else to
-- hang them on. A tinted band holding three text blocks means setting the same style three
-- times and hoping they stay in step; a gallery beside a form cannot be said at all. After
-- this the style belongs to the thing it describes.
--
-- DEPTH IS EXACTLY TWO: section -> column -> block. A section cannot hold a section and a
-- block cannot hold a block. That cap is what keeps the block contract untouched — a block
-- stays a leaf with fields, so every definition, template, validator and repeater is
-- unaffected — and it is what makes this affordable at all.
--
-- THIS STEP CHANGES NOTHING ANYBODY CAN SEE. Every existing block becomes its own
-- one-column section carrying that block's style, so every page renders byte for byte as it
-- did. Columns come in the step after this one.
CREATE TABLE page_sections (
    id {{pk}},
    page_id INTEGER NOT NULL,
    sort INTEGER NOT NULL DEFAULT 0,
    -- How many columns and in what proportion: a closed set, never a percentage. A column
    -- at 37% takes the design system's guarantee with it and cannot say how it collapses on
    -- a phone — the argument SectionStyle already makes about colour. Every migrated
    -- section is 'one'.
    layout VARCHAR(32) NOT NULL DEFAULT 'one',
    -- The five enumerated keys plus the background picture id, exactly the shape
    -- page_blocks.style_json held (SPEC §5.4).
    style_json TEXT NOT NULL,
    created_at VARCHAR(19) NOT NULL,
    updated_at VARCHAR(19) NOT NULL,
    CONSTRAINT page_sections_page_fk FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE
);

CREATE INDEX page_sections_page_sort ON page_sections (page_id, sort);

-- NULL only for the moment between this statement and the copy below; every row has one
-- before the migration ends. It carries no FOREIGN KEY because SQLite cannot add one to an
-- existing table, and a constraint that exists on MySQL and not on SQLite is worse than a
-- rule kept in one place: Page::update() is the only writer, and a page's deletion still
-- cascades through page_id.
ALTER TABLE page_blocks ADD COLUMN section_id INTEGER NULL;

CREATE INDEX page_blocks_section ON page_blocks (section_id, sort);

-- EVERY EXISTING BLOCK BECOMES ITS OWN SECTION, and the section takes the block's id.
--
-- Writing the id explicitly is what makes the pairing exact without a temporary column and
-- without trusting the order rows come back in: both databases accept an explicit value in
-- an auto-increment column and continue the sequence above the highest one used. The style,
-- the position and the timestamps are the block's own, so the page it describes is
-- unchanged.
INSERT INTO page_sections (id, page_id, sort, layout, style_json, created_at, updated_at)
SELECT id, page_id, sort, 'one', style_json, created_at, updated_at FROM page_blocks;

UPDATE page_blocks SET section_id = id;

-- AND THE OLD COPY IS EMPTIED, so there is one place a section's style lives.
--
-- The column itself stays: a committed migration is never edited, and dropping a column is
-- not portable. What is not wanted is a second, plausible, silently stale copy — a reader
-- nobody updated would go on returning last week's surface for ever. Emptied, it gives the
-- defaults, which is wrong in a way somebody notices. The value is not lost: it is in the
-- section row this migration just wrote from it.
UPDATE page_blocks SET style_json = '{}';

-- page_blocks.style_json is LEFT IN PLACE and stops being read. A committed migration is
-- never edited and dropping a column is not portable, so the column stays; what stops it
-- being read by accident is a test that refuses to find page_blocks and style_json named
-- together anywhere in app/. Its sort becomes the block's place WITHIN its section.
