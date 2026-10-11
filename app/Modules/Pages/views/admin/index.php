<?php

use App\Support\Url;

/**
 * Provided by AdminView::render().
 *
 * @var list<array{id: int, locale: string, slug: string, title: string, status: string, updated: string, parent: int, depth: int, first: bool, last: bool}> $pages the rows the filters leave
 * @var int $total every page there is
 * @var string $lang the language chosen, '' for all
 * @var string $query the words searched for, '' for none
 * @var list<string> $codes the site's languages
 * @var array<string, string> $localeLabels code => label
 * @var array<int, int> $stale translations with blocks behind their source: page id => how many
 * @var string $zone the site's time zone, for the Last edited column
 * @var array{id: int, parent: int|null, position: int}|null $undo where the page just placed was (D-133)
 * @var int $maxLevels how deep the tree may go (PagePlacing::MAX_LEVELS)
 * @var string $csrf
 */
// The page above each row among its own siblings: what → places it under (D-133). The list
// is the tree in order, so it is the last row seen in the same group.
$above = [];
$seen = [];
foreach ($pages as $row) {
    $key = $row['locale'] . ':' . $row['parent'];
    $above[$row['id']] = $seen[$key] ?? null;
    $seen[$key] = $row;
}
?>
        <div class="page-header">
            <h1><?= e(t('pages.title')) ?></h1>
            <a class="button" href="<?= e(Url::admin('pages', 'new')) ?>"><?= e(t('pages.new')) ?></a>
        </div>
<?php if ($total === 0): ?>
        <div class="empty-state">
            <p><?= e(t('pages.empty')) ?></p>
            <a class="button" href="<?= e(Url::admin('pages', 'new')) ?>"><?= e(t('pages.new')) ?></a>
        </div>
<?php else: ?>
        <p class="page-subtitle"><?= e(t('pages.order_hint')) ?></p>
<?php if ($undo !== null): ?>
        <?php /* The move just made, undone by the same request naming where the page was. */ ?>
        <form method="post" action="<?= e(Url::admin('pages', $undo['id'], 'place')) ?>" class="page-undo">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="parent" value="<?= e($undo['parent'] === null ? '' : (string) $undo['parent']) ?>">
            <input type="hidden" name="position" value="<?= e((string) $undo['position']) ?>">
            <input type="hidden" name="undo" value="1">
<?php if ($lang !== ''): ?>
            <input type="hidden" name="lang" value="<?= e($lang) ?>">
<?php endif; ?>
            <button type="submit" class="button button-secondary"><?= icon('undo-2') ?> <?= e(t('pages.place.undo')) ?></button>
        </form>
<?php endif; ?>
        <?php /* The filters: words in the title or address, and a language. A plain GET
                 form and plain links, so each view has an address of its own. */ ?>
        <div class="list-filters">
            <form method="get" action="<?= e(Url::admin('pages')) ?>" class="list-search" role="search">
<?php if ($lang !== ''): ?>
                <input type="hidden" name="lang" value="<?= e($lang) ?>">
<?php endif; ?>
                <label for="pages-q" class="visually-hidden"><?= e(t('pages.filter')) ?></label>
                <input type="search" id="pages-q" name="q" value="<?= e($query) ?>" placeholder="<?= e(t('pages.filter')) ?>">
                <button type="submit" class="button button-secondary"><?= e(t('pages.filter_button')) ?></button>
            </form>
<?php if (count($codes) > 1): ?>
            <nav class="segmented" aria-label="<?= e(t('pages.col.locale')) ?>">
                <a href="<?= e(Url::admin('pages') . ($query !== '' ? '?' . http_build_query(['q' => $query]) : '')) ?>"<?= $lang === '' ? ' aria-current="page"' : '' ?>><?= e(t('pages.all_languages')) ?></a>
<?php foreach ($codes as $code): ?>
                <a href="<?= e(Url::admin('pages') . '?' . http_build_query(['lang' => $code] + ($query !== '' ? ['q' => $query] : []))) ?>"<?= $lang === $code ? ' aria-current="page"' : '' ?> title="<?= e($localeLabels[$code] ?? $code) ?>"><?= e(strtoupper($code)) ?></a>
<?php endforeach; ?>
            </nav>
<?php endif; ?>
            <span class="list-count"><?= e(t('pages.rows', ['count' => (string) count($pages), 'total' => (string) $total])) ?></span>
        </div>
<?php if ($pages === []): ?>
        <p class="hint"><?= e(t('pages.none_match')) ?> <a href="<?= e(Url::admin('pages')) ?>"><?= e(t('pages.show_all')) ?></a></p>
