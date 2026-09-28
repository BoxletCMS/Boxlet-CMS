<?php

use App\Support\Url;

/**
 * Provided by View::render().
 *
 * @var array<mixed> $old
 * @var array<string, string> $languages ISO 639-1 code => native name
 * @var list<string> $timezones
 * @var string $csrf
 */
$value = static fn (string $key, string $default = ''): string => is_string($old[$key] ?? null) ? $old[$key] : $default;
$selectedLocale = $value('locale', 'en');
$selectedTimezone = $value('timezone', date_default_timezone_get());
?>
        <h1><?= e(t('install.site.title')) ?></h1>
        <form method="post" action="<?= e(Url::asset('install.php')) ?>" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label class="field">
                <span><?= e(t('install.site.name')) ?></span>
                <input type="text" name="name" maxlength="100" value="<?= e($value('name')) ?>" required>
            </label>
            <label class="field">
                <span><?= e(t('install.site.locale')) ?></span>
                <select name="locale" aria-describedby="locale-immutable" required>
<?php foreach ($languages as $code => $name): ?>
                    <option value="<?= e($code) ?>"<?= $code === $selectedLocale ? ' selected' : '' ?>><?= e($name) ?> (<?= e($code) ?>)</option>
<?php endforeach; ?>
                </select>
                <span id="locale-immutable" class="notice notice-warning"><?= e(t('install.site.locale_immutable')) ?></span>
            </label>
            <label class="field">
                <span><?= e(t('install.site.timezone')) ?></span>
                <select name="timezone" required>
<?php foreach ($timezones as $timezone): ?>
                    <option value="<?= e($timezone) ?>"<?= $timezone === $selectedTimezone ? ' selected' : '' ?>><?= e($timezone) ?></option>
<?php endforeach; ?>
                </select>
            </label>
            <label class="checkbox">
                <input type="checkbox" name="demo" value="1"<?= $old === [] || $value('demo') === '1' ? ' checked' : '' ?>>
                <span><?= e(t('install.site.demo')) ?></span>
            </label>
            <button type="submit" class="button"><?= e(t('install.site.submit')) ?></button>
        </form>
