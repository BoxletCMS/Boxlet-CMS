-- WHAT A LANGUAGE'S MENU ITEM AND FORM WERE COPIED FROM (PLAN.md D-262, the owner, 2026-10-14).
--
-- A page copied into another language takes its place in that language's menus, and a form on it
-- becomes that language's form. Each copy remembers its original, as a page does through
-- content_group_id and a block through block_group_id: so copying again finds the copy instead of
-- making a second one, an item Boxlet placed is told apart from one the owner added, and AI
-- translation knows which words are still the original's.
--
-- NULL for anything made by hand, and for everything that existed before this migration.
--
-- NO FOREIGN KEY, deliberately: MySQL ignores REFERENCES on an added column, and SQLite cannot add
-- a constraint to a table that exists. Both ids are made by Migrator's primary key, which never
-- gives a number twice: an original that is deleted leaves a number that names nothing, which
-- every reader treats as "copied from nothing".
ALTER TABLE menu_items ADD COLUMN source_item_id INTEGER NULL;

ALTER TABLE forms ADD COLUMN source_form_id INTEGER NULL;
