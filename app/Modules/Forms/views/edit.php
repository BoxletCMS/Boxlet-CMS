<?php

use App\Modules\Forms\Form;
use App\Support\Url;

/**
 * A form's edit screen (PLAN.md D-046): its name, its fields in order, and what it does
 * when sent. One form element; every button posts it with an `action` and saves
 * (FormsController), so it works the same with or without a script. form-builder.js only
 * shows a field's list options when its type is a list.
 *
 * @var array{id: int, locale: string, name: string, fields: list<array{key: string, type: string, label: string, required: bool, options: list<string>}>, settings: array{submit: string, success: string, notify: bool, autoreply: bool, autoreply_subject: string, autoreply_body: string}} $form
 * @var string $language the form's language, by name
 * @var array<string, string> $errors
 * @var string $csrf
 */
$error = static fn (string $key): string => isset($errors[$key])
    ? '<p class="field-error" role="alert">' . e($errors[$key]) . '</p>'
    : '';
$fields = $form['fields'];
$last = count($fields) - 1;
$settings = $form['settings'];
?>
        <div class="page-header">
            <h1><?= e($form['name'] !== '' ? $form['name'] : t('forms.edit')) ?></h1>
            <a class="button button-secondary" href="<?= e(Url::admin('forms', $form['id'], 'messages')) ?>"><?= e(t('forms.messages')) ?></a>
        </div>
        <p class="page-subtitle"><a href="<?= e(Url::admin('forms')) ?>"><?= e(t('forms.back')) ?></a> · <?= e(t('forms.in_language', ['language' => $language])) ?></p>

        <form method="post" action="<?= e(Url::admin('forms', $form['id'])) ?>" class="stack form-builder">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <?php /* First, so Enter in any text box saves rather than pressing the first
                     field's Move up (the first submit button in the form). */ ?>
            <button type="submit" name="action" value="save" class="visually-hidden" tabindex="-1" aria-hidden="true"><?= e(t('forms.save')) ?></button>

            <div class="panel stack">
                <div class="field">
                    <label for="form-name"><?= e(t('forms.name')) ?></label>
                    <input type="text" id="form-name" name="name" value="<?= e($form['name']) ?>" maxlength="190" required aria-describedby="form-name-hint">
                    <span class="hint" id="form-name-hint"><?= e(t('forms.name_hint')) ?></span>
                    <?= $error('name') ?>
                </div>
            </div>

            <div class="panel stack" id="fields">
                <h2><?= e(t('forms.fields')) ?></h2>
                <p class="hint"><?= e(t('forms.fields_intro')) ?></p>
                <?= $error('fields') ?>
                <ol class="form-fields">
<?php foreach ($fields as $n => $field): ?>
<?php $id = 'field-' . $n . '-'; ?>
                    <li class="form-field" data-form-field>
                        <input type="hidden" name="fields[<?= $n ?>][key]" value="<?= e($field['key']) ?>">
                        <div class="form-field-main">
                            <div class="field">
                                <label for="<?= e($id) ?>label"><?= e(t('forms.field.label')) ?></label>
                                <input type="text" id="<?= e($id) ?>label" name="fields[<?= $n ?>][label]" value="<?= e($field['label']) ?>" maxlength="200">
                            </div>
                            <div class="field">
                                <label for="<?= e($id) ?>type"><?= e(t('forms.field.type')) ?></label>
                                <select id="<?= e($id) ?>type" name="fields[<?= $n ?>][type]" data-field-type>
<?php foreach (Form::TYPES as $type): ?>
                                    <option value="<?= e($type) ?>"<?= $type === $field['type'] ? ' selected' : '' ?>><?= e(t('forms.type.' . $type)) ?></option>
