<?php

use App\Support\Url;

/**
 * The list of menus, and the form that starts another: the foot of Navigation (D-180).
 * Required by navigation.php, in its scope.
 *
 * @var list<array{id: int, locale: string, name: string, items: int}> $menus
 * @var list<array<string, mixed>> $locales
 * @var array<string, string> $menuErrors
 * @var string $mainLocale the main language's code, the one other languages' menus are matched with (D-262)
 * @var string $csrf
 * @var Closure(string, string): string $hint
 */
$menuError = static fn (string $key): string => isset($menuErrors[$key])
    ? '<p class="field-error" role="alert">' . e($menuErrors[$key]) . '</p>'
    : '';
?>
        <section class="stack navigation-menus" aria-labelledby="navigation-menus-title">
        <h2 id="navigation-menus-title"><?= e(t('menus.title')) ?></h2>
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
                        <td><?= e(tn('menus.items_count', (int) $menu['items'])) ?></td>
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

<?php
    // MATCHING ANOTHER LANGUAGE'S MENUS (D-262): one button per language beside the main one.
    $mainLabel = '';
    foreach ($locales as $locale) {
        if ((string) $locale['code'] === $mainLocale) {
            $mainLabel = (string) $locale['label'];
        }
    }
    $others = array_values(array_filter($locales, static fn (array $locale): bool => (string) $locale['code'] !== $mainLocale));
?>
<?php if ($mainLocale !== '' && $others !== []): ?>
        <div class="panel stack">
            <h3><?= e(t('menus.match_title')) ?></h3>
            <?= $hint('menus-match-hint', t('menus.match_intro', ['main' => $mainLabel])) ?>
            <div class="form-actions">
<?php foreach ($others as $locale): ?>
                <form method="post" action="<?= e(Url::admin('navigation', 'match')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="locale" value="<?= e((string) $locale['code']) ?>">
                    <button type="submit" class="button button-secondary"><?= e(t('menus.match', ['language' => (string) $locale['label'], 'main' => $mainLabel])) ?></button>
                </form>
<?php endforeach; ?>
            </div>
        </div>
<?php endif; ?>

        <div class="panel stack">
            <h3><?= e(t('menus.new')) ?></h3>
            <form method="post" action="<?= e(Url::admin('menus')) ?>" class="stack">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="menu-name"><?= e(t('menus.name')) ?></label>
                    <input type="text" id="menu-name" name="name" maxlength="190" required
                           aria-describedby="menu-name-hint">
                    <?= $hint('menu-name-hint', t('menus.name_hint')) ?>
                    <?= $menuError('name') ?>
                </div>
                <div class="field">
                    <label for="menu-locale"><?= e(t('menus.locale')) ?></label>
                    <select id="menu-locale" name="locale" aria-describedby="menu-locale-hint">
<?php foreach ($locales as $locale): ?>
                        <option value="<?= e($locale['code']) ?>"><?= e($locale['label']) ?></option>
<?php endforeach; ?>
                    </select>
                    <?= $hint('menu-locale-hint', t('hint.menus.locale')) ?>
                    <?= $menuError('locale') ?>
                </div>
                <button type="submit" class="button"><?= e(t('menus.create')) ?></button>
            </form>
        </div>
        </section>
