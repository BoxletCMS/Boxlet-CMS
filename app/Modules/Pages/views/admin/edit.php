<?php

use App\Modules\Design\Composition;
use App\Modules\Pages\Page;
use App\Support\Url;

/**
 * The page editor. Every block's fields are ordinary inputs in one form, so editing and
 * saving work without JavaScript. _end must stay the last field in the form: when PHP
 * cuts input off at max_input_vars it is the field that goes missing.
 *
 * @var array<string, mixed> $page
 * @var string $titleValue
 * @var string $slugValue
 * @var list<array{id: int, title: string, depth: int}> $parents
 * @var list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string, section?: string, column?: int}> $blocks
 * @var array<string, string> $errors
 * @var string|null $notice
 * @var string $character the character new blocks are composed with
 * @var \App\Core\Blocks $registry
 * @var list<array{id: int, name: string, thumb: string|null}> $pictures every picture a media field may choose
 * @var string $csrf
 */
$pageId = (int) $page['id'];
$published = $page['status'] === 'published';
// What is stored, not what a visitor would see: on a rejected save $page already carries
// the submitted seo_json, so this shows back what was typed rather than what was kept.
$seo = Page::seo($page);
$error = static fn (string $key): string => isset($errors[$key]) ? '<p class="field-error" role="alert">' . e($errors[$key]) . '</p>' : '';
?>
        <div class="page-header">
            <h1><?= e(t('pages.edit')) ?></h1>
            <span class="status status-<?= e($page['status']) ?>"><?= e(t('pages.status.' . $page['status'])) ?></span>
            <a class="button button-secondary" href="<?= e(Url::admin('pages', $pageId)) ?>"><?= e(t('pages.editor.visual')) ?></a>
<?php if ($published): ?>
            <a href="<?= e(Url::page((string) $page['locale'], (string) $page['slug'])) ?>"><?= e(t('pages.view')) ?></a>
<?php endif; ?>
        </div>
        <p class="page-subtitle"><?= e(t('pages.editor.fallback_hint')) ?></p>
<?php if ($notice !== null): ?>
        <p class="notice notice-error" role="alert"><?= e($notice) ?></p>
<?php endif; ?>
        <form method="post" action="<?= e(Url::admin('pages', $pageId)) ?>" class="editor-form" data-page-editor>
            <?php /* First submit button in the form: pressing Enter in a field saves. */ ?>
            <button type="submit" name="action" value="save" class="visually-hidden" tabindex="-1" aria-hidden="true"><?= e(t('pages.save')) ?></button>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

            <div class="panel editor-meta">
                <div class="field">
                    <label for="page-title"><?= e(t('pages.field.title')) ?></label>
                    <input type="text" id="page-title" name="title" value="<?= e($titleValue) ?>" maxlength="255" required>
                    <?= field_hint('hint.page.title') ?>
                    <?= $error('title') ?>
                </div>
                <div class="field">
                    <label for="page-slug"><?= e(t('pages.field.slug')) ?></label>
                    <input type="text" id="page-slug" name="slug" value="<?= e($slugValue) ?>" maxlength="100" autocapitalize="off" spellcheck="false" aria-describedby="page-slug-hint">
                    <span class="hint" id="page-slug-hint"><?= e(t('pages.field.slug_hint')) ?></span>
<?php if ($page['parent_id'] !== null && (string) $page['slug'] !== ''): ?>
                    <span class="hint"><?= e(t('pages.slug.nested', ['address' => Url::page((string) $page['locale'], (string) $page['slug'])])) ?></span>
<?php endif; ?>
                    <?= $error('slug') ?>
                </div>
                <?php /* The same control the builder has. It is here so the parent can be
                         changed without JavaScript — no longer to prevent damage: the
                         route now tells an absent field from an empty one and leaves what
                         is stored alone, which is what stopped this form un-parenting the
                         page on every save. A page's parent is changed in page settings
                         and never by dragging (PLAN.md D-011). */ ?>
                <div class="field">
                    <label for="page-parent"><?= e(t('pages.field.parent')) ?></label>
                    <select id="page-parent" name="parent_id">
                        <option value=""><?= e(t('pages.parent.none')) ?></option>
<?php foreach ($parents as $option): ?>
                        <option value="<?= e($option['id']) ?>"<?= (int) ($page['parent_id'] ?? 0) === $option['id'] ? ' selected' : '' ?>><?= e(str_repeat('— ', $option['depth']) . $option['title']) ?></option>
