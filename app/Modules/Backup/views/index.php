<?php

use App\Support\Bytes;
use App\Support\Url;

/**
 * The backups (PLAN.md D-139): making one, the one under way, and those kept, each to
 * download, restore or delete. Provided by BackupsController::index().
 *
 * @var list<array{name: string, kind: string, size: int, made: string, version: string, readable: bool}> $backups
 * @var int $total bytes all of them take
 * @var bool $working a backup or a restore is under way
 * @var bool $restoring the work under way ends in a restore
 * @var string|null $progress how far the last step got
 * @var string $zone the site's time zone
 * @var string $title
 * @var string $csrf
 */
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
<?php if (!$working): ?>
            <form method="post" action="<?= e(Url::admin('backups')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="button button-primary"><?= icon('database-backup') ?> <?= e(t('backups.make')) ?></button>
            </form>
<?php endif; ?>
        </div>
        <p class="page-subtitle"><?= e(t('backups.intro')) ?></p>

<?php if ($working): ?>
        <div class="panel stack backups-progress" id="progress">
            <h2><?= e(t($restoring ? 'backups.restoring' : 'backups.making')) ?></h2>
            <p role="status"><?= e($progress ?? t('backups.starting')) ?></p>
<?php if ($restoring): ?>
            <p class="hint"><?= e(t('backups.restoring_hint')) ?></p>
<?php endif; ?>
            <form method="post" action="<?= e(Url::admin('backups', 'step')) ?>" data-auto-continue>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="button"><?= e(t('backups.continue')) ?></button>
                <span class="hint js-only"><?= e(t('backups.auto')) ?></span>
            </form>
        </div>
<?php endif; ?>

<?php if ($backups === []): ?>
        <div class="empty-state">
            <p><?= e(t('backups.none')) ?></p>
        </div>
<?php else: ?>
        <div class="table-wrap">
            <table class="table backups-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(t('backups.col.made')) ?></th>
                        <th scope="col"><?= e(t('backups.col.kind')) ?></th>
                        <th scope="col" class="backups-version"><?= e(t('backups.col.version')) ?></th>
                        <th scope="col" class="backups-size"><?= e(t('backups.col.size')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= e(t('backups.col.actions')) ?></span></th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($backups as $backup): ?>
                    <tr>
                        <td><?= e($backup['made'] !== '' ? App\Support\Dates::local($backup['made'], $zone) : $backup['name']) ?></td>
                        <td><?= e(t('backups.kind.' . $backup['kind'])) ?><?= $backup['readable'] ? '' : ' · <span class="backups-damaged">' . e(t('backups.damaged')) . '</span>' ?></td>
                        <td class="backups-version"><?= e($backup['version'] === 'development' ? t('backups.development') : $backup['version']) ?></td>
                        <td class="backups-size"><?= e(Bytes::human($backup['size'])) ?></td>
                        <td class="backups-actions">
                            <a class="button button-ghost" href="<?= e(Url::admin('backups', $backup['name'], 'download')) ?>"><?= icon('download') ?> <?= e(t('backups.download')) ?></a>
<?php if ($backup['readable'] && !$working): ?>
                            <a class="button button-ghost" href="<?= e(Url::admin('backups', $backup['name'], 'restore')) ?>"><?= icon('history') ?> <?= e(t('backups.restore')) ?></a>
<?php endif; ?>
<?php if (!$working): ?>
                            <form method="post" action="<?= e(Url::admin('backups', $backup['name'], 'delete')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <button type="submit" class="button button-ghost button-danger" data-confirm="<?= e(t('backups.delete_confirm')) ?>"><?= icon('trash-2') ?> <?= e(t('backups.delete')) ?></button>
                            </form>
<?php endif; ?>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="hint backups-total"><?= e(t('backups.total', ['size' => Bytes::human($total)])) ?></p>
<?php endif; ?>
        <p class="hint"><?= e(t('backups.keep_elsewhere')) ?></p>
