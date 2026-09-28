<?php

use App\Support\Url;

/**
 * Provided by View::render().
 *
 * @var string $title
 * @var string $email
 * @var string|null $error
 * @var string|null $notice what a file put there over FTP did, when one ran (D-050, D-132),
 *                          or that a new password was set from an emailed link
 * @var string $csrf
 */
?>
        <h1><?= e($title) ?></h1>
<?php if ($notice !== null): ?>
        <p class="notice notice-warning" role="status"><?= e($notice) ?></p>
<?php endif; ?>
<?php if ($error !== null): ?>
        <p class="notice notice-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>
        <form method="post" action="<?= e(Url::admin('login')) ?>" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="field">
                <span><?= e(t('auth.email')) ?></span>
                <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
            </label>
            <label class="field">
                <span><?= e(t('auth.password')) ?></span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit" class="button"><?= e(t('auth.submit')) ?></button>
        </form>
        <p class="hint"><a href="<?= e(Url::admin('forgot')) ?>"><?= e(t('account.forgot_link')) ?></a></p>
