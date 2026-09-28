-- Page templates: a named starting list of block types. Choosing one when creating a
-- page copies those block types into the page; nothing links back afterwards.
-- Built-in names are keys, shown through t('template.<name>') in the admin.
CREATE TABLE templates (
    id {{pk}},
    name VARCHAR(100) NOT NULL,
    layout_json TEXT NOT NULL,
    preview_image VARCHAR(255) NULL,
    is_builtin SMALLINT NOT NULL DEFAULT 0
);
INSERT INTO templates (name, layout_json, is_builtin) VALUES ('landing', '{"blocks":["hero","image_text","text"]}', 1);
INSERT INTO templates (name, layout_json, is_builtin) VALUES ('article', '{"blocks":["text"]}', 1);
INSERT INTO templates (name, layout_json, is_builtin) VALUES ('feature', '{"blocks":["hero","image_text","image_text"]}', 1);
