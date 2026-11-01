-- WHO KEPT A SAVED DESIGN (PLAN.md D-290). An AI client keeps a design in the library through
-- MCP, and the owner looking for it in Appearance has to be able to tell it from their own:
-- 'ai' for one an AI client kept, '' for the owner's. Writing over a design from the screen
-- makes it the owner's again.
ALTER TABLE design_library ADD COLUMN source VARCHAR(16) NOT NULL DEFAULT '';
