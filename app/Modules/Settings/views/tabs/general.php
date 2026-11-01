<?php

use App\Modules\Media\MediaReference;
use App\Support\Url;

/**
 * GENERAL (PLAN.md D-028, D-294): the site's name, its time, the credit line, and the pictures
 * that stand for it. One form and one Save, as before the tabs. Required by settings.php.
 *
 * @var array<string, mixed> $values
 * @var array<string, string> $errors
 * @var list<array{id: int, name: string, thumb: string|null, whole: string|null}> $pictures
 * @var list<string> $timezones
 * @var array{logo: array{url: string, width: int, height: int}|null, logo_dark: array{url: string, width: int, height: int}|null} $logoSvg
 * @var string|null $lastSaved when these settings were last saved, from the activity log
 * @var string $csrf
 */
$error = static fn (string $key): string => isset($errors[$key])
    ? '<p class="field-error" role="alert">' . e($errors[$key]) . '</p>'
    : '';

$text = static fn (string $key): string => is_string($values[$key] ?? null) ? $values[$key] : '';
$picked = static fn (string $key): int => is_int($values[$key] ?? null) ? $values[$key] : 0;

/** A picture chooser. Without JavaScript this select IS the control, as in the block editor. */
$picker = static function (string $key, int $chosen, bool $whole = false) use ($pictures): string {
    // A logo keeps its shape (D-038): its preview is the uncropped picture, fitted rather
    // than filled, where every other picker shows the square thumbnail.
    $html = '<select id="' . e($key) . '" name="' . e($key) . '" data-media-field'
        . ($whole ? ' data-picker-whole' : '')
        . MediaReference::pickerAttributes() . '>';
    $html .= '<option value="">' . e(t('pages.field.media_none')) . '</option>';
    foreach ($pictures as $picture) {
        $preview = $whole ? ($picture['whole'] ?? $picture['thumb']) : $picture['thumb'];
        $html .= '<option value="' . e($picture['id']) . '"'
            . ($preview === null ? '' : ' data-thumb="' . e($preview) . '"')
            . ($chosen === $picture['id'] ? ' selected' : '') . '>'
            . e($picture['name']) . '</option>';
    }

    return $html . '</select>';
};

/* An SVG in place of the picture (D-142). Its controls stand here, under the picker they
   stand in for, but belong to a form of their own after this one (form="…"): the upload is
   a file, cleaned the moment it arrives, and forms cannot nest. */
$vector = static function (string $slot) use ($logoSvg): string {
    $form = 'logo-svg-' . $slot;
    $current = $logoSvg[$slot] ?? null;
    $html = '<div class="logo-svg"><span class="logo-svg-label">' . e(t('svg.or')) . '</span>';
    if ($current !== null) {
        $html .= '<span class="logo-svg-current"><img src="' . e($current['url']) . '" alt="" width="' . $current['width']
            . '" height="' . $current['height'] . '"><span class="hint">' . e(t('svg.current')) . '</span></span>'
            . '<button type="submit" form="' . e($form) . '" name="action" value="remove" class="button button-ghost button-danger">'
            . e(t('svg.remove')) . '</button>';
    }
    /* The browser's own file control speaks the browser's language and cannot be styled, so
       it is hidden and a label in the admin's words opens it, as the media library does
       (D-038). Choosing a file sends it (file-sends.js); without a script, Upload does. */
    $input = 'logo-svg-file-' . $slot;
    $html .= '<span class="logo-svg-upload">'
        . '<input type="file" id="' . e($input) . '" name="svg" accept=".svg,image/svg+xml" form="' . e($form) . '" class="visually-hidden" data-file-sends>'
        . '<label for="' . e($input) . '" class="button button-secondary">' . icon('cloud-upload') . ' ' . e(t($current === null ? 'svg.upload' : 'svg.replace')) . '</label>'
        . '<button type="submit" form="' . e($form) . '" name="action" value="upload" class="button no-js-only">' . e(t('svg.send')) . '</button>'
        . '</span>';

    return $html . '<span class="hint">' . e(t('svg.hint')) . '</span></div>';
};
?>
        <form method="post" action="<?= e(Url::admin('settings')) ?>" class="settings-form">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

            <?php /* A LEDGER: each setting a row, its name and what it does on the left, the
                     control on the right, so the eye can run down the names alone. */ ?>
            <div class="panel stack ledger" id="general">
                <h2><?= e(t('settings.general')) ?></h2>
                <div class="field">
                    <label for="site_name"><?= e(t('settings.site_name')) ?></label>
                    <input type="text" id="site_name" name="site_name" maxlength="120"
                           value="<?= e($text('site_name')) ?>" aria-describedby="site_name-hint">
                    <span class="hint" id="site_name-hint"><?= e(t('settings.site_name_hint')) ?></span>
                </div>

                <div class="field">
                    <label for="timezone"><?= e(t('settings.timezone')) ?></label>
                    <select id="timezone" name="timezone" aria-describedby="timezone-hint">
