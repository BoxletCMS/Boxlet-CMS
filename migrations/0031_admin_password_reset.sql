-- A forgotten password, reset by a link sent by email (PLAN.md D-132).
--
-- Only a HASH of the link's token is kept: the database leaking must not hand anyone a
-- working link. One admin, one link at a time, so the two columns live on the admin's own
-- row rather than in a table of their own; asking for a second link replaces the first.
ALTER TABLE admin ADD COLUMN reset_hash VARCHAR(64) NULL;

-- When the link stops working, an hour after it was asked for. UTC, Y-m-d H:i:s.
ALTER TABLE admin ADD COLUMN reset_expires_at VARCHAR(19) NULL;
