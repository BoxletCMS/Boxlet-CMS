<?php

use App\Support\Url;

/**
 * Your login on the Settings screen (PLAN.md D-132): the admin's email and password, and
 * two-step login (D-050) under them. Every form is its own, outside the settings form, and
 * the email and the password each ask for the current password.
 *
 * @var array{on: bool, codesLeft: int} $twoStep
 * @var string $accountEmail
 * @var string $csrf
 */
?>
        <div class="panel stack account" id="account">
            <h2><?= e(t('account.title')) ?></h2>
            <p class="hint"><?= e(t('account.intro')) ?></p>

            <form method="post" action="<?= e(Url::admin('account', 'email')) ?>" class="stack account-form">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <h3><?= e(t('account.email')) ?></h3>
                <div class="field">
                    <label for="account-email"><?= e(t('account.email_label')) ?></label>
                    <input type="email" id="account-email" name="email" value="<?= e($accountEmail) ?>" autocomplete="username" required>
                </div>
                <div class="field">
                    <label for="account-email-current"><?= e(t('account.current')) ?></label>
                    <input type="password" id="account-email-current" name="current_password" autocomplete="current-password" required>
                </div>
                <div><button type="submit" class="button button-secondary"><?= e(t('account.email_save')) ?></button></div>
            </form>

            <form method="post" action="<?= e(Url::admin('account', 'password')) ?>" class="stack account-form">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <h3><?= e(t('account.password')) ?></h3>
                <?php /* The address beside it, hidden, so a password manager knows which
                         account the new password belongs to. */ ?>
                <input type="email" name="username" value="<?= e($accountEmail) ?>" autocomplete="username" hidden>
                <div class="field">
                    <label for="account-password-current"><?= e(t('account.current')) ?></label>
                    <input type="password" id="account-password-current" name="current_password" autocomplete="current-password" required>
                </div>
                <div class="field">
                    <label for="account-password-new"><?= e(t('account.new')) ?></label>
                    <input type="password" id="account-password-new" name="new_password" minlength="<?= e((string) \App\Modules\Auth\Password::MIN) ?>" autocomplete="new-password" required aria-describedby="account-password-hint">
                    <span class="hint" id="account-password-hint"><?= e(t('account.new_hint', ['min' => (string) \App\Modules\Auth\Password::MIN])) ?></span>
                </div>
                <div class="field">
                    <label for="account-password-confirm"><?= e(t('account.confirm')) ?></label>
                    <input type="password" id="account-password-confirm" name="new_password_confirm" minlength="<?= e((string) \App\Modules\Auth\Password::MIN) ?>" autocomplete="new-password" required>
                </div>
                <div><button type="submit" class="button button-secondary"><?= e(t('account.password_save')) ?></button></div>
            </form>

            <h3 id="two-step"><?= e(t('twofactor.title')) ?></h3>
            <p class="hint"><?= e(t('twofactor.intro')) ?></p>
<?php if (!$twoStep['on']): ?>
            <p><?= e(t('twofactor.off_now')) ?></p>
            <p><a class="button button-secondary" href="<?= e(Url::admin('two-step')) ?>"><?= e(t('twofactor.set_up')) ?></a></p>
<?php else: ?>
            <p><strong><?= e(t('twofactor.on_now')) ?></strong> <?= e(t('twofactor.codes_left', ['left' => (string) $twoStep['codesLeft']])) ?></p>
            <form method="post" action="<?= e(Url::admin('two-step', 'codes')) ?>" class="two-step-inline">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <label for="two-step-renew-code"><?= e(t('twofactor.renew_label')) ?></label>
                <input type="text" id="two-step-renew-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required spellcheck="false">
                <button type="submit" class="button button-secondary"><?= e(t('twofactor.renew')) ?></button>
            </form>
            <form method="post" action="<?= e(Url::admin('two-step', 'off')) ?>" class="two-step-inline">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <label for="two-step-off-password"><?= e(t('twofactor.off_label')) ?></label>
                <input type="password" id="two-step-off-password" name="password" autocomplete="current-password" required>
                <button type="submit" class="button button-ghost button-danger"><?= e(t('twofactor.turn_off')) ?></button>
            </form>
            <p class="hint"><?= e(t('twofactor.lost_phone', ['file' => 'storage/' . \App\Modules\Auth\TwoFactor::RESET_FILE])) ?></p>
<?php endif; ?>
        </div>
