<?php

use App\Support\Url;

/**
 * The header tab of the Appearance screen (PLAN.md D-111). Included by appearance.php, whose
 * variables and helpers it reads.
 *
 * ITS OWN TAB, and the footer has its own. They were one tab called "Header" that held both
 * halves and every language's words under them — the longest column on the screen, growing
 * by four fields with every language, beside a Shape tab a third as tall. Each of the two now
 * holds what a person asks about THAT part: which menu, how it is arranged, what it stands
 * on, how far it reaches, and the words in it.
 *
 * The header's width and where it breaks out of the sheet are DESIGN decisions (Tokens), not
 * chrome choices — they stay stored where they were; only their place on the screen moved,
 * because a person setting up the header looks for them here.
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
 * @var string $menu the menu the header shows, by name
 * @var list<string> $menus every menu name on offer
 * @var array<string, array<int, array{title: string, depth: int, published: bool, url: string}>> $linkPages
 * @var array<int, array<string, mixed>> $locales
 */
?>
            <fieldset class="fieldset">
                <?php /* The logo is the site's, not the header's: one setting, under
                         Settings → Branding, with the favicon and the sharing picture
                         (D-038). */ ?>
                <p class="hint"><?= e(t('chrome.logo_where')) ?> <a href="<?= e(Url::admin('settings')) ?>"><?= e(t('chrome.logo_where_link')) ?></a></p>

                <?php /* By NAME, not by id: the same name is each language's own menu, so
                         this is one choice rather than one per translation (D-030). */ ?>
                <div class="field">
                    <label for="header_menu"><?= e(t('chrome.menu')) ?></label>
                    <select id="header_menu" name="header_menu" aria-describedby="header_menu-hint">
                        <option value=""><?= e(t('chrome.menu_none')) ?></option>
<?php foreach ($menus as $name): ?>
                        <option value="<?= e($name) ?>"<?= $menu === $name ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
                    </select>
                    <span class="hint" id="header_menu-hint">
                        <?= e($menus === [] ? t('chrome.no_menus') : t('chrome.menu_hint')) ?>
                    </span>
                </div>

                <?php /* In the order a person builds a header (D-112): what stands for the
                         site, where things stand, what the bar does, what it stands on,
                         how the menu and the button are set, how big and how far. */ ?>
                <?= $lookGroup('brand') ?>
                <?= $lookGroup('logo_size') ?>
                <?= $lookGroup('header_arrangement') ?>
                <?= $lookGroup('header_behaviour') ?>
                <?= $lookGroup('header_surface') ?>
                <?= $ownColour('header_colour') ?>
                <?= $lookGroup('header_edge') ?>
                <?= $lookGroup('nav_style') ?>
                <?= $lookGroup('nav_ink') ?>
                <?= $lookGroup('header_button') ?>
                <?= $lookGroup('density') ?>
                <?php /* THE BAR, THEN WHAT STANDS IN IT (D-123, the owner's model): Header width —
                         the bar inside the box or across the window, a question only a boxed page
                         asks — and Header content — the logo and menu in line with the text, the
                         box, or the window. An answer that would change nothing is not offered
                         (admin-appearance.css), and one already chosen is never hidden. */ ?>
                <div class="when-boxed" data-when-boxed><?= $segmented('header_bleed', $labels('header_bleed', App\Modules\Design\Tokens::BLEED)) ?></div>
                <?= $segmented('header_width', $labels('header_width', App\Modules\Design\Tokens::HEADER_WIDTH)) ?>
            </fieldset>

<?php foreach ($locales as $locale): ?>
<?php
    $code = (string) $locale['code'];
    $field = static fn (string $name): string => \App\Modules\Settings\ChromeWords::field($name, $code);
    // A page first, an address second (PLAN.md D-034), exactly as in a block's link field:
    // choosing a page shows its address and offers its title as the label (D-038).
    $buttonGroup = \App\Modules\Pages\PageLinks::reference($word($code, 'button_url'));
    $buttonPages = $linkPages[$code] ?? [];
    $buttonChoice = $buttonGroup === null ? null : ($buttonPages[$buttonGroup] ?? false);
    $buttonAddress = $buttonGroup === null ? $word($code, 'button_url') : (is_array($buttonChoice) ? $buttonChoice['url'] : '');
    $buttonReadonly = $buttonGroup === null ? '' : ' readonly';
    ob_start();
?>
                <div class="stack" data-link>
                    <div class="field">
                        <label for="<?= e($field('button_page')) ?>"><?= e(t('chrome.button_url')) ?></label>
                        <select id="<?= e($field('button_page')) ?>" name="<?= e($field('button_page')) ?>" data-link-page
                                aria-describedby="<?= e($field('button_url')) ?>-hint">
                            <option value=""<?= $buttonGroup === null ? ' selected' : '' ?>><?= e(t('pages.field.link_address')) ?></option>
<?php if ($buttonChoice === false): ?>
                            <option value="<?= e((string) $buttonGroup) ?>" selected><?= e(t('pages.field.link_page_gone')) ?></option>
<?php endif; ?>
<?php foreach ($buttonPages as $group => $choice): ?>
                            <option value="<?= e((string) $group) ?>" data-url="<?= e($choice['url']) ?>" data-title="<?= e($choice['title']) ?>"<?= $group === $buttonGroup ? ' selected' : '' ?>><?= e(str_repeat('— ', $choice['depth']) . $choice['title'] . ($choice['published'] ? '' : ' ' . t('pages.field.link_page_draft'))) ?></option>
<?php endforeach; ?>
                        </select>
                        <span class="hint" id="<?= e($field('button_url')) ?>-hint"><?= e(t('chrome.button_url_hint')) ?></span>
                    </div>

                    <div class="field">
                        <label for="<?= e($field('button_url')) ?>"><?= e(t('chrome.button_address')) ?></label>
                        <input type="text" id="<?= e($field('button_url')) ?>" name="<?= e($field('button_url')) ?>"
                               maxlength="2048" value="<?= e($buttonAddress) ?>" data-link-address<?= $buttonReadonly ?>
                               placeholder="<?= e(t('pages.field.link_url_input')) ?>" aria-describedby="<?= e($field('button_url')) ?>-address-hint">
                        <span class="hint" id="<?= e($field('button_url')) ?>-address-hint"><?= e(t('chrome.button_address_hint')) ?></span>
                        <?= $error($field('button_url')) ?>
                    </div>

                    <div class="field">
                        <label for="<?= e($field('button_label')) ?>"><?= e(t('chrome.button_label')) ?></label>
                        <input type="text" id="<?= e($field('button_label')) ?>" name="<?= e($field('button_label')) ?>"
                               maxlength="60" value="<?= e($word($code, 'button_label')) ?>" data-link-label
                               aria-describedby="<?= e($field('button_label')) ?>-hint">
                        <span class="hint" id="<?= e($field('button_label')) ?>-hint"><?= e(t('chrome.button_label_hint')) ?></span>
                    </div>
                </div>
<?= $wordsPanel($code, t('chrome.words.header'), (string) ob_get_clean()) ?>
<?php endforeach; ?>
