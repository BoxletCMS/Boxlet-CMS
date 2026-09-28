-- What a page was before the last few saves (PLAN.md D-088), the table SPEC §5.2 has
-- carried since the beginning and nothing had built.
--
-- WHY IT CHANGES WHAT THE OWNER DARES DO. Undo covers the editing session (D-079) and dies
-- with the tab. Everything before that save was unrecoverable: a heading rewritten last
-- Tuesday, a paragraph deleted and saved, a block removed and saved — gone, with no way
-- back short of a database backup nobody on shared hosting knows how to read. An editor you
-- are afraid of is an editor you use carefully and slowly.
--
-- ONE ROW IS ONE WHOLE PAGE as it stood: its settings and every block, in the shape the
-- editor reads and the shape a save writes. So restoring is not a special path — it is a
-- save, through the same validation, the same media resolution and the same sitemap
-- refresh as any other. A restore that took a shortcut past those would be the one code
-- path nobody exercises until the day it matters.
--
-- JSON in one column rather than a mirror of pages and page_blocks, for the reason
-- design_library gives (D-061): the shape is the editor's, not this table's, and a block
-- definition gaining a field must not need a migration here.
--
-- FIVE PER PAGE, pruned on write. A limit is what keeps this from being a second copy of
-- the site that grows without end on hosting sold by the gigabyte; five is what covers the
-- mistake this exists for, the same reasoning that gave undo twenty steps.
CREATE TABLE page_revisions (
    id {{pk}},
    page_id INTEGER NOT NULL,
    data_json TEXT NOT NULL,
    created_at VARCHAR(19) NOT NULL,
    -- A revision has no meaning without its page, and a deleted page's history is not
    -- something anyone can reach: the same rule menu_items follows for its menu.
    CONSTRAINT page_revisions_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE
);

-- Every read is "this page's revisions, newest first", and so is the pruning.
CREATE INDEX page_revisions_page_created ON page_revisions (page_id, created_at);
