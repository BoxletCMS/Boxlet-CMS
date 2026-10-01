<?php

use App\Modules\Media\MediaCrop;

/**
 * The media browser (PLAN.md D-145): one dialog for a whole screen, fetched the first time a
 * picture field opens it and kept for every field after. Its words and its shapes come from
 * here, so media-picker.js carries no English and no second list of crop shapes.
 *
 * Two steps in one dialog. Choosing shows the search, the drop zone and the library's cards;
 * a file chosen or dropped moves to cropping, which sends the file and a rectangle, never a
 * drawn image (D-026). The dialog is always opened by the script: without one the field's
 * select is the control, and none of this is reached.
 *
 * @var list<array<string, mixed>> $pictures
 * @var string $search
 * @var bool $picking
 * @var int|null $next
 * @var string $csrf
 * @var array{file: int, request: int, fileLabel: string, requestLabel: string} $limits
 */
$shapes = [
    'free' => 'media.crop_ratio_free',
    'hero' => 'media.crop_ratio_hero',
    'card' => 'media.crop_ratio_card',
    'wide' => 'media.crop_ratio_wide',
    'thumb' => 'media.crop_ratio_thumb',
];
?>
<dialog class="dialog media-browser" data-browser aria-labelledby="media-browser-title"
        data-max-file="<?= e((string) $limits['file']) ?>"
        data-max-request="<?= e((string) $limits['request']) ?>"
        data-too-large="<?= e(t('media.too_large_named', ['limit' => $limits['fileLabel']])) ?>"
        data-text-uploading="<?= e(t('picker.uploading')) ?>"
        data-text-finishing="<?= e(t('picker.finishing')) ?>"
        data-text-failed="<?= e(t('picker.failed')) ?>"
        data-text-chosen="<?= e(t('picker.chosen')) ?>">
    <div class="dialog-head media-browser-head">
        <h2 id="media-browser-title" data-browser-heading data-title-pick="<?= e(t('picker.title')) ?>" data-title-crop="<?= e(t('picker.crop_title')) ?>"><?= e(t('picker.title')) ?></h2>
        <button type="button" class="button button-ghost media-browser-close" data-browser-close aria-label="<?= e(t('picker.close')) ?>"><?= icon('x') ?></button>
    </div>
    <p class="media-browser-error" role="alert" data-browser-error hidden></p>

    <div class="media-browser-pick" data-browser-pick>
        <div class="media-browser-tools">
            <label class="media-browser-search">
                <?= icon('search') ?>
                <span class="visually-hidden"><?= e(t('picker.search')) ?></span>
                <input type="search" placeholder="<?= e(t('picker.search')) ?>" data-browser-search>
            </label>
            <button type="button" class="button button-secondary" data-browser-none><?= e(t('picker.none')) ?></button>
        </div>
        <label class="media-browser-drop" data-browser-drop>
            <input type="file" class="visually-hidden" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" data-browser-file>
            <?= icon('image-up') ?>
            <span class="media-browser-drop-text"><?= e(t('picker.drop')) ?> <span class="media-browser-browse"><?= e(t('picker.browse')) ?></span></span>
            <span class="media-browser-limits"><?= e(t('picker.limits', ['file' => $limits['fileLabel']])) ?></span>
        </label>
        <div class="media-browser-results" data-browser-results>
<?php require __DIR__ . '/pick.php'; ?>
        </div>
    </div>

    <div class="media-browser-crop" data-browser-crop hidden>
        <p class="hint"><?= e(t('picker.crop_hint')) ?></p>
        <div class="media-browser-stage">
            <img alt="" data-browser-crop-image>
        </div>
        <div class="media-browser-crop-bar">
            <div class="media-crop-shapes" role="group" aria-label="<?= e(t('media.crop_ratio')) ?>">
<?php foreach ($shapes as $name => $label): ?>
                <button type="button" class="media-crop-shape" data-crop-ratio="<?= e((string) MediaCrop::RATIOS[$name]) ?>"
                        data-crop-name="<?= e($name) ?>" aria-pressed="<?= $name === 'free' ? 'true' : 'false' ?>"><?= e(t($label)) ?></button>
<?php endforeach; ?>
            </div>
            <div class="media-browser-crop-actions">
                <button type="button" class="button button-ghost" data-browser-crop-cancel><?= e(t('picker.crop_cancel')) ?></button>
                <button type="button" class="button button-secondary" data-browser-crop-skip><?= e(t('picker.crop_skip')) ?></button>
                <button type="button" class="button" data-browser-crop-confirm><?= icon('crop') ?> <?= e(t('picker.crop_confirm')) ?></button>
            </div>
        </div>
    </div>

    <div class="media-browser-busy" data-browser-busy hidden>
        <span class="media-browser-spinner" aria-hidden="true"></span>
        <p role="status" data-browser-busy-text></p>
    </div>
</dialog>
