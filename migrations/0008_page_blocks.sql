-- Blocks of a page, in sort order. block_group_id ties the same block across locales; a
-- new block starts its own group with its own id. block_type never changes after
-- creation. style_json holds the layer-2 section style (SPEC §5.4).
CREATE TABLE page_blocks (
    id {{pk}},
    page_id INTEGER NOT NULL,
    block_group_id INTEGER NULL,
    block_type VARCHAR(64) NOT NULL,
    sort INTEGER NOT NULL DEFAULT 0,
    content_json TEXT NOT NULL,
    style_json TEXT NOT NULL,
    translation_status VARCHAR(16) NOT NULL DEFAULT 'source',
    source_hash VARCHAR(64) NULL,
    created_at VARCHAR(19) NOT NULL,
    updated_at VARCHAR(19) NOT NULL,
    CONSTRAINT page_blocks_page_fk FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE
);
CREATE INDEX page_blocks_page_sort ON page_blocks (page_id, sort);
CREATE INDEX page_blocks_block_group ON page_blocks (block_group_id);
