<?php

use App\Modules\Appearance\Overrides;
use App\Modules\Design\Palette;
use App\Modules\Design\Tokens;
use App\Support\Controls;

/**
 * Colours (PLAN.md D-157): the main colour and the second, the palette, and the contrast
 * check. Included by appearance.php inside its section, with the closures of
 * parts/controls.php in scope.
 *
 * @var array<string, string> $decisions
 * @var array<string, string> $colors
 * @var array<string, string> $dark the owner's dark colours, '' for none (D-187)
 * @var array<string, string> $darkColors the palette as dark mode draws it
 * @var list<array{pair: string, decision: string, ratio: float, required: float, passes: bool, foreground: string, background: string}> $pairs
 * @var list<string> $changed
 * @var Closure(string): string $error
 * @var Closure(string, array<array-key, string>): string $segmented
 * @var Closure(string): string $control
 * @var Closure(string, list<string>): array<string, string> $labels
 * @var Closure(string, string): string $swatch
 * @var Closure(string, string, array<string, mixed>=): array<string, mixed> $rowOptions
 * @var Closure(string, string, string, bool=): string $group
 */

/**
 * One row of the contrast check (D-058): the pair as it would actually look, its name, and
 * what it measures. An SVG rather than a styled box: the admin may hold no site colour and
 * no style attribute.
 *
 * @param array{pair: string, ratio: float, passes: bool, foreground: string, background: string} $pair
 */
$gaugeRow = static function (array $pair): string {
    return '<li class="gauge-row' . ($pair['passes'] ? '' : ' gauge-fails') . '" data-pair="' . e($pair['pair']) . '">'
        . '<svg class="gauge-sample" viewBox="0 0 28 18" aria-hidden="true">'
        . '<rect width="28" height="18" fill="' . e($pair['background']) . '" data-pair-background/>'
        . '<text x="14" y="13" text-anchor="middle" font-size="11" fill="' . e($pair['foreground']) . '" data-pair-foreground>' . e(t('design.contrast.sample')) . '</text>'
        . '</svg>'
        . '<span class="gauge-name">' . e(t('design.pair.' . $pair['pair'])) . '</span>'
        . '<span class="gauge-ratio"><span data-pair-ratio>' . e(number_format($pair['ratio'], 2)) . '</span>:1'
        . ' <span class="gauge-verdict" data-pair-verdict data-pass="' . e(t('design.contrast.pass')) . '" data-fail="' . e(t('design.contrast.fail')) . '">'
        . e(t($pair['passes'] ? 'design.contrast.pass' : 'design.contrast.fail')) . '</span></span>'
        . '</li>';
};

/**
 * One role of the palette. Those the owner may take (Palette::BY_HAND) carry a colour input
 * that IS the swatch, a switch that says it is meant, and the button that gives it back; the
 * dot says it is set by hand, not that it differs from anything (D-158). The rest are worked
 * out and say so.
 */
$roleRow = static function (string $name, string $hex) use ($decisions, $error, $swatch, $changed): string {
    if (!in_array($name, Palette::BY_HAND, true)) {
        return '<li class="role">' . $swatch($hex, $name)
            . '<span class="role-name" title="' . e(t('design.color.' . $name)) . '">' . e(t('design.color.' . $name)) . '</span>'
            . '<code class="role-value" data-swatch-value="' . e($name) . '">' . e($hex) . '</code>'
            . '<span class="role-derived">' . e(t('design.by_hand.computed')) . '</span></li>';
    }
    $field = 'color_' . $name;
    $id = 'design-' . $field;
    $taken = $decisions[$field] !== '';
    $free = t('design.by_hand.free', ['role' => t('design.color.' . $name)]);

    return '<li class="role' . (in_array($field, $changed, true) ? ' is-changed' : '') . '" data-control="' . e($field) . '" data-kind="by_hand" data-default="">'
        . '<input type="color" class="role-swatch" id="' . e($id) . '" name="' . e($field) . '" value="' . e($taken ? $decisions[$field] : $hex) . '"'
        . ' data-by-hand="' . e($field) . '" aria-label="' . e(t('design.color.' . $name)) . '">'
        . '<span class="role-name" aria-hidden="true" title="' . e(t('design.color.' . $name)) . '">' . e(t('design.color.' . $name)) . ' <span class="control-changed"></span></span>'
        // Both handles, and they never disagree: data-colour-for follows the hand,
        // data-swatch-value is where the server's answer is written (D-063).
        . '<code class="role-value" data-colour-for="' . e($id) . '" data-swatch-value="' . e($name) . '">' . e($taken ? $decisions[$field] : $hex) . '</code>'
        . '<input type="checkbox" name="' . e($field) . '_on" value="1"' . ($taken ? ' checked' : '') . ' data-by-hand-switch="' . e($field) . '" tabindex="-1" aria-hidden="true">'
        . '<button type="submit" form="design-form" name="action" value="colour:free:' . e($name) . '" class="icon-button role-free" title="' . e($free) . '">'
        . icon('history') . '<span class="visually-hidden">' . e($free) . '</span></button>'
        . $error($field)
        . '</li>';
};

