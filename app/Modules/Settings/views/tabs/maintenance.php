<?php

use App\Support\Url;

/**
 * MAINTENANCE (PLAN.md D-038, D-053, D-294): the maintenance switch and its message, and the
 * page cache, both about the site as a whole being served. Required by settings.php.
 *
 * @var array<string, mixed> $values
 * @var bool $maintenanceOn
 * @var array{on: bool, count: int} $pageCache
 * @var string $csrf
 */
$text = static fn (string $key): string => is_string($values[$key] ?? null) ? $values[$key] : '';
?>
        <?php /* MAINTENANCE IN ONE PLACE (D-038): the switch, and the message visitors see
                 while it is on. Two forms, because HTML has none nested and the switch posts
                 to /admin/maintenance, which owns the flag file (D-021); the message is a
                 setting and saves with its own button. The state is said in words first:
                 "Turn on maintenance mode" alone does not say which way round the site is.
                 The tab after General, not last (the owner, 2026-09-27: it is the setting
                 reached for most, and in a hurry; D-294 kept that). */ ?>
        <div class="panel stack" id="maintenance">
            <h2><?= e(t('maintenance.title')) ?></h2>
            <p class="hint"><?= e(t('settings.maintenance_intro')) ?></p>
            <p class="<?= $maintenanceOn ? 'notice notice-warning' : 'hint' ?>"<?= $maintenanceOn ? ' role="status"' : '' ?>>
                <?= e($maintenanceOn ? t('maintenance.on_now') : t('maintenance.off_now')) ?>
            </p>
            <form method="post" action="<?= e(Url::admin('maintenance')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="state" value="<?= $maintenanceOn ? 'off' : 'on' ?>">
                <input type="hidden" name="return" value="settings">
                <button type="submit" class="button<?= $maintenanceOn ? '' : ' button-secondary' ?>">
                    <?= e($maintenanceOn ? t('maintenance.turn_off') : t('maintenance.turn_on')) ?>
                </button>
            </form>

            <form method="post" action="<?= e(Url::admin('settings', 'maintenance-message')) ?>" class="stack maintenance-message">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="maintenance_message"><?= e(t('settings.maintenance_message')) ?></label>
                    <textarea id="maintenance_message" name="maintenance_message" rows="3"
                              placeholder="<?= e(site_t('site.closed.maintenance.body', Url::primaryLocale())) ?>"
                              aria-describedby="maintenance_message-hint"><?= e($text('maintenance_message')) ?></textarea>
                    <span class="hint" id="maintenance_message-hint"><?= e(t('settings.maintenance_message_hint')) ?></span>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button button-secondary"><?= e(t('settings.maintenance_message_save')) ?></button>
                </div>
            </form>
        </div>

<?php require dirname(__DIR__) . '/cache-panel.php'; ?>
