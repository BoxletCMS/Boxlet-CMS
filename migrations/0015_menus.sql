-- Menus, built by hand (PLAN.md D-028, resolving O-7).
--
-- A menu is its own thing, NOT the page tree. The tree orders the admin list (D-011) and
-- says what is under what; a menu says what a visitor is offered and what it is called
-- there, which is a different question with a different answer on most sites.
--
-- One menu belongs to one locale, like a page does, so a translation has its own labels
-- rather than borrowing the source language's. The locale column is VARCHAR(12) with the
-- same foreign key `pages` uses, because it is the same kind of value.
CREATE TABLE menus (
    id {{pk}},
    locale VARCHAR(12) NOT NULL,
    name VARCHAR(190) NOT NULL,
    created_at VARCHAR(19) NOT NULL,
    updated_at VARCHAR(19) NOT NULL,
    CONSTRAINT menus_locale_name_unique UNIQUE (locale, name),
    CONSTRAINT menus_locale_fk FOREIGN KEY (locale) REFERENCES locales (code)
);

-- An item points at a page OR carries an address of its own, may rename what it points
-- at, is ordered among its siblings, and may sit one level under another item.
--
-- WHAT HAPPENS WHEN THE PAGE GOES. page_id is ON DELETE SET NULL, the same rule
-- pages.parent_id already follows: deleting a page must not delete the menu entry a
-- person built, and it must not leave a link to nothing either. The row survives with
-- page_id NULL and url NULL, which is an item that names no destination — the front end
-- leaves it out, and the admin shows it, marked, so the owner can repoint or remove it.
-- Silently vanishing from the admin as well would be the same defect MediaReference was
-- written to prevent: a reference that resolves to nothing must be visible somewhere.
--
-- An UNPUBLISHED page is a different case and not a schema one: the row is intact and the
-- page may be published again tomorrow. That is decided at render time, not here.
--
-- menu_id cascades: a menu that is deleted takes its items, which have no meaning without
-- it. parent_id cascades for the same reason one level down. Depth is limited to one
-- submenu in code, because SQL cannot express "no grandchildren" portably.
CREATE TABLE menu_items (
    id {{pk}},
    menu_id INTEGER NOT NULL,
    parent_id INTEGER NULL,
    page_id INTEGER NULL,
    url VARCHAR(2048) NULL,
    label VARCHAR(255) NULL,
    sort INTEGER NOT NULL DEFAULT 0,
    created_at VARCHAR(19) NOT NULL,
    updated_at VARCHAR(19) NOT NULL,
    CONSTRAINT menu_items_menu_fk FOREIGN KEY (menu_id) REFERENCES menus (id) ON DELETE CASCADE,
    CONSTRAINT menu_items_parent_fk FOREIGN KEY (parent_id) REFERENCES menu_items (id) ON DELETE CASCADE,
    CONSTRAINT menu_items_page_fk FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE SET NULL
);

-- Reading a menu is always "this menu, in order, within a parent", which is what the
-- front end and the admin both ask for.
CREATE INDEX menu_items_menu_sort ON menu_items (menu_id, parent_id, sort);

-- And the question the page editor needs: which items point at the page being deleted.
CREATE INDEX menu_items_page ON menu_items (page_id);
