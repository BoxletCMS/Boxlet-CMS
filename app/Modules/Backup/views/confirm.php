<?php

use App\Support\Url;

/**
 * Before restoring (PLAN.md D-139): what happens, in the order it happens, and one button.
 *
 * @var array{name: string, kind: string, size: int, made: string, version: string, readable: bool}|null $backup
 * @var string $zone
 * @var string $title
 * @var string $csrf
 */
if ($backup === null) {
    return;
}
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
        <div class="panel stack backups-confirm">
            <p><?= e(t('backups.restore_which', [
                'made' => $backup['made'] !== '' ? App\Support\Dates::local($backup['made'], $zone) : $backup['name'],
            ])) ?></p>
            <ol>
                <li><?= e(t('backups.restore_step1')) ?></li>
                <li><?= e(t('backups.restore_step2')) ?></li>
                <li><?= e(t('backups.restore_step3')) ?></li>
                <li><?= e(t('backups.restore_step4')) ?></li>
            </ol>
            <p class="hint"><?= e(t('backups.restore_login')) ?></p>
            <div class="form-actions">
                <form method="post" action="<?= e(Url::admin('backups', $backup['name'], 'restore')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button type="submit" class="button button-primary"><?= icon('history') ?> <?= e(t('backups.restore_go')) ?></button>
                </form>
                <a class="button button-secondary" href="<?= e(Url::admin('backups')) ?>"><?= e(t('backups.cancel')) ?></a>
            </div>
        </div>
