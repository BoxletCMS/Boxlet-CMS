-- What a picture means, per language (SPEC §5.2). Alt text describes the image for
-- someone who cannot see it, so it belongs to the locale rather than to the file: the
-- same photograph needs different words in English and Croatian.
--
-- A row exists only for a locale that has been given words. A missing row is not an
-- error; the front end falls back, and an image with nothing to say renders with an
-- empty alt, which is correct for decoration and better than reciting a filename.
CREATE TABLE media_meta (
    id {{pk}},
    media_id INTEGER NOT NULL,
    locale VARCHAR(12) NOT NULL,
    alt VARCHAR(255) NOT NULL DEFAULT '',
    caption TEXT NULL,
    CONSTRAINT media_meta_unique UNIQUE (media_id, locale),
    CONSTRAINT media_meta_media_fk FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE,
    CONSTRAINT media_meta_locale_fk FOREIGN KEY (locale) REFERENCES locales (code)
);
