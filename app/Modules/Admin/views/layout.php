<?php

use App\Support\Url;

/**
 * The admin shell. It links only the admin's own stylesheets: the site's compiled
 * tokens never reach the admin, so the tool stays a stable reference whatever the site
 * is set to (SPEC §5.4). The one place the site's design appears is the Design
 * preview, which is an iframe with its own document.
 *
 * Provided by AdminView::render().
 *
 * @var string $locale
 * @var string $title
 * @var string $content rendered HTML of the page template
 * @var string $siteName
 * @var string $nav current section: dashboard, pages, media, menus, forms, design, chrome, settings or statistics
 * @var list<string> $styles extra stylesheets under public/assets
 * @var list<string> $scripts extra scripts under public/assets, in load order
 * @var bool $wide whether this screen wants the wide column
 * @var bool $bare whether this screen fills the window instead of the reading column
 * @var string|null $flash one-time message from the previous request
 * @var string $flashKind 'success', 'warning' or 'error'; a refusal must not be coloured as a win
 * @var string $csrf
 * @var bool $statsOn whether statistics are counted, and so have a place in the rail
 * @var array{pages: int, media: int, menus: int, forms: int} $counts how many of each, beside its rail entry
 * @var string $host the site's own host name
 * @var string $zone the site's time zone
 * @var string $time the time there now, H:i
 * @var string $adminEmail who is logged in
 * @var string $railState 'compact' or 'wide' as the owner last left the rail, '' if never
 * @var string $theme 'light', 'dark' or 'system' — which palette this browser is drawn in
 * @var string $here this page's own address, so the theme switch comes back to it
 */
$current = static fn (string $section): string => $nav === $section ? ' aria-current="page"' : '';

/* The rail's groups and entries, in order. A count where the screen lists things; the
   Statistics entry only while statistics are counted (D-051). */
$rail = [
    'site' => [
        ['nav' => 'dashboard', 'href' => Url::admin(), 'icon' => 'gauge', 'label' => t('admin.nav.dashboard'), 'count' => null],
    ],
    'content' => [
        ['nav' => 'pages', 'href' => Url::admin('pages'), 'icon' => 'file-text', 'label' => t('admin.nav.pages'), 'count' => $counts['pages']],
        ['nav' => 'media', 'href' => Url::admin('media'), 'icon' => 'image', 'label' => t('admin.nav.media'), 'count' => $counts['media']],
        ['nav' => 'menus', 'href' => Url::admin('menus'), 'icon' => 'list', 'label' => t('admin.nav.menus'), 'count' => $counts['menus']],
        ['nav' => 'forms', 'href' => Url::admin('forms'), 'icon' => 'list-checks', 'label' => t('admin.nav.forms'), 'count' => $counts['forms']],
    ],
    'presentation' => [
        // One item where there were two (D-059): Design and Header & footer were one screen
        // cut in half, and the rail said so twice.
        ['nav' => 'appearance', 'href' => Url::admin('appearance'), 'icon' => 'palette', 'label' => t('admin.nav.appearance'), 'count' => null],
    ],
    // Statistics last, so dropping it leaves the others where they were.
    'administration' => array_filter([
        ['nav' => 'settings', 'href' => Url::admin('settings'), 'icon' => 'settings', 'label' => t('admin.nav.settings'), 'count' => null],
        ['nav' => 'redirects', 'href' => Url::admin('redirects'), 'icon' => 'signpost', 'label' => t('admin.nav.redirects'), 'count' => null],
        $statsOn ? ['nav' => 'statistics', 'href' => Url::admin('statistics'), 'icon' => 'chart-column', 'label' => t('admin.nav.statistics'), 'count' => null] : null,
    ]),
];

/* The screen's name in the strip: the rail entry that is current, else the page's title. */
$section = $title;
foreach ($rail as $entries) {
    foreach ($entries as $entry) {
        if ($entry['nav'] === $nav) {
            $section = $entry['label'];
        }
    }
}
?>
<!doctype html>
<html lang="<?= e($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin-tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin-choices.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin-shell.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin-strip.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin-rail-compact.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin-ui.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin-forms.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin-tables.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin-parts.css')) ?>">
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/admin-palette.css')) ?>">
<?php foreach ($styles as $style): ?>
    <link rel="stylesheet" href="<?= e(Url::versioned('assets/' . $style)) ?>">
