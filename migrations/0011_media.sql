-- Uploaded files (SPEC §5.2). One row per distinct file: `hash` is the sha1 of the
-- bytes and is unique, so uploading the same picture twice reuses the record rather than
-- filling the disk with copies.
--
-- `path` is the original, stored untouched, relative to public/. Variants are not listed
-- here: their names follow from the id and filename (SPEC §5.1), and they are generated
-- on upload so that a request for one is always a request for a file that exists.
--
-- focal_x and focal_y are percentages, 0 to 100, defaulting to the middle. Cropped
-- presets keep that point in frame, which is what stops a crop cutting off a head.
CREATE TABLE media (
    id {{pk}},
    filename VARCHAR(190) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    path VARCHAR(255) NOT NULL,
    mime VARCHAR(100) NOT NULL,
    size INTEGER NOT NULL,
    width INTEGER NOT NULL,
    height INTEGER NOT NULL,
    hash VARCHAR(40) NOT NULL,
    focal_x SMALLINT NOT NULL DEFAULT 50,
    focal_y SMALLINT NOT NULL DEFAULT 50,
    created_at VARCHAR(19) NOT NULL,
    CONSTRAINT media_hash_unique UNIQUE (hash)
);
CREATE INDEX media_created ON media (created_at);
