<?php

use App\Support\Url;

/**
 * Provided by AdminView::render().
 *
 * @var string $title
 * @var list<string> $pending migration filenames waiting to be applied
 * @var bool $isSqlite whether a copy of the database is taken automatically
 * @var string $csrf
 */
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
<?php if ($pending === []): ?>
        <div class="panel stack">
            <p><?= e(t('update.done')) ?></p>
            <div class="row-actions">
                <a class="button" href="<?= e(Url::admin()) ?>"><?= e(t('admin.nav.dashboard')) ?></a>
            </div>
        </div>
<?php else: ?>
        <div class="panel stack">
            <p><?= e(t('update.intro')) ?></p>

            <p><strong><?= e(t('update.pending')) ?></strong></p>
            <ul class="steps">
<?php foreach ($pending as $file): ?>
                <li><code><?= e($file) ?></code></li>
<?php endforeach; ?>
            </ul>

            <?php /* SQLite is a file, so Boxlet copies it. MySQL needs the host's tools,
                     which arrive with Slice 8 — until then the honest thing is to say so
                     rather than imply a backup nobody took. */ ?>
            <p class="notice <?= $isSqlite ? 'notice-success' : 'notice-warning' ?>" role="status">
                <?= e($isSqlite ? t('update.backup_sqlite') : t('update.backup_mysql')) ?>
            </p>

            <form method="post" action="<?= e(Url::admin('update')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="button"><?= e(t('update.run')) ?></button>
            </form>
        </div>
<?php endif; ?>