<?php else: ?>
        <?php /* The drag writes where the page landed into this form — under which page,
                 and where among the pages there — points it at that page's place route and
                 submits it: the request → and ← and Undo make (D-133). No fetch, and the
                 router's CSRF check covers it. */ ?>
        <form method="post" action="" data-page-place>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<?php if ($lang !== ''): ?>
            <input type="hidden" name="lang" value="<?= e($lang) ?>">
<?php endif; ?>
            <input type="hidden" name="parent" value="">
            <input type="hidden" name="position" value="">
        </form>
<?php
    $bulkId = 'pages-bulk';
    $bulkAction = Url::admin('pages', 'delete');
    $bulkWords = 'pages.bulk';
    $bulkKeep = ['lang' => $lang, 'q' => $query];
    require dirname(__DIR__, 3) . '/Admin/views/bulk.php';
?>
        <div class="table-wrap">
            <table class="table page-tree">
                <thead>
                    <tr>
                        <th scope="col" class="col-pick"><input type="checkbox" class="js-only" data-bulk-all form="pages-bulk" aria-label="<?= e(t('pages.bulk.all')) ?>" title="<?= e(t('pages.bulk.all')) ?>"></th>
                        <th scope="col" class="col-order"><span class="visually-hidden"><?= e(t('pages.col.order')) ?></span></th>
                        <th scope="col"><?= e(t('pages.col.title')) ?></th>
                        <th scope="col" class="col-address"><?= e(t('pages.col.address')) ?></th>
                        <th scope="col" class="col-lang"><?= e(t('pages.col.lang')) ?></th>
                        <th scope="col" class="col-status"><?= e(t('pages.col.status')) ?></th>
                        <th scope="col" class="col-date"><?= e(t('pages.col.updated')) ?></th>
                        <th scope="col" class="col-menu"><span class="visually-hidden"><?= e(t('pages.col.actions')) ?></span></th>
                    </tr>
                </thead>
                <tbody data-page-rows data-max-levels="<?= e((string) $maxLevels) ?>">
<?php foreach ($pages as $page): ?>
<?php
    $id = $page['id'];
    $published = $page['status'] === 'published';
    $address = Url::page($page['locale'], $page['slug']);
    // Siblings share a group: a drag may only reorder rows within one, and Up and Down
    // stop at its ends. Keyed on the parent, not the depth — two pages under different
    // parents sit at the same depth, and keying on depth let a drag carry a child into
    // another parent's rows, which the server then refused (PLAN.md D-011).
    $group = $page['locale'] . ':' . $page['parent'];
?>
                    <?php /* What the drag reads (pages.js, D-133): the level, the language, the
                             parent, the title it names as a parent, whether it is a home page,
                             which takes no subpages, and where to send the page. */ ?>
                    <tr data-page-id="<?= $id ?>" data-page-group="<?= e($group) ?>" data-depth="<?= e((string) $page['depth']) ?>" data-locale="<?= e($page['locale']) ?>" data-parent="<?= $page['parent'] > 0 ? e((string) $page['parent']) : '' ?>" data-title="<?= e($page['title']) ?>"<?= $page['slug'] === '' ? ' data-home' : '' ?> data-place="<?= e(Url::admin('pages', $id, 'place')) ?>">
                        <td class="col-pick"><input type="checkbox" name="ids[]" value="<?= $id ?>" form="pages-bulk" data-bulk-pick aria-label="<?= e(t('pages.bulk.pick', ['title' => $page['title']])) ?>"></td>
                        <td class="page-order">
<?php if ($query === ''): ?>
                            <span class="drag-handle" data-page-handle aria-hidden="true"><?= icon('grip-vertical') ?></span>
                            <button type="submit" form="page-move-<?= $id ?>" name="move" value="up" title="<?= e(t('pages.move_up')) ?>" class="button button-ghost move-button"<?= $page['first'] ? ' disabled' : '' ?>>
                                <span class="visually-hidden"><?= e(t('pages.move_up')) ?></span><?= icon('arrow-up') ?>
                            </button>
                            <button type="submit" form="page-move-<?= $id ?>" name="move" value="down" title="<?= e(t('pages.move_down')) ?>" class="button button-ghost move-button"<?= $page['last'] ? ' disabled' : '' ?>>
                                <span class="visually-hidden"><?= e(t('pages.move_down')) ?></span><?= icon('arrow-down') ?>
                            </button>
<?php
    // → : under the page above it, where there is one, it is not a home page, and a level
    //     is left; ← : out one level. The server holds every rule besides (PagePlacing).
    $into = $above[$id] ?? null;
    $canIn = $into !== null && $into['slug'] !== '' && $page['depth'] + 1 < $maxLevels;
