<?php

use App\Support\Url;

/**
 * One picture. Provided by AdminView::render().
 *
 * @var array{id: int, filename: string, original: string, size: string, width: int, height: int, complete: bool, thumb: string|null} $picture
 * @var string|null $preview the uncropped variant, or null while it is still being made
 * @var array<string, array{alt: string, caption: string, suggested: bool}> $meta
 * @var list<array<string, mixed>> $locales
 * @var array<int, string> $usedBy page id => title
 * @var array{file: int, request: int, fileLabel: string, requestLabel: string} $limits
 * @var string $added
 * @var string $mime
 * @var array{x: int, y: int} $focal
 * @var string $csrf
 */
?>
        <div class="page-header">
            <h1><?= e($picture['filename']) ?></h1>
            <a class="button button-secondary" href="<?= e(Url::admin('media')) ?>"><?= e(t('media.back')) ?></a>
        </div>

        <div class="media-detail">
            <div class="media-preview">
<?php if ($preview !== null): ?>
                <?php /* THE FOCAL POINT, CHOSEN ON THE PICTURE ITSELF (PLAN.md D-121). The
                         preview is the uncropped `full`, the only size where a click means what
                         it looks like it means. Without JavaScript the two number fields are the
                         control, and they are what the form posts either way: media.js only
                         fills them in from a click, and shows the two cuts beside it. */ ?>
                <form class="media-focal" method="post" action="<?= e(Url::admin('media', $picture['id'], 'focal')) ?>" data-focal-form>
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <div class="focal-frame" data-focal-frame>
                        <img class="media-preview-image" src="<?= e($preview) ?>" alt="<?= e($picture['original']) ?>">
                        <span class="focal-marker js-only" data-focal-marker aria-hidden="true"></span>
                    </div>
                    <h2><?= e(t('media.focal')) ?></h2>
                    <p class="hint"><?= e(t('media.focal_hint')) ?></p>
                    <?php /* The same picture cut two ways, following the point as it moves:
                             what a hero's background shows on a computer and on a phone. Only
                             with a script, which is what moves them; without one they would
                             show the middle whatever the point says. */ ?>
                    <div class="focal-samples js-only" aria-hidden="true">
                        <figure class="focal-sample focal-sample-wide">
                            <div class="focal-sample-frame"><img src="<?= e($preview) ?>" alt="" data-focal-sample></div>
                            <figcaption class="hint"><?= e(t('media.focal_wide')) ?></figcaption>
                        </figure>
                        <figure class="focal-sample focal-sample-tall">
                            <div class="focal-sample-frame"><img src="<?= e($preview) ?>" alt="" data-focal-sample></div>
                            <figcaption class="hint"><?= e(t('media.focal_tall')) ?></figcaption>
                        </figure>
                    </div>
                    <div class="focal-fields">
                        <div class="field">
                            <label for="focal-x"><?= e(t('media.focal_x')) ?></label>
                            <input type="number" id="focal-x" name="x" min="0" max="100" value="<?= $focal['x'] ?>" data-focal-input-x>
                        </div>
                        <div class="field">
                            <label for="focal-y"><?= e(t('media.focal_y')) ?></label>
                            <input type="number" id="focal-y" name="y" min="0" max="100" value="<?= $focal['y'] ?>" data-focal-input-y>
                        </div>
                        <button type="submit" class="button button-secondary"><?= e(t('media.focal_save')) ?></button>
                    </div>
                </form>
<?php else: ?>
                <p class="hint"><?= e(t('media.no_thumb')) ?></p>
<?php endif; ?>
            </div>

            <div class="media-side">
                <h2><?= e(t('media.details')) ?></h2>
                <dl class="media-facts-list">
                    <dt><?= e(t('media.original_name')) ?></dt>
                    <dd><?= e($picture['original']) ?></dd>
                    <dt><?= e(t('media.dimensions_label')) ?></dt>
                    <dd><?= e(t('media.dimensions', ['width' => (string) $picture['width'], 'height' => (string) $picture['height']])) ?></dd>
                    <dt><?= e(t('media.size')) ?></dt>
                    <dd><?= e($picture['size']) ?></dd>
                    <dt><?= e(t('media.format')) ?></dt>
                    <dd><?= e($mime) ?></dd>
                    <dt><?= e(t('media.added')) ?></dt>
                    <dd><?= e($added) ?></dd>
                </dl>

                <h2><?= e(t('media.used_by')) ?></h2>
