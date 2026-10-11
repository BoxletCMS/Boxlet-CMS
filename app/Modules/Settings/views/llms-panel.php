<?php

use App\Support\Url;

/**
 * llms.txt (PLAN.md D-151): on or off, and a way to see it. Required by settings.php.
 *
 * @var array{on: bool, ownersOwn: bool, exists: bool} $llms
 * @var string $csrf
 */
?>
        <div class="panel stack" id="llms">
            <h2><?= e(t('llms.title')) ?></h2>
            <p class="hint"><?= e(t('llms.intro')) ?></p>
<?php if ($llms['ownersOwn']): ?>
            <p class="hint"><?= e(t('llms.owners_own')) ?> <a href="<?= e(Url::asset('llms.txt')) ?>" target="_blank" rel="noopener"><?= e(t('llms.view')) ?></a></p>
<?php else: ?>
            <form method="post" action="<?= e(Url::admin('settings', 'llms')) ?>" class="stack">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <label class="checkbox">
                    <input type="checkbox" name="llms_txt" value="1"<?= $llms['on'] ? ' checked' : '' ?>>
                    <?= e(t('llms.on')) ?>
                </label>
<?php if ($llms['on'] && $llms['exists']): ?>
                <p class="hint"><a href="<?= e(Url::asset('llms.txt')) ?>" target="_blank" rel="noopener"><?= e(t('llms.view')) ?></a></p>
<?php elseif ($llms['on']): ?>
                <p class="hint"><?= e(t('llms.cannot_write')) ?></p>
<?php else: ?>
                <p class="hint"><?= e(t('llms.off_now')) ?></p>
<?php endif; ?>
                <div class="form-actions">
                    <button type="submit" class="button button-secondary"><?= e(t('llms.save')) ?></button>
                </div>
            </form>
<?php endif; ?>
        </div>
