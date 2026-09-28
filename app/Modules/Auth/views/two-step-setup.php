<?php

use App\Support\Url;

/**
 * Setting up two-step login (PLAN.md D-050): scan, then prove it with a code.
 *
 * @var string $title
 * @var string $qr an SVG, drawn on this server
 * @var string $secret the same secret as text, for an app that cannot scan
 * @var string|null $error
 * @var string $csrf
 */
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
        <p class="page-subtitle"><a href="<?= e(Url::admin('settings') . '#two-step') ?>"><?= e(t('twofactor.back')) ?></a></p>

        <div class="panel stack two-step-setup">
            <ol class="two-step-steps">
                <li><?= e(t('twofactor.step_app')) ?></li>
                <li>
                    <?= e(t('twofactor.step_scan')) ?>
                    <div class="two-step-qr"><?= $qr ?></div>
                    <p class="hint"><?= e(t('twofactor.step_type')) ?> <code class="two-step-secret"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code></p>
                </li>
                <li>
                    <?= e(t('twofactor.step_confirm')) ?>
<?php if ($error !== null): ?>
                    <p class="field-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>
                    <form method="post" action="<?= e(Url::admin('two-step')) ?>" class="two-step-confirm">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <label for="two-step-code" class="visually-hidden"><?= e(t('twofactor.code')) ?></label>
                        <input type="text" id="two-step-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required spellcheck="false">
                        <button type="submit" class="button"><?= e(t('twofactor.confirm')) ?></button>
                    </form>
                </li>
            </ol>
        </div>
