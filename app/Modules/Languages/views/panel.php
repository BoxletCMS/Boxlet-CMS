<?php

use App\Support\Url;

/**
 * The Languages panel on the Settings screen (PLAN.md D-043), with forms of its own: it
 * sits outside the settings form because HTML has no nested forms, as maintenance does.
 *
 * The status is the switch, as on the pages list (D-039): pressing "On" switches the
 * language off. The primary language has no switch and no order buttons: it is always
 * first, always on, and fixed at install.
 *
 * @var list<array{code: string, label: string, primary: bool, enabled: bool, pages: int}> $languages
 * @var array<string, string> $addable code => name, the languages not yet on the site
 * @var string $csrf
 */
$others = array_values(array_filter($languages, static fn (array $l): bool => !$l['primary']));
$lastOther = $others === [] ? '' : $others[count($others) - 1]['code'];
$firstOther = $others === [] ? '' : $others[0]['code'];
?>
        <div class="panel stack" id="languages">
            <h2><?= e(t('languages.title')) ?></h2>
            <p class="hint"><?= e(t('languages.intro')) ?></p>
            <div class="table-wrap">
                <table class="table languages-table">
                    <thead>
                        <tr>
                            <th scope="col"><span class="visually-hidden"><?= e(t('languages.col.order')) ?></span></th>
                            <th scope="col"><?= e(t('languages.col.language')) ?></th>
                            <th scope="col"><?= e(t('languages.col.code')) ?></th>
                            <th scope="col"><?= e(t('languages.col.pages')) ?></th>
                            <th scope="col"><?= e(t('languages.col.status')) ?></th>
                            <th scope="col"><span class="visually-hidden"><?= e(t('languages.col.actions')) ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach ($languages as $language): ?>
<?php $code = $language['code']; ?>
                        <tr>
                            <td class="page-order">
<?php if (!$language['primary']): ?>
                                <button type="submit" form="language-move-<?= e($code) ?>" name="move" value="up" title="<?= e(t('languages.move_up')) ?>" class="button button-ghost move-button"<?= $code === $firstOther ? ' disabled' : '' ?>>
                                    <span class="visually-hidden"><?= e(t('languages.move_up')) ?></span><?= icon('arrow-up') ?>
                                </button>
                                <button type="submit" form="language-move-<?= e($code) ?>" name="move" value="down" title="<?= e(t('languages.move_down')) ?>" class="button button-ghost move-button"<?= $code === $lastOther ? ' disabled' : '' ?>>
                                    <span class="visually-hidden"><?= e(t('languages.move_down')) ?></span><?= icon('arrow-down') ?>
                                </button>
<?php endif; ?>
                            </td>
                            <td class="language-name" lang="<?= e($code) ?>">
                                <?= e($language['label']) ?>
<?php if ($language['primary']): ?>
                                <span class="badge"><?= e(t('languages.primary')) ?></span>
<?php endif; ?>
                            </td>
                            <td><code><?= e($code) ?></code></td>
                            <td><?= e((string) $language['pages']) ?></td>
                            <td>
<?php if ($language['primary']): ?>
                                <span class="hint"><?= e(t('languages.always_on')) ?></span>
<?php else: ?>
                                <form method="post" action="<?= e(Url::admin('languages', $code, 'enabled')) ?>">
                                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="enabled" value="<?= $language['enabled'] ? '0' : '1' ?>">
                                    <button type="submit" class="status <?= $language['enabled'] ? 'status-published' : 'status-draft' ?> status-toggle" title="<?= e(t($language['enabled'] ? 'languages.switch_off_hint' : 'languages.switch_on_hint')) ?>">
                                        <?= e(t($language['enabled'] ? 'languages.on' : 'languages.off')) ?><span class="visually-hidden"> — <?= e(t($language['enabled'] ? 'languages.switch_off' : 'languages.switch_on')) ?></span>
                                    </button>
                                </form>
<?php endif; ?>
                            </td>
                            <td class="row-actions">
<?php if (!$language['primary'] && $language['pages'] === 0): ?>
                                <form method="post" action="<?= e(Url::admin('languages', $code, 'delete')) ?>">
                                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                    <button type="submit" class="button button-ghost button-danger button-icon" title="<?= e(t('languages.remove')) ?>" data-confirm="<?= e(t('languages.remove_confirm', ['language' => $language['label']])) ?>"><?= icon('trash-2') ?><span class="visually-hidden"><?= e(t('languages.remove')) ?></span></button>
                                </form>
<?php endif; ?>
                            </td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
<?php foreach ($others as $language): ?>
            <form method="post" action="<?= e(Url::admin('languages', $language['code'], 'move')) ?>" id="language-move-<?= e($language['code']) ?>" class="visually-hidden">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            </form>
<?php endforeach; ?>

<?php if ($addable !== []): ?>
            <form method="post" action="<?= e(Url::admin('languages')) ?>" class="language-add">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="language-code"><?= e(t('languages.add_label')) ?></label>
                    <div class="language-add-row">
                        <select id="language-code" name="code" aria-describedby="language-code-hint" required>
                            <option value="" selected disabled><?= e(t('languages.choose')) ?></option>
<?php foreach ($addable as $code => $name): ?>
                            <option value="<?= e($code) ?>"><?= e($name) ?> (<?= e($code) ?>)</option>
<?php endforeach; ?>
                        </select>
                        <button type="submit" class="button button-secondary"><?= e(t('languages.add')) ?></button>
                    </div>
                    <span class="hint" id="language-code-hint"><?= e(t('languages.add_hint')) ?></span>
                </div>
            </form>
<?php endif; ?>
        </div>
