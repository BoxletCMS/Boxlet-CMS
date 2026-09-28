-- Addresses that used to lead somewhere (PLAN.md D-129).
--
-- TWO KINDS IN ONE TABLE, told apart by `kind`:
--
--   history   kept by Boxlet when a published page's slug changes. `path` is the old slug,
--             matched against the LAST segment of a request, since the path above it may
--             have changed since; `locale` is the page's language.
--   rule      typed by the owner for an address from an old site, `/usluge.html` or
--             `/index.php?id=12`. `path` is the whole address as typed, query included if
--             it had one; `locale` is ''.
--
-- A row points at a PAGE, never at an address, so a page renamed twice sends both old
-- addresses straight to where it is now, never through a chain. `url` is a rule's other
-- kind of target, an address rather than a page.
--
-- page_id is SET NULL, not CASCADE: a rule the owner typed must not vanish because its
-- page did, any more than a menu item does (0015). It stays, pointing nowhere, and the
-- admin says so. A deleted page's HISTORY is removed by Page::delete() itself.
--
-- hits and last_hit_at let a rule nobody follows any more be seen and removed.
CREATE TABLE redirects (
    id {{pk}},
    kind VARCHAR(10) NOT NULL,
    locale VARCHAR(10) NOT NULL,
    path VARCHAR(500) NOT NULL,
    page_id INTEGER NULL,
    url VARCHAR(1000) NULL,
    hits INTEGER NOT NULL DEFAULT 0,
    last_hit_at VARCHAR(19) NULL,
    created_at VARCHAR(19) NOT NULL,
    CONSTRAINT redirects_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE SET NULL,
    CONSTRAINT redirects_kind_locale_path UNIQUE (kind, locale, path)
);