<?php endforeach; ?>
    <script src="<?= e(Url::versioned('assets/admin.js')) ?>" defer></script>
    <script src="<?= e(Url::versioned('assets/row-menu.js')) ?>" defer></script>
    <script src="<?= e(Url::versioned('assets/admin-nav.js')) ?>" defer></script>
    <script src="<?= e(Url::versioned('assets/admin-palette.js')) ?>" defer></script>
<?php foreach ($scripts as $script): ?>
    <script src="<?= e(Url::versioned('assets/' . $script)) ?>" defer></script>
<?php endforeach; ?>
</head>
<body class="admin" data-ui-theme="<?= e($theme) ?>">
    <a class="skip-link" href="#admin-content"><?= e(t('admin.skip')) ?></a>
    <?php /* The page editor always opens with the rail folded to its icons: the canvas needs
             the room. Elsewhere it is as the owner last left it (admin-rail-compact.css). */ ?>
    <div class="admin-frame<?= $bare || $railState === 'compact' ? ' rail-compact' : ($railState === 'wide' ? ' rail-wide' : '') ?>" data-admin-frame<?= $bare ? ' data-rail-editor' : '' ?>>
        <?php /* THE RAIL (D-052): the site's name, then every screen in four groups, each entry
                 with its icon and, where it has one, how many there are. Grouped because it
                 has to hold a dozen screens, which a bar across the top could not. Without a
                 script it is simply there — beside the content, or above it on a phone. */ ?>
        <aside class="admin-rail" id="admin-rail" data-admin-rail>
            <div class="rail-brand">
                <span class="rail-mark" aria-hidden="true"></span>
                <span class="rail-name"><?= e($siteName !== '' ? $siteName : t('admin.brand')) ?></span>
            </div>
            <?php /* Search: a link to the Search screen, which works without a script;
                     admin-palette.js opens the ⌘K palette over the page instead. */ ?>
            <a class="rail-search" href="<?= e(Url::admin('search')) ?>" title="<?= e(t('search.open')) ?>" data-palette-open>
                <?= icon('search') ?><span class="rail-label"><?= e(t('search.open')) ?></span><kbd class="rail-count">⌘K</kbd>
            </a>
            <nav class="rail-nav" aria-label="<?= e(t('admin.nav.label')) ?>">
<?php foreach ($rail as $group => $entries): ?>
                <p class="rail-group"><?= e(t('admin.nav.group.' . $group)) ?></p>
                <ul role="list">
<?php foreach ($entries as $entry): ?>
                    <li><a href="<?= e($entry['href']) ?>"<?= $current($entry['nav']) ?> title="<?= e($entry['label']) ?>">
                        <?= icon($entry['icon']) ?>
                        <span class="rail-label"><?= e($entry['label']) ?></span>
<?php if ($entry['count'] !== null): ?>
                        <span class="rail-count"><?= e((string) $entry['count']) ?></span>
<?php endif; ?>
                    </a></li>
<?php endforeach; ?>
                </ul>
<?php endforeach; ?>
            </nav>
<?php if ($adminEmail !== ''): ?>
            <a class="rail-user" href="<?= e(Url::admin('settings') . '#account') ?>" title="<?= e(t('admin.your_login')) ?>">
                <span class="rail-avatar" aria-hidden="true"><?= e(strtoupper(mb_substr($adminEmail, 0, 1))) ?></span>
                <span class="rail-who">
                    <span class="rail-email"><?= e($adminEmail) ?></span>
                    <span class="rail-role"><?= e(t('admin.your_login')) ?></span>
                </span>
            </a>
