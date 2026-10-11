<?php

use App\Support\Url;

/**
 * Provided by View::render().
 *
 * @var bool $deleted whether install.php could be deleted
 * @var bool $withoutPictures the demo came without its pictures (D-215)
 */
?>
        <h1><?= e(t('install.done.title')) ?></h1>
        <p><?= e(t('install.done.text')) ?></p>
<?php if ($withoutPictures): ?>
        <p class="notice notice-warning" role="status"><?= e(t('install.done.no_pictures')) ?></p>
<?php endif; ?>
<?php if (!$deleted): ?>
        <p class="notice notice-error" role="alert"><?= e(t('install.script_not_deleted')) ?></p>
<?php endif; ?>
        <p><a class="button" href="<?= e(Url::admin('login')) ?>"><?= e(t('install.done.login')) ?></a></p>
