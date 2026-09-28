-- A sixth preset, `natural`: 960 px wide at most, never cropped (PLAN.md D-119).
--
-- Every picture already uploaded has no `natural` variant, and variants are made on
-- upload, never on demand (SPEC §5.5). So every finished picture is marked as owed a
-- remake — the resumable pass D-048 built for exactly this, "after a preset changes" — and
-- the Media screen offers to carry it out, a request's worth at a time. Until it has, a
-- block that asks for `natural` falls back to `full`, which keeps the shape too: nothing
-- renders cropped, only heavier.
UPDATE media SET remake = '' WHERE status = 'complete';
