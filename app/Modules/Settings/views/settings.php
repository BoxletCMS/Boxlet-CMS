<?php

use App\Support\Url;

/**
 * Site settings (PLAN.md D-028), IN TABS since D-294: the tabs down the left, each its own
 * address, and the one asked for beside them (views/tabs/). Provided by AdminView::render()
 * through SettingsController::render(). The owner, 2026-10-24: one long page of anchors was
 * confusing, and the AI settings were in three places; AI is now one tab of three.
 *
 * @var string $tab general, maintenance, languages, mail, ai or account
 * @var string|null $notice
 * @var string $title
 * @var string $csrf
 */
/* WHERE THE ONE LONG PAGE'S SECTIONS WENT (D-294), for settings-anchors.js: an address kept from
   before, /admin/settings#mail, reaches only General (the anchor never reaches the server), and
   is sent on from there. The anchors that are still on General (#branding…) are not here. */
$moved = [
    'maintenance' => Url::admin('settings', 'maintenance'),
    'cache' => Url::admin('settings', 'maintenance') . '#cache',
    'languages' => Url::admin('settings', 'languages'),
    'mail' => Url::admin('settings', 'mail'),
    'ai' => Url::admin('settings', 'ai'),
    'mcp' => Url::admin('settings', 'ai', 'access'),
    'llms' => Url::admin('settings', 'ai', 'search'),
    'account' => Url::admin('settings', 'account'),
    'two-step' => Url::admin('settings', 'account') . '#two-step',
    'statistics' => Url::admin('statistics', 'settings'),
    'geo' => Url::admin('statistics', 'settings') . '#geo',
];
$tabs = [
    'general' => [Url::admin('settings'), t('settings.general')],
    'maintenance' => [Url::admin('settings', 'maintenance'), t('settings.tab.maintenance')],
    'languages' => [Url::admin('settings', 'languages'), t('languages.title')],
    'mail' => [Url::admin('settings', 'mail'), t('mail.title')],
    'ai' => [Url::admin('settings', 'ai'), t('settings.tab.ai')],
    'account' => [Url::admin('settings', 'account'), t('account.title')],
];
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
<?php if (($notice ?? null) !== null): ?>
        <p class="notice notice-error" role="alert"><?= e($notice) ?></p>
<?php endif; ?>

        <?php /* THE SETTINGS HAVE THEIR OWN AXIS (D-052): the tabs down the left, so the part
                 wanted is one press away; on a phone a row above (admin-settings.css). */ ?>
        <div class="settings-layout">
        <nav class="settings-nav" aria-label="<?= e(t('settings.sections')) ?>" data-settings-nav<?= $tab === 'general' ? ' data-moved="' . e((string) json_encode($moved, JSON_UNESCAPED_SLASHES)) . '"' : '' ?>>
<?php foreach ($tabs as $name => [$href, $label]): ?>
            <a href="<?= e($href) ?>"<?= $name === $tab ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
        </nav>
        <div class="settings-sections">
<?php require __DIR__ . '/tabs/' . $tab . '.php'; ?>
        </div>
        </div>
