<?php

use App\Support\Url;

/**
 * Provided by View::render(). Passwords are never refilled after an error.
 *
 * @var array<mixed> $old
 * @var string $csrf
 */
$email = is_string($old['email'] ?? null) ? $old['email'] : '';
?>
        <h1><?= e(t('install.admin.title')) ?></h1>
        <p class="hint"><?= e(t('install.admin.intro')) ?></p>
        <form method="post" action="<?= e(Url::asset('install.php')) ?>" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="field">
                <span><?= e(t('install.admin.email')) ?></span>
                <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required>
            </label>
            <label class="field">
                <span><?= e(t('install.admin.password')) ?></span>
                <input type="password" name="password" minlength="12" autocomplete="new-password" required>
                <span class="hint"><?= e(t('install.admin.password_hint', ['min' => 12])) ?></span>
            </label>
            <label class="field">
                <span><?= e(t('install.admin.password_confirm')) ?></span>
                <input type="password" name="password_confirm" minlength="12" autocomplete="new-password" required>
            </label>
            <button type="submit" class="button"><?= e(t('install.admin.submit')) ?></button>
        </form>