<?php if ($usedBy === []): ?>
                <p class="hint"><?= e(t('media.used_by_none')) ?></p>
<?php else: ?>
                <ul class="media-used">
<?php foreach ($usedBy as $pageId => $pageTitle): ?>
                    <li><a href="<?= e(Url::admin('pages', $pageId)) ?>"><?= e($pageTitle) ?></a></li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>

                <?php /* THE THREE THINGS DONE TO A PICTURE, each one word and an icon
                         (D-038). Replace opens its form only when pressed — a <details>, so it
                         needs no script. */ ?>
                <div class="media-actions">
<?php if ($preview !== null): ?>
                    <?php /* Hidden until media-crop.js runs, and revealed by it. Without
                             JavaScript there is no box to drag, so a visible Crop button would
                             be a control that cannot do anything — worse than an absent one. */ ?>
                    <button type="button" class="button button-secondary" data-crop-open hidden><?= icon('crop') ?> <?= e(t('media.crop_open')) ?></button>
<?php endif; ?>
                    <?php /* Replace opens its drop zone under this row rather than in it, so
                             the three buttons never move (D-039). A script-only button: without
                             one the drop zone below is simply always shown. */ ?>
                    <button type="button" class="button button-secondary js-only" aria-expanded="false" aria-controls="media-replace" data-replace-toggle><?= icon('image-up') ?> <?= e(t('media.replace')) ?></button>
                    <?php /* Deleting is refused while a page still shows the picture, so this
                             button is not hidden when it is in use: the refusal names the
                             pages, which is more use than a control that has quietly gone. */ ?>
                    <form class="media-delete" method="post" action="<?= e(Url::admin('media', $picture['id'], 'delete')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <button type="submit" class="button button-ghost button-danger"
                                data-confirm="<?= e(t('media.delete_confirm', ['name' => $picture['filename']])) ?>"><?= icon('trash-2') ?> <?= e(t('media.delete')) ?></button>
                    </form>
                </div>
                <div class="media-replace" id="media-replace" data-replace-panel>
                    <?php /* The same drop zone as the library's, for one picture: dropped or
                             chosen, it goes up at once (D-039). Without a script the zone
                             still opens the file chooser and a button sends it. */ ?>
                    <form method="post" action="<?= e(Url::admin('media', $picture['id'], 'replace')) ?>" enctype="multipart/form-data" class="media-upload media-replace-form"
                          data-media-upload
                          data-max-file="<?= $limits['file'] ?>"
                          data-max-request="<?= $limits['request'] ?>"
                          data-file-label="<?= e($limits['fileLabel']) ?>"
                          data-request-label="<?= e($limits['requestLabel']) ?>"
                          data-too-large="<?= e(t('media.too_large_named')) ?>"
                          data-too-large-total="<?= e(t('media.too_large_total')) ?>"
                          data-uploading="<?= e(t('media.uploading')) ?>"
                          data-confirm-send="<?= e($usedBy === [] ? t('media.replace_confirm_unused', ['name' => $picture['filename']]) : t('media.replace_confirm', ['name' => $picture['filename'], 'count' => (string) count($usedBy)])) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <label class="dropzone" for="replace-file">
                            <?= icon('image-up') ?>
                            <span class="dropzone-text"><?= e(t('media.replace_drop')) ?> <span class="dropzone-browse"><?= e(t('media.browse')) ?></span></span>
                            <span class="dropzone-limits"><?= e(t('media.replace_hint')) ?></span>
                        </label>
                        <input type="file" id="replace-file" name="file" class="visually-hidden" data-media-input
                               accept="image/jpeg,image/png,image/webp,image/gif,image/avif">
                        <p class="field-error" data-media-error role="alert" hidden></p>
                        <button type="submit" class="button no-js-only"><?= e(t('media.replace_submit')) ?></button>
                    </form>
                </div>
            </div>
        </div>

