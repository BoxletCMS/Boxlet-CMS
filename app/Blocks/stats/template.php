<?php
/**
 * A row of numbers with a word under each.
 *
 * <dl>, because that is the shape: each number is a value and the line under it is what the
 * value is of. A screen reader reads the pair together; a row of divs reads as a list of
 * numbers followed by a list of words.
 *
 * The number is drawn at display size by the character, not by a size written here — which
 * is the entire reason this is a block rather than three Text blocks in a row.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 */
?>
<div class="stats">
<?php if ($content['heading'] !== ''): ?>
    <h2 class="stats-heading"><?= e($content['heading']) ?></h2>
<?php endif; ?>
    <dl class="stats-grid">
<?php foreach ($content['items'] as $item): ?>
        <div class="stats-item<?= $item['value'] === '' && $item['label'] === '' ? ' is-empty' : '' ?>">
            <dt class="stats-value"><?= e($item['value']) ?></dt>
            <dd class="stats-label"><?= e($item['label']) ?></dd>
        </div>
<?php endforeach; ?>
    </dl>
</div>
