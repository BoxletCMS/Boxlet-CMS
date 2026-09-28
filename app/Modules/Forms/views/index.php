<?php

use App\Support\Url;

/**
 * The list of forms, and the form that starts another (PLAN.md D-046).
 *
 * @var list<array{id: int, locale: string, name: string, fields: int, messages: int, unread: int}> $forms
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
        <p class="page-subtitle"><?= e(t('forms.intro')) ?></p>

<?php if ($forms === []): ?>
        <div class="empty-state">
            <p><?= e(t('forms.none')) ?></p>
        </div>
<?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(t('forms.col.name')) ?></th>
                        <th scope="col"><?= e(t('forms.col.locale')) ?></th>
                        <th scope="col"><?= e(t('forms.col.fields')) ?></th>
                        <th scope="col"><?= e(t('forms.col.messages')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= e(t('forms.col.actions')) ?></span></th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($forms as $form): ?>
                    <tr>
                        <td class="row-title"><a href="<?= e(Url::admin('forms', $form['id'])) ?>"><?= e($form['name']) ?></a></td>
                        <td><?= e($form['locale']) ?></td>
                        <td><?= e((string) $form['fields']) ?></td>
                        <td>
                            <a href="<?= e(Url::admin('forms', $form['id'], 'messages')) ?>"><?= e(t('forms.messages_count', ['count' => (string) $form['messages']])) ?></a>
<?php if ($form['unread'] > 0): ?>
                            <span class="badge badge-accent"><?= e(t('forms.unread_count', ['count' => (string) $form['unread']])) ?></span>
<?php endif; ?>
                        </td>
                        <td class="row-actions">
                            <form method="post" action="<?= e(Url::admin('forms', $form['id'], 'delete')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <button type="submit" class="button button-ghost button-danger button-icon" title="<?= e(t('forms.delete')) ?>"
                                        data-confirm="<?= e(t('forms.delete_confirm', ['name' => $form['name'], 'count' => (string) $form['messages']])) ?>"><?= icon('trash-2') ?><span class="visually-hidden"><?= e(t('forms.delete')) ?></span></button>
                            </form>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php endif; ?>

        <div class="panel stack">
            <h2><?= e(t('forms.new')) ?></h2>
            <form method="post" action="<?= e(Url::admin('forms')) ?>" class="stack">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="form-name"><?= e(t('forms.name')) ?></label>
                    <input type="text" id="form-name" name="name" maxlength="190" required
                           aria-describedby="form-name-hint">
                    <span class="hint" id="form-name-hint"><?= e(t('forms.name_hint')) ?></span>
                    <?= $error('name') ?>
                </div>
                <div class="field">
                    <label for="form-locale"><?= e(t('forms.locale')) ?></label>
                    <select id="form-locale" name="locale">
<?php foreach ($locales as $locale): ?>
                        <option value="<?= e($locale['code']) ?>"><?= e($locale['label']) ?></option>
<?php endforeach; ?>
                    </select>
                    <?= field_hint('hint.forms.locale') ?>
                    <?= $error('locale') ?>
                </div>
                <button type="submit" class="button"><?= e(t('forms.create')) ?></button>
            </form>
        </div>
