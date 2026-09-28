<?php

/**
 * One item of a repeater: its fields, and the controls that move or remove it.
 *
 * Rendered from two places: for each stored item (repeater.php), and once per repeater
 * into a <template> the editors clone when an item is added (edit.php, builder.php).
 *
 * THE BLOCK INDEX IS ALWAYS A PLACEHOLDER IN THE TEMPLATE, never a real number. A block
 * can be moved after the page is drawn, and a <template>'s contents are not live nodes —
 * neither editor's renumber() reaches inside one — so a template that had baked in the
 * block's position would add items to the wrong block after the first move. repeater.js
 * substitutes both indices when it clones.
 *
 * @var string $blockType
 * @var int|string $blockIndex     the block's position, or '__INDEX__' inside a template
 * @var string $repeaterName       the repeater field's name
 * @var array<string, mixed> $repeaterField the validated repeater declaration
 * @var int|string $itemIndex      the item's position, or '__ITEM__' inside a template
 * @var array<string, mixed> $itemValue    the item's stored values, already normalized
 * @var list<array{id: int, name: string, thumb: string|null}> $pictures
 */
$itemPrefix = 'blocks[' . $blockIndex . '][' . $repeaterName . '][' . $itemIndex . ']';
$itemIdPrefix = 'block-' . $blockIndex . '-' . $repeaterName . '-' . $itemIndex . '-';
// Counted from one for a reader. In the template the index is a placeholder and this is
// simply the first number; repeater.js rewrites every legend after any change, so the
// value rendered here for a cloned item never reaches the screen.
$itemNumber = is_int($itemIndex) ? $itemIndex + 1 : 1;
// What the no-JavaScript controls submit. The same route serves both paths: with scripts
// repeater.js intercepts these buttons, without them the server does the work (D-011).
$itemAction = '-' . $blockIndex . '-' . $repeaterName . '-' . $itemIndex;
?>
                    <fieldset class="repeater-item" data-repeater-item>
                        <legend class="repeater-item-legend" data-repeater-number><?= e(t('pages.field.repeater_item', ['number' => $itemNumber])) ?></legend>
                        <div class="repeater-item-body">
<?php foreach ($repeaterField['fields'] as $itemFieldName => $itemFieldSpec): ?>
<?php
    $fieldSpec = $itemFieldSpec;
    // One more segment than a block's own field. The third segment names an item's field
    // where a select's names an option, and the two can never collide: a repeater refuses
    // 'options' and a select cannot take 'fields' (SPEC §5.3).
    $fieldKey = 'block.' . $blockType . '.' . $repeaterName . '.' . $itemFieldName;
    $fieldName = $itemPrefix . '[' . $itemFieldName . ']';
    $fieldId = $itemIdPrefix . $itemFieldName;
    $fieldValue = $itemValue[$itemFieldName] ?? null;
    // Errors are reported per repeater FIELD, not per item: BlockForm names the first
    // thing wrong once, because a message per item per field buries the block's own
    // errors under a list nobody reads. The repeater shows it under the whole group.
    $fieldError = null;
    require __DIR__ . '/field.php';
?>
<?php endforeach; ?>
                        </div>
                        <div class="repeater-item-controls">
                            <button type="submit" name="action" value="item-up<?= e($itemAction) ?>" class="button button-ghost" data-repeater-action="up"><?= e(t('pages.move_up')) ?></button>
                            <button type="submit" name="action" value="item-down<?= e($itemAction) ?>" class="button button-ghost" data-repeater-action="down"><?= e(t('pages.move_down')) ?></button>
                            <button type="button" class="button button-ghost button-danger js-only" data-repeater-action="remove"><?= e(t('pages.field.repeater_remove')) ?></button>
                            <label class="checkbox no-js-only">
                                <input type="checkbox" name="<?= e($itemPrefix) ?>[_delete]" value="1">
                                <span><?= e(t('pages.remove_on_save')) ?></span>
                            </label>
                        </div>
                    </fieldset>