<?php endif; ?>
        </aside>

        <div class="admin-column">
            <?php /* THE STRIP: where you are — the site's host, then the screen — and, on the
                     right, the site's own time and zone, the site in a new tab, and Log out. */ ?>
            <header class="admin-strip" data-admin-bar>
                <?php /* Folds the rail to its icons or opens it again; born hidden, shown by
                         admin-nav.js, which is what makes it work. */ ?>
                <button type="button" class="rail-toggle" aria-controls="admin-rail" aria-expanded="true" hidden data-rail-toggle
                        data-fold="<?= e(t('admin.rail.fold')) ?>" data-open="<?= e(t('admin.rail.open')) ?>" title="<?= e(t('admin.rail.fold')) ?>">
                    <?= icon('chevron-left') ?><span class="visually-hidden" data-rail-toggle-label><?= e(t('admin.rail.fold')) ?></span>
                </button>
                <?php /* The phone's menu button, born hidden: admin-nav.js shows it and folds
                         the rail behind it. */ ?>
                <button type="button" class="admin-strip-icon admin-nav-toggle" aria-expanded="false" aria-controls="admin-rail" hidden data-admin-nav-toggle>
                    <?= icon('menu') ?><span class="visually-hidden"><?= e(t('admin.nav.open')) ?></span>
                </button>
                <p class="admin-where">
                    <a class="admin-host" href="<?= e(Url::asset('')) ?>" target="_blank" rel="noopener"><span class="admin-live" aria-hidden="true"></span><?= e($host) ?></a>
                    <span class="admin-where-slash" aria-hidden="true">/</span>
                    <span class="admin-where-screen"><?= e($section) ?></span>
                </p>
                <div class="admin-strip-end">
                    <?php /* LIGHT, DARK OR THE MACHINE'S OWN (D-054). Three buttons rather than
                             a toggle: a toggle cannot say "follow the system", and its label
                             would have to mean the state one moment and the action the next.
                             A form, so it works with no script at all; the page comes back
                             already painted in the chosen palette. */ ?>
                    <form class="theme-switch" method="post" action="<?= e(Url::admin('theme')) ?>"
                          aria-label="<?= e(t('admin.appearance')) ?>" title="<?= e(t('admin.appearance_hint')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="back" value="<?= e($here) ?>">
<?php foreach (['light' => 'sun', 'dark' => 'moon', 'system' => 'contrast'] as $choice => $glyph): ?>
                        <button type="submit" class="theme-choice" name="theme" value="<?= e($choice) ?>"
                                aria-pressed="<?= $theme === $choice ? 'true' : 'false' ?>" title="<?= e(t('admin.appearance_' . $choice)) ?>">
                            <?= icon($glyph) ?><span class="visually-hidden"><?= e(t('admin.appearance_' . $choice)) ?></span>
                        </button>
<?php endforeach; ?>
                    </form>
                    <span class="admin-clock" title="<?= e(t('admin.site_time')) ?>"><?= e($zone) ?> · <?= e($time) ?></span>
                    <?php /* The site's home in a new tab: leaving the admin mid-edit would lose
                             whatever is unsaved. */ ?>
                    <a class="admin-strip-icon" href="<?= e(Url::asset('')) ?>" target="_blank" rel="noopener" title="<?= e(t('admin.view_site')) ?>">
                        <?= icon('external-link') ?><span class="visually-hidden"><?= e(t('admin.view_site')) ?></span>
                    </a>
                    <form class="admin-logout" method="post" action="<?= e(Url::admin('logout')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <button type="submit" class="admin-strip-icon" title="<?= e(t('admin.logout')) ?>">
                            <?= icon('log-out') ?><span class="visually-hidden"><?= e(t('admin.logout')) ?></span>
                        </button>
                    </form>
                </div>
            </header>
            <main class="admin-main<?= $wide ? ' admin-main-wide' : '' ?><?= $bare ? ' admin-main-bare' : '' ?>" id="admin-content">
<?php if ($flash !== null): ?>
                <p class="notice notice-<?= e($flashKind) ?>" role="status"><?= e($flash) ?></p>
<?php endif; ?>
<?= $content ?>
            </main>
        </div>
    </div>
    <?php /* The ⌘K palette (D-052): a dialog admin-palette.js opens and fills from the Search
             screen's fragment. Without a script it is never opened; the rail's Search is a
             plain link to that screen. */ ?>
    <dialog class="palette" aria-label="<?= e(t('search.title')) ?>" data-palette data-search-url="<?= e(Url::admin('search')) ?>">
        <form class="palette-search" method="get" action="<?= e(Url::admin('search')) ?>" role="search">
            <?= icon('search') ?>
            <label for="palette-q" class="visually-hidden"><?= e(t('search.placeholder')) ?></label>
            <input type="search" id="palette-q" name="q" placeholder="<?= e(t('search.placeholder')) ?>" autocomplete="off" spellcheck="false" data-palette-input>
            <kbd class="palette-esc">esc</kbd>
        </form>
        <div class="palette-results" data-palette-results></div>
        <p class="palette-keys"><?= e(t('search.keys')) ?></p>
    </dialog>
</body>
</html>
