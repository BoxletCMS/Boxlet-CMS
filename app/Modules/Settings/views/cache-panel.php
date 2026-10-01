<?php

use App\Support\Url;

/**
 * The page cache (PLAN.md D-053): on or off, how many pages are kept, and Clear now.
 * Required by settings.php.
 *
 * @var array{on: bool, count: int} $pageCache
 * @var string $csrf
 */
?>
        <div class="panel stack" id="cache">
            <h2><?= e(t('cache.title')) ?></h2>
            <p class="hint"><?= e(t('cache.intro')) ?></p>
            <form method="post" action="<?= e(Url::admin('settings', 'cache')) ?>" class="stack">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <label class="checkbox">
                    <input type="checkbox" name="page_cache" value="1"<?= $pageCache['on'] ? ' checked' : '' ?>>
                    <?= e(t('cache.on')) ?>
                </label>
                <p class="hint"><?= e($pageCache['on'] ? t('cache.kept', ['count' => (string) $pageCache['count']]) : t('cache.off_now')) ?></p>
                <div class="form-actions">
                    <button type="submit" class="button button-secondary"><?= e(t('cache.save')) ?></button>
<?php if ($pageCache['on'] && $pageCache['count'] > 0): ?>
                    <button type="submit" name="action" value="clear" class="button button-ghost"><?= e(t('cache.clear')) ?></button>
<?php endif; ?>
                </div>
            </form>
        </div>
