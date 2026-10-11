<?php

/**
 * THE TICKED ROWS, DELETED TOGETHER (PLAN.md D-218): the bar above a list whose rows carry a
 * tick: a checkbox named ids[] whose form attribute is $bulkId. Required by Pages' and Media's
 * lists.
 *
 * Without a script the button sends whatever is ticked, after one question; with one
 * (admin-bulk.js) it says how many are ticked, stays off while none is, and the question
 * names the count. Delete says so in the danger ink, a shape at rest like every button.
 *
 * @var string $bulkId the form's id, which the ticks name
 * @var string $bulkAction where it posts
 * @var string $bulkWords the language keys' prefix: pages.bulk or media.bulk
 * @var array<string, string> $bulkKeep the list's filters, carried back to it
 * @var string $csrf
 */
?>
        <form method="post" action="<?= e($bulkAction) ?>" id="<?= e($bulkId) ?>" class="list-bulk" data-bulk>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<?php foreach ($bulkKeep as $name => $value): ?>
<?php if ($value !== ''): ?>
            <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
<?php endif; ?>
<?php endforeach; ?>
            <span class="list-bulk-count" data-bulk-count data-none="<?= e(t($bulkWords . '.none_ticked')) ?>" data-some="<?= e(t($bulkWords . '.ticked')) ?>" aria-live="polite"><?= e(t($bulkWords . '.none_ticked')) ?></span>
            <button type="submit" class="button button-secondary button-danger button-small" data-bulk-delete
                    data-confirm="<?= e(t($bulkWords . '.confirm_any')) ?>" data-confirm-some="<?= e(t($bulkWords . '.confirm')) ?>"><?= icon('trash-2') ?> <?= e(t($bulkWords . '.delete')) ?></button>
        </form>