<?php foreach ($timezones as $zone): ?>
                        <option value="<?= e($zone) ?>"<?= $text('timezone') === $zone ? ' selected' : '' ?>><?= e($zone) ?></option>
<?php endforeach; ?>
                    </select>
                    <span class="hint" id="timezone-hint"><?= e(t('settings.timezone_hint')) ?></span>
                    <?= $error('timezone') ?>
                </div>

                <?php /* One line in the site's own footer (O-20). Off unless the owner asks:
                         what a visitor reads belongs to them, not to Boxlet. */ ?>
                <div class="field">
                    <label class="checkbox"><input type="checkbox" name="site_credit" value="1"<?= !empty($values['site_credit']) ? ' checked' : '' ?>> <span><?= e(t('settings.credit')) ?></span></label>
                    <span class="hint"><?= e(t('settings.credit_hint')) ?></span>
                </div>
            </div>

            <div class="panel stack ledger" id="branding">
                <h2><?= e(t('settings.branding')) ?></h2>
                <p class="hint"><?= e(t('settings.branding_intro')) ?></p>

                <div class="field">
                    <label for="site_logo"><?= e(t('settings.logo')) ?></label>
                    <?= $picker('site_logo', $picked('site_logo'), true) ?>
                    <span class="hint"><?= e(t('settings.logo_hint')) ?></span>
                    <?= $vector('logo') ?>
                </div>

                <?php /* A second logo for dark surfaces (D-112): a dark wordmark vanishes on a
                         contrast header or over a dark hero, and no CSS can fix a picture.
                         Chosen by the renderer whenever the ink on the header is light. */ ?>
                <div class="field">
                    <label for="site_logo_dark"><?= e(t('settings.logo_dark')) ?></label>
                    <?= $picker('site_logo_dark', $picked('site_logo_dark'), true) ?>
                    <span class="hint"><?= e(t('settings.logo_dark_hint')) ?></span>
                    <?= $vector('logo_dark') ?>
                </div>

                <div class="field">
                    <label for="site_favicon"><?= e(t('settings.favicon')) ?></label>
                    <?= $picker('site_favicon', $picked('site_favicon')) ?>
                    <span class="hint"><?= e(t('settings.favicon_hint')) ?></span>
                </div>

                <div class="field">
                    <label for="site_share_image"><?= e(t('settings.share_image')) ?></label>
                    <?= $picker('site_share_image', $picked('site_share_image')) ?>
                    <span class="hint"><?= e(t('settings.share_image_hint')) ?></span>
                </div>
            </div>

            <div class="form-actions settings-save">
                <button type="submit" class="button"><?= e(t('settings.save')) ?></button>
<?php if ($lastSaved !== null): ?>
                <span class="hint"><?= e(t('settings.last_saved', ['when' => $lastSaved])) ?></span>
<?php endif; ?>
            </div>
        </form>

        <?php /* The SVG logos' own forms (D-142), empty: their file inputs and buttons stand
                 under Branding above and name these by id. */ ?>
<?php foreach (App\Modules\Settings\LogoSvg::SLOTS as $slot): ?>
        <form method="post" action="<?= e(Url::admin('settings', 'logo-svg')) ?>" enctype="multipart/form-data" id="logo-svg-<?= e($slot) ?>" hidden>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="slot" value="<?= e($slot) ?>">
        </form>
<?php endforeach; ?>