<?php endforeach; ?>
                    </select>
                    <?= field_hint('hint.page.parent') ?>
                    <?= $error('parent') ?>
                </div>
                <?php /* D-004. Left EMPTY when unset rather than pre-filled with the page
                         title: a filled field cannot be told apart from an inherited one,
                         and saving it back would freeze the title in place. The hint says
                         what an empty one does, exactly as the slug field's does.

                         Their own row, because dropped into the panel's grid they landed
                         under Title and Address and left a hole under Parent page — and
                         the description, the one field meant to hold a sentence, came out
                         the narrowest thing on the screen with a hint longer than itself. */ ?>
                <div class="editor-meta-seo">
                    <div class="field">
                        <label for="page-seo-title"><?= e(t('pages.field.seo_title')) ?></label>
                        <input type="text" id="page-seo-title" name="seo_title" value="<?= e($seo['title']) ?>" maxlength="255" aria-describedby="page-seo-title-hint">
                        <span class="hint" id="page-seo-title-hint"><?= e(t('pages.field.seo_title_hint')) ?></span>
                    </div>
                    <div class="field">
                        <label for="page-seo-description"><?= e(t('pages.field.seo_description')) ?></label>
                        <textarea id="page-seo-description" name="seo_description" rows="2" aria-describedby="page-seo-description-hint"><?= e($seo['description']) ?></textarea>
                        <span class="hint" id="page-seo-description-hint"><?= e(t('pages.field.seo_description_hint')) ?></span>
                    </div>
                </div>
            </div>

            <div class="editor-section-title">
                <h2><?= e(t('pages.blocks')) ?></h2>
            </div>
            <div class="block-list" data-block-list>
<?php $bandSeen = []; ?>
<?php foreach ($blocks as $index => $block): ?>
<?php
    /* ONE BAND'S FIELDS ARE SHOWN ONCE, at the foot of the FIRST block standing in it
       (D-099). This editor is one scroll with no tabs, so the section's style stays folded
       exactly where it has always been — and a band holding two blocks does not offer the
       same five selects twice under different halves of itself. */
    $bandKey = $block['section'] ?? '';
    $showSection = !isset($bandSeen[$bandKey]);
    $bandSeen[$bandKey] = true;
    require __DIR__ . '/block.php';
?>
<?php endforeach; ?>
            </div>
<?php if ($blocks === []): ?>
            <p class="hint"><?= e(t('pages.no_blocks')) ?></p>
<?php endif; ?>

            <div class="block-add">
                <div class="field">
                    <label for="add-type"><?= e(t('pages.add_block')) ?></label>
                    <select id="add-type" name="add_type" data-add-type>
<?php foreach ($registry->types() as $type): ?>
                        <option value="<?= e($type) ?>"><?= e(t('block.' . $type)) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="action" value="add" class="button button-secondary" data-editor-action="add"><?= e(t('pages.add')) ?></button>
            </div>

            <div class="editor-actions">
                <button type="submit" name="action" value="save" class="button"><?= e(t('pages.save')) ?></button>
                <span class="hint"><?= e(t('pages.save_hint')) ?></span>
            </div>
            <input type="hidden" name="_end" value="1">
        </form>

<?php foreach ($registry->types() as $type): ?>
        <template data-block-template="<?= e($type) ?>">
<?php
    $index = '__INDEX__';
    $block = [
        // admin.js mints a key over this when it clones the template (D-094); it used to
        // write a position here, and the two are the same substitution.
        'key' => '__INDEX__',
        'id' => null,
        'type' => $type,
        'content' => $registry->fresh($type),
        'style' => Composition::style($character, $type),
        'layout' => Composition::layout($registry, $character, $type),
        // A block cloned out of this template arrives in a band of its own, so it carries
        // that band's fields. admin.js mints the section key over the placeholder the same
        // way it mints the block's (D-098).
        'section' => '__INDEX__',
        'column' => 0,
    ];
    $showSection = true;
    require __DIR__ . '/block.php';
?>
        </template>
<?php endforeach; ?>

<?php /* One <template> per repeater, keyed type.field. At the TOP LEVEL rather than
         nested inside the block template above: a <template>'s contents are not live
         nodes, so neither editor's renumber() reaches inside one, and a template that had
         baked in a block's position would add items to the wrong block after the first
         move. Both indices are placeholders and repeater.js substitutes them on clone
         (PLAN.md O-11). An item's markup depends only on the block type and the field, so
         one template per pair serves every block of that type on the page. */ ?>
<?php foreach ($registry->types() as $templateType): ?>
<?php foreach ($registry->get($templateType)['fields'] as $templateField => $templateSpec): ?>
<?php if ($templateSpec['type'] !== 'repeater') { continue; } ?>
        <template data-item-template="<?= e($templateType) ?>.<?= e($templateField) ?>">
<?php
    $blockType = $templateType;
    $blockIndex = '__INDEX__';
    $repeaterName = (string) $templateField;
    $repeaterField = $templateSpec;
    $itemIndex = '__ITEM__';
    $itemValue = \App\Core\Blocks::emptyItem($templateSpec);
    require __DIR__ . '/item.php';
?>
        </template>
<?php endforeach; ?>
<?php endforeach; ?>

        <div class="row-actions editor-secondary">
            <form method="post" action="<?= e(Url::admin('pages', $pageId, 'status')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="return" value="edit">
                <input type="hidden" name="status" value="<?= $published ? 'draft' : 'published' ?>">
                <button type="submit" class="button button-secondary"><?= e(t($published ? 'pages.unpublish' : 'pages.publish')) ?></button>
            </form>
            <form method="post" action="<?= e(Url::admin('pages', $pageId, 'delete')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="button button-ghost button-danger" data-confirm="<?= e(t('pages.delete_confirm', ['title' => (string) $page['title']])) ?>"><?= e(t('pages.delete')) ?></button>
            </form>
        </div>
