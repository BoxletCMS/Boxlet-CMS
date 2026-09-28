<?php

use App\Support\Url;

/**
 * The library as a table (PLAN.md D-052): a grid shows pictures, a table shows which of them
 * need something — no description, used nowhere, not finished. Required by admin/index.php.
 * The picker keeps the grid (admin/cards.php): choosing a picture is choosing by eye.
 *
 * @var list<array{id: int, filename: string, ext: string, size: string, width: int, height: int, complete: bool, thumb: string|null, kind: string, downloads: int, download: string, pages: int, site: bool, described: string}> $rows
 * @var string $csrf
 */
?>
        <div class="table-wrap">
            <table class="table media-table">
                <thead>
                    <tr>
                        <th scope="col" class="col-thumb"><span class="visually-hidden"><?= e(t('media.col.picture')) ?></span></th>
                        <th scope="col"><?= e(t('media.col.file')) ?></th>
                        <th scope="col" class="col-dimensions"><?= e(t('media.col.dimensions')) ?></th>
                        <th scope="col" class="col-size"><?= e(t('media.col.size')) ?></th>
                        <th scope="col" class="col-used"><?= e(t('media.col.used')) ?></th>
                        <th scope="col" class="col-described"><?= e(t('media.col.described')) ?></th>
                        <th scope="col" class="col-menu"><span class="visually-hidden"><?= e(t('media.col.actions')) ?></span></th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($rows as $row):
    $link = Url::admin('media', $row['id']);
    $name = $row['filename'] . ($row['ext'] !== '' ? '.' . $row['ext'] : '');
    ?>
                    <tr class="media-row" data-media-id="<?= e((string) $row['id']) ?>">
                        <td class="media-row-thumb">
<?php if ($row['kind'] === 'file'): ?>
                            <span class="media-row-file" aria-hidden="true"><?= icon(\App\Modules\Media\MediaFileType::icon($row['ext'])) ?><span class="media-row-file-ext"><?= e(strtoupper($row['ext'])) ?></span></span>
<?php elseif ($row['thumb'] !== null): ?>
                            <img class="media-thumb" src="<?= e($row['thumb']) ?>" alt="" width="72" height="54" loading="lazy">
<?php else: ?>
                            <span class="media-row-none" aria-hidden="true"></span>
<?php endif; ?>
                        </td>
                        <td class="row-title media-row-name">
                            <a class="media-link" href="<?= e($link) ?>"><span class="media-name"><?= e($row['filename']) ?></span><?= $row['ext'] !== '' ? '<span class="media-ext">.' . e($row['ext']) . '</span>' : '' ?></a>
<?php if (!$row['complete']): ?>
                            <span class="badge badge-warning"><?= e(t('media.unfinished')) ?></span>
<?php endif; ?>
                        </td>
<?php if ($row['kind'] === 'file'): ?>
                        <?php /* A file has no dimensions (D-126): what it is, and how often it
                                 was taken, instead. */ ?>
                        <td class="numeric media-facts"><?= e(strtoupper($row['ext'])) ?> · <?= e(t($row['downloads'] === 1 ? 'media.downloads_one' : 'media.downloads_many', ['count' => (string) $row['downloads']])) ?></td>
<?php else: ?>
                        <td class="numeric media-facts"><?= e(t('media.dimensions', ['width' => (string) $row['width'], 'height' => (string) $row['height']])) ?></td>
<?php endif; ?>
                        <td class="numeric media-facts"><?= e($row['size']) ?></td>
                        <td class="media-used">
<?php if ($row['pages'] > 0): ?>
                            <?= e(t($row['pages'] === 1 ? 'media.used_one' : 'media.used_many', ['count' => (string) $row['pages']])) ?><?= $row['site'] ? ' · ' . e(t('media.used_site')) : '' ?>
<?php elseif ($row['site']): ?>
                            <?= e(t('media.used_site')) ?>
<?php else: ?>
                            <span class="media-unused" title="<?= e(t('media.unused_hint')) ?>">—</span>
<?php endif; ?>
                        </td>
                        <td>
<?php if ($row['described'] === 'missing'): ?>
                            <a class="badge badge-accent" href="<?= e($link) ?>#meta"><?= e(t('media.described.missing')) ?></a>
<?php elseif ($row['described'] === 'none'): ?>
                            <span class="media-unused">—</span>
<?php else: ?>
                            <span class="media-described"><?= e(t('media.described.set')) ?></span>
<?php endif; ?>
                        </td>
                        <td class="row-menu-cell">
                            <details class="row-menu" data-menu>
                                <summary title="<?= e(t('media.more', ['name' => $name])) ?>"><?= icon('ellipsis-vertical') ?><span class="visually-hidden"><?= e(t('media.more', ['name' => $name])) ?></span></summary>
                                <div class="row-menu-list">
                                    <a href="<?= e($link) ?>"><?= icon('pencil') ?> <?= e(t('media.open')) ?></a>
<?php if (!$row['complete']): ?>
                                    <form method="post" action="<?= e(Url::admin('media', $row['id'], 'finish')) ?>">
                                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                        <button type="submit"><?= icon('check') ?> <?= e(t('media.finish')) ?></button>
                                    </form>
<?php endif; ?>
                                    <form method="post" action="<?= e(Url::admin('media', $row['id'], 'delete')) ?>">
                                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                        <button type="submit" class="row-menu-danger" data-confirm="<?= e(t('media.delete_confirm', ['name' => $row['filename']])) ?>"><?= icon('trash-2') ?> <?= e(t('media.delete')) ?></button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
