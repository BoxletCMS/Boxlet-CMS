<?php

/**
 * The page a visitor sees while the site is closed (PLAN.md D-019, D-021), served with
 * 503 and Retry-After. Both triggers share it; only the words differ.
 *
 * Deliberately self-contained: no layout, no stylesheet, no database. The site's own
 * templates read tables that a pending migration may be about to create or change, and
 * the compiled design stylesheet is looked up in settings — so rendering the real site
 * here is the failure this page exists to prevent. Its style is inline because there is
 * no guarantee a stylesheet can be served either, and this page has no CSP of its own.
 *
 * Provided by View::render() with no layout.
 *
 * @var string $locale
 * @var string $title
 * @var string $message what to tell the visitor: being updated, or back in a moment
 */
?>
<!doctype html>
<html lang="<?= e($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title) ?></title>
    <style>
        body { margin: 0; display: grid; place-items: center; min-height: 100vh;
               font: 1rem/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
               color: #1b1f27; background: #f5f7fa; }
        main { max-width: 32rem; padding: 2rem; text-align: center; }
        h1 { font-size: 1.3125rem; margin: 0 0 0.5rem; }
        p { margin: 0; color: #545d6b; }
    </style>
</head>
<body>
    <main>
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>
    </main>
</body>
</html>
