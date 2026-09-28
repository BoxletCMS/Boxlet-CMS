<?php

use App\Support\Url;

/**
 * Provided by View::render().
 *
 * @var bool $deleted whether install.php could be deleted
 */
?>
        <h1><?= e(t('install.done.title')) ?></h1>
        <p><?= e(t('install.done.text')) ?></p>
<?php if (!$deleted): ?>
        <p class="notice notice-error" role="alert"><?= e(t('install.script_not_deleted')) ?></p>
<?php endif; ?>
        <p><a class="button" href="<?= e(Url::admin('login')) ?>"><?= e(t('install.done.login')) ?></a></p>
