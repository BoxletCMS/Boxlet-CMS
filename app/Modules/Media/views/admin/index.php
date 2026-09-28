<?php

use App\Support\Url;

/**
 * The picture library. Provided by AdminView::render().
 *
 * @var list<array{id: int, filename: string, original: string, ext: string, size: string, bytes: int, width: int, height: int, complete: bool, thumb: string|null}> $pictures every picture the search found
 * @var list<array{id: int, filename: string, ext: string, size: string, width: int, height: int, complete: bool, thumb: string|null, pages: int, site: bool, described: string}> $rows what the filter leaves
 * @var string $show the filter: '', 'unused' or 'undescribed'
 * @var string $kind pictures, files or both: '', 'pictures' or 'files' (D-126)
 * @var string $accept what the file chooser offers, every extension the library takes
 * @var int $bytes what the pictures found weigh together
 * @var int $page which page of the list this is, from 1
 * @var int $pages how many pages the list has
 * @var int $total how many rows the filter leaves, on every page together
 * @var string $search
 * @var int $remakeLeft pictures still owed a remake (D-048)
 * @var array{file: int, request: int, fileLabel: string, requestLabel: string} $limits
 * @var string $csrf
 *
 * The list is a table (admin/table.php); the grid in admin/cards.php is the picker's.
 */
?>
        <div class="page-header">
            <h1><?= e(t('media.title')) ?></h1>
            <?php /* The upload's file input, as a button: a label, so it opens the chooser
                     without a script. media.js uploads as soon as files are chosen. */ ?>
            <label class="button" for="media-files"><?= icon('cloud-upload') ?> <?= e(t('media.upload')) ?></label>
        </div>
        <p class="page-subtitle"><?= e(t('media.lede')) ?></p>

        <?php /* Finding a picture: by name, and by what it is missing (D-052). Plain GET
                 forms and links, so each view has an address. */ ?>
        <div class="list-filters">
            <form class="list-search" method="get" action="<?= e(Url::admin('media')) ?>" role="search">
<?php if ($show !== ''): ?>
                <input type="hidden" name="show" value="<?= e($show) ?>">
<?php endif; ?>
<?php if ($kind !== ''): ?>
                <input type="hidden" name="kind" value="<?= e($kind) ?>">
<?php endif; ?>
                <label for="media-search" class="visually-hidden"><?= e(t('media.search')) ?></label>
                <input type="search" id="media-search" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('media.search_placeholder')) ?>">
                <button type="submit" class="button button-secondary"><?= e(t('media.search_submit')) ?></button>
            </form>
            <?php /* Pictures or files (D-126), beside what they are missing. */ ?>
            <nav class="segmented" aria-label="<?= e(t('media.kind')) ?>">
<?php foreach (['' => 'media.kind.all', 'pictures' => 'media.kind.pictures', 'files' => 'media.kind.files'] as $value => $key): ?>
                <a href="<?= e(Url::admin('media') . (($query = array_filter(['q' => $search, 'show' => $show, 'kind' => $value])) !== [] ? '?' . http_build_query($query) : '')) ?>"<?= $kind === $value ? ' aria-current="page"' : '' ?>><?= e(t($key)) ?></a>
<?php endforeach; ?>
            </nav>
            <nav class="segmented" aria-label="<?= e(t('media.show')) ?>">
<?php foreach (['' => 'media.show.all', 'unused' => 'media.show.unused', 'undescribed' => 'media.show.undescribed'] as $value => $key): ?>
                <a href="<?= e(Url::admin('media') . (($query = array_filter(['q' => $search, 'show' => $value, 'kind' => $kind])) !== [] ? '?' . http_build_query($query) : '')) ?>"<?= $show === $value ? ' aria-current="page"' : '' ?>><?= e(t($key)) ?></a>
<?php endforeach; ?>
            </nav>
            <span class="list-count"><?= e(t('media.count', ['count' => (string) count($pictures), 'size' => \App\Support\Bytes::human($bytes)])) ?></span>
        </div>

<?php if ($pictures === []): ?>
        <div class="empty-state">
            <p><?= e($search === '' ? t('media.empty') : t('media.search_none', ['term' => $search])) ?></p>
        </div>
<?php elseif ($rows === []): ?>
        <p class="hint"><?= e(t('media.show.none')) ?> <a href="<?= e(Url::admin('media')) ?>"><?= e(t('media.show.every')) ?></a></p>
<?php else: ?>
<?php require __DIR__ . '/table.php'; ?>
<?php if ($pages > 1):
    $at = static fn (int $n): string => Url::withQuery(Url::admin('media'), array_filter(['q' => $search, 'show' => $show, 'kind' => $kind, 'page' => $n > 1 ? (string) $n : '']));
    ?>
        <nav class="media-pager" aria-label="<?= e(t('media.pager')) ?>">
<?php if ($page > 1): ?>
            <a class="button button-secondary" href="<?= e($at($page - 1)) ?>" rel="prev"><?= e(t('media.newer')) ?></a>
<?php endif; ?>
            <span class="media-pager-where"><?= e(t('media.page_of', ['page' => (string) $page, 'pages' => (string) $pages, 'total' => (string) $total])) ?></span>
<?php if ($page < $pages): ?>
            <a class="button button-secondary" href="<?= e($at($page + 1)) ?>" rel="next"><?= e(t('media.older')) ?></a>
<?php endif; ?>
        </nav>
<?php endif; ?>
<?php endif; ?>

        <?php /* The size limits travel as data attributes rather than inline script: the
                 admin sends default-src 'self' with no 'unsafe-inline', so a <script> in
                 this page would be dropped and the check would silently never run.

                 media.js refuses an oversized file before the form is submitted. That is
                 the only place it CAN be refused readably: nginx answers a body over
                 client_max_body_size with its own 413 page before PHP runs at all.

                 One compact row under the table (D-052): the whole screen takes a drop
                 (data-drop-anywhere), and this row says so and what is accepted. */ ?>
        <form class="media-upload media-upload-row" method="post" action="<?= e(Url::admin('media')) ?>" enctype="multipart/form-data"
              data-media-upload data-drop-anywhere
              data-max-file="<?= $limits['file'] ?>"
              data-max-request="<?= $limits['request'] ?>"
              data-file-label="<?= e($limits['fileLabel']) ?>"
              data-request-label="<?= e($limits['requestLabel']) ?>"
              data-too-large="<?= e(t('media.too_large_named')) ?>"
              data-too-large-total="<?= e(t('media.too_large_total')) ?>"
              data-uploading="<?= e(t('media.uploading')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <?php /* The zone is the file input's label, so a click anywhere in it opens the
                     file chooser without a script. */ ?>
            <label class="dropzone" for="media-files" data-dropzone>
                <?= icon('cloud-upload') ?>
                <span class="dropzone-text"><?= e(t('media.drop_anywhere')) ?> <span class="dropzone-browse"><?= e(t('media.browse')) ?></span></span>
                <span class="dropzone-limits"><?= e(t('media.limits', ['file' => $limits['fileLabel'], 'request' => $limits['requestLabel']])) ?></span>
            </label>
            <input type="file" id="media-files" name="files[]" multiple class="visually-hidden"
                   accept="<?= e($accept) ?>" data-media-input>
            <p class="field-error" data-media-error role="alert" hidden></p>
            <?php /* Only without a script, where nothing uploads by itself. */ ?>
            <button type="submit" class="button no-js-only"><?= e(t('media.upload_submit')) ?></button>
        </form>

<?php if ($pictures !== []): ?>

<?php require __DIR__ . '/remake.php'; ?>
<?php endif; ?>
