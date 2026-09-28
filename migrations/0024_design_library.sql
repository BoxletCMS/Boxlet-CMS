-- Designs the owner keeps (PLAN.md D-061), the thing the design layer was missing.
--
-- Five characters could be LOADED and nothing could be SAVED: an afternoon spent on colour
-- and type had nowhere to go, and loading any character threw it away. That is the real
-- source of "too few options" — not the number of controls, but that nothing you do with
-- them can be kept.
--
-- ONE ROW IS ONE WHOLE LOOK: the ten decisions and the seven header-and-footer choices, as
-- they were on the screen. Not the menu and not the owner's words — those are the site's
-- content, and a design carrying them would overwrite a Croatian footer with an English one
-- the moment it was used on another page of the same site.
--
-- JSON in one column rather than seventeen columns: the decisions are layer 1 and their
-- shape is SPEC's, not this table's. A new decision there must not need a migration here,
-- and Tokens::validate() already refuses anything that does not belong (SPEC §5.4). The
-- same reasoning design_tokens itself was built on.
--
-- The name is unique, because saving over a design you already have is one of the three
-- things this table exists for — save, overwrite, delete — and "which of these two is the
-- one I meant" is not a question a library should ever ask.
CREATE TABLE design_library (
    id {{pk}},
    name VARCHAR(80) NOT NULL,
    -- The character it was built from, when it was, so a saved design can say where it
    -- came from and compose new blocks the same way. Empty when it was made by hand.
    character_name VARCHAR(32) NOT NULL DEFAULT '',
    decisions_json TEXT NOT NULL,
    look_json TEXT NOT NULL,
    created_at VARCHAR(19) NOT NULL,
    updated_at VARCHAR(19) NOT NULL,
    CONSTRAINT design_library_name UNIQUE (name)
);
