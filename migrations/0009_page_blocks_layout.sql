-- Layer-3 layout of each block instance (SPEC §5.4). A column, not a key inside
-- style_json: its valid values are the block definition's closed list, validated on
-- save. '' and any layout the definition no longer declares render as its default.
ALTER TABLE page_blocks ADD COLUMN layout VARCHAR(64) NOT NULL DEFAULT '';
