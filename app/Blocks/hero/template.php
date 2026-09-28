<?php
/**
 * Hero block. Only class names here; every colour, size and font comes from CSS custom
 * properties in public/assets/blocks.css (SPEC §5.3).
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 * The picture shape is restated rather than imported: @phpstan-import-type resolves in a
 * class docblock, and a template has no class.
 *
 * @var array<int, array{id: int, filename: string, width: int, height: int, focalX: int, focalY: int, variants: array<string, array{width: int, height: int, formats: list<string>}>, alt: string, version: string}> $media id => resolved picture
 * @var bool $eager the first section on the page, which is never lazy-loaded
 */

/* THE PICTURE BEHIND THE WORDS (PLAN.md D-118): the four cover arrangements. The hero's own
   picture, so it is content and keeps its alt, laid under the words with a veil of the
   contrast colour between them; blocks-hero.css decides how far it reaches — the whole band
   when the hero stands alone in it, its own box when it shares one. The layer is drawn even
   with no picture chosen: its colour is the contrast surface the words are set for, so an
   empty cover hero is a panel of that colour with its own words, never words set for the
   contrast surface standing on a plain one. */
$cover = str_starts_with($layout, 'cover-');
$coverTag = '';
if ($cover) {
    $coverPicture = is_int($content['image'] ?? null) ? ($media[$content['image']] ?? null) : null;
    $coverTag = \App\Modules\Media\MediaPicture::tag($coverPicture, ['wide', 'hero', 'full'], '100vw', $eager);
}
?>
<div class="hero<?= $cover ? ' is-cover height-' . e($content['height']) . ' veil-' . e($content['veil']) : '' ?>">
<?php if ($cover): ?>
    <div class="hero-cover-picture"<?= $coverTag === '' && $content['image'] !== null ? ' data-media-id="' . e($content['image']) . '"' : '' ?>>
<?php if ($coverTag !== ''): ?>
        <?= $coverTag ?>
<?php endif; ?>
    </div>
<?php endif; ?>
    <div class="hero-text">
        <h1 class="hero-heading"><?= e($content['heading']) ?></h1>
<?php if ($content['subheading'] !== ''): ?>
        <p class="hero-subheading"><?= nl2br(e($content['subheading'])) ?></p>
<?php endif; ?>
<?php if ($content['cta']['url'] !== '' && $content['cta']['label'] !== ''): ?>
        <p class="hero-action"><a class="button" href="<?= e($content['cta']['url']) ?>"><?= e($content['cta']['label']) ?></a></p>
<?php endif; ?>
    </div>
<?php
/* A layout that reserves a picture area always draws one, with the same token-coloured
   placeholder image_text uses when nothing is chosen. An area the layout has set aside and
   then leaves empty is a hole the design never intended — measured on the demo's /services,
   where a split hero with no picture put the heading left, the subheading and button right,
   and nothing at all below them.

   A list rather than "always", because a layout that reserves nothing should draw nothing:
   a centred hero has no picture area to fill. */
$reservesPictureArea = in_array($layout, ['split'], true);

// hero and full: a hero picture can be asked to fill the whole column, so the largest
// preset it offers is the biggest one that exists (SPEC §5.5).
$picture = is_int($content['image'] ?? null) ? ($media[$content['image']] ?? null) : null;
$tag = \App\Modules\Media\MediaPicture::tag($picture, ['hero', 'full'], '(max-width: 40rem) 100vw, 50vw', $eager);
?>
<?php if (!$cover && ($reservesPictureArea || $content['image'] !== null)): ?>
    <div class="hero-media">
<?php if ($tag !== ''): ?>
        <?= $tag ?>
<?php else: ?>
        <div class="media-placeholder"<?= $content['image'] !== null ? ' data-media-id="' . e($content['image']) . '"' : '' ?> aria-hidden="true"></div>
<?php endif; ?>
    </div>
<?php endif; ?>
</div>
