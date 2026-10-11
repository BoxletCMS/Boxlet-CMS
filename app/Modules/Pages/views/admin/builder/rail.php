<?php

/**
 * The builder's rail (README 4.2): three tabs — Structure, Add, Page — and the fold that hides
 * it, folded by default under 1200px. Structure is drawn by builder-tree.js from the document;
 * Add is a list drawn here, filtered by builder-add.js; Page is a form bound to the document.
 *
 * @var list<array{type: string, label: string, icon: string, group: string, summary: string}> $library in the
 *      builder's order (PageBuilderController::ORDER), shelved here by group (D-176)
 * @var array{mine: list<array{id: int, name: string, created_at: string}>, set: list<array{id: string, name: string}>, setName: string} $patterns
 */
?>
        <nav class="pb-tabs" aria-label="<?= e(t('builder.rail.toggle')) ?>">
<?php foreach (['structure' => 'list-tree', 'add' => 'square-plus', 'page' => 'file'] as $tab => $tabIcon): ?>
            <button type="button" class="pb-tab" data-pb-tab="<?= e($tab) ?>" aria-pressed="<?= $tab === 'structure' ? 'true' : 'false' ?>" title="<?= e(t('builder.rail.' . $tab)) ?>"><?= icon($tabIcon) ?><span class="visually-hidden"><?= e(t('builder.rail.' . $tab)) ?></span></button>
<?php endforeach; ?>
            <button type="button" class="pb-tab pb-tab-fold" data-pb-fold aria-expanded="true" title="<?= e(t('builder.rail.toggle')) ?>"><?= icon('panel-left') ?><span class="visually-hidden"><?= e(t('builder.rail.toggle')) ?></span></button>
        </nav>
        <aside class="pb-rail" data-pb-rail>
            <section class="pb-panel" data-pb-panel="structure" aria-label="<?= e(t('builder.rail.structure')) ?>">
                <div class="pb-panel-head"><h2><?= e(t('builder.rail.structure')) ?></h2><span class="pb-panel-count" data-pb-count></span></div>
                <ol class="pb-tree" data-pb-tree></ol>
            </section>
            <section class="pb-panel" data-pb-panel="add" aria-label="<?= e(t('builder.rail.add')) ?>" hidden>
                <div class="pb-panel-head"><h2><?= e(t('builder.rail.add')) ?></h2></div>
                <label class="pb-search"><?= icon('search') ?><span class="visually-hidden"><?= e(t('builder.add.search')) ?></span>
                    <input type="search" data-pb-add-search placeholder="<?= e(t('builder.add.search')) ?>" autocomplete="off"></label>
                <div class="segmented-choice pb-add-kind" role="group">
                    <button type="button" class="segment" data-pb-add-kind="blocks" aria-pressed="true"><?= e(t('builder.add.blocks')) ?></button>
                    <button type="button" class="segment" data-pb-add-kind="patterns" aria-pressed="false"><?= e(t('builder.add.patterns')) ?></button>
                </div>
                <p class="pb-add-where" data-pb-add-where><?= e(t('builder.add.where_end')) ?></p>
                <div data-pb-add-list="blocks">
<?php foreach (['text', 'media', 'layout', 'marketing', 'embed'] as $shelf): ?>
<?php $onShelf = array_values(array_filter($library, static fn (array $item): bool => $item['group'] === $shelf)); ?>
<?php if ($onShelf === []) { continue; } ?>
                    <section class="pb-add-shelf" data-pb-shelf>
                        <h3 class="pb-add-group"><?= e(t('block.group.' . $shelf)) ?></h3>
                        <ul class="pb-add-list">
<?php foreach ($onShelf as $item): ?>
                            <li><button type="button" class="pb-add-item" data-add-block="<?= e($item['type']) ?>" data-words="<?= e(mb_strtolower($item['label'] . ' ' . $item['summary'] . ' ' . $item['type'])) ?>">
                                <span class="pb-add-icon"><?= icon($item['icon']) ?></span>
                                <span class="pb-add-text"><span class="pb-add-name"><?= e($item['label']) ?></span><span class="pb-add-line"><?= e($item['summary']) ?></span></span>
                                <span class="pb-add-plus" aria-hidden="true"><?= icon('plus') ?></span></button></li>
<?php endforeach; ?>
                        </ul>
                    </section>
<?php endforeach; ?>
                </div>
                <div data-pb-add-list="patterns" hidden>
                    <h3 class="pb-add-group"><?= e(t('builder.add.mine')) ?></h3>
                    <ul class="pb-add-list" data-pb-patterns-mine>
<?php if ($patterns['mine'] === []): ?>
                        <?php /* Empty, said as a card like the rest of the list (D-176), not a line. */ ?>
                        <li data-pb-mine-none><div class="pb-add-item pb-add-empty">
                            <span class="pb-add-icon"><?= icon('bookmark-plus') ?></span>
                            <span class="pb-add-text"><span class="pb-add-name"><?= e(t('builder.add.none_mine_title')) ?></span><span class="pb-add-line"><?= e(t('builder.add.none_mine')) ?></span></span></div></li>
<?php endif; ?>
<?php foreach ($patterns['mine'] as $pattern): ?>
                        <li><button type="button" class="pb-add-item" data-add-pattern="user:<?= e($pattern['id']) ?>" data-words="<?= e(mb_strtolower($pattern['name'])) ?>">
                            <span class="pb-add-icon"><?= icon('bookmark-plus') ?></span><span class="pb-add-text"><span class="pb-add-name"><?= e($pattern['name']) ?></span></span>
                            <span class="pb-add-plus" aria-hidden="true"><?= icon('plus') ?></span></button></li>
<?php endforeach; ?>
                    </ul>
                    <h3 class="pb-add-group"><?= e(t('builder.add.from_set', ['set' => $patterns['setName']])) ?></h3>
                    <ul class="pb-add-list">
<?php foreach ($patterns['set'] as $pattern): ?>
                        <li><button type="button" class="pb-add-item" data-add-pattern="set:<?= e($pattern['id']) ?>" data-words="<?= e(mb_strtolower($pattern['name'])) ?>">
                            <span class="pb-add-icon"><?= icon('square-plus') ?></span><span class="pb-add-text"><span class="pb-add-name"><?= e($pattern['name']) ?></span></span>
                            <span class="pb-add-plus" aria-hidden="true"><?= icon('plus') ?></span></button></li>
<?php endforeach; ?>
                    </ul>
                </div>
                <p class="pb-add-none" data-pb-add-none hidden><?= e(t('builder.add.none_found')) ?></p>
            </section>
<?php require __DIR__ . '/page.php'; ?>
        </aside>