<?php endforeach; ?>
                                </select>
                            </div>
                            <label class="checkbox form-field-required">
                                <input type="checkbox" name="fields[<?= $n ?>][required]" value="1"<?= $field['required'] ? ' checked' : '' ?>>
                                <?= e(t('forms.field.required')) ?>
                            </label>
                        </div>
                        <div class="field form-field-options" data-field-options>
                            <label for="<?= e($id) ?>options"><?= e(t('forms.field.options')) ?></label>
                            <textarea id="<?= e($id) ?>options" name="fields[<?= $n ?>][options]" rows="3" aria-describedby="<?= e($id) ?>options-hint"><?= e(implode("\n", $field['options'])) ?></textarea>
                            <span class="hint" id="<?= e($id) ?>options-hint"><?= e(t('forms.field.options_hint')) ?></span>
                        </div>
                        <?= $error('fields.' . $n) ?>
                        <div class="form-field-actions">
                            <button type="submit" name="action" value="up-<?= $n ?>" class="button button-ghost button-icon" title="<?= e(t('forms.field.up')) ?>"<?= $n === 0 ? ' disabled' : '' ?>><?= icon('arrow-up') ?><span class="visually-hidden"><?= e(t('forms.field.up')) ?></span></button>
                            <button type="submit" name="action" value="down-<?= $n ?>" class="button button-ghost button-icon" title="<?= e(t('forms.field.down')) ?>"<?= $n === $last ? ' disabled' : '' ?>><?= icon('arrow-down') ?><span class="visually-hidden"><?= e(t('forms.field.down')) ?></span></button>
                            <button type="submit" name="action" value="remove-<?= $n ?>" class="button button-ghost button-danger button-icon" title="<?= e(t('forms.field.remove')) ?>"<?= count($fields) === 1 ? ' disabled' : '' ?>><?= icon('trash-2') ?><span class="visually-hidden"><?= e(t('forms.field.remove')) ?></span></button>
                        </div>
                    </li>
<?php endforeach; ?>
                </ol>
<?php if (count($fields) < Form::MAX_FIELDS): ?>
                <div>
                    <button type="submit" name="action" value="add" class="button button-secondary"><?= icon('plus') ?> <?= e(t('forms.field.add')) ?></button>
                </div>
<?php endif; ?>
            </div>

            <div class="panel stack">
                <h2><?= e(t('forms.after')) ?></h2>
                <div class="field-pair">
                    <div class="field">
                        <label for="form-submit"><?= e(t('forms.submit')) ?></label>
                        <input type="text" id="form-submit" name="settings[submit]" value="<?= e($settings['submit']) ?>" maxlength="60" placeholder="<?= e(site_t('site.form.send', $form['locale'])) ?>" aria-describedby="form-submit-hint">
                        <span class="hint" id="form-submit-hint"><?= e(t('forms.submit_hint')) ?></span>
                    </div>
                    <div class="field">
                        <label for="form-success"><?= e(t('forms.success')) ?></label>
                        <input type="text" id="form-success" name="settings[success]" value="<?= e($settings['success']) ?>" maxlength="500" placeholder="<?= e(site_t('site.form.thanks', $form['locale'])) ?>" aria-describedby="form-success-hint">
                        <span class="hint" id="form-success-hint"><?= e(t('forms.success_hint')) ?></span>
                    </div>
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="settings[notify]" value="1"<?= $settings['notify'] ? ' checked' : '' ?>>
                    <?= e(t('forms.notify')) ?>
                </label>
                <p class="hint"><?= e(t('forms.notify_hint')) ?> <a href="<?= e(Url::admin('settings') . '#mail') ?>"><?= e(t('forms.mail_settings')) ?></a></p>

                <label class="checkbox">
                    <input type="checkbox" name="settings[autoreply]" value="1"<?= $settings['autoreply'] ? ' checked' : '' ?>>
                    <?= e(t('forms.autoreply')) ?>
                </label>
                <p class="hint"><?= e(t('forms.autoreply_hint')) ?></p>
                <div class="field">
                    <label for="form-autoreply-subject"><?= e(t('forms.autoreply_subject')) ?></label>
                    <input type="text" id="form-autoreply-subject" name="settings[autoreply_subject]" value="<?= e($settings['autoreply_subject']) ?>" maxlength="200">
                </div>
                <div class="field">
                    <label for="form-autoreply-body"><?= e(t('forms.autoreply_body')) ?></label>
                    <textarea id="form-autoreply-body" name="settings[autoreply_body]" rows="5" aria-describedby="form-autoreply-body-hint"><?= e($settings['autoreply_body']) ?></textarea>
                    <span class="hint" id="form-autoreply-body-hint"><?= e(t('forms.autoreply_body_hint')) ?></span>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="action" value="save" class="button"><?= e(t('forms.save')) ?></button>
            </div>
        </form>
