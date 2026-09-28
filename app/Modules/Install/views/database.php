<?php

use App\Support\Url;

/**
 * Provided by View::render(). Passwords are never refilled after an error.
 *
 * @var array<mixed> $old
 * @var string $csrf
 */
$value = static fn (string $key, string $default = ''): string => is_string($old[$key] ?? null) ? $old[$key] : $default;
$driver = $value('driver', 'mysql');
?>
        <h1><?= e(t('install.db.title')) ?></h1>
        <form method="post" action="<?= e(Url::asset('install.php')) ?>" class="stack">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <fieldset>
                <legend>
                    <label class="choice">
                        <input type="radio" name="driver" value="mysql"<?= $driver !== 'sqlite' ? ' checked' : '' ?>>
                        <?= e(t('install.db.mysql_choice')) ?>
                    </label>
                </legend>
                <p class="hint"><?= e(t('install.db.mysql_hint')) ?></p>
                <label class="field">
                    <span><?= e(t('install.db.host')) ?></span>
                    <input type="text" name="host" value="<?= e($value('host', 'localhost')) ?>">
                </label>
                <label class="field">
                    <span><?= e(t('install.db.port')) ?></span>
                    <input type="text" name="port" inputmode="numeric" value="<?= e($value('port', '3306')) ?>">
                </label>
                <label class="field">
                    <span><?= e(t('install.db.database')) ?></span>
                    <input type="text" name="database" value="<?= e($value('database')) ?>">
                </label>
                <label class="field">
                    <span><?= e(t('install.db.username')) ?></span>
                    <input type="text" name="username" autocomplete="off" value="<?= e($value('username')) ?>">
                </label>
                <label class="field">
                    <span><?= e(t('install.db.password')) ?></span>
                    <input type="password" name="password" autocomplete="off">
                </label>
            </fieldset>
            <fieldset>
                <legend>
                    <label class="choice">
                        <input type="radio" name="driver" value="sqlite"<?= $driver === 'sqlite' ? ' checked' : '' ?>>
                        <?= e(t('install.db.sqlite_choice')) ?>
                    </label>
                </legend>
                <p class="hint"><?= e(t('install.db.sqlite_hint')) ?></p>
                <label class="field">
                    <span><?= e(t('install.db.path')) ?></span>
                    <input type="text" name="path" value="<?= e($value('path', 'storage/database.sqlite')) ?>">
                </label>
            </fieldset>
            <button type="submit" class="button"><?= e(t('install.db.submit')) ?></button>
        </form>
