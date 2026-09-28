<?php

use App\Support\Url;

/**
 * Provided by AdminView::render().
 *
 * @var array<mixed> $old submitted fields to refill after an error
 * @var array<string, string> $errors
 * @var array<int, array<string, mixed>> $locales enabled locales
 * @var list<array{id: int, name: string, builtin: bool, blocks: list<string>}> $templates
 * @var string $csrf
 */
$value = static fn (string $key, string $default = ''): string => is_string($old[$key] ?? null) ? $old[$key] : $default;
$error = static fn (string $key): string => isset($errors[$key]) ? '<p class="field-error" role="alert">' . e($errors[$key]) . '</p>' : '';
?>
        <div class="page-header">
            <h1><?= e(t('pages.new')) ?></h1>
        </div>
        <form method="post" action="<?= e(Url::admin('pages')) ?>" class="stack panel">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="field">
                <label for="page-title"><?= e(t('pages.field.title')) ?></label>
                <input type="text" id="page-title" name="title" value="<?= e($value('title')) ?>" maxlength="255" required>
                <?= field_hint('hint.page.title') ?>
                <?= $error('title') ?>
            </div>
            <div class="field">
                <label for="page-locale"><?= e(t('pages.field.locale')) ?></label>
                <select id="page-locale" name="locale">
<?php foreach ($locales as $option): ?>
                    <option value="<?= e($option['code']) ?>"<?= $value('locale') === $option['code'] ? ' selected' : '' ?>><?= e($option['label']) ?></option>
<?php endforeach; ?>
                </select>
                <?= field_hint('hint.page.locale') ?>
                <?= $error('locale') ?>
            </div>
            <div class="field">
                <label for="page-template"><?= e(t('pages.field.template')) ?></label>
                <select id="page-template" name="template">
                    <option value=""><?= e(t('pages.template.none')) ?></option>
<?php foreach ($templates as $template): ?>
<?php $blockNames = implode(', ', array_map(static fn (string $type): string => t('block.' . $type), $template['blocks'])); ?>
                    <option value="<?= e($template['id']) ?>"<?= $value('template') === (string) $template['id'] ? ' selected' : '' ?>><?= e($template['builtin'] ? t('template.' . $template['name']) : $template['name']) ?>: <?= e($blockNames) ?></option>
<?php endforeach; ?>
                </select>
                <?= field_hint('hint.page.template') ?>
                <?= $error('template') ?>
            </div>
            <div class="field">
                <label for="page-slug"><?= e(t('pages.field.slug')) ?></label>
                <input type="text" id="page-slug" name="slug" value="<?= e($value('slug')) ?>" maxlength="100" autocapitalize="off" spellcheck="false" aria-describedby="page-slug-hint">
                <span class="hint" id="page-slug-hint"><?= e(t('pages.field.slug_hint_create')) ?></span>
                <?= $error('slug') ?>
            </div>
            <label class="checkbox">
                <input type="checkbox" name="home" value="1"<?= $value('home') === '1' ? ' checked' : '' ?>>
                <span><?= e(t('pages.field.home')) ?></span>
            </label>
            <?= $error('home') ?>
            <button type="submit" class="button"><?= e(t('pages.create')) ?></button>
        </form>
