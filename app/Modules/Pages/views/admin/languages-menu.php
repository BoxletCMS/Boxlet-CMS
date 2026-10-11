<?php

use App\Support\Url;

/**
 * The builder's language menu (PLAN.md D-043): which language this page is in, and its
 * version in each of the others — opened where it exists, made where it does not.
 *
 * A <details>, so it opens and closes without a script. Making a translation posts one of
 * the forms builder.php puts after its own form, reached by the form attribute. Moving to
 * another version is a link: an unsaved change here is guarded by the builder's leave
 * warning as any other navigation is.
 *
 * With one language on the site there is nothing to choose, and the menu is a plain label.
 *
 * @var list<array{code: string, label: string, page: int|null, current: bool}> $languages
 * @var array<string, mixed> $page
 * @var string $state published, changes or draft (D-173): a published page is translated as it
 *      is published, and with changes not yet published the menu says they stay behind
 */
$here = (string) $page['locale'];
?>
<?php if (count($languages) < 2): ?>
                <span class="builder-locale" title="<?= e(t('translations.one_language')) ?>"><?= e(strtoupper($here)) ?></span>
<?php else: ?>
                <details class="builder-locale menu-popover">
                    <summary class="button button-ghost" title="<?= e(t('translations.menu')) ?>">
                        <?= icon('languages') ?> <?= e(strtoupper($here)) ?><span class="visually-hidden"> — <?= e(t('translations.menu')) ?></span>
                    </summary>
                    <ul class="menu-popover-list">
<?php if ($state === 'changes'): ?>
                        <li class="menu-popover-note"><?= e(t('translations.unpublished_stay')) ?></li>
<?php endif; ?>
<?php foreach ($languages as $language): ?>
                        <li lang="<?= e($language['code']) ?>">
<?php if ($language['current']): ?>
                            <span class="menu-popover-item" aria-current="page"><?= e($language['label']) ?> <span class="hint"><?= e(t('translations.this_page')) ?></span></span>
<?php elseif ($language['page'] !== null): ?>
                            <a class="menu-popover-item" href="<?= e(Url::admin('pages', $language['page'])) ?>"><?= e($language['label']) ?> <span class="hint"><?= e(t('translations.open')) ?></span></a>
<?php else: ?>
                            <button type="submit" form="translate-<?= e($language['code']) ?>" class="menu-popover-item"><?= e($language['label']) ?> <span class="hint"><?= e(t('translations.translate')) ?></span></button>
<?php endif; ?>
                        </li>
<?php endforeach; ?>
                    </ul>
                </details>
<?php endif; ?>
