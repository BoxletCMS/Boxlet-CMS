<?php

use App\Support\Url;

/**
 * One block's library preview, written to public/cache/previews as a static file.
 *
 * It links the same stylesheets the site does, so the library shows a block as this
 * site renders it rather than as a generic drawing. The URLs are baked in when the file
 * is generated; a design change gives the file a new name and this is written again.
 *
 * @var string $locale
 * @var string $title
 * @var string $blockHtml
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
</head>
<body class="bx-preview">
    <main>
<?= $blockHtml ?>
    </main>
</body>
</html>
