<?php

use App\Modules\Settings\ChromeWords;
use App\Modules\Settings\SiteChrome;

/**
 * The footer tab of the Appearance screen (PLAN.md D-111, D-113, D-115). Included by
 * appearance.php, whose variables and helpers it reads — see header.php for why the two
 * are apart.
 *
 * THE FOOTER IS COLUMNS OF CONTENT (D-115): up to three, each a title, words and a menu. The
 * arrangement says how many are drawn; the menus are the same in every language and stand
 * with the look; the titles and words are per language and stand with the words.
 *
 * Where the footer breaks out of the sheet is a DESIGN decision (Tokens, D-067) and stays
 * stored as one; it is shown here because this is where a person setting up the footer looks
 * for it.
 *
 * @var array<string, string> $decisions
 * @var array<string, string> $errors
 * @var callable(string): string $error
 * @var callable(string, array<array-key, string>, string=, string=): string $segmented
 * @var callable(string, list<string>): array<string, string> $labels
 * @var callable(string): string $lookGroup one chrome choice as a row of buttons
 * @var callable(string): string $ownColour the header's or footer's colour of its own
 * @var callable(string, string, string): string $wordsPanel one language's words, folded or not
 * @var callable(string, string): string $word a stored word
 * @var array<int, array<string, mixed>> $locales
 * @var array<int, string> $footerMenus each column's menu: `header`, `none`, or a name (D-115)
 * @var list<string> $menus every menu name on offer
 * @var array<string, array<int, array{title: string, depth: int, published: bool, url: string}>> $linkPages
 */

/**
 * RICH TEXT, WITH A SHORT TOOLBAR (D-113): bold, italic, a link, and undo. No headings, lists
 * or quotations — the footer's whitelist (RichText::INLINE) would throw them away, and the
 * toolbar offers only what can be stored, as the page editor's does (D-017). The textarea is
 * the real field; richtext.js puts the editor above it and the plain toggle shows it again.
 * Without a script it is a textarea of HTML, which still saves. A text stored before D-113
 * is plain and is handed over as one paragraph with its breaks (ChromeWords::asHtml).
 *
 * The link panel's address box is TEXT, not type="url": an email or a phone number is a link
 * as typed (D-039), and a url input holding one made the whole form refuse to submit.
 */
$richInline = static function (string $name, string $value, string $code, string $hintId) use ($linkPages): string {
    $html = '<div class="richtext" data-richtext>'
        . '<div class="richtext-toolbar" data-richtext-toolbar role="toolbar" aria-label="' . e(t('richtext.toolbar')) . '">'
        . '<div class="rt-group">'
        . '<button type="button" class="rt-button" data-rt="bold" aria-pressed="false" title="' . e(t('richtext.bold')) . '"><span aria-hidden="true">B</span><span class="visually-hidden">' . e(t('richtext.bold')) . '</span></button>'
        . '<button type="button" class="rt-button rt-italic" data-rt="italic" aria-pressed="false" title="' . e(t('richtext.italic')) . '"><span aria-hidden="true">I</span><span class="visually-hidden">' . e(t('richtext.italic')) . '</span></button>'
        . '<button type="button" class="rt-button" data-rt="link" aria-pressed="false" title="' . e(t('richtext.link')) . '"><svg class="rt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10.5 13.5a4.5 4.5 0 0 0 6.36 0l2.83-2.83a4.5 4.5 0 0 0-6.36-6.36l-1.06 1.06"/><path d="M13.5 10.5a4.5 4.5 0 0 0-6.36 0l-2.83 2.83a4.5 4.5 0 0 0 6.36 6.36l1.06-1.06"/></svg><span class="visually-hidden">' . e(t('richtext.link')) . '</span></button>'
        . '</div>'
        . '<div class="rt-group rt-history">'
        . '<button type="button" class="rt-button" data-rt="undo" title="' . e(t('richtext.undo')) . '"><span aria-hidden="true">&#8630;</span><span class="visually-hidden">' . e(t('richtext.undo')) . '</span></button>'
        . '<button type="button" class="rt-button" data-rt="redo" title="' . e(t('richtext.redo')) . '"><span aria-hidden="true">&#8631;</span><span class="visually-hidden">' . e(t('richtext.redo')) . '</span></button>'
        . '</div></div>'
        . '<div class="richtext-link" data-richtext-link hidden>'
        . '<select class="rt-link-page" aria-label="' . e(t('richtext.page')) . '"><option value="">' . e(t('pages.field.link_address')) . '</option>';
    foreach ($linkPages[$code] ?? [] as $group => $choice) {
        $html .= '<option value="' . e(\App\Modules\Pages\PageLinks::to($group)) . '">' . e(str_repeat('— ', $choice['depth']) . $choice['title'] . ($choice['published'] ? '' : ' ' . t('pages.field.link_page_draft'))) . '</option>';
    }
    $html .= '</select>'
        . '<input type="text" inputmode="url" class="rt-link-input" placeholder="' . e(t('richtext.url_placeholder')) . '" aria-label="' . e(t('richtext.url')) . '">'
        . '<button type="button" class="button button-secondary" data-rt-link="apply">' . e(t('richtext.link')) . '</button>'
        . '<button type="button" class="button button-ghost" data-rt-link="remove">' . e(t('richtext.unlink')) . '</button>'
        . '</div>'
        . '<textarea id="' . e($name) . '" name="' . e($name) . '" rows="3" data-richtext-source aria-describedby="' . e($hintId) . '">' . e(ChromeWords::asHtml($value)) . '</textarea>'
        . '<div class="richtext-actions">'
        . '<button type="button" class="button button-ghost js-only" data-richtext-toggle data-label-plain="' . e(t('richtext.plain')) . '" data-label-rich="' . e(t('richtext.rich')) . '">' . e(t('richtext.plain')) . '</button>'
        . '</div></div>';

    return $html;
};
?>
            <fieldset class="fieldset">
                <?= $lookGroup('footer_layout') ?>
                <?php /* Each column's menu (D-115): none, the header's, or a menu of its own,
                         by name, so each language's menu of that name is the column's. The
                         same in every language, so it stands with the look. */ ?>
