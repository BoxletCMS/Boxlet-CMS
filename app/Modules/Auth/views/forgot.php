<?php

use App\Support\Url;

/**
 * A forgotten password (PLAN.md D-132): the address to send a link to, what happens next,
 * and the FTP way for a site that cannot send mail.
 *
 * @var string $title
 * @var bool $sent a link was asked for, whether or not the address was the admin's
 * @var bool $noMail this site has no way of sending mail set up
 * @var string|null $error
 * @var string $file where the FTP way's file goes
 * @var string $csrf
 */
?>
        <h1><?= e($title) ?></h1>
<?php if ($sent): ?>
        <p class="notice" role="status"><?= e(t('account.forgot_sent', ['minutes' => (string) intdiv(\App\Modules\Auth\PasswordReset::LIFETIME_SECONDS, 60)])) ?></p>
<?php elseif ($noMail): ?>
        <p class="notice notice-warning" role="status"><?= e(t('account.forgot_no_mail')) ?></p>
<?php else: ?>
        <p class="hint"><?= e(t('account.forgot_intro')) ?></p>
<?php if ($error !== null): ?>
        <p class="notice notice-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>
        <form method="post" action="<?= e(Url::admin('forgot')) ?>" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="field">
                <span><?= e(t('auth.email')) ?></span>
                <input type="email" name="email" autocomplete="username" required autofocus>
            </label>
            <button type="submit" class="button"><?= e(t('account.forgot_submit')) ?></button>
        </form>
<?php endif; ?>
        <p class="hint"><?= e(t('account.forgot_ftp', ['file' => $file])) ?></p>
        <p class="hint"><a href="<?= e(Url::admin('login')) ?>"><?= e(t('account.back_to_login')) ?></a></p>
