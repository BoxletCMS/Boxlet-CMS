<?php
/**
 * Form block (PLAN.md D-046): a heading, a sentence, and the chosen form — drawn from what
 * FormBlocks resolved for it, never from the database. Everything is escaped; the words
 * the site itself supplies (the button's default, "optional", the thank-you) come in the
 * page's language through site_t().
 *
 * Browser checks are left on (required, type="email"): they catch most mistakes before a
 * round trip, and the server checks everything again regardless. The honeypot is a text
 * field a person never sees or reaches, named like one a script fills in.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 * @var array<string, mixed> $resolved ['forms' => form id => what FormBlocks resolved]
 * @var string $locale
 */
$drawn = is_int($content['form']) && is_array($resolved['forms'] ?? null) ? ($resolved['forms'][$content['form']] ?? null) : null;
$formId = is_array($drawn) ? (int) $drawn['form']['id'] : 0;
$anchor = 'form-' . $formId;
?>
<div class="form-block"<?= $formId > 0 ? ' id="' . e($anchor) . '"' : '' ?>>
<?php if ($content['heading'] !== '' || $content['intro'] !== ''): ?>
    <div class="form-block-head">
<?php if ($content['heading'] !== ''): ?>
        <h2 class="form-block-heading"><?= e($content['heading']) ?></h2>
<?php endif; ?>
<?php if ($content['intro'] !== ''): ?>
        <p class="form-block-intro"><?= e($content['intro']) ?></p>
<?php endif; ?>
    </div>
<?php endif; ?>
<?php if (is_array($drawn)): ?>
<?php
    $form = $drawn['form'];
    $settings = $form['settings'];
    $errors = $drawn['errors'];
    $old = $drawn['old'];
?>
<?php if ($drawn['sent']): ?>
    <p class="form-block-sent" role="status"><?= e($settings['success'] !== '' ? $settings['success'] : site_t('site.form.thanks', $locale)) ?></p>
<?php else: ?>
    <form class="site-form" method="post" action="<?= e($drawn['action']) ?>#<?= e($anchor) ?>">
        <input type="hidden" name="page" value="<?= e((string) ($drawn['page'] ?? '')) ?>">
        <input type="hidden" name="started" value="<?= e($drawn['token']) ?>">
<?php if ($drawn['notice'] !== '' || $errors !== []): ?>
        <p class="site-form-alert" role="alert"><?= e($drawn['notice'] !== '' ? $drawn['notice'] : site_t('site.form.error', $locale)) ?></p>
<?php endif; ?>
        <div class="site-form-trap" aria-hidden="true">
            <label>Website <input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label>
        </div>
<?php foreach ($form['fields'] as $field): ?>
<?php
    $name = 'f[' . $field['key'] . ']';
    $id = $anchor . '-' . $field['key'];
    $value = $old[$field['key']] ?? '';
    $error = $errors[$field['key']] ?? null;
    $describedBy = $error !== null ? ' aria-describedby="' . e($id) . '-error" aria-invalid="true"' : '';
    $required = $field['required'] ? ' required' : '';
?>
<?php if ($field['type'] === 'checkbox'): ?>
        <div class="site-form-field site-form-check">
            <label><input type="checkbox" id="<?= e($id) ?>" name="<?= e($name) ?>" value="1"<?= $value === '1' ? ' checked' : '' ?><?= $required ?><?= $describedBy ?>> <?= e($field['label']) ?><?php if (!$field['required']): ?> <span class="site-form-optional"><?= e(site_t('site.form.optional', $locale)) ?></span><?php endif; ?></label>
<?php else: ?>
        <div class="site-form-field">
            <label for="<?= e($id) ?>"><?= e($field['label']) ?><?php if (!$field['required']): ?> <span class="site-form-optional"><?= e(site_t('site.form.optional', $locale)) ?></span><?php endif; ?></label>
<?php if ($field['type'] === 'textarea'): ?>
            <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="5" maxlength="5000"<?= $required ?><?= $describedBy ?>><?= e($value) ?></textarea>
<?php elseif ($field['type'] === 'select'): ?>
            <select id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $required ?><?= $describedBy ?>>
                <option value=""><?= e(site_t('site.form.choose', $locale)) ?></option>
<?php foreach ($field['options'] as $option): ?>
                <option<?= $value === $option ? ' selected' : '' ?>><?= e($option) ?></option>
<?php endforeach; ?>
            </select>
<?php else: ?>
            <input type="<?= e($field['type']) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" maxlength="500"<?= $field['type'] === 'email' ? ' autocomplete="email"' : ($field['type'] === 'tel' ? ' autocomplete="tel"' : '') ?><?= $required ?><?= $describedBy ?>>
<?php endif; ?>
<?php endif; ?>
<?php if ($error !== null): ?>
            <p class="site-form-error" id="<?= e($id) ?>-error"><?= e($error) ?></p>
<?php endif; ?>
        </div>
<?php endforeach; ?>
        <p class="site-form-actions"><button type="submit" class="button"><?= e($settings['submit'] !== '' ? $settings['submit'] : site_t('site.form.send', $locale)) ?></button></p>
    </form>
<?php endif; ?>
<?php endif; ?>
</div>
