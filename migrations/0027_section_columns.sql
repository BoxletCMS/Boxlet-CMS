-- A section gains COLUMNS, and a block gains the column it sits in (PLAN.md D-093 step 3).
--
-- 0026 gave every section a `layout` of 'one' and reserved it for exactly this. What was
-- missing was the block's place across the section: `sort` is its place DOWN a column, and
-- with one column that was the whole answer. Now a block needs to say which column too.
--
-- ZERO IS WHERE EVERY EXISTING BLOCK ALREADY IS. A section of layout 'one' has one column,
-- numbered 0, so the default is not a migration of the data but a statement of where the
-- data already stands. Nothing is rewritten and nothing renders differently.
ALTER TABLE page_blocks ADD COLUMN column_index INTEGER NOT NULL DEFAULT 0;

-- NOT NAMED `column`, which is reserved in MySQL and would need quoting in every statement
-- that touches it — and a column name that only works when quoted is a trap for the next
-- person to write a query, not a style preference.

-- HOW A SECTION BEHAVES ON A NARROW SCREEN, the one knob D-093 allows: stack (the columns
-- become rows, top to bottom), stay (they keep their proportions, for a row of small things
-- that genuinely still fits), reverse (they stack bottom to top).
--
-- Reverse earns its place because it is the one arrangement an owner needs and cannot
-- otherwise express: a picture left of text reads correctly on a wide screen and wrongly
-- stacked, because the picture then comes before the words it illustrates. Every other
-- responsive wish is a design decision the character already makes.
ALTER TABLE page_sections ADD COLUMN stack VARCHAR(16) NOT NULL DEFAULT 'stack';
