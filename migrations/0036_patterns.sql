-- SAVED SECTIONS (PLAN.md D-163, D-173, README 2.3): "Save as pattern" keeps a copy of a
-- section — its arrangement, its style, its blocks with their content — to insert into any
-- page later, deep-copied with new keys.
--
-- The owner's patterns live here. A design set's own patterns (D-169) are read from the set
-- when they are offered, not copied in: a set that changes, or a character that is removed,
-- leaves no stale copies behind. `source` and `set_id` are the handoff's columns, kept so a
-- pattern can say where it came from: 'user' with no set for every row written today.
--
-- MEDIUMTEXT for the reason page_drafts gives.
CREATE TABLE patterns (
    id {{pk}},
    name VARCHAR(120) NOT NULL,
    section_json MEDIUMTEXT NOT NULL,
    source VARCHAR(16) NOT NULL DEFAULT 'user',
    set_id VARCHAR(64) NULL,
    created_at VARCHAR(19) NOT NULL
);