/**
 * THE SAME ROLE IN DARK (D-187, the owner): shown instead of the light row while Mode is Dark.
 * A colour picked here is the owner's for dark mode only, and says so; "Use light value" gives
 * it up, and dark mode draws the colour held for both modes, the character's dark version or
 * the palette's. Its own field and switch, `dark_color_<role>`, so light mode keeps its own.
 */
$darkRow = static function (string $name) use ($dark, $darkColors, $error, $changed): string {
    $key = 'color_' . $name;
    $field = 'dark_' . $key;
    $id = 'design-' . $field;
    $taken = ($dark[$key] ?? '') !== '';
    $value = $taken ? $dark[$key] : ($darkColors[$name] ?? '#000000');
    $light = t('design.dark.use_light', ['role' => t('design.color.' . $name)]);

    return '<li class="role role-dark' . (in_array($field, $changed, true) ? ' is-changed' : '') . '" data-control="' . e($field) . '" data-kind="by_hand" data-default="">'
        . '<input type="color" class="role-swatch" id="' . e($id) . '" name="' . e($field) . '" value="' . e($value) . '"'
        . ' data-by-hand="' . e($field) . '" aria-label="' . e(t('design.color.' . $name)) . '">'
        . '<span class="role-name" aria-hidden="true" title="' . e(t('design.color.' . $name)) . '">' . e(t('design.color.' . $name))
        . ' <span class="role-only-dark">' . e(t('design.dark.only')) . '</span> <span class="control-changed"></span></span>'
        . '<code class="role-value" data-colour-for="' . e($id) . '" data-swatch-value="' . e($name) . '">' . e($value) . '</code>'
        . '<input type="checkbox" name="' . e($field) . '_on" value="1"' . ($taken ? ' checked' : '') . ' data-by-hand-switch="' . e($field) . '" tabindex="-1" aria-hidden="true">'
        . '<button type="submit" form="design-form" name="action" value="colour:light:' . e($name) . '" class="icon-button role-free" title="' . e($light) . '">'
        . icon('history') . '<span class="visually-hidden">' . e($light) . '</span></button>'
        . $error($field)
        . '</li>';
};

$failing = array_values(array_filter($pairs, static fn (array $pair): bool => !$pair['passes']));
// What "Fix automatically" can reach: a failing pair a colour by hand is blamed for (D-160).
$freeable = array_filter($failing, static fn (array $pair): bool => Overrides::kind($pair['decision']) === 'by_hand');
$seedBound = count($failing) - count($freeable);

ob_start();
?>
                    <?= Controls::row(t('design.seed'), '<div class="colour-field">'
                        . '<input type="color" class="colour-input" id="design-seed" name="seed" value="' . e($decisions['seed']) . '">'
                        . '<output class="colour-value" for="design-seed" data-colour-for="design-seed">' . e($decisions['seed']) . '</output></div>',
                        $rowOptions('seed', t('design.seed'), ['for' => 'design-seed', 'hint' => '<span class="hint">' . e(t('design.seed_hint')) . '</span>'])) ?>
                    <?php /* Light or dark, for the whole site (D-164, the owner's choice): the
                             same palette walked the other way, checked by the same pairs. */ ?>
                    <?= $control('mode') ?>
                    <?php /* The second colour is two controls and one decision: the switch says there
                             is one, and the picker below it, shown only while the switch is on,
                             says which. Without the switch a colour input always posts a colour. */ ?>
                    <?= Controls::row(t('design.secondary'), '<label class="checkbox"><input type="checkbox" name="use_secondary" value="1"' . ($decisions['secondary'] !== '' ? ' checked' : '') . '> <span>' . e(t('design.use_secondary')) . '</span></label>'
                        . '<div class="colour-field when-secondary">'
                        . '<input type="color" class="colour-input" id="design-secondary" name="secondary" value="' . e($decisions['secondary'] !== '' ? $decisions['secondary'] : $colors['contrast']) . '" aria-label="' . e(t('design.secondary')) . '">'
                        . '<output class="colour-value" for="design-secondary" data-colour-for="design-secondary">' . e($decisions['secondary'] !== '' ? $decisions['secondary'] : $colors['contrast']) . '</output></div>',
                        $rowOptions('secondary', t('design.secondary'), ['hint' => field_hint('hint.design.secondary'), 'attributes' => ['data-kind' => 'secondary']])) ?>
                    <?= $control('surface_contrast') ?>
