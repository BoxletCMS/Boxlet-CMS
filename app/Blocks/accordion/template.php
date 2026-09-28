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
 */
?>
<div class="accordion">
<?php if ($content['heading'] !== ''): ?>
    <h2 class="accordion-heading"><?= e($content['heading']) ?></h2>
<?php endif; ?>
    <div class="accordion-items">
<?php foreach ($content['items'] as $at => $item): ?>
        <details class="accordion-item<?= $item['question'] === '' && $item['answer'] === '' ? ' is-empty' : '' ?>"<?= $at === 0 && $content['start'] === 'first-open' ? ' open' : '' ?>>
            <summary class="accordion-question"><?= e($item['question']) ?></summary>
<?php if ($item['answer'] !== ''): ?>
            <div class="accordion-answer richtext"><?= $item['answer'] ?></div>
<?php endif; ?>
        </details>
<?php endforeach; ?>
    </div>
</div>
