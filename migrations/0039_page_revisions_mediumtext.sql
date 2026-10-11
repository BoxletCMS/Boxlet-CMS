-- A REVISION HOLDS AS MUCH AS A DRAFT (PLAN.md D-200). A revision is the same whole-page
-- document a draft is (PageDocument), and page_drafts was given MEDIUMTEXT because MySQL's
-- TEXT stops at 64 KB (0035); page_revisions kept TEXT, so on MySQL a page longer than that
-- could be drafted and then not recorded when published. Found by the audit before v0.1, at
-- 4.9 KB for the largest revision on the copy — far from the edge, which is the time to move it.
--
-- A column's type cannot be changed the same way on both drivers (SQLite has no MODIFY), so
-- the table is made again and its rows copied, ids kept; SQLite reads MEDIUMTEXT as text.
CREATE TABLE page_revisions_wide (
    id {{pk}},
    page_id INTEGER NOT NULL,
    data_json MEDIUMTEXT NOT NULL,
    created_at VARCHAR(19) NOT NULL,
    CONSTRAINT page_revisions_wide_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE
);

INSERT INTO page_revisions_wide (id, page_id, data_json, created_at)
    SELECT id, page_id, data_json, created_at FROM page_revisions;

DROP TABLE page_revisions;

ALTER TABLE page_revisions_wide RENAME TO page_revisions;

-- Every read is "this page's revisions, newest first", and so is the pruning.
CREATE INDEX page_revisions_page_created ON page_revisions (page_id, created_at);
