<?php
/**
 * A grid of pictures, as many across as the layout says.
 *
 * EVERY ITEM IS DRAWN, an empty one included, exactly as the Columns block does it: an
 * empty cell is a hole the owner can see and remove, never one that appears only after
 * publishing. is-empty lets canvas.css outline it while editing.
 *
 * `card` and `wide`, sized from how many share a row, so a row of four does not download
 * pictures made for two.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 * @var array<string, string> $options the block's options, every one answered (D-166)
 * @var array<int, array{id: int, filename: string, width: int, height: int, focalX: int, focalY: int, variants: array<string, array{width: int, height: int, formats: list<string>}>, alt: string, version: string}> $media
 * @var bool $eager
 */
$sizes = '(max-width: 40rem) 50vw, ' . (['two' => '50vw', 'four' => '25vw'][$layout] ?? '33vw');
// A natural shape is each picture's own, so it asks for the uncropped presets (D-119).
$presets = $options['shape'] === 'natural' ? ['natural', 'full'] : ['card', 'wide'];
?>
<div class="gallery shape-<?= e($options['shape']) ?>">
<?php if (edit_show($content['heading'] !== '')): ?>
    <h2 class="gallery-heading"<?= edit_attr('heading') ?>><?= e($content['heading']) ?></h2>
<?php endif; ?>
    <div class="gallery-grid">
<?php foreach ($content['items'] as $at => $item): ?>
<?php
    $picture = is_int($item['image']) ? ($media[$item['image']] ?? null) : null;
    $tag = \App\Modules\Media\MediaPicture::tag($picture, $presets, $sizes, $eager);
?>
        <figure class="gallery-item<?= $item['image'] === null && $item['caption'] === '' ? ' is-empty' : '' ?>"<?= edit_item('items', $at) ?>>
            <div class="gallery-frame"<?= edit_attr('items', $at, 'image') ?>>
<?php if ($tag !== ''): ?>
                <?= $tag ?>
<?php else: ?>
                <div class="media-placeholder"<?= $item['image'] !== null ? ' data-media-id="' . e($item['image']) . '"' : '' ?> aria-hidden="true"></div>
<?php endif; ?>
            </div>
<?php if (edit_show($item['caption'] !== '')): ?>
            <figcaption class="gallery-caption"<?= edit_attr('items', $at, 'caption') ?>><?= e($item['caption']) ?></figcaption>
<?php endif; ?>
        </figure>
<?php endforeach; ?>
<?= edit_add('items', 'gallery-item') ?>
    </div>
</div>
