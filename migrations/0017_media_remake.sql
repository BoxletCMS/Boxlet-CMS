-- Making a picture's sizes again (PLAN.md O-13, D-048).
--
-- revision counts how many times a picture's variants were made again after its upload.
-- It is part of every variant's address (MediaPresets::version) beside the original's
-- hash, because a remake changes the files and keeps both the hash and the file names:
-- without it browsers would go on showing the old variants from their cache (D-047).
--
-- remake is the work still owed: NULL for nothing, otherwise the variants already made
-- again in this pass, comma-separated ('' = none yet), so a pass cut short by the time
-- limit carries on where it stopped rather than from the start.
ALTER TABLE media ADD COLUMN revision INTEGER NOT NULL DEFAULT 0;
ALTER TABLE media ADD COLUMN remake VARCHAR(500) NULL;
