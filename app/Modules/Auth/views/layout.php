<?php

use App\Support\Url;

/**
 * Provided by View::render().
 *
 * @var string $locale
 * @var string $title
 * @var string $content rendered HTML of the page template
 * @var string $theme 'light', 'dark' or 'system' — the palette this browser last chose in
 *                    the admin (D-054), so logging out does not change the colours
 */
?>
<!doctype html>
<html lang="<?= e($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin-tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin-ui.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin-forms.css')) ?>">
</head>
<body class="admin admin-centered" data-ui-theme="<?= e($theme) ?>">
    <main class="card">
<?= $content ?>
    </main>
</body>
</html>
