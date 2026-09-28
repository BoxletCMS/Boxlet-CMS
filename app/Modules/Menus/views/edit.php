<?php

use App\Support\Url;

/**
 * One menu: its items in order, and the form that adds another.
 *
 * @var array<string, mixed> $menu
 * @var list<array{id: int, parent_id: int|null, depth: int, label: string, target: string,
 *                 page_id: int|null, page_title: string|null, published: bool, broken: bool,
 *                 hidden: bool, first: bool, last: bool}> $items
 * @var list<array{id: int, title: string, status: string, url: string}> $pages
 * @var array<string, string> $errors
 * @var int $editing the item whose edit dialog is open on arrival, 0 for none
 * @var string $title
 * @var string $csrf
 */
$menuId = (int) $menu['id'];
$error = static fn (string $key): string => isset($errors[$key])
    ? '<p class="field-error" role="alert">' . e($errors[$key]) . '</p>'
    : '';

/**
 * The page chooser, shared by the add form and every item's edit form: each option carries
 * its address and title, which admin.js fills in when it is chosen (D-038).
 */
$pageOptions = static function (?int $chosen) use ($pages): string {
    $html = '<option value="">' . e(t('menus.item.page_none')) . '</option>';
    foreach ($pages as $page) {
        $html .= '<option value="' . e($page['id']) . '" data-url="' . e($page['url']) . '" data-title="' . e($page['title']) . '"'
            . ($chosen === $page['id'] ? ' selected' : '') . '>'
            . e($page['title']) . ($page['status'] === 'published' ? '' : ' — ' . e(t('menus.item.draft'))) . '</option>';
    }

    return $html;
};

/** Top-level items only: one level of submenu, so only these may hold children (D-028). */
$parents = array_values(array_filter($items, static fn (array $item): bool => $item['parent_id'] === null));
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
            <span class="status"><?= e($menu['locale']) ?></span>
            <a class="button button-secondary" href="<?= e(Url::admin('menus')) ?>"><?= e(t('menus.title')) ?></a>
        </div>

        <div class="panel">
            <form method="post" action="<?= e(Url::admin('menus', $menuId, 'rename')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="menu-name"><?= e(t('menus.name')) ?></label>
                    <?php /* The name and its button on one line: one field, one action. */ ?>
                    <div class="field-inline">
                        <input type="text" id="menu-name" name="name" maxlength="190" value="<?= e($menu['name']) ?>" required aria-describedby="menu-name-hint">
                        <?php /* A verb, not the field's own noun. "Name" on a button says what
                                 the thing beside it is called, never what pressing it does. */ ?>
                        <button type="submit" class="button button-secondary"><?= e(t('menus.rename')) ?></button>
                    </div>
                    <span class="hint" id="menu-name-hint"><?= e(t('menus.name_edit_hint')) ?></span>
                    <?= $error('name') ?>
                </div>
            </form>
        </div>

