<?php

/**
 * THE PAGE AS A TREE, beside the canvas (PLAN.md D-100, from the review's §3.2).
 *
 * The review asked for this in the same slice as the tree itself and not later, and it was
 * built later — which is exactly why the editor felt like a panel with no handles: the page
 * had become sections of columns and there was nowhere to see that. A canvas shows what a
 * page LOOKS like; this shows what it IS.
 *
 * WHAT EACH ROW SAYS, following the design artifact:
 *   Section N   its arrangement, as notation: 1, 1/2, 2/3+
 *   Column N    how many blocks stand in it — and only when there is more than one column,
 *               because "Column 1" under a single-column band is a level of nothing
 *   <block>     its own name, and its key, which is what the rest of the editor calls it
 *
 * RENDERED BY THE SERVER, so it is right before a single script has run and right again
 * after a save — the same data the canvas and the panel are drawn from. The script moves
 * the highlight and rebuilds rows as blocks come and go; it does not own the shape.
 *
 * @var array<string, array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}> $sections
 * @var list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string, section?: string, column?: int}> $blocks
 * @var \App\Core\Blocks $registry
 */

use App\Modules\Pages\SectionLayout;

$held = [];
foreach ($blocks as $block) {
    $held[$block['section'] ?? ''][(int) ($block['column'] ?? 0)][] = $block;
}
$sectionNumber = 0;
?>
            <aside class="builder-outline" data-outline aria-label="<?= e(t('pages.outline')) ?>"
                   data-icons="<?= e(\App\Support\Url::versioned('assets/vendor/icons.svg')) ?>"
                   data-label-section="<?= e(t('pages.outline.section', ['n' => '%'])) ?>"
                   data-label-column="<?= e(t('pages.outline.column', ['n' => '%'])) ?>">
                <div class="outline-head">
                    <h2 class="outline-title"><?= e(t('pages.outline')) ?></h2>
                    <?php /* Sections and blocks, the two numbers that say how big the page is
                             without anybody counting rows. */ ?>
                    <p class="outline-count" data-outline-count><?= e(count($sections) . ' / ' . count($blocks)) ?></p>
                </div>
                <div class="outline-rows" data-outline-rows>
<?php foreach ($sections as $key => $section): ?>
<?php
    $sectionNumber += 1;
    $layout = SectionLayout::normalize($section['layout']);
    $columns = $held[$key] ?? [];
    $many = SectionLayout::columns($layout) > 1;
?>
                    <button type="button" class="outline-row outline-row-section" data-outline-section="<?= e($key) ?>">
                        <?= icon('panels-top-left') ?>
                        <span class="outline-label"><?= e(t('pages.outline.section', ['n' => $sectionNumber])) ?></span>
                        <span class="outline-note"><?= e(t('style.layout.short.' . $layout)) ?></span>
                    </button>
<?php for ($at = 0; $at < SectionLayout::columns($layout); $at += 1): ?>
<?php $inside = $columns[$at] ?? []; ?>
<?php if ($many): ?>
                    <?php /* Not a button: a column is not a thing you edit, it is where
                             things stand. It says how many, and the blocks under it are
                             what you reach for. */ ?>
                    <p class="outline-row outline-row-column">
                        <?= icon('columns-3') ?>
                        <span class="outline-label"><?= e(t('pages.outline.column', ['n' => $at + 1])) ?></span>
                        <span class="outline-note"><?= e((string) count($inside)) ?></span>
                    </p>
<?php endif; ?>
<?php foreach ($inside as $block): ?>
                    <button type="button" class="outline-row outline-row-block<?= $many ? ' outline-deep' : '' ?>" data-outline-block="<?= e($block['key']) ?>">
                        <?= icon($registry->has($block['type']) ? (string) ($registry->get($block['type'])['icon'] ?? 'file-text') : 'circle-alert') ?>
                        <span class="outline-label"><?= e($registry->has($block['type']) ? t('block.' . $block['type']) : t('pages.block.unknown', ['type' => $block['type']])) ?></span>
                        <span class="outline-note"><?= e($block['key']) ?></span>
                    </button>
<?php endforeach; ?>
<?php endfor; ?>
<?php endforeach; ?>
<?php if ($sections === []): ?>
                    <p class="outline-empty"><?= e(t('pages.outline.empty')) ?></p>
<?php endif; ?>
                </div>
            </aside>
