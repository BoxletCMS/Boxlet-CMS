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
 * @var array<int, array{id: int, filename: string, width: int, height: int, focalX: int, focalY: int, variants: array<string, array{width: int, height: int, formats: list<string>}>, alt: string, version: string}> $media
 * @var bool $eager
 */
$sizes = '(max-width: 40rem) 50vw, ' . (['two' => '50vw', 'four' => '25vw'][$layout] ?? '33vw');
// A natural shape is each picture's own, so it asks for the uncropped presets (D-119).
$presets = $content['shape'] === 'natural' ? ['natural', 'full'] : ['card', 'wide'];
?>
<div class="gallery shape-<?= e($content['shape']) ?>">
<?php if ($content['heading'] !== ''): ?>
    <h2 class="gallery-heading"><?= e($content['heading']) ?></h2>
<?php endif; ?>
    <div class="gallery-grid">
<?php foreach ($content['items'] as $item): ?>
<?php
    $picture = is_int($item['image']) ? ($media[$item['image']] ?? null) : null;
    $tag = \App\Modules\Media\MediaPicture::tag($picture, $presets, $sizes, $eager);
?>
        <figure class="gallery-item<?= $item['image'] === null && $item['caption'] === '' ? ' is-empty' : '' ?>">
            <div class="gallery-frame">
<?php if ($tag !== ''): ?>
                <?= $tag ?>
<?php else: ?>
                <div class="media-placeholder"<?= $item['image'] !== null ? ' data-media-id="' . e($item['image']) . '"' : '' ?> aria-hidden="true"></div>
<?php endif; ?>
            </div>
<?php if ($item['caption'] !== ''): ?>
            <figcaption class="gallery-caption"><?= e($item['caption']) ?></figcaption>
<?php endif; ?>
        </figure>
<?php endforeach; ?>
    </div>
</div>
