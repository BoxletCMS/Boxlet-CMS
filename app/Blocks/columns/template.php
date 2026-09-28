<?php
/**
 * Columns block (PLAN.md D-008). body is richtext, reduced to the safe-HTML whitelist on
 * save; everything else is escaped.
 *
 * EVERY ITEM IS DRAWN, an empty one included, so the canvas and the page show the same
 * grid: an empty column is a hole the owner can see and remove, never one that appears
 * only after publishing. is-empty lets canvas.css outline it while editing.
 *
 * A column's picture is drawn when one has been chosen. An item without one is a column of
 * words, which is what a row of services or reasons usually is; a picture chosen and since
 * deleted, or still being made, keeps its place as the placeholder the other blocks draw.
 *
 * card and wide: a column is half the container at most. The sizes follow how many share a
 * row, so a row of four does not download pictures sized for two.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 * The picture shape is restated rather than imported: @phpstan-import-type resolves in a
 * class docblock, and a template has no class.
 *
 * @var array<int, array{id: int, filename: string, width: int, height: int, focalX: int, focalY: int, variants: array<string, array{width: int, height: int, formats: list<string>}>, alt: string, version: string}> $media id => resolved picture
 * @var bool $eager
 */
$sizes = '(max-width: 40rem) 100vw, ' . (['two' => '50vw', 'four' => '25vw'][$layout] ?? '33vw');
?>
<div class="columns shape-<?= e($content['image_shape']) ?>">
<?php if ($content['heading'] !== '' || $content['intro'] !== ''): ?>
    <div class="columns-head">
<?php if ($content['heading'] !== ''): ?>
        <h2 class="columns-heading"><?= e($content['heading']) ?></h2>
<?php endif; ?>
<?php if ($content['intro'] !== ''): ?>
        <p class="columns-intro"><?= e($content['intro']) ?></p>
<?php endif; ?>
    </div>
<?php endif; ?>
    <div class="columns-grid">
<?php foreach ($content['items'] as $item): ?>
<?php
    $picture = is_int($item['image']) ? ($media[$item['image']] ?? null) : null;
    $tag = \App\Modules\Media\MediaPicture::tag($picture, ['card', 'wide'], $sizes, $eager);
    $link = $item['link']['url'] !== '' && $item['link']['label'] !== '';
    // Marked so the editor can outline it; on the page the class draws nothing.
    $empty = $item['image'] === null && $item['heading'] === '' && $item['body'] === '' && !$link;
?>
        <div class="columns-item<?= $empty ? ' is-empty' : '' ?>">
<?php if ($tag !== ''): ?>
            <div class="columns-media"><?= $tag ?></div>
<?php elseif ($item['image'] !== null): ?>
            <div class="columns-media"><div class="media-placeholder" data-media-id="<?= e($item['image']) ?>" aria-hidden="true"></div></div>
<?php endif; ?>
<?php if ($item['heading'] !== ''): ?>
            <h3 class="columns-item-heading"><?= e($item['heading']) ?></h3>
<?php endif; ?>
<?php if ($item['body'] !== ''): ?>
            <div class="richtext"><?= $item['body'] ?></div>
<?php endif; ?>
<?php if ($link): ?>
            <p class="columns-link"><a href="<?= e($item['link']['url']) ?>"><?= e($item['link']['label']) ?></a></p>
<?php endif; ?>
        </div>
<?php endforeach; ?>
    </div>
</div>
