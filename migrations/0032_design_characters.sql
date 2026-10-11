-- Characters an owner imports in the admin (PLAN.md D-152), and later ones a model writes.
--
-- The five Boxlet ships are files in designs/core/, and the ones an owner drops in over FTP
-- are files in designs/custom/ — but a shared host rarely lets PHP write beside its code, so
-- an import made on the Appearance screen is kept here, where Boxlet can always write.
--
-- ONE ROW IS ONE WHOLE SET, as the file it would be: DesignSet::export()'s text, read back
-- through DesignSet::parse() like any other set, so the rules that admit it are the rules
-- that read it — and a set a later Boxlet would refuse is left out, not trusted.
--
-- JSON in one column rather than a column per decision, for the reason design_library gives
-- (0024): the decisions' shape is SPEC's and the format's, not this table's.
--
-- The slug is unique, because it is how a site and its library name the character: a second
-- import of `soft` becomes `soft-2` rather than replacing the first, or the core one.
CREATE TABLE design_characters (
    id {{pk}},
    slug VARCHAR(32) NOT NULL,
    set_json TEXT NOT NULL,
    -- Where it came from: 'import' now; 'ai' when a model can write one.
    source VARCHAR(16) NOT NULL DEFAULT 'import',
    created_at VARCHAR(19) NOT NULL,
    updated_at VARCHAR(19) NOT NULL,
    CONSTRAINT design_characters_slug UNIQUE (slug)
);
