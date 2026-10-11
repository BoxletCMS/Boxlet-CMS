<?php
/**
 * Cards block (PLAN.md D-008, D-166: the Columns block, renamed). body is richtext, reduced
 * to the safe-HTML whitelist on save; everything else is escaped.
 *
 * EVERY ITEM IS DRAWN, an empty one included, so the canvas and the page show the same
 * grid: an empty card is a hole the owner can see and remove, never one that appears
 * only after publishing. is-empty lets canvas.css outline it while editing.
 *
 * A card's picture area is part of the card (README 1.6): the picture when one is chosen,
 * the placeholder wash until then, and nothing at all when the shape is none — a card of
 * words, which is what a row of services or reasons usually is; a picture chosen and since
 * deleted, or still being made, keeps its place as the placeholder the other blocks draw.
 *
 * card and wide: a card is half the container at most. The sizes follow how many share a
 * row, so a row of four does not download pictures sized for two.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout grid, or list (the picture beside the words)
 * @var array<string, string> $options per_row 2–4, image_shape wide/square/round (D-166)
 * The picture shape is restated rather than imported: @phpstan-import-type resolves in a
 * class docblock, and a template has no class.
 *
 * @var array<int, array{id: int, filename: string, width: int, height: int, focalX: int, focalY: int, variants: array<string, array{width: int, height: int, formats: list<string>}>, alt: string, version: string}> $media id => resolved picture
 * @var bool $eager
 */
$perRow = (int) $options['per_row'];
$sizes = $layout === 'list' ? '(max-width: 40rem) 100vw, 12rem' : '(max-width: 40rem) 100vw, ' . (['2' => '50vw', '4' => '25vw'][(string) $perRow] ?? '33vw');
?>
<div class="cards per-row-<?= e((string) $perRow) ?> shape-<?= e($options['image_shape']) ?>">
<?php if (edit_show($content['heading'] !== '' || $content['intro'] !== '')): ?>
    <div class="cards-head">
<?php if (edit_show($content['heading'] !== '')): ?>
        <h2 class="cards-heading"<?= edit_attr('heading') ?>><?= e($content['heading']) ?></h2>
<?php endif; ?>
<?php if (edit_show($content['intro'] !== '')): ?>
        <p class="cards-intro"<?= edit_attr('intro') ?>><?= e($content['intro']) ?></p>
<?php endif; ?>
    </div>
<?php endif; ?>
    <div class="cards-grid">
<?php foreach ($content['items'] as $at => $item): ?>
<?php
    $picture = is_int($item['image']) ? ($media[$item['image']] ?? null) : null;
    $tag = \App\Modules\Media\MediaPicture::tag($picture, ['card', 'wide'], $sizes, $eager);
    $link = $item['link']['url'] !== '' && $item['link']['label'] !== '';
    // Marked so the editor can outline it; on the page the class draws nothing.
    $empty = $item['image'] === null && $item['heading'] === '' && $item['body'] === '' && !$link;
?>
        <div class="cards-item<?= $empty ? ' is-empty' : '' ?>"<?= edit_item('items', $at) ?>>
<?php if ($options['image_shape'] !== 'none'): ?>
<?php if ($tag !== ''): ?>
            <div class="cards-media"<?= edit_attr('items', $at, 'image') ?>><?= $tag ?></div>
<?php else: ?>
            <div class="cards-media"<?= edit_attr('items', $at, 'image') ?>><div class="media-placeholder"<?= $item['image'] !== null ? ' data-media-id="' . e($item['image']) . '"' : '' ?> aria-hidden="true"></div></div>
<?php endif; ?>
<?php endif; ?>
<?php if (edit_show($item['heading'] !== '')): ?>
            <h3 class="cards-item-heading"<?= edit_attr('items', $at, 'heading') ?>><?= e($item['heading']) ?></h3>
<?php endif; ?>
<?php if (edit_show($item['body'] !== '')): ?>
            <div class="richtext"<?= edit_attr('items', $at, 'body') ?>><?= $item['body'] ?></div>
<?php endif; ?>
<?php if (edit_show($link)): ?>
            <p class="cards-link"><a href="<?= e($item['link']['url']) ?>"<?= edit_attr('items', $at, 'link') ?>><?= e($item['link']['label']) ?></a></p>
<?php endif; ?>
        </div>
<?php endforeach; ?>
<?= edit_add('items', 'cards-item') ?>
    </div>
</div>