<?php if ($preview !== null): ?>
        <?php /* CROPPING (D-026). The dialog shows the `full` variant, which is the only
                 uncropped one and the only one that is public — the original never is
                 (D-020). What the form posts is a RECTANGLE in that variant's pixels,
                 measured against the size it was measured on, so the server can scale it
                 to the original and cut that at full quality.

                 The two buttons are deliberately unalike. One adds a picture and leaves
                 this one alone; the other destroys an original that cannot be brought
                 back, so it wears the same danger colours as Delete and asks first. */ ?>
        <section class="media-crop" data-crop hidden>
            <h2><?= e(t('media.crop')) ?></h2>
            <p class="hint"><?= e(t('media.crop_hint')) ?></p>

            <div class="media-crop-stage">
                <img src="<?= e($preview) ?>" alt="<?= e($picture['original']) ?>" data-crop-image>
            </div>

            <form method="post" action="<?= e(Url::admin('media', $picture['id'], 'crop')) ?>" data-crop-form>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="ratio" value="free">
<?php foreach (['x', 'y', 'w', 'h', 'full_w', 'full_h'] as $field): ?>
                <input type="hidden" name="<?= e($field) ?>" value="0">
<?php endforeach; ?>

                <fieldset class="media-crop-shapes">
                    <legend class="visually-hidden"><?= e(t('media.crop_ratio')) ?></legend>
<?php foreach ([
    'free' => 'media.crop_ratio_free',
    'hero' => 'media.crop_ratio_hero',
    'card' => 'media.crop_ratio_card',
    'wide' => 'media.crop_ratio_wide',
    'thumb' => 'media.crop_ratio_thumb',
] as $name => $label): ?>
                    <button type="button" class="media-crop-shape" data-crop-ratio="<?= e((string) \App\Modules\Media\MediaCrop::RATIOS[$name]) ?>"
                            data-crop-name="<?= e($name) ?>" aria-pressed="<?= $name === 'free' ? 'true' : 'false' ?>"><?= e(t($label)) ?></button>
<?php endforeach; ?>
                </fieldset>

                <div class="media-crop-actions">
                    <button type="submit" name="action" value="new" class="button"><?= e(t('media.crop_new')) ?></button>
                    <button type="submit" name="action" value="replace" class="button button-ghost button-danger"
                            data-confirm="<?= e(t('media.crop_replace_confirm', ['name' => $picture['filename']])) ?>"><?= e(t('media.crop_replace')) ?></button>
                    <button type="button" class="button button-secondary" data-crop-cancel><?= e(t('media.crop_cancel')) ?></button>
                    <p class="hint"><?= e(t('media.crop_replace_hint')) ?></p>
                </div>
            </form>
        </section>
<?php endif; ?>

        <form class="media-meta" id="meta" method="post" action="<?= e(Url::admin('media', $picture['id'])) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <h2><?= e(t('media.meaning')) ?></h2>
<?php foreach ($locales as $enabled): ?>
<?php $code = (string) $enabled['code']; ?>
            <fieldset class="fieldset">
                <legend><?= e((string) $enabled['label']) ?></legend>
                <div class="field">
                    <label for="alt-<?= e($code) ?>"><?= e(t('media.alt')) ?></label>
                    <input type="text" id="alt-<?= e($code) ?>" name="alt_<?= e($code) ?>" maxlength="255"
                           value="<?= e($meta[$code]['alt'] ?? '') ?>">
                    <?php /* No "suggested" badge any more (D-038, the owner's review): a
                             description filled in from the picture or its name is simply
                             there to keep or change. */ ?>
                    <p class="hint"><?= e(t('media.alt_hint')) ?></p>
                </div>
                <div class="field">
                    <label for="caption-<?= e($code) ?>"><?= e(t('media.caption')) ?></label>
                    <textarea id="caption-<?= e($code) ?>" name="caption_<?= e($code) ?>" rows="2"><?= e($meta[$code]['caption'] ?? '') ?></textarea>
                    <?= field_hint('hint.media.caption') ?>
                </div>
            </fieldset>
<?php endforeach; ?>
            <button type="submit" class="button"><?= e(t('media.save')) ?></button>
        </form>
