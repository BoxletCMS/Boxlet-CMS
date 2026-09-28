<?php

use App\Support\Bytes;
use App\Support\Url;

/**
 * Updating Boxlet (PLAN.md D-140): the version running, a newer one from GitHub, one from a
 * ZIP, the update under way, and rolling the last one back. Provided by UpdatesController.
 *
 * @var string $current
 * @var bool $working
 * @var string|null $progress
 * @var array{version: string, url: string, digest: string, published: string, page: string}|null $found
 * @var array<string, mixed>|null $last the last update's state
 * @var bool $canRollBack
 * @var int $limit the largest upload this server takes, in bytes
 * @var string $title
 * @var string $csrf
 */
$development = $current === App\Support\Version::DEVELOPMENT;
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
        <p class="page-subtitle"><?= e($development ? t('updates.running_development') : t('updates.running', ['version' => $current])) ?></p>

<?php if ($working): ?>
        <div class="panel stack backups-progress" id="progress">
            <h2><?= e(t('updates.updating')) ?></h2>
            <p role="status"><?= e($progress ?? t('updates.starting')) ?></p>
            <p class="hint"><?= e(t('updates.updating_hint')) ?></p>
            <form method="post" action="<?= e(Url::admin('updates', 'step')) ?>" data-auto-continue>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="button"><?= e(t('backups.continue')) ?></button>
                <span class="hint js-only"><?= e(t('backups.auto')) ?></span>
            </form>
        </div>
<?php elseif (!$development): ?>
        <div class="panel stack">
            <h2><?= e(t('updates.github_title')) ?></h2>
<?php if ($found !== null): ?>
            <p><?= e(t('updates.found', ['version' => $found['version'], 'date' => substr($found['published'], 0, 10)])) ?>
                <a href="<?= e($found['page']) ?>" rel="noopener" target="_blank"><?= e(t('updates.whats_new')) ?></a></p>
            <p class="hint"><?= e(t('updates.before')) ?></p>
            <form method="post" action="<?= e(Url::admin('updates', 'github')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="button button-primary" data-confirm="<?= e(t('updates.confirm', ['version' => $found['version']])) ?>"><?= icon('cloud-download') ?> <?= e(t('updates.install', ['version' => $found['version']])) ?></button>
            </form>
<?php else: ?>
            <p class="hint"><?= e(t('updates.github_intro')) ?></p>
            <form method="post" action="<?= e(Url::admin('updates', 'check')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="button button-secondary"><?= e(t('updates.check')) ?></button>
            </form>
<?php endif; ?>
        </div>

        <div class="panel stack">
            <h2><?= e(t('updates.file_title')) ?></h2>
            <p class="hint"><?= e(t('updates.file_intro', ['limit' => Bytes::human($limit)])) ?></p>
            <form method="post" action="<?= e(Url::admin('updates', 'upload')) ?>" enctype="multipart/form-data" class="stack">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="package"><?= e(t('updates.file_label')) ?></label>
                    <input type="file" id="package" name="package" accept=".zip,application/zip" required>
                </div>
                <div>
                    <button type="submit" class="button button-secondary" data-confirm="<?= e(t('updates.confirm_file')) ?>"><?= icon('cloud-upload') ?> <?= e(t('updates.file_go')) ?></button>
                </div>
            </form>
        </div>
<?php endif; ?>

<?php if ($last !== null && in_array($last['phase'] ?? null, ['done', 'rolled-back'], true)): ?>
        <div class="panel stack">
            <h2><?= e(t('updates.last_title')) ?></h2>
            <p><?= e(t($last['phase'] === 'done' ? 'updates.last_done' : 'updates.last_rolled_back', [
                'from' => (string) ($last['from'] ?? ''),
                'version' => (string) ($last['version'] ?? ''),
            ])) ?></p>
<?php foreach ((array) ($last['warnings'] ?? []) as $warning): ?>
            <p class="notice notice-warning" role="alert"><?= e((string) $warning) ?></p>
<?php endforeach; ?>
<?php if ($canRollBack && !$working): ?>
            <p class="hint"><?= e(t('updates.roll_back_intro', ['from' => (string) ($last['from'] ?? '')])) ?></p>
            <form method="post" action="<?= e(Url::admin('updates', 'roll-back')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="button button-ghost button-danger" data-confirm="<?= e(t('updates.roll_back_confirm')) ?>"><?= icon('undo-2') ?> <?= e(t('updates.roll_back', ['from' => (string) ($last['from'] ?? '')])) ?></button>
            </form>
<?php endif; ?>
        </div>
<?php endif; ?>
