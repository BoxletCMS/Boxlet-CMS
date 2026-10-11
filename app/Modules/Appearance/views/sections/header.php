<?php

use App\Support\Url;

/**
 * Header (PLAN.md D-111, D-157): how it is arranged, what it does on scroll, what it stands
 * on, how its menu and button look. Which menu, and the button's words, are Navigation's
 * (D-180). How far it reaches is in Layout & widths, with every other width.
 *
 * Included by appearance.php inside its section, with the closures of parts/controls.php in
 * scope.
 *
 * @var Closure(string): string $lookGroup one chrome choice as a row of buttons
 * @var Closure(string): string $ownColour
 * @var Closure(string, string, string, bool=): string $group
 */
ob_start();
?>
                    <?php /* The logo is the site's, not the header's: one setting, under
                             Settings → Branding (D-038). */ ?>
                    <p class="hint"><?= e(t('chrome.logo_where')) ?> <a href="<?= e(Url::admin('settings')) ?>"><?= e(t('chrome.logo_where_link')) ?></a></p>
                    <?= $lookGroup('header_arrangement') ?>
                    <?= $lookGroup('brand') ?>
                    <?php /* A logo's size means nothing where the header shows only the name, so
                             it is offered only where there is a logo to size (admin-appearance.css). */ ?>
                    <?= $lookGroup('logo_size') ?>
                    <?= $lookGroup('header_height') ?>
<?php $arrangement = (string) ob_get_clean(); ob_start(); ?>
                    <?= $lookGroup('header_surface') ?>
                    <?= $ownColour('header_colour') ?>
                    <?= $lookGroup('header_edge') ?>
<?php $background = (string) ob_get_clean(); ob_start(); ?>
                    <?php /* Which menu, and the button's words and address, are Navigation's
                             (D-180); how they look is here. */ ?>
                    <p class="hint hint-always navigation-where"><?= e(t('chrome.header_words_where')) ?> <a href="<?= e(Url::admin('navigation')) ?>"><?= e(t('chrome.navigation_link')) ?></a></p>
                    <?= $lookGroup('nav_style') ?>
                    <?= $lookGroup('nav_ink') ?>
                    <?= $lookGroup('header_button') ?>
<?php $menuGroup = (string) ob_get_clean(); ?>
                <?= $group('header', 'arrangement', $arrangement) ?>
                <?php /* How see-through and blurred a bar that moves with the page is: only a
                         sticky bar or one over the hero has anything behind it (D-164). */ ?>
                <?= $group('header', 'behaviour', $lookGroup('header_behaviour') . '<div class="when-moving">' . $lookGroup('header_opacity') . $lookGroup('header_blur') . '</div>') ?>
                <?= $group('header', 'background', $background) ?>
                <?= $group('header', 'menu', $menuGroup) ?>