<?php if ($items !== []): ?>
        <p class="hint"><?= e(t('menus.order_hint')) ?></p>
        <?php /* The drag writes the new sibling order into this form and submits it, so the
                 request a drag makes is the request the buttons make. No fetch, and the
                 router's CSRF check covers both (D-011, the same shape the page list uses). */ ?>
        <form method="post" action="<?= e(Url::admin('menus', $menuId, 'order')) ?>" data-menu-order>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="order" value="">
        </form>
        <div class="table-wrap">
            <table class="table page-tree">
                <thead>
                    <tr>
                        <th scope="col"><span class="visually-hidden"><?= e(t('menus.col.order')) ?></span></th>
                        <th scope="col"><?= e(t('menus.col.label')) ?></th>
                        <th scope="col"><?= e(t('menus.col.target')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= e(t('menus.col.actions')) ?></span></th>
                    </tr>
                </thead>
                <tbody data-menu-rows>
<?php foreach ($items as $item): ?>
<?php $group = $item['parent_id'] === null ? 'top' : 'child-' . $item['parent_id']; ?>
                    <tr data-menu-item="<?= e($item['id']) ?>" data-menu-group="<?= e($group) ?>">
                        <td class="page-order">
                            <span class="drag-handle" data-menu-handle aria-hidden="true"><?= icon('grip-vertical') ?></span>
                            <button type="submit" form="menu-move-<?= e($item['id']) ?>" name="move" value="up" title="<?= e(t('menus.move_up')) ?>"
                                    class="button button-ghost move-button"<?= $item['first'] ? ' disabled' : '' ?>><?= icon('arrow-up') ?><span class="visually-hidden"><?= e(t('menus.move_up')) ?></span></button>
                            <button type="submit" form="menu-move-<?= e($item['id']) ?>" name="move" value="down" title="<?= e(t('menus.move_down')) ?>"
                                    class="button button-ghost move-button"<?= $item['last'] ? ' disabled' : '' ?>><?= icon('arrow-down') ?><span class="visually-hidden"><?= e(t('menus.move_down')) ?></span></button>
                        </td>
                        <td class="depth-<?= e($item['depth']) ?>"><?= e($item['label']) ?></td>
                        <td>
<?php if ($item['broken']): ?>
                            <?php /* A badge, not a notice. notice-warning is a full-width
                                     block meant for the top of a screen; inside a table cell
                                     it swallowed the row and pushed the label out of line
                                     with it. Same size as the draft badge beside it. */ ?>
                            <span class="status status-draft"><?= e(t('menus.item.broken')) ?></span>
                            <span class="hint"><?= e(t('menus.item.broken_hint')) ?></span>
<?php else: ?>
                            <span class="address"><?= e($item['target']) ?></span>
<?php /* ONLY when there is a page. An address is neither published nor a draft, and the
         first version of this row put a "draft" badge on every plain URL. */ ?>
<?php if ($item['page_id'] !== null && !$item['published']): ?>
                            <span class="status status-draft"><?= e(t('menus.item.draft')) ?></span>
                            <span class="hint"><?= e(t('menus.item.draft_hint')) ?></span>
<?php endif; ?>
<?php endif; ?>
                        </td>
                        <td class="row-actions">
                            <?php /* Editing opens in a dialog over the list (D-039): the row stays
                                     as it is. A link, so it works without a script too — the
                                     server then draws the dialog already open; menus.js opens
                                     it in place instead. */ ?>
                            <a class="button button-ghost" href="<?= e(Url::admin('menus', $menuId)) ?>?edit=<?= e($item['id']) ?>" data-dialog-open="item-dialog-<?= e($item['id']) ?>"><?= icon('pencil') ?> <?= e(t('menus.item.edit')) ?></a>
                            <form method="post" action="<?= e(Url::admin('menus', $menuId, 'items', $item['id'], 'delete')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <button type="submit" class="button button-ghost button-danger"><?= icon('trash-2') ?> <?= e(t('menus.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php /* One form per row, outside the table so a form element cannot land between <tr>s,
         referenced by the buttons' form attribute. The same arrangement the page list uses. */ ?>
<?php foreach ($items as $item): ?>
        <?php /* One dialog per item, outside the table like the move forms. `open` when the
                 page was asked for with ?edit=, which is the no-script path; the X and Cancel
                 close it without a script too, as a method="dialog" form does. */ ?>
        <dialog class="dialog" id="item-dialog-<?= e($item['id']) ?>" aria-labelledby="item-dialog-<?= e($item['id']) ?>-title"<?= $editing === $item['id'] ? ' open' : '' ?>>
            <div class="dialog-head">
                <h2 id="item-dialog-<?= e($item['id']) ?>-title"><?= e(t('menus.item.edit_title', ['label' => $item['label']])) ?></h2>
                <form method="dialog">
                    <button type="submit" class="button button-ghost button-icon" title="<?= e(t('menus.item.cancel')) ?>"><?= icon('x') ?><span class="visually-hidden"><?= e(t('menus.item.cancel')) ?></span></button>
                </form>
            </div>
            <form method="post" action="<?= e(Url::admin('menus', $menuId, 'items', $item['id'])) ?>" class="stack" data-link>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="item-<?= e($item['id']) ?>-page"><?= e(t('menus.item.page')) ?></label>
                    <select id="item-<?= e($item['id']) ?>-page" name="page_id" data-link-page><?= $pageOptions($item['page_id']) ?></select>
                    <?= field_hint('menus.item.page_hint') ?>
                </div>
                <div class="field">
                    <label for="item-<?= e($item['id']) ?>-url"><?= e(t('menus.item.url')) ?></label>
                    <input type="text" id="item-<?= e($item['id']) ?>-url" name="url" maxlength="2048" data-link-address
                           value="<?= e($item['target']) ?>"<?= $item['page_id'] !== null ? ' readonly' : '' ?>>
                </div>
                <div class="field">
                    <label for="item-<?= e($item['id']) ?>-label"><?= e(t('menus.item.label')) ?></label>
                    <input type="text" id="item-<?= e($item['id']) ?>-label" name="label" maxlength="255" data-link-label value="<?= e($item['label']) ?>">
                </div>
                <div class="form-actions">
                    <button type="submit" class="button"><?= e(t('menus.item.save')) ?></button>
                    <a class="button button-ghost" href="<?= e(Url::admin('menus', $menuId)) ?>" data-dialog-close><?= e(t('menus.item.cancel')) ?></a>
                </div>
            </form>
        </dialog>
<?php endforeach; ?>
<?php foreach ($items as $item): ?>
        <form method="post" action="<?= e(Url::admin('menus', $menuId, 'order')) ?>" id="menu-move-<?= e($item['id']) ?>" class="visually-hidden">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="item" value="<?= e($item['id']) ?>">
        </form>
<?php endforeach; ?>
<?php endif; ?>

        <div class="panel stack">
            <h2><?= e(t('menus.item.add')) ?></h2>
            <form method="post" action="<?= e(Url::admin('menus', $menuId, 'items')) ?>" class="stack" data-link>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="item-page"><?= e(t('menus.item.page')) ?></label>
                    <select id="item-page" name="page_id" data-link-page aria-describedby="item-page-hint"><?= $pageOptions(null) ?></select>
                    <span class="hint" id="item-page-hint"><?= e(t('menus.item.page_hint')) ?></span>
                </div>
                <div class="field">
                    <label for="item-url"><?= e(t('menus.item.url')) ?></label>
                    <input type="text" id="item-url" name="url" maxlength="2048" data-link-address aria-describedby="item-url-hint">
                    <span class="hint" id="item-url-hint"><?= e(t('menus.item.url_hint')) ?></span>
                </div>
                <div class="field">
                    <label for="item-label"><?= e(t('menus.item.label')) ?></label>
                    <input type="text" id="item-label" name="label" maxlength="255" data-link-label aria-describedby="item-label-hint">
                    <span class="hint" id="item-label-hint"><?= e(t('menus.item.label_hint')) ?></span>
                </div>
                <div class="field">
                    <label for="item-parent"><?= e(t('menus.item.parent')) ?></label>
                    <select id="item-parent" name="parent_id">
                        <option value=""><?= e(t('menus.item.parent_top')) ?></option>
<?php foreach ($parents as $parent): ?>
                        <option value="<?= e($parent['id']) ?>"><?= e($parent['label']) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <?= $error('item') ?>
                <button type="submit" class="button"><?= e(t('menus.item.add')) ?></button>
            </form>
        </div>
