<?php

use App\Support\Url;

/**
 * Provided by View::render().
 *
 * @var list<array{id: string, label: string, ok: bool, required: bool, detail: string}> $checks
 * @var bool $blocked
 * @var string $tokenPath
 * @var string $csrf
 */
$apache = "<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteCond %{REQUEST_FILENAME} !-f\n"
    . "    RewriteCond %{REQUEST_FILENAME} !-d\n    RewriteRule ^ index.php [QSA,L]\n</IfModule>";
$nginx = 'location / {' . "\n" . '    try_files $uri $uri/ /index.php?$query_string;' . "\n" . '}';
?>
        <h1><?= e(t('install.req.title')) ?></h1>
        <ul class="checks">
<?php foreach ($checks as $check): ?>
<?php $status = $check['ok'] ? 'ok' : ($check['required'] ? 'fail' : 'warn'); ?>
            <li class="check check-<?= e($status) ?>">
                <span class="check-status"><?= e(t('install.req.' . $status)) ?></span>
                <span><?= e($check['label']) ?></span>
<?php if (!$check['ok']): ?>
                <p class="hint"><?= e($check['detail']) ?></p>
<?php if ($check['id'] === 'rewrite'): ?>
                <p class="hint">Apache</p>
                <pre><?= e($apache) ?></pre>
                <p class="hint">nginx</p>
                <pre><?= e($nginx) ?></pre>
<?php endif; ?>
<?php endif; ?>
            </li>
<?php endforeach; ?>
        </ul>
<?php if ($blocked): ?>
        <p class="notice notice-error"><?= e(t('install.req.blocked')) ?></p>
        <form method="get" action="<?= e(Url::asset('install.php')) ?>">
            <button type="submit" class="button"><?= e(t('install.req.recheck')) ?></button>
        </form>
<?php else: ?>
        <form method="post" action="<?= e(Url::asset('install.php')) ?>" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="field">
                <span><?= e(t('install.token.label')) ?></span>
                <input type="text" name="token" autocomplete="off" spellcheck="false" required>
                <span class="hint"><?= e(t('install.token.hint', ['path' => $tokenPath])) ?></span>
            </label>
            <button type="submit" class="button"><?= e(t('install.continue')) ?></button>
        </form>
<?php endif; ?>