<?php $basics = (string) ob_get_clean(); ob_start(); ?>
                    <div class="palette-head">
                        <span class="hint hint-always"><?= e(t('inspector.palette_note')) ?></span>
                        <?php /* Shown only while a colour is the owner's, by the stylesheet: the
                                 switches flip under the hand, faster than a round trip. */ ?>
                        <button type="submit" form="design-form" name="action" value="colour:free" class="button button-quiet palette-reset"><?= e(t('design.by_hand.free_all')) ?></button>
                    </div>
                    <ul class="roles roles-palette" role="list" aria-label="<?= e(t('design.palette')) ?>">
<?php foreach (Palette::BY_HAND as $name): ?>
                        <?= $roleRow($name, $colors[$name]) ?>
                        <?= $darkRow($name) ?>
<?php endforeach; ?>
                    </ul>
                    <p class="hint role-dark-hint"><?= e(t('design.dark.hint')) ?></p>
                    <?php /* The roles nobody sets by hand are worked out from the ones above:
                             there, for whoever wants to read them, and out of the way. */ ?>
                    <details class="roles-more">
                        <summary><?= e(t('inspector.palette_all', ['count' => count($colors) - count(Palette::BY_HAND)])) ?></summary>
                        <ul class="roles" role="list">
<?php foreach ($colors as $name => $hex): ?>
<?php if (!in_array($name, Palette::BY_HAND, true)): ?>
                            <?= $roleRow($name, $hex) ?>
<?php endif; ?>
<?php endforeach; ?>
                        </ul>
                    </details>
<?php $palette = (string) ob_get_clean(); ob_start(); ?>
                    <?php /* THE CHECK IN ONE LINE (D-160). Every pair is still measured and listed —
                             a palette that passes by a hair should not look like one that passes
                             easily (D-058) — but the list stands behind the verdict rather than in
                             front of it, open by itself when something fails. */ ?>
                    <div class="contrast" data-gauge data-contrast>
                        <p class="contrast-ok" data-contrast-ok<?= $failing === [] ? '' : ' hidden' ?>><?= icon('check') ?>
                            <span><?= e(t('inspector.contrast.ok')) ?></span>
                            <span class="contrast-tally" data-contrast-tally><?= e(count($pairs) . '/' . count($pairs)) ?></span></p>
                        <div class="contrast-fails" data-contrast-fails<?= $failing === [] ? ' hidden' : '' ?> role="status">
                            <p><?= icon('circle-alert') ?> <span data-contrast-count data-one="<?= e(t('inspector.contrast.fail_one')) ?>" data-many="<?= e(t('inspector.contrast.fail_many')) ?>"><?= e(t(count($failing) === 1 ? 'inspector.contrast.fail_one' : 'inspector.contrast.fail_many', ['count' => count($failing)])) ?></span></p>
                            <button type="submit" form="design-form" name="action" value="colour:free:failing" class="button button-secondary" data-contrast-fix<?= $freeable === [] ? ' hidden' : '' ?>><?= e(t('inspector.contrast.fix')) ?></button>
                            <p class="hint hint-always" data-contrast-seed<?= $seedBound > 0 ? '' : ' hidden' ?>><?= e(t('inspector.contrast.change_seed')) ?></p>
                        </div>
                        <details class="gauge-more"<?= $failing === [] ? '' : ' open' ?>>
                            <summary><?= e(t('inspector.contrast.every', ['count' => count($pairs)])) ?></summary>
                            <span class="hint"><?= e(t('design.contrast.intro')) ?></span>
                            <ul class="gauge-list">
<?php foreach ($pairs as $pair): ?>
                                <?= $gaugeRow($pair) ?>
<?php endforeach; ?>
                            </ul>
                        </details>
                    </div>
<?php $contrast = (string) ob_get_clean(); ?>
                <?= $group('colours', 'basics', $basics) ?>
                <?= $group('colours', 'palette', $palette) ?>
                <?= $group('colours', 'contrast', $contrast) ?>
