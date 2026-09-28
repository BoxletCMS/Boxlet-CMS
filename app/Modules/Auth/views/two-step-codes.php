<?php

use App\Support\Url;

/**
 * The recovery codes, shown this once (PLAN.md D-050).
 *
 * @var string $title
 * @var list<string> $codes
 * @var string $said
 */
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
        <p class="notice notice-success" role="status"><?= e($said) ?></p>

        <div class="panel stack">
            <p><?= e(t('twofactor.codes_intro')) ?></p>
            <ul class="two-step-codes">
<?php foreach ($codes as $code): ?>
                <li><code><?= e($code) ?></code></li>
<?php endforeach; ?>
            </ul>
            <p class="hint"><?= e(t('twofactor.codes_once')) ?></p>
            <p><a class="button" href="<?= e(Url::admin('settings') . '#two-step') ?>"><?= e(t('twofactor.codes_saved')) ?></a></p>
        </div>
