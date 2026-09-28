<?php
/**
 * One picture, with an optional caption under it.
 *
 * `card` and `wide` rather than `hero`: this block is as wide as the column it stands in,
 * and a column is at most the container (SPEC §5.5). The natural shape asks for `natural`
 * and `full` instead, the two that are not cropped. A picture that has been deleted, or
 * whose variants are still being made, shows the same placeholder every other block shows
 * rather than a broken URL.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 * @var array<int, array{id: int, filename: string, width: int, height: int, focalX: int, focalY: int, variants: array<string, array{width: int, height: int, formats: list<string>}>, alt: string, version: string}> $media
 * @var bool $eager
 */
$picture = is_int($content['image'] ?? null) ? ($media[$content['image']] ?? null) : null;
/* A NATURAL SHAPE IS THE PICTURE'S OWN (D-119). `card` and `wide` are cropped to 3:2 and
   1.9:1, so "natural" drew every picture landscape — a portrait 1000×1333 as 600×400. The
   other shapes are drawn by the stylesheet over a crop, and keep asking for one. */
$presets = $content['shape'] === 'natural' ? ['natural', 'full'] : ['card', 'wide'];
$tag = \App\Modules\Media\MediaPicture::tag($picture, $presets, '(max-width: 40rem) 100vw, 50vw', $eager);
?>
<figure class="picture shape-<?= e($content['shape']) ?>">
    <div class="picture-frame">
<?php if ($tag !== ''): ?>
        <?= $tag ?>
<?php else: ?>
        <div class="media-placeholder"<?= $content['image'] !== null ? ' data-media-id="' . e($content['image']) . '"' : '' ?> aria-hidden="true"></div>
<?php endif; ?>
    </div>
<?php if ($content['caption'] !== ''): ?>
    <figcaption class="picture-caption"><?= e($content['caption']) ?></figcaption>
<?php endif; ?>
</figure>
