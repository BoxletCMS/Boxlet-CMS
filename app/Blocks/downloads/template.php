<?php
/**
 * Files for visitors to download (PLAN.md D-127): each a title, a line about it, and the
 * file's type and size — read from the file, never typed.
 *
 * The files come resolved in $resolved['files'] (MediaFiles::forBlocks()), never from the
 * database here. An item whose file is not chosen, or has since been deleted, is drawn
 * with .is-empty: blocks-media.css hides it on the page, where it would be a download of
 * nothing, and canvas.css shows it in the editor as the place to choose one.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 * @var array<string, mixed> $resolved ['files' => file id => {name, extension, size, url}]
 */
$files = is_array($resolved['files'] ?? null) ? $resolved['files'] : [];
?>
<div class="downloads">
<?php if ($content['heading'] !== ''): ?>
    <h2 class="downloads-heading"><?= e($content['heading']) ?></h2>
<?php endif; ?>
    <ul class="downloads-list">
<?php foreach ($content['items'] as $item):
    $file = is_int($item['file']) ? ($files[$item['file']] ?? null) : null;
    $title = $item['title'] !== '' ? $item['title'] : ($file['name'] ?? '');
    ?>
<?php if ($file === null): ?>
        <li class="downloads-item is-empty">
            <span class="downloads-type" aria-hidden="true"></span>
            <span class="downloads-text">
                <span class="downloads-title"><?= e($title) ?></span>
<?php if ($item['description'] !== ''): ?>
                <span class="downloads-description"><?= e($item['description']) ?></span>
<?php endif; ?>
            </span>
        </li>
<?php else: ?>
        <li class="downloads-item">
            <?php /* The whole item is the link: the type, the title and the line about it
                     are one thing to press, and the address ends in the file's own name. */ ?>
            <a class="downloads-link" href="<?= e($file['url']) ?>" download>
                <span class="downloads-type" aria-hidden="true"><?= e(strtoupper($file['extension'])) ?></span>
                <span class="downloads-text">
                    <span class="downloads-title"><?= e($title) ?></span>
<?php if ($item['description'] !== ''): ?>
                    <span class="downloads-description"><?= e($item['description']) ?></span>
<?php endif; ?>
                    <span class="downloads-meta"><?= e(strtoupper($file['extension'])) ?> · <?= e($file['size']) ?></span>
                </span>
            </a>
        </li>
<?php endif; ?>
<?php endforeach; ?>
    </ul>
</div>
