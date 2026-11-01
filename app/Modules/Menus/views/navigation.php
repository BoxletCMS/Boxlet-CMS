<?php

use App\Support\Url;

/**
 * NAVIGATION (PLAN.md D-180, O-74): two tabs, each at its own address — the menus, each edited
 * on its own page; and what the header and footer say and where they lead, saved by one Save.
 * Provided by AdminView::render().
 *
 * @var string $tab NavigationController::MENUS or ::CHROME
 * @var string $title
 * @var string $csrf
 * @var ?string $notice
 * @var array<string, string> $errors the words', keyed by the field at fault
 * @var array<int, array<string, mixed>> $locales
 * @var string $shownLocale
 */
$error = static fn (string $key): string => isset($errors[$key])
    ? '<p class="field-error" role="alert">' . e($errors[$key]) . '</p>'
    : '';
require __DIR__ . '/parts/words.php';
?>
        <div class="navigation-screen">
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
        <?php /* THE TABS (O-74): links, each to its own address, the current one marked as the
                 Settings screen marks its section. Legible at rest; hover raises the other. */ ?>
        <nav class="tabs navigation-tabs" aria-label="<?= e(t('navigation.tabs')) ?>">
<?php foreach (['menus' => [Url::admin('navigation'), t('menus.title')], 'header-footer' => [Url::admin('navigation', 'header-footer'), t('navigation.tab_chrome')]] as $name => [$href, $label]): ?>
            <a href="<?= e($href) ?>"<?= $name === $tab ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
<?php endforeach; ?>
        </nav>
<?php if ($notice !== null): ?>
        <p class="notice notice-error" role="alert"><?= e($notice) ?></p>
<?php endif; ?>

<?php if ($tab === 'menus'): ?>
<?php require __DIR__ . '/parts/menus-list.php'; ?>
<?php else: ?>
        <p class="page-subtitle"><?= e(t('navigation.intro')) ?> <a href="<?= e(Url::admin('appearance')) ?>"><?= e(t('navigation.look_where')) ?></a></p>
<?php if (count($locales) > 1):
    $languages = [];
    foreach ($locales as $locale) {
        $languages[(string) $locale['code']] = strtoupper((string) $locale['code']);
    }
?>
        <?php /* THE LANGUAGE, CHOSEN ONCE (D-182): every card shows that language's words. Not a
                 field of the form (form= names one that is not there), so it is never sent. */ ?>
        <div class="navigation-language" data-navigation-language>
            <span class="navigation-language-label" id="navigation-language-label"><?= e(t('navigation.language')) ?></span>
            <?= segmented_group('navigation-language', $languages, $shownLocale, 'navigation-language-label', 'navigation-language-', 'navigation-language-none') ?>
        </div>
<?php endif; ?>

        <?php /* One column, as a form is read (D-183): the header, the footer column by column,
                 the small print. Saved by a POST to /admin/navigation, which comes back here. */ ?>
        <form method="post" action="<?= e(Url::admin('navigation')) ?>" class="navigation stack" id="navigation-form">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="navigation-parts">
<?php require __DIR__ . '/parts/header-words.php'; ?>
<?php require __DIR__ . '/parts/footer-words.php'; ?>
            </div>
            <div class="navigation-save">
                <button type="submit" class="button"><?= e(t('navigation.save')) ?></button>
            </div>
        </form>
<?php endif; ?>
        </div>
