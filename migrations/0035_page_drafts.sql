-- WHAT A PAGE IS BECOMING (PLAN.md D-163, D-173): its draft, one whole page as a document —
-- settings, sections with their style, blocks with content, options, layout and column —
-- while the published page stays in pages, page_sections and page_blocks, which is all a
-- visitor's page ever reads.
--
-- ONE ROW PER PAGE, at most: the page id is the key. Publishing writes the document into
-- the published tables in one transaction and deletes this row; Discard deletes it; a
-- revision is restored INTO it, never straight onto the live page.
--
-- VERSIONED, for two tabs. Every save says which version it was made from and is refused
-- (409) when the row has moved on since, so one tab's autosave never quietly overwrites
-- another's work.
--
-- MEDIUMTEXT, not TEXT: MySQL's TEXT stops at 64 KB, and a long page of rich text is a
-- whole page here. SQLite reads the name as text.
CREATE TABLE page_drafts (
    page_id INTEGER NOT NULL PRIMARY KEY,
    draft_json MEDIUMTEXT NOT NULL,
    version INTEGER NOT NULL,
    updated_at VARCHAR(19) NOT NULL,
    -- Which administrator; one today, and the column is what lets a second be told apart.
    updated_by INTEGER NULL,
    CONSTRAINT page_drafts_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE
);
