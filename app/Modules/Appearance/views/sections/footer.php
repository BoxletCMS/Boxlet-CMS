<?php

use App\Support\Url;

/**
 * Footer (PLAN.md D-111, D-113, D-115, D-157): how it is arranged and what it stands on.
 * How far it reaches is in Layout & widths, with every other width.
 *
 * THE FOOTER IS COLUMNS OF CONTENT (D-115): up to three, each a title, words and a menu. The
 * arrangement says how many are drawn; what each column holds is Navigation's (D-180).
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
                    <?= $lookGroup('footer_layout') ?>
                    <?php /* How many columns the MENU runs in: a question only the Columns
                             arrangement asks, so only there is it offered (admin-appearance.css). */ ?>
                    <?= $lookGroup('footer_columns') ?>
                    <?= $lookGroup('footer_links') ?>
                    <?= $lookGroup('small_print_row') ?>
<?php $arrangement = (string) ob_get_clean(); ob_start(); ?>
                    <?php /* The columns' words and menus and the small print are Navigation's
                             (D-180); how they look is here. */ ?>
                    <p class="hint hint-always navigation-where"><?= e(t('chrome.footer_words_where')) ?> <a href="<?= e(Url::admin('navigation')) ?>"><?= e(t('chrome.navigation_link')) ?></a></p>
                    <?= $lookGroup('footer_surface') ?>
                    <?= $ownColour('footer_colour') ?>
                    <?= $lookGroup('footer_edge') ?>
<?php $background = (string) ob_get_clean(); ?>
                <?= $group('footer', 'arrangement', $arrangement) ?>
                <?= $group('footer', 'background', $background) ?>
