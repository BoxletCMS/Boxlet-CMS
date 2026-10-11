<?php

use App\Support\Url;

/**
 * The canvas document, shown in the editor's iframe.
 *
 * It links exactly what a visitor's page links, so the canvas is the page and not an
 * approximation of it. canvas.css and canvas-marks.css add the editor's own chrome; they are
 * written in their own literal values and never read a site token, the same rule the admin
 * follows.
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
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks-hero-split.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks-cards.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks-words.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks-accordion.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks-stats.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks-media.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks-logos.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks-embed.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/blocks-downloads.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/sections.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/sections-edges.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/sections-steps.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/sections-columns.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/canvas.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/canvas-inserter.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/canvas-inline.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/canvas-marks.css')) ?>">
    <?php /* No script of its own: the builder draws over this document from the parent, which
             it can reach because both are this site's (builder-overlay.js, D-175). */ ?>
</head>
<body class="bx-canvas">
    <?php /* Sections are direct children of main, exactly as on the front end: their CSS
             depends on being siblings, so nothing may be inserted between them. */ ?>
    <main data-bx-blocks>
<?= $blocksHtml ?>
    </main>
</body>
</html>
