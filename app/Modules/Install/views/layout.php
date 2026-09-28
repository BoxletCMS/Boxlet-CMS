<?php

use App\Support\Url;

/**
 * Provided by View::render().
 *
 * @var string $locale
 * @var string $title
 * @var string $content rendered HTML of the step template
 * @var string $step
 * @var list<string> $steps
 * @var string|null $error
 * @var string $csrf
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
<body class="admin admin-centered">
    <main class="card">
        <p class="hint"><?= e(t('install.brand')) ?></p>
<?php if (in_array($step, $steps, true)): ?>
        <ol class="steps">
<?php foreach ($steps as $name): ?>
            <li<?= $name === $step ? ' aria-current="step"' : '' ?>><?= e(t('install.step.' . $name)) ?></li>
<?php endforeach; ?>
        </ol>
<?php endif; ?>
<?php if ($error !== null): ?>
        <p class="notice notice-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>
<?= $content ?>
<?php if (in_array($step, ['database', 'admin', 'site'], true)): ?>
        <form method="post" action="<?= e(Url::asset('install.php')) ?>" class="restart">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <button type="submit" name="action" value="restart" class="button button-quiet"><?= e(t('install.restart')) ?></button>
        </form>
<?php endif; ?>
    </main>
</body>
</html>
