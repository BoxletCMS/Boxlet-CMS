-- WHETHER A KEY MAY PUBLISH, PER KEY (PLAN.md D-291; the owner, 2026-10-23: one checkbox for every
-- AI app, saved by a button at the foot of the panel, read as saving the whole panel). A key now
-- reads; reads and writes drafts; or also publishes, chosen when it is made, as its writing is.
--
-- The checkbox's answer is carried over: where it was on, every key that writes and is not
-- revoked keeps publishing. Then the setting goes; nothing reads it any more.
ALTER TABLE mcp_keys ADD COLUMN can_publish INTEGER NOT NULL DEFAULT 0;

UPDATE mcp_keys SET can_publish = 1
WHERE can_write = 1 AND revoked_at IS NULL
  AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'mcp_publish' AND value_json = 'true');

DELETE FROM settings WHERE `key` = 'mcp_publish';
