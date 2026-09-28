<?php

use App\Support\Url;

/**
 * One file for visitors to download (PLAN.md D-126). Provided by AdminView::render().
 *
 * Short on purpose: a file has no preview, no crop, no focal point and no description read
 * aloud. What there is to know is what it is, the address it is downloaded from, how often it
 * was, and that it can be deleted.
 *
 * @var array{id: int, filename: string, original: string, ext: string, size: string, bytes: int, width: int, height: int, complete: bool, thumb: string|null, kind: string, downloads: int, download: string} $file
 * @var array<int, string> $usedBy page id => title
 * @var string $added
 * @var string $mime
 * @var string $csrf
 */
?>
        <div class="page-header">
            <h1><?= e($file['filename']) ?><span class="media-ext">.<?= e($file['ext']) ?></span></h1>
            <a class="button button-secondary" href="<?= e(Url::admin('media')) ?>"><?= e(t('media.back')) ?></a>
        </div>

        <div class="media-side media-file">
            <h2><?= e(t('media.details')) ?></h2>
            <?php /* One block after the heading, so the space a picture's page keeps under its
                     facts is kept here under the words and the facts together. */ ?>
            <div class="stack">
            <p class="hint"><?= e(t('media.file_details')) ?></p>
            <dl class="media-facts-list">
                <dt><?= e(t('media.original_name')) ?></dt>
                <dd><?= e($file['original']) ?></dd>
                <dt><?= e(t('media.format')) ?></dt>
                <dd><?= e(strtoupper($file['ext'])) ?> · <?= e($mime) ?></dd>
                <dt><?= e(t('media.size')) ?></dt>
                <dd><?= e($file['size']) ?></dd>
                <dt><?= e(t('media.added')) ?></dt>
                <dd><?= e($added) ?></dd>
                <dt><?= e(t('media.file_downloads')) ?></dt>
                <dd><?= e(t($file['downloads'] === 1 ? 'media.downloads_one' : 'media.downloads_many', ['count' => (string) $file['downloads']])) ?></dd>
                <dt><?= e(t('media.file_address')) ?></dt>
                <dd><a href="<?= e($file['download']) ?>"><?= e($file['download']) ?></a></dd>
            </dl>
            </div>

            <h2><?= e(t('media.used_by')) ?></h2>
<?php if ($usedBy === []): ?>
            <p class="hint"><?= e(t('media.file_used_by_none')) ?></p>
<?php else: ?>
            <ul class="media-used">
<?php foreach ($usedBy as $pageId => $pageTitle): ?>
                <li><a href="<?= e(Url::admin('pages', $pageId)) ?>"><?= e($pageTitle) ?></a></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>

            <div class="media-actions">
                <a class="button button-secondary" href="<?= e($file['download']) ?>"><?= icon(\App\Modules\Media\MediaFileType::icon($file['ext'])) ?> <?= e(t('media.file_try')) ?></a>
                <form class="media-delete" method="post" action="<?= e(Url::admin('media', $file['id'], 'delete')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button type="submit" class="button button-ghost button-danger"
                            data-confirm="<?= e(t('media.delete_confirm', ['name' => $file['filename']])) ?>"><?= icon('trash-2') ?> <?= e(t('media.delete')) ?></button>
                </form>
            </div>
        </div>
