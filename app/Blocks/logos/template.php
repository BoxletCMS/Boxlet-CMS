<?php
/**
 * A row of client marks, each at one height and its own width.
 *
 * NEVER CROPPED. `natural`, and `full` until a picture has one — the two presets that keep a
 * picture's own shape — drawn with object-fit: contain and no fixed proportion, because a
 * logo cut to fit a square is a logo somebody is entitled to complain about. It asked for
 * `thumb` and `card` until D-119, and both ARE cut to a shape: a 320×69 wordmark came out
 * as its middle.
 *
 * A mark with a name and no picture shows the NAME, set in the site's own type. That is a
 * legitimate way to run this block, not a fallback: half the marks a small studio can show
 * are companies that never sent an SVG.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 * @var array<int, array{id: int, filename: string, width: int, height: int, focalX: int, focalY: int, variants: array<string, array{width: int, height: int, formats: list<string>}>, alt: string, version: string}> $media
 * @var bool $eager
 */
?>
<div class="logos">
<?php if (edit_show($content['heading'] !== '')): ?>
    <h2 class="logos-heading"<?= edit_attr('heading') ?>><?= e($content['heading']) ?></h2>
<?php endif; ?>
    <ul class="logos-row">
<?php foreach ($content['items'] as $at => $item): ?>
<?php
    $picture = is_int($item['image']) ? ($media[$item['image']] ?? null) : null;
    $tag = \App\Modules\Media\MediaPicture::tag($picture, ['natural', 'full'], '10rem', $eager);
    $link = $item['link']['url'] !== '';
    $empty = $item['image'] === null && $item['name'] === '';
?>
        <li class="logos-item<?= $empty ? ' is-empty' : '' ?>"<?= edit_item('items', $at) ?>>
<?php if ($link): ?>
            <a class="logos-mark" href="<?= e($item['link']['url']) ?>">
<?php else: ?>
            <span class="logos-mark">
<?php endif; ?>
<?php if ($tag !== ''): ?>
                <span class="logos-picture"<?= edit_attr('items', $at, 'image') ?>><?= $tag ?></span>
<?php elseif ($item['image'] !== null): ?>
                <div class="media-placeholder" data-media-id="<?= e($item['image']) ?>" aria-hidden="true"<?= edit_attr('items', $at, 'image') ?>></div>
<?php else: ?>
                <span class="logos-name"<?= edit_attr('items', $at, 'name') ?>><?= e($item['name']) ?></span>
<?php endif; ?>
<?= $link ? '            </a>' : '            </span>' ?>

        </li>
<?php endforeach; ?>
<?= edit_add('items', 'logos-item', 'li') ?>
    </ul>
</div>