?>
                            <button type="submit" form="page-place-<?= $id ?>" name="to" value="out" title="<?= e(t('pages.place.out')) ?>" class="button button-ghost move-button"<?= $page['depth'] > 0 ? '' : ' disabled' ?>>
                                <span class="visually-hidden"><?= e(t('pages.place.out')) ?></span><?= icon('arrow-left') ?>
                            </button>
                            <button type="submit" form="page-place-<?= $id ?>" name="to" value="in" title="<?= e($into === null ? t('pages.place.in') : t('pages.place.in_under', ['parent' => $into['title']])) ?>" class="button button-ghost move-button"<?= $canIn ? '' : ' disabled' ?>>
                                <span class="visually-hidden"><?= e(t('pages.place.in')) ?></span><?= icon('arrow-right') ?>
                            </button>
<?php endif; ?>
                        </td>
                        <?php /* A class, not style="--depth: n": the admin sends
                                 default-src 'self' with no 'unsafe-inline', so a style
                                 attribute is blocked and every child would render flush
                                 with its parent. Capped at the depth the stylesheet
                                 declares; deeper pages stop indenting rather than
                                 marching off the column. */ ?>
                        <td class="page-name depth-<?= min($page['depth'], 6) ?>">
                            <a href="<?= e(Url::admin('pages', $id)) ?>"><?= e($page['title']) ?></a>
                            <span class="page-landing" data-landing data-under="<?= e(t('pages.place.landing_under')) ?>" data-top="<?= e(t('pages.place.landing_top')) ?>"></span>
<?php if ($page['slug'] === ''): ?>
                            <span class="badge badge-edge"><?= e(t('pages.home_badge')) ?></span>
<?php endif; ?>
<?php if (isset($stale[$id])): ?>
                            <span class="badge badge-warning" title="<?= e(t('translations.stale_badge_hint')) ?>"><?= e(t('translations.stale_badge', ['count' => (string) $stale[$id]])) ?></span>
<?php endif; ?>
                        </td>
                        <td class="address"><?= e($address) ?></td>
                        <td class="lang"><abbr title="<?= e($localeLabels[$page['locale']] ?? $page['locale']) ?>"><?= e(strtoupper($page['locale'])) ?></abbr></td>
                        <?php /* THE STATUS IS THE SWITCH (D-039): pressing "Published" makes
                                 the page a draft, pressing "Draft" publishes it. A pill with an
                                 edge, so it reads as something to press at rest; the title and
                                 the hidden words say what pressing it does. */ ?>
                        <td>
                            <form method="post" action="<?= e(Url::admin('pages', $id, 'status')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="status" value="<?= $published ? 'draft' : 'published' ?>">
                                <button type="submit" class="status status-<?= e($page['status']) ?> status-toggle" title="<?= e(t($published ? 'pages.unpublish_hint' : 'pages.publish_hint')) ?>">
                                    <?= e(t('pages.status.' . $page['status'])) ?><span class="visually-hidden"> — <?= e(t($published ? 'pages.unpublish' : 'pages.publish')) ?></span>
                                </button>
                            </form>
                        </td>
                        <td class="date"><?= e(\App\Support\Dates::local($page['updated'], $zone)) ?></td>
                        <?php /* The rest behind a menu (D-052): a <details>, so it opens without a
                                 script, and its button is a drawn shape at rest. Deleting is
                                 still a real form, and still asks first. */ ?>
                        <td class="row-menu-cell">
                            <details class="row-menu" data-menu>
                                <summary title="<?= e(t('pages.more', ['title' => $page['title']])) ?>"><?= icon('ellipsis-vertical') ?><span class="visually-hidden"><?= e(t('pages.more', ['title' => $page['title']])) ?></span></summary>
                                <div class="row-menu-list">
<?php if ($published): ?>
                                    <a href="<?= e($address) ?>" target="_blank" rel="noopener"><?= icon('external-link') ?> <?= e(t('pages.view_on_site')) ?></a>
<?php endif; ?>
                                    <form method="post" action="<?= e(Url::admin('pages', $id, 'delete')) ?>">
                                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                        <button type="submit" class="row-menu-danger" data-confirm="<?= e(t('pages.delete_confirm', ['title' => $page['title']])) ?>"><?= icon('trash-2') ?> <?= e(t('pages.delete')) ?></button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php /* One form per row, outside the table: a form element cannot sit between a
                 tbody and a tr, so the buttons reach it by id instead. */ ?>
<?php foreach ($pages as $page): ?>
        <form method="post" action="<?= e(Url::admin('pages', 'order')) ?>" id="page-move-<?= $page['id'] ?>" class="visually-hidden">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= $page['id'] ?>">
        </form>
        <form method="post" action="<?= e(Url::admin('pages', $page['id'], 'place')) ?>" id="page-place-<?= $page['id'] ?>" class="visually-hidden">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<?php if ($lang !== ''): ?>
            <input type="hidden" name="lang" value="<?= e($lang) ?>">
<?php endif; ?>
        </form>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
