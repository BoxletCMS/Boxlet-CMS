<?php

use App\Support\Url;

/**
 * Making every picture's sizes again (PLAN.md D-048): a panel under the library. While work
 * is owed it says how much and offers Continue, which auto-continue.js presses by itself.
 *
 * @var int $remakeLeft pictures still owed a remake
 * @var string $csrf
 */
?>
        <div class="panel stack media-remake" id="remake">
            <h2><?= e(t('media.remake_title')) ?></h2>
            <p class="hint"><?= e(t('media.remake_intro')) ?></p>
<?php if ($remakeLeft > 0): ?>
            <p role="status"><?= e(t('media.remake_left', ['left' => (string) $remakeLeft])) ?></p>
            <form method="post" action="<?= e(Url::admin('media', 'remake', 'step')) ?>" data-auto-continue>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="button"><?= e(t('media.remake_continue')) ?></button>
                <span class="hint js-only"><?= e(t('media.remake_auto')) ?></span>
            </form>
<?php else: ?>
            <form method="post" action="<?= e(Url::admin('media', 'remake')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="button button-secondary" data-confirm="<?= e(t('media.remake_confirm')) ?>"><?= e(t('media.remake_start')) ?></button>
            </form>
<?php endif; ?>
        </div>
