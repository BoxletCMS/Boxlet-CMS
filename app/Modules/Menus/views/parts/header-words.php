<?php

use App\Modules\Pages\PageLinks;
use App\Modules\Settings\ChromeWords;

/**
 * NAVIGATION'S HEADER (D-180): which menu the header shows, and its button's address and words
 * in each language. Moved from Appearance's Header section, which keeps how they look.
 * Required by navigation.php, with the closures of parts/words.php in scope.
 *
 * @var Closure(string): string $error
 * @var Closure(string, string, string): string $wordsPanel
 * @var Closure(string, string): string $hint
 * @var Closure(string, string): string $word
 * @var string $menu the menu the header shows, by name
 * @var list<string> $menuNames every menu name on offer
 * @var array<string, array<int, array{title: string, depth: int, published: bool, url: string}>> $linkPages
 * @var array<int, array<string, mixed>> $locales
 */
?>
            <section class="panel stack navigation-part" aria-labelledby="navigation-header-title">
                <h2 id="navigation-header-title"><?= e(t('navigation.header')) ?></h2>
                    <?php /* By NAME, not by id: the same name is each language's own menu, so this
                             is one choice rather than one per translation (D-030). */ ?>
                    <div class="field">
                        <label for="header_menu"><?= e(t('chrome.menu')) ?></label>
                        <select id="header_menu" name="header_menu" aria-describedby="header_menu-hint">
                            <option value=""><?= e(t('chrome.menu_none')) ?></option>
<?php foreach ($menuNames as $name): ?>
                            <option value="<?= e($name) ?>"<?= $menu === $name ? ' selected' : '' ?>><?= e($name) ?></option>
<?php endforeach; ?>
                        </select>
                        <?= $hint('header_menu-hint', $menuNames === [] ? t('chrome.no_menus') : t('chrome.menu_hint')) ?>
                    </div>
<?php foreach ($locales as $locale): ?>
<?php
    $code = (string) $locale['code'];
    $field = static fn (string $name): string => ChromeWords::field($name, $code);
    // A page first, an address second (D-034), exactly as in a block's link field: choosing
    // a page shows its address and offers its title as the label (D-038).
    $buttonGroup = PageLinks::reference($word($code, 'button_url'));
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
<?php foreach ($buttonPages as $pageGroup => $choice): ?>
                                <option value="<?= e((string) $pageGroup) ?>" data-url="<?= e($choice['url']) ?>" data-title="<?= e($choice['title']) ?>"<?= $pageGroup === $buttonGroup ? ' selected' : '' ?>><?= e(str_repeat('— ', $choice['depth']) . $choice['title'] . ($choice['published'] ? '' : ' ' . t('pages.field.link_page_draft'))) ?></option>
<?php endforeach; ?>
                            </select>
                            <?= $hint($field('button_url') . '-hint', t('chrome.button_url_hint')) ?>
                        </div>

                        <div class="field">
                            <label for="<?= e($field('button_url')) ?>"><?= e(t('chrome.button_address')) ?></label>
                            <input type="text" id="<?= e($field('button_url')) ?>" name="<?= e($field('button_url')) ?>"
                                   maxlength="2048" value="<?= e($buttonAddress) ?>" data-link-address<?= $buttonReadonly ?>
                                   placeholder="<?= e(t('pages.field.link_url_input')) ?>" aria-describedby="<?= e($field('button_url')) ?>-address-hint">
                            <?= $hint($field('button_url') . '-address-hint', t('chrome.button_address_hint')) ?>
                            <?= $error($field('button_url')) ?>
                        </div>

                        <div class="field">
                            <label for="<?= e($field('button_label')) ?>"><?= e(t('chrome.button_label')) ?></label>
                            <input type="text" id="<?= e($field('button_label')) ?>" name="<?= e($field('button_label')) ?>"
                                   maxlength="60" value="<?= e($word($code, 'button_label')) ?>" data-link-label
                                   aria-describedby="<?= e($field('button_label')) ?>-hint">
                            <?= $hint($field('button_label') . '-hint', t('chrome.button_label_hint')) ?>
                        </div>
                    </div>
<?= $wordsPanel($code, t('chrome.words.header'), (string) ob_get_clean()) ?>
<?php endforeach; ?>
            </section>
