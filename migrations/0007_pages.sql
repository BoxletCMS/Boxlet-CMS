-- One row per page per locale (SPEC §5.2). content_group_id ties translations of the
-- same page together; a new page starts its own group with its own id. The home page
-- of a locale is the page whose slug is ''.
CREATE TABLE pages (
    id {{pk}},
    content_group_id INTEGER NULL,
    locale VARCHAR(12) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'draft',
    template_id INTEGER NULL,
    parent_id INTEGER NULL,
    sort INTEGER NOT NULL DEFAULT 0,
    seo_json TEXT NULL,
    translation_status VARCHAR(16) NOT NULL DEFAULT 'source',
    source_hash VARCHAR(64) NULL,
    published_at VARCHAR(19) NULL,
    created_at VARCHAR(19) NOT NULL,
    updated_at VARCHAR(19) NOT NULL,
    CONSTRAINT pages_locale_slug_unique UNIQUE (locale, slug),
    CONSTRAINT pages_locale_fk FOREIGN KEY (locale) REFERENCES locales (code),
    CONSTRAINT pages_template_fk FOREIGN KEY (template_id) REFERENCES templates (id) ON DELETE SET NULL,
    CONSTRAINT pages_parent_fk FOREIGN KEY (parent_id) REFERENCES pages (id) ON DELETE SET NULL
);
CREATE INDEX pages_content_group ON pages (content_group_id);
