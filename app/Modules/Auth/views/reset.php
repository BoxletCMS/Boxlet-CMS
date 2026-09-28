<?php

use App\Support\Url;

/**
 * Setting a new password from an emailed link (PLAN.md D-132), or saying the link no longer
 * works — used once already, or older than an hour.
 *
 * @var string $title
 * @var string $token
 * @var bool $working
 * @var string|null $error
 * @var string $csrf
 */
?>
        <h1><?= e(t('account.reset_title')) ?></h1>
<?php if (!$working): ?>
        <p class="notice notice-warning" role="status"><?= e(t('account.reset_gone')) ?></p>
        <p class="hint"><a href="<?= e(Url::admin('forgot')) ?>"><?= e(t('account.reset_again')) ?></a></p>
<?php else: ?>
<?php if ($error !== null): ?>
        <p class="notice notice-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>
        <form method="post" action="<?= e(Url::admin('reset', $token)) ?>" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="field">
                <span><?= e(t('account.new')) ?></span>
                <input type="password" name="new_password" minlength="<?= e((string) \App\Modules\Auth\Password::MIN) ?>" autocomplete="new-password" required autofocus>
                <span class="hint"><?= e(t('account.new_hint', ['min' => (string) \App\Modules\Auth\Password::MIN])) ?></span>
            </label>
            <label class="field">
                <span><?= e(t('account.confirm')) ?></span>
                <input type="password" name="new_password_confirm" minlength="<?= e((string) \App\Modules\Auth\Password::MIN) ?>" autocomplete="new-password" required>
            </label>
            <button type="submit" class="button"><?= e(t('account.reset_submit')) ?></button>
        </form>
<?php endif; ?>
