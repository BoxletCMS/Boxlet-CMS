<?php

use App\Support\Url;

/**
 * THE PAGE BUILDER'S SHELL (PLAN.md D-175, README 4, `Page Builder Mockup.html`): the bar, the
 * rail with Structure, Add and Page, the canvas, and the inspector. The document is handed to
 * builder-*.js once, as JSON the page carries (not a script: the admin's CSP runs none inline),
 * and the scripts keep it from then on.
 *
 * Without a script the builder cannot work at all — its document lives in the browser — so the
 * bar offers the plain editor, which needs none.
 *
 * @var array<string, mixed> $page
 * @var array<string, mixed> $document
 * @var string $state published, changes or draft
 * @var array<string, mixed> $data what builder-*.js starts from
 * @var \App\Core\Blocks $registry
 * @var list<array{code: string, label: string, page: int|null, current: bool, source: bool}> $languages
 * @var bool $aiReady Translate with AI offered beside Translate, and Translate again beside a stale block (D-248)
 * @var string $csrf
 * @var array{source: array<string, mixed>|null, stale: array<int, array{source: int, type: string, content: array<string, mixed>}>, missing: int, sourceLabel: string} $translation
 */
$pageId = (int) $page['id'];
?>
<div class="pb" data-pb data-tag-locale="<?= e((string) $page['locale']) ?>" data-csrf="<?= e($csrf) ?>" data-canvas-url="<?= e(Url::admin('pages', $pageId, 'canvas')) ?>" data-icons="<?= e(Url::versioned('assets/vendor/icons.svg')) ?>">
<?php require __DIR__ . '/builder/bar.php'; ?>
    <div class="pb-body" data-pb-body>
<?php require __DIR__ . '/builder/rail.php'; ?>
        <main class="pb-stage">
            <p class="pb-small-note"><a href="<?= e(Url::admin('pages', $pageId, 'form')) ?>"><?= e(t('builder.small_screen')) ?></a></p>
            <div class="pb-stage-head">
                <nav class="pb-trail" data-pb-trail aria-label="<?= e(t('builder.canvas.page')) ?>"><button type="button" class="pb-trail-part" data-trail="page"><?= e(t('builder.canvas.page')) ?></button></nav>
                <p class="pb-stage-hint"><?= icon('mouse-pointer-click') ?> <?= e(t('builder.canvas.hint')) ?></p>
                <p class="pb-stage-size" data-pb-size></p>
            </div>
            <div class="pb-frame" data-pb-frame>
                <iframe src="<?= e(Url::admin('pages', $pageId, 'canvas')) ?>" title="<?= e(t('pages.canvas')) ?>" data-pb-canvas></iframe>
            </div>
        </main>
        <aside class="pb-inspector" data-pb-inspector aria-live="polite">
<?php require __DIR__ . '/builder/nothing.php'; ?>
        </aside>
    </div>
    <script type="application/json" data-pb-data><?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?></script>
</div>

<?php /* Making a translation posts one of these, reached by the language menu's form attribute. */ ?>
<?php foreach ($languages as $language): ?>
<?php if ($language['page'] === null): ?>
<form method="post" action="<?= e(Url::admin('pages', $pageId, 'translate')) ?>" id="translate-<?= e($language['code']) ?>" class="visually-hidden">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="locale" value="<?= e($language['code']) ?>">
</form>
<?php endif; ?>
<?php endforeach; ?>

<?php /* A version that exists, translated again with AI (D-252), reached by the language menu. */ ?>
<?php foreach ($languages as $language): ?>
<?php if ($aiReady && $language['page'] !== null && !$language['source']): ?>
<form method="post" action="<?= e(Url::admin('pages', $language['page'], 'ai')) ?>" id="ai-page-<?= e((string) $language['page']) ?>" class="visually-hidden">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
</form>
<?php endif; ?>
<?php endforeach; ?>

<?php /* "Mark as up to date" on a stale block of a translation (D-043, step 3) posts one of
         these, reached from the inspector by the button's form attribute. */ ?>
<?php foreach (array_keys($translation['stale']) as $staleId): ?>
<form method="post" action="<?= e(Url::admin('pages', $pageId, 'blocks', $staleId, 'current')) ?>" id="current-<?= e((string) $staleId) ?>" class="visually-hidden">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
</form>
<?php if ($aiReady): ?>
<form method="post" action="<?= e(Url::admin('pages', $pageId, 'blocks', $staleId, 'ai')) ?>" id="ai-again-<?= e((string) $staleId) ?>" class="visually-hidden">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
</form>
<?php endif; ?>
<?php endforeach; ?>

<?php /* One <template> per repeater, keyed type.field, at the top level so repeater.js can clone
         an item into the inspector's All content (PLAN.md O-11): the item the canvas's "+"
         adds too, saying it is new (D-179). */ ?>
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
    $itemValue = $data['inline']['items'][$templateType][$templateField] ?? \App\Core\Blocks::emptyItem($templateSpec);
    require __DIR__ . '/item.php';
?>
</template>
<?php endforeach; ?>
<?php endforeach; ?>

<?php /* In this order: the document and its history, then what draws it (canvas, tree, add,
         page), then the inspector, then the shell that wires them (D-175). */ ?>
<script src="<?= e(Url::versioned('assets/builder-doc.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-actions.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-canvas.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-overlay.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-overlay-place.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-overlay-press.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-inserter.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-columns.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-inline.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-inline-errors.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-inline-items.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-inline-link.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-inline-rich.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-overlay-apart.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-tree.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-add.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-page.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-inspector-follow.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-inspector.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder-inspector-actions.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/builder.js')) ?>" defer></script>
<script src="<?= e(Url::versioned('assets/hints.js')) ?>" defer></script>
