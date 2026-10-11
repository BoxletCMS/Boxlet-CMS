-- SNIPPETS (PLAN.md D-201, SPEC §5.6): a few words the owner writes once and places in rich
-- text as {{snippet:name}} — opening hours, an address, a phone number — so changing them is
-- one edit, not one per page. One row per snippet and language: a translation is its own
-- words, as a menu's labels are (menus, 0015). The value is inline rich text (bold, italic,
-- a link), cleaned with RichText::INLINE, because a tag stands inside a sentence.
--
-- The name is how a tag names it and never changes once made: a renamed snippet would leave
-- every tag that named it pointing at nothing. Unique per language.
CREATE TABLE snippets (
    id {{pk}},
    name VARCHAR(64) NOT NULL,
    locale VARCHAR(12) NOT NULL,
    value TEXT NOT NULL,
    created_at VARCHAR(19) NOT NULL,
    updated_at VARCHAR(19) NOT NULL,
    CONSTRAINT snippets_name_locale UNIQUE (name, locale)
);
