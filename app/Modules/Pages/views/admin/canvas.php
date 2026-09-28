<?php

use App\Support\Url;

/**
 * The canvas document, shown in the editor's iframe.
 *
 * It links exactly what a visitor's page links, so the canvas is the page and not an
 * approximation of it. canvas.css adds the editor's own chrome; it is written in its own
 * literal values and never reads a site token, the same rule the admin follows.
 *
 * @var string $locale
 * @var string $title
 * @var string $blocksHtml already rendered by Blocks::render()
 */
?>
<!doctype html>
<html lang="<?= e($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(Url::stylesheet()) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/site.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks-hero.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks-words.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks-media.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks-downloads.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/sections.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/canvas.css')) ?>">
    <?php /* Reordering happens here, inside the canvas, because drag events do not cross
             a document boundary. See SPEC 3 for the vendored front-end asset rule. */ ?>
    <script src="<?= e(Url::versioned('assets/vendor/sortable.min.js')) ?>" defer></script>
    <script src="<?= e(Url::versioned('assets/canvas.js')) ?>" defer></script>
    <script src="<?= e(Url::versioned('assets/canvas-tools.js')) ?>" defer></script>
    <script src="<?= e(Url::versioned('assets/canvas-drag.js')) ?>" defer></script>
</head>
<body class="bx-canvas" data-insert-labels="<?= e(implode('|', [t('pages.insert_here'), t('pages.insert_at_end'), t('pages.insert_in_column'), t('pages.insert_word.section'), t('pages.insert_word.block')])) ?>"
      data-block-labels="<?= e(implode('|', [t('pages.move_up'), t('pages.move_down'), t('pages.duplicate'), t('pages.remove')])) ?>"
      data-empty-label="<?= e(t('pages.canvas_empty_block')) ?>"
      data-icons="<?= e(Url::versioned('assets/vendor/icons.svg')) ?>">
    <?php /* Sections are direct children of main, exactly as on the front end: their CSS
             depends on being siblings, so nothing may be inserted between them. */ ?>
    <main data-bx-blocks>
<?= $blocksHtml ?>
    </main>
</body>
</html>
