-- A BLOCK'S LAYOUT MAY FOLLOW THE CHARACTER (PLAN.md D-191, the owner). '' is "the layout the
-- character composes", drawn as whatever character the site has; any other is the owner's own,
-- which Apply with composition no longer touches. Until now every block stored a layout by
-- name, so a layout no one chose looked chosen.
--
-- A block whose layout is the one the site's character composes for its type is taken to have
-- followed it, and stores ''. One statement per core character and block type, generated from
-- their compositions on 2026-10-05; the site's character is the `design_character` setting, the
-- default (Minimal) where there is none. A custom character's blocks keep their names. Drafts
-- keep theirs too: they store whole documents, and a layout kept by name draws the same.
UPDATE page_blocks SET layout = '' WHERE block_type = 'accordion' AND layout = 'list' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'cards' AND layout = 'grid' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'cta' AND layout = 'banner' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'divider' AND layout = 'space' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'downloads' AND layout = 'list' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'embed' AND layout = 'full' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'form' AND layout = 'stacked' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'gallery' AND layout = 'three' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'hero' AND layout = 'left' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'image_text' AND layout = 'image-left' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'logos' AND layout = 'row' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'picture' AND layout = 'full' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'quote' AND layout = 'plain' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'stats' AND layout = 'three' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'text' AND layout = 'single' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"editorial"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'accordion' AND layout = 'list' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'cards' AND layout = 'grid' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'cta' AND layout = 'banner' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'divider' AND layout = 'space' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'downloads' AND layout = 'list' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'embed' AND layout = 'full' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'form' AND layout = 'stacked' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'gallery' AND layout = 'three' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'hero' AND layout = 'center' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'image_text' AND layout = 'image-left' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'logos' AND layout = 'row' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'picture' AND layout = 'full' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'quote' AND layout = 'plain' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'stats' AND layout = 'three' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'text' AND layout = 'single' AND (EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"minimal"') OR NOT EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character'));
UPDATE page_blocks SET layout = '' WHERE block_type = 'accordion' AND layout = 'list' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'cards' AND layout = 'grid' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'cta' AND layout = 'banner' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'divider' AND layout = 'space' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'downloads' AND layout = 'list' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'embed' AND layout = 'full' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'form' AND layout = 'stacked' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'gallery' AND layout = 'three' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'hero' AND layout = 'center' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'image_text' AND layout = 'image-left' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'logos' AND layout = 'row' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'picture' AND layout = 'full' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'quote' AND layout = 'plain' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'stats' AND layout = 'three' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'text' AND layout = 'single' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"bold"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'accordion' AND layout = 'list' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'cards' AND layout = 'grid' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'cta' AND layout = 'banner' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'divider' AND layout = 'space' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'downloads' AND layout = 'list' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'embed' AND layout = 'full' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'form' AND layout = 'stacked' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'gallery' AND layout = 'three' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'hero' AND layout = 'split' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'image_text' AND layout = 'image-right' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'logos' AND layout = 'row' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'picture' AND layout = 'full' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'quote' AND layout = 'plain' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'stats' AND layout = 'three' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'text' AND layout = 'single' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"soft"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'accordion' AND layout = 'list' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'cards' AND layout = 'grid' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'cta' AND layout = 'banner' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'divider' AND layout = 'space' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'downloads' AND layout = 'list' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'embed' AND layout = 'full' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'form' AND layout = 'stacked' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'gallery' AND layout = 'three' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'hero' AND layout = 'split' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'image_text' AND layout = 'image-left' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'logos' AND layout = 'row' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'picture' AND layout = 'full' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'quote' AND layout = 'plain' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'stats' AND layout = 'three' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
UPDATE page_blocks SET layout = '' WHERE block_type = 'text' AND layout = 'columns' AND EXISTS (SELECT 1 FROM settings WHERE `key` = 'design_character' AND value_json = '"brutalist"');
