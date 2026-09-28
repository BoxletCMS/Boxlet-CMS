<?php
/**
 * Image and text block. body is richtext, reduced to the safe-HTML whitelist on save.
 *
 * The picture is drawn when one has been resolved for this block, and a placeholder
 * otherwise — a picture that was deleted, or whose variants are still being made, shows
 * the same grey box it showed before media existed rather than a broken URL.
 *
 * card and wide: this block is half the column at most, so the widest it is ever asked to
 * fill is the wide preset (SPEC §5.5).
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
$picture = is_int($content['image'] ?? null) ? ($media[$content['image']] ?? null) : null;
$tag = \App\Modules\Media\MediaPicture::tag($picture, ['card', 'wide'], '(max-width: 40rem) 100vw, 50vw', $eager);
?>
<div class="image-text fit-<?= e($content['image_fit']) ?>">
    <div class="image-text-media">
<?php if ($tag !== ''): ?>
        <?= $tag ?>
<?php else: ?>
        <div class="media-placeholder"<?= $content['image'] !== null ? ' data-media-id="' . e($content['image']) . '"' : '' ?> aria-hidden="true"></div>
<?php endif; ?>
    </div>
    <div class="image-text-body">
<?php if ($content['heading'] !== ''): ?>
        <h2 class="image-text-heading"><?= e($content['heading']) ?></h2>
<?php endif; ?>
        <div class="richtext"><?= $content['body'] ?></div>
<?php if ($content['link']['url'] !== '' && $content['link']['label'] !== ''): ?>
        <p><a href="<?= e($content['link']['url']) ?>"><?= e($content['link']['label']) ?></a></p>
<?php endif; ?>
    </div>
</div>
