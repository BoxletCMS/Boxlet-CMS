-- The activity log (PLAN.md D-052): what changed on the site, and when, for the Overview's
-- "Recent activity" and the full log behind it.
--
-- No actor column: Boxlet has one admin (CLAUDE.md), so "who" would say the same thing on
-- every row. A visitor's message is logged as its own kind.
--
-- kind is what changed (page, media, menu, form, message, design, settings) and action what
-- happened to it (created, saved, published, deleted, ...); the admin turns the pair into
-- words through t(), so the log reads in the admin's language rather than the one it was
-- written in. subject is the thing's name AT THE TIME — a page renamed or deleted since
-- keeps the name the log knew it by — and subject_id points at it while it exists.
--
-- Kept for a year, pruned on write.
CREATE TABLE activity (
    id {{pk}},
    occurred_at VARCHAR(19) NOT NULL,
    kind VARCHAR(16) NOT NULL,
    action VARCHAR(32) NOT NULL,
    subject_id INTEGER NULL,
    subject VARCHAR(190) NOT NULL
);
CREATE INDEX activity_occurred ON activity (occurred_at);
