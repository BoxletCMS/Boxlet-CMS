-- THE OWNER'S OWN SCRIPTS, AND WHAT A VISITOR AGREED TO (PLAN.md O-73, the owner, 2026-10-22: "mogućnost
-- da admin može insertirati javascript na stranicu, na primjer za vanjsku statistiku", with a cookie
-- banner, "sa popisom cookia i sa prikazivanjem javascripta samo ako posjetitelj dozvoli").
--
-- One row per script the owner adds: its code as written, where it goes (head, or the end of the
-- body), and its kind — necessary runs on every page; statistics and marketing only once a visitor
-- has agreed to that kind, in their own browser. cookies_json lists what it sets, for the banner to
-- show: name, who sets it, what for, how long. position orders the scripts within a place.
CREATE TABLE scripts (
    id {{pk}},
    name VARCHAR(190) NOT NULL,
    code TEXT NOT NULL,
    place VARCHAR(10) NOT NULL,
    kind VARCHAR(20) NOT NULL,
    cookies_json TEXT NOT NULL,
    enabled INTEGER NOT NULL,
    position INTEGER NOT NULL,
    created_at VARCHAR(19) NOT NULL,
    updated_at VARCHAR(19) NOT NULL
);
