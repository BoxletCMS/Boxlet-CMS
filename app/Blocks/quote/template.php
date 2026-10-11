<?php
/**
 * A testimonial: the words, who said them, and optionally their face.
 *
 * <blockquote> and <cite>, because that is what they are — a screen reader announces a
 * quotation as one, and the attribution is marked as the source rather than as another
 * line of text that happens to sit under it.
 *
 * `thumb` and `card`: a portrait here is small at every size this block is ever drawn at.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 * @var array<int, array{id: int, filename: string, width: int, height: int, focalX: int, focalY: int, variants: array<string, array{width: int, height: int, formats: list<string>}>, alt: string, version: string}> $media
 * @var bool $eager
 * @var array<string, string> $options measure comfortable/wide/full (D-188)
 */
$picture = is_int($content['portrait'] ?? null) ? ($media[$content['portrait']] ?? null) : null;
$tag = \App\Modules\Media\MediaPicture::tag($picture, ['thumb', 'card'], '4rem', $eager);
$says = trim((string) $content['attribution']) !== '' || trim((string) $content['role']) !== '';
?>
<figure class="quote measure-<?= e($options['measure']) ?>">
<?php if ($tag !== ''): ?>
    <div class="quote-portrait"<?= edit_attr('portrait') ?>><?= $tag ?></div>
<?php endif; ?>
    <blockquote class="quote-words"<?= edit_attr('quote') ?>><?= nl2br(e($content['quote'])) ?></blockquote>
<?php if (edit_show($says)): ?>
    <figcaption class="quote-said">
<?php if (edit_show($content['attribution'] !== '')): ?>
        <cite class="quote-who"<?= edit_attr('attribution') ?>><?= e($content['attribution']) ?></cite>
<?php endif; ?>
<?php if (edit_show($content['role'] !== '')): ?>
        <span class="quote-role"<?= edit_attr('role') ?>><?= e($content['role']) ?></span>
<?php endif; ?>
    </figcaption>
<?php endif; ?>
</figure>
