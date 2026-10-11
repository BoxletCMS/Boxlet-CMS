-- THE OWNER'S DARK COLOURS IN A KEPT DESIGN (PLAN.md D-187). A hand colour holds in both modes;
-- one set while Appearance is in Dark is the owner's for dark mode only, and a design kept in
-- the library keeps those too. The site's own live in design_tokens as `dark.<key>` rows,
-- which need no change.
--
-- NULL rather than a default, as options_json: the code reads NULL as `{}`.
ALTER TABLE design_library ADD COLUMN dark_json TEXT NULL;
