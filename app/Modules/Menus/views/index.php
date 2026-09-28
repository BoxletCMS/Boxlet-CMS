<?php

use App\Support\Url;

/**
 * The list of menus, and the form that starts another. Provided by AdminView::render().
 *
 * @var list<array{id: int, locale: string, name: string, items: int}> $menus
 * @var list<array<string, mixed>> $locales
 * @var array<string, string> $errors
 * @var string $title
 * @var string $csrf
 */
$error = static fn (string $key): string => isset($errors[$key])
    ? '<p class="field-error" role="alert">' . e($errors[$key]) . '</p>'
    : '';
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
        <p class="page-subtitle"><?= e(t('menus.intro')) ?></p>

<?php if ($menus === []): ?>
        <div class="empty-state">
            <p><?= e(t('menus.none')) ?></p>
        </div>
<?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(t('menus.col.name')) ?></th>
                        <th scope="col"><?= e(t('menus.col.locale')) ?></th>
                        <th scope="col"><?= e(t('menus.col.items')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= e(t('menus.col.actions')) ?></span></th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($menus as $menu): ?>
                    <tr>
                        <td class="row-title"><a href="<?= e(Url::admin('menus', $menu['id'])) ?>"><?= e($menu['name']) ?></a></td>
                        <td><?= e($menu['locale']) ?></td>
                        <td><?= e(t('menus.items_count', ['count' => (string) $menu['items']])) ?></td>
                        <td>
                            <form method="post" action="<?= e(Url::admin('menus', $menu['id'], 'delete')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <?php /* button-ghost, like every other delete in this admin: the danger ink
                                         on a filled accent measures 1.15:1, far under the 4.5:1 text rule. */ ?>
                                <button type="submit" class="button button-ghost button-danger"
                                        data-confirm="<?= e(t('menus.delete_confirm', ['name' => $menu['name']])) ?>"><?= e(t('menus.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php endif; ?>

        <div class="panel stack">
            <h2><?= e(t('menus.new')) ?></h2>
            <form method="post" action="<?= e(Url::admin('menus')) ?>" class="stack">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="menu-name"><?= e(t('menus.name')) ?></label>
                    <input type="text" id="menu-name" name="name" maxlength="190" required
                           aria-describedby="menu-name-hint">
                    <span class="hint" id="menu-name-hint"><?= e(t('menus.name_hint')) ?></span>
                    <?= $error('name') ?>
                </div>
                <div class="field">
                    <label for="menu-locale"><?= e(t('menus.locale')) ?></label>
                    <select id="menu-locale" name="locale">
<?php foreach ($locales as $locale): ?>
                        <option value="<?= e($locale['code']) ?>"><?= e($locale['label']) ?></option>
<?php endforeach; ?>
                    </select>
                    <?= field_hint('hint.menus.locale') ?>
                    <?= $error('locale') ?>
                </div>
                <button type="submit" class="button"><?= e(t('menus.create')) ?></button>
            </form>
        </div>
