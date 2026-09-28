<?php

use App\Support\Url;

/**
 * The pictures themselves: the library screen's grid, and the only thing the picker asks
 * for. Rendered by admin/index.php inside the full screen, and on its own as the fragment
 * the picker loads — one query, one card, one set of markup, so the two can never drift.
 *
 * $picking is what differs. On the library screen a card is a link to the picture; in the
 * picker it is a button that chooses it, because a picker that navigated away would lose
 * everything typed since the last save.
 *
 * @var list<array{id: int, filename: string, original: string, size: string, width: int, height: int, complete: bool, thumb: string|null, suggested: bool}> $pictures
 * @var string $search
 * @var bool $picking
 * @var string $csrf
 */
?>
<?php if ($pictures === []): ?>
        <div class="empty-state">
            <p><?= e($search === '' ? t('media.empty') : t('media.search_none', ['term' => $search])) ?></p>
        </div>
<?php else: ?>
        <ul class="media-grid<?= $picking ? ' media-grid-picking' : '' ?>">
<?php foreach ($pictures as $picture): ?>
            <li class="media-card">
<?php if ($picking): ?>
                <button type="button" class="media-card-link" data-pick="<?= e($picture['id']) ?>" data-pick-name="<?= e($picture['filename']) ?>">
<?php else: ?>
                <a class="media-card-link" href="<?= e(Url::admin('media', $picture['id'])) ?>">
<?php endif; ?>
                    <?php /* An empty alt: the filename is the link text right below, so
                             announcing it twice would be noise. */ ?>
<?php if ($picture['thumb'] !== null): ?>
                    <img class="media-thumb" src="<?= e($picture['thumb']) ?>" alt="" width="200" height="200" loading="lazy">
<?php else: ?>
                    <span class="media-thumb media-thumb-none"><?= e(t('media.no_thumb')) ?></span>
<?php endif; ?>
                    <span class="media-name"><?= e($picture['filename']) ?></span>
<?php if ($picking): ?>
                </button>
<?php else: ?>
                </a>
<?php endif; ?>
                <p class="media-facts">
                    <?= e(t('media.dimensions', ['width' => (string) $picture['width'], 'height' => (string) $picture['height']])) ?>
                    · <?= e($picture['size']) ?>
                </p>
<?php if (!$picture['complete'] && !$picking): ?>
                <div class="media-pending">
                    <p class="hint"><?= e(t('media.incomplete')) ?></p>
                    <form method="post" action="<?= e(Url::admin('media', $picture['id'], 'finish')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <button type="submit" class="button button-secondary"><?= e(t('media.finish')) ?></button>
                    </form>
                </div>
<?php endif; ?>
            </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
