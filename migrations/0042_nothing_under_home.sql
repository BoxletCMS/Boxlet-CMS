-- NOTHING STANDS UNDER A HOME PAGE (PLAN.md O-55, the owner, 2026-10-11). The page list's move
-- refused it and the editor and Publish offered it; the owner chose the list's rule. A page
-- under a home page kept a top-level address anyway, so it moves to the top level of its
-- language and its address stays as it is: only the tree and the breadcrumb change.
--
-- The derived table is for MySQL, which refuses an UPDATE that reads the table it changes.
UPDATE pages SET parent_id = NULL WHERE parent_id IN (SELECT id FROM (SELECT id FROM pages WHERE slug = '') AS homes);
