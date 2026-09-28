<?php

/**
 * One repeater field: the list of its items, and the control that adds another
 * (PLAN.md O-11).
 *
 * A fieldset rather than a .field, because there is no single input for a label to point
 * at. The legend names the field the way a label would.
 *
 * ADD IS A SUBMIT, NOT A SCRIPT-ONLY BUTTON. Adding a block without JavaScript is a
 * server action, and an item deserves the same: this posts action=item-add-{n}-{field},
 * repeater.js intercepts it where scripts run, and the repeater is fully usable either
 * way — add, remove and reorder (D-011, one route for both paths).
 *
 * @var int|string $index the block's position; NOT part of any field name
 * @var string $key   what the block is called in field names and error keys (D-094)
 * @var array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string} $block
 * @var string $repeaterName
 * @var array<string, mixed> $repeaterField the validated repeater declaration
 * @var list<array<string, mixed>> $items the stored items, already normalized
 * @var string|null $fieldError
 * @var list<array{id: int, name: string, thumb: string|null}> $pictures
 */
$repeaterLabel = t('block.' . $block['type'] . '.' . $repeaterName)
    . ($repeaterField['required'] ? ' ' . t('pages.required_marker') : '');
// HELD IN A NAME OF ITS OWN, before a single item is rendered. A view included with
// require shares this scope, and item.php sets $fieldError for each field it draws — so
// reading $fieldError at the foot of this file gave whatever the last item's last field
// left behind, which was always null. The refusal never reached the screen: the editor
// said "fix the fields marked below" and marked nothing. Caught by the test, not by
// reading.
$repeaterError = $fieldError;
?>
                <fieldset class="repeater" data-repeater="<?= e($repeaterName) ?>" data-repeater-type="<?= e($block['type']) ?>" data-repeater-max="<?= (int) $repeaterField['max'] ?>" data-text-item="<?= e(t('pages.field.repeater_item', ['number' => '%n'])) ?>">
                    <legend class="repeater-legend"><?= e($repeaterLabel) ?></legend>
                    <?= field_hint('hint.block.' . $block['type'] . '.' . $repeaterName) ?>
                    <div class="repeater-items" data-repeater-items>
<?php foreach ($items as $itemIndex => $itemValue): ?>
<?php
    $blockType = $block['type'];
    $blockIndex = $key;
    require __DIR__ . '/item.php';
?>
<?php endforeach; ?>
                    </div>
                    <?php /* Said plainly rather than left as an empty box: an editor that
                             shows nothing at all reads as a feature that failed to load. */ ?>
                    <p class="hint" data-repeater-empty<?= $items === [] ? '' : ' hidden' ?>><?= e(t('pages.field.repeater_empty')) ?></p>
                    <div class="repeater-controls">
                        <button type="submit" name="action" value="item-add-<?= e($key) ?>-<?= e($repeaterName) ?>" class="button button-secondary" data-repeater-action="add"><?= e(t('pages.field.repeater_add')) ?></button>
                    </div>
<?php if ($repeaterError !== null): ?>
                    <p class="field-error" role="alert"><?= e($repeaterError) ?></p>
<?php endif; ?>
                </fieldset>
