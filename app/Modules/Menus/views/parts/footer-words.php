<?php

use App\Modules\Settings\ChromeWords;
use App\Modules\Settings\SiteChrome;

/**
 * NAVIGATION'S FOOTER (D-180, D-183): each column a group of its own — its menu, and in each
 * language its title and words — and the small print last. Moved from Appearance's Footer section, which keeps how they
 * look and how many columns are drawn. Required by navigation.php, with the closures of
 * parts/words.php in scope.
 *
 * @var Closure(string, string, string): string $wordsPanel
 * @var Closure(string, string): string $hint
 * @var Closure(string, string): string $word
 * @var array<int, array<string, mixed>> $locales
 * @var array<int, string> $footerMenus each column's menu: `header`, `none`, or a name (D-115)
 * @var list<string> $menuNames every menu name on offer
 * @var array<string, array<int, array{title: string, depth: int, published: bool, url: string}>> $linkPages
 */

// The footer's rich text fields (D-113), with Insert for the replacement tags (D-201).
$richInline = static fn (string $name, string $value, string $code, string $hintId): string
    => \App\Modules\Admin\RichInline::field($name, $name, $value, $code, $linkPages[$code] ?? [], $hintId, true);
?>
            <section class="panel stack navigation-part" aria-labelledby="navigation-footer-title">
                <h2 id="navigation-footer-title"><?= e(t('navigation.footer')) ?></h2>
                <p class="navigation-lead"><?= $hint('navigation-footer-hint', t('navigation.footer_hint')) ?></p>
                    <?php /* Each column's menu (D-115): none, the header's, or a menu of its own,
                             by name, so each language's menu of that name is the column's. */ ?>
<?php foreach (ChromeWords::COLUMN_FIELDS as $n => [$titleName, $textName]): ?>
<?php $chosen = $footerMenus[$n] ?? ''; ?>
                <?php /* ONE COLUMN, ONE GROUP (D-183): its menu, then its title and words in the
                         language shown, under the column's own heading. The arrangement in
                         Appearance says whether the column is drawn; what is set for a column
                         that is not drawn is kept. The menu is by name (D-115), so each
                         language's menu of that name is the column's: one choice, not one per
                         language. */ ?>
                <section class="stack footer-column" aria-labelledby="footer-column-<?= e((string) $n) ?>">
                    <h3 id="footer-column-<?= e((string) $n) ?>"><?= e(t('chrome.footer_column', ['n' => (string) $n])) ?></h3>
                    <div class="field">
                        <label for="footer_menu_<?= e((string) $n) ?>"><?= e(t('chrome.menu')) ?></label>
                        <select id="footer_menu_<?= e((string) $n) ?>" name="footer_menu_<?= e((string) $n) ?>" aria-describedby="footer_menu_<?= e((string) $n) ?>-hint">
                            <option value="<?= e(SiteChrome::FOOTER_MENU_NONE) ?>"<?= $chosen === SiteChrome::FOOTER_MENU_NONE || $chosen === '' ? ' selected' : '' ?>><?= e(t('chrome.menu_none')) ?></option>
                            <option value="<?= e(SiteChrome::FOOTER_MENU_HEADER) ?>"<?= $chosen === SiteChrome::FOOTER_MENU_HEADER ? ' selected' : '' ?>><?= e(t('chrome.footer_menu_same')) ?></option>
<?php foreach ($menuNames as $name): ?>
                            <option value="<?= e($name) ?>"<?= $chosen === $name ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
                        </select>
                        <?= $hint('footer_menu_' . $n . '-hint', $n === 1 ? t('chrome.footer_menu_hint') : t('chrome.footer_column_menu_hint')) ?>
                    </div>
<?php foreach ($locales as $locale): ?>
<?php
    $code = (string) $locale['code'];
    $field = static fn (string $name): string => ChromeWords::field($name, $code);
    ob_start();
?>
                    <div class="field">
                        <label for="<?= e($field($titleName)) ?>"><?= e(t('chrome.footer_column_title')) ?></label>
                        <input type="text" id="<?= e($field($titleName)) ?>" name="<?= e($field($titleName)) ?>"
                               maxlength="80" value="<?= e($word($code, $titleName)) ?>">
                    </div>
                    <div class="field">
                        <label for="<?= e($field($textName)) ?>"><?= e(t('chrome.text')) ?></label>
                        <?= $richInline($field($textName), $word($code, $textName), $code, $field($textName) . '-hint') ?>
                        <?= $hint($field($textName) . '-hint', t('chrome.text_hint')) ?>
                    </div>
<?= $wordsPanel($code, t('chrome.footer_column', ['n' => (string) $n]), (string) ob_get_clean()) ?>
<?php endforeach; ?>
                </section>
<?php endforeach; ?>
                <?php /* The small print, last, as it stands on the page. */ ?>
                <section class="stack footer-column" aria-labelledby="footer-small-print">
                    <h3 id="footer-small-print"><?= e(t('chrome.small_print')) ?></h3>
<?php foreach ($locales as $locale): ?>
<?php
    $code = (string) $locale['code'];
    $field = static fn (string $name): string => ChromeWords::field($name, $code);
    ob_start();
?>
                    <div class="field">
                        <label class="visually-hidden" for="<?= e($field('small_print')) ?>"><?= e(t('chrome.small_print')) ?></label>
                        <input type="text" id="<?= e($field('small_print')) ?>" name="<?= e($field('small_print')) ?>"
                               maxlength="255" value="<?= e($word($code, 'small_print')) ?>"
                               aria-describedby="<?= e($field('small_print')) ?>-hint">
                        <?= $hint($field('small_print') . '-hint', t('chrome.small_print_hint')) ?>
                    </div>
<?= $wordsPanel($code, t('chrome.small_print'), (string) ob_get_clean()) ?>
<?php endforeach; ?>
                </section>
            </section>
