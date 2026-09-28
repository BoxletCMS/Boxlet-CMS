<?php

use App\Support\Url;

/**
 * The second step of logging in (PLAN.md D-050): the code from the authenticator app, or a
 * recovery code in the same field.
 *
 * @var string $title
 * @var string|null $error
 * @var string $csrf
 */
?>
        <h1><?= e($title) ?></h1>
        <p class="hint"><?= e(t('twofactor.login_intro')) ?></p>
<?php if ($error !== null): ?>
        <p class="notice notice-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>
        <form method="post" action="<?= e(Url::admin('login', 'code')) ?>" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="field">
                <span><?= e(t('twofactor.code')) ?></span>
                <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required autofocus spellcheck="false">
            </label>
            <button type="submit" class="button"><?= e(t('twofactor.login_submit')) ?></button>
        </form>
        <p class="hint"><?= e(t('twofactor.login_recovery_hint')) ?></p>
        <p class="hint"><a href="<?= e(Url::admin('login')) ?>"><?= e(t('twofactor.login_back')) ?></a></p>