<?php foreach (range(1, SiteChrome::FOOTER_COLUMNS) as $n): ?>
<?php $chosen = $footerMenus[$n] ?? ''; ?>
                <div class="field">
                    <label for="footer_menu_<?= e((string) $n) ?>"><?= e(t('chrome.footer_column_menu', ['n' => (string) $n])) ?></label>
                    <select id="footer_menu_<?= e((string) $n) ?>" name="footer_menu_<?= e((string) $n) ?>" aria-describedby="footer_menu_<?= e((string) $n) ?>-hint">
                        <option value="<?= e(SiteChrome::FOOTER_MENU_NONE) ?>"<?= $chosen === SiteChrome::FOOTER_MENU_NONE || $chosen === '' ? ' selected' : '' ?>><?= e(t('chrome.menu_none')) ?></option>
                        <option value="<?= e(SiteChrome::FOOTER_MENU_HEADER) ?>"<?= $chosen === SiteChrome::FOOTER_MENU_HEADER ? ' selected' : '' ?>><?= e(t('chrome.footer_menu_same')) ?></option>
<?php foreach ($menus as $name): ?>
                        <option value="<?= e($name) ?>"<?= $chosen === $name ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
                    </select>
                    <span class="hint" id="footer_menu_<?= e((string) $n) ?>-hint"><?= e($n === 1 ? t('chrome.footer_menu_hint') : t('chrome.footer_column_menu_hint')) ?></span>
                </div>
<?php endforeach; ?>
                <?= $lookGroup('footer_columns') ?>
                <?= $lookGroup('small_print_row') ?>
                <?= $lookGroup('footer_surface') ?>
                <?= $ownColour('footer_colour') ?>
                <?= $lookGroup('footer_edge') ?>
                <div class="when-boxed" data-when-boxed><?= $segmented('footer_bleed', $labels('footer_bleed', App\Modules\Design\Tokens::BLEED)) ?></div>
                <?= $segmented('footer_width', $labels('footer_width', App\Modules\Design\Tokens::FOOTER_WIDTH)) ?>
            </fieldset>

<?php foreach ($locales as $locale): ?>
<?php
    $code = (string) $locale['code'];
    $field = static fn (string $name): string => ChromeWords::field($name, $code);
    ob_start();
?>
<?php foreach (ChromeWords::COLUMN_FIELDS as $n => [$titleName, $textName]): ?>
                <?php /* One column's words (D-115): a title and a line or two under it. The
                         arrangement says whether the column is drawn; what is typed for a
                         column that is not drawn is kept. */ ?>
                <div class="stack footer-column-words">
                    <p class="field-label"><?= e(t('chrome.footer_column', ['n' => (string) $n])) ?></p>
                    <div class="field">
                        <label for="<?= e($field($titleName)) ?>"><?= e(t('chrome.footer_column_title')) ?></label>
                        <input type="text" id="<?= e($field($titleName)) ?>" name="<?= e($field($titleName)) ?>"
                               maxlength="80" value="<?= e($word($code, $titleName)) ?>">
                    </div>
                    <div class="field">
                        <label for="<?= e($field($textName)) ?>"><?= e(t('chrome.text')) ?></label>
                        <?= $richInline($field($textName), $word($code, $textName), $code, $field($textName) . '-hint') ?>
                        <span class="hint" id="<?= e($field($textName)) ?>-hint"><?= e(t('chrome.text_hint')) ?></span>
                    </div>
                </div>
<?php endforeach; ?>

                <div class="field">
                    <label for="<?= e($field('small_print')) ?>"><?= e(t('chrome.small_print')) ?></label>
                    <input type="text" id="<?= e($field('small_print')) ?>" name="<?= e($field('small_print')) ?>"
                           maxlength="255" value="<?= e($word($code, 'small_print')) ?>"
                           aria-describedby="<?= e($field('small_print')) ?>-hint">
                    <span class="hint" id="<?= e($field('small_print')) ?>-hint"><?= e(t('chrome.small_print_hint')) ?></span>
                </div>
<?= $wordsPanel($code, t('chrome.words.footer'), (string) ob_get_clean()) ?>
<?php endforeach; ?>
