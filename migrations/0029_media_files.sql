-- Files for visitors to download, in the same library as the pictures (PLAN.md O-17, D-126).
--
-- `kind` says which a row is. Every row already here is a picture, which is what the default
-- says, so nothing is rewritten. A picture has variants, a focal point and alt text; a file
-- has none of them and is served whole, through PHP, as a download — and whatever reads the
-- library for pictures (the picker, a remake) asks for `kind = 'picture'`.
ALTER TABLE media ADD COLUMN kind VARCHAR(16) NOT NULL DEFAULT 'picture';

-- How many times a file was downloaded by a visitor: counted where it is served, and never
-- by the admin or a crawler. Pictures keep it at nought.
ALTER TABLE media ADD COLUMN downloads INTEGER NOT NULL DEFAULT 0;
