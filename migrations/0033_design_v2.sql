-- The global decisions become numbers, and the header and footer join them in one store
-- (PLAN.md D-162, D-164).
--
-- design_tokens keeps its shape and changes its meaning: one row per value the OWNER set,
-- none for a key that follows the character. A row written before this held every key, by
-- name ("roomy", "pill"), and would be read as an owner's choice of a value the vocabulary
-- no longer takes; the look's own settings, chrome_look_<choice>, move into design_tokens.
-- There is no site whose rows have to be carried over (D-162), so both are emptied: the
-- site follows its character until the owner changes something.
DELETE FROM design_tokens;
DELETE FROM settings WHERE `key` LIKE 'chrome_look_%';
