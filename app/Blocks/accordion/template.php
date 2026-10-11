<?php
/**
 * Questions with their answers folded behind them.
 *
 * <details>/<summary>, with no script of any kind: it opens and closes on its own, it opens
 * when the page is printed, the browser's own find-in-page opens the one it matched, and a
 * screen reader already knows what it is.
 *
 * `open` IS NOT STATE. It is written from `start`, so the page a visitor is handed looks the
 * same every time; what they open afterwards is theirs and is not stored anywhere.
 *
 * `answer` is richtext, reduced to the whitelist on save; everything else is escaped.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 * @var array<string, string> $options measure comfortable/wide/full (D-188)
 */
?>
<div class="accordion measure-<?= e($options['measure']) ?>">
<?php if (edit_show($content['heading'] !== '')): ?>
    <h2 class="accordion-heading"<?= edit_attr('heading') ?>><?= e($content['heading']) ?></h2>
<?php endif; ?>
    <div class="accordion-items">
<?php foreach ($content['items'] as $at => $item): ?>
        <?php /* Every answer open in the canvas, to be typed in where it is read (D-178). */ ?>
        <details class="accordion-item<?= $item['question'] === '' && $item['answer'] === '' ? ' is-empty' : '' ?>"<?= ($at === 0 && $content['start'] === 'first-open') || \App\Support\Editing::on() ? ' open' : '' ?><?= edit_item('items', $at) ?>>
            <summary class="accordion-question"<?= edit_attr('items', $at, 'question') ?>><?= e($item['question']) ?></summary>
<?php if (edit_show($item['answer'] !== '')): ?>
            <div class="accordion-answer richtext"<?= edit_attr('items', $at, 'answer') ?>><?= $item['answer'] ?></div>
<?php endif; ?>
        </details>
<?php endforeach; ?>
<?= edit_add('items', 'accordion-item') ?>
    </div>
</div>
