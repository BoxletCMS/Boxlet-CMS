<?php

use App\Modules\Appearance\Overrides;
use App\Modules\Design\Vocabulary\Decisions;
use App\Modules\Settings\ChromeLook;
use App\Support\Controls;

/**
 * THE APPEARANCE SCREEN'S CONTROLS, as this screen asks them (PLAN.md D-157). Required by
 * appearance.php, in its scope, before anything that draws a control.
 *
 * Only the GLUE is here: which value a control holds, what the character gives it, whether
 * it is changed, and the action that puts it back. How a row, a slider or a group is drawn
 * is App\Support\Controls and segmented_group(), which the page builder will call as well and
 * which know nothing of designs. They were closures in this view until the second caller
 * was announced; a part another screen needs cannot live inside this one's template.
 *
 * @var array<string, string> $decisions
 * @var array<string, string> $errors
 * @var array<string, string> $readouts
 * @var array<string, string> $defaults what each control is when the owner has not made it theirs
 * @var list<string> $changed the keys the owner has made theirs (D-158)
 * @var array<string, string> $look
 * @var array<string, string> $characterLook
 * @var array<string, string> $colors
 * @var array<string, string> $dark the owner's dark colours, '' for none (D-187)
 * @var array<string, string> $darkColors the palette as dark mode draws it
 */
$error = static fn (string $key): string => isset($errors[$key])
    ? '<p class="field-error" data-error-for="' . e($key) . '" role="alert">' . e($errors[$key]) . '</p>'
    : '<p class="field-error" data-error-for="' . e($key) . '" hidden></p>';

$labels = static function (string $key, array $values): array {
    $result = [];
    foreach ($values as $value) {
        $result[$value] = t('design.' . $key . '.' . $value);
    }

    return $result;
};

$swatch = static fn (string $hex, string $name): string => '<svg viewBox="0 0 10 10" aria-hidden="true">'
    . '<rect width="10" height="10" fill="' . e($hex) . '" data-swatch="' . e($name) . '"/></svg>';

/**
 * What every row of this screen shares: the key a script keeps live, what the character
 * gives, whether it is the owner's, and the button that puts it back — `reset:<key>`, a
 * plain submit of the one form, so it works with no script (D-158).
 *
 * @param array<string, mixed> $more more of Controls::row()'s options
 * @return array<string, mixed>
 */
$rowOptions = static function (string $key, string $label, array $more = []) use ($defaults, $changed, $error): array {
    return $more + [
        'key' => $key,
        'default' => $defaults[$key] ?? null,
        'changed' => in_array($key, $changed, true),
        'reset' => ['form' => 'design-form', 'name' => 'action', 'value' => 'reset:' . $key, 'title' => t('inspector.reset.one', ['name' => $label])],
        'hint' => field_hint('hint.design.' . $key),
        'error' => $error($key),
        'attributes' => ['data-kind' => Overrides::kind($key)],
    ];
};

/**
 * A CLOSED SET IS A ROW OF BUTTONS, NOT A DROPDOWN (PLAN.md D-065): every answer visible at
 * rest, the current one visibly current, one press to choose. Radio inputs, so it submits
 * without a script and arrow keys move inside it.
 *
 * @param array<array-key, string> $labels value => what it is called. array-key, not string:
 *        the heading weights are their own labels and PHP turns '600' into 600.
 */
$segmented = static function (string $key, array $labels) use ($decisions, $readouts, $rowOptions): string {
    $id = 'design-' . $key;
    $label = t('design.' . $key);
    $options = $rowOptions($key, $label, [
        'labelId' => $id . '-label',
        'readout' => $readouts[$key] ?? '',
        'readoutKey' => isset($readouts[$key]) ? $key : '',
    ]);

    return Controls::row($label, segmented_group($key, $labels, $decisions[$key] ?? '', $id . '-label', $id . '-'), $options);
};

/**
 * A decision that is a NUMBER: a slider, with what it comes to beside its name (D-062,
 * D-066). The readout is the number a person can picture, not the one the CSS is in.
 *
 * @param array<array-key, string> $marks value => name, under the slider
 */
$slider = static function (string $key, float $min, float $max, float $step, array $marks = [], string $labelKey = '') use ($decisions, $look, $readouts, $rowOptions): string {
    $id = 'design-' . $key;
    $isLook = in_array($key, ChromeLook::keys(), true);
    $name = $isLook ? ChromeLook::field($key) : $key;
    $label = t($labelKey !== '' ? $labelKey : 'design.' . $key);
    $options = $rowOptions($key, $label, [
        'for' => $id,
        'readout' => $readouts[$key] ?? '',
        'readoutKey' => $key,
        'readoutFor' => $id,
        'following' => true,
    ]);

    return Controls::row($label, Controls::slider($name, $id, ($isLook ? $look : $decisions)[$key] ?? '', $min, $max, $step, $marks, ['data-slider-for' => $id . '-value']), $options);
};
require __DIR__ . '/pictograms.php';
/** @var Closure(string, string): string $pictogram */

/**
 * ONE CHROME CHOICE AS A ROW OF BUTTONS (D-032, D-065), WITH NO "FOLLOW" BUTTON (D-159) —
 * or, for how the header and the footer are arranged, as tiles with a drawing of each
 * answer, three to a row, as the mockup has them (D-161).
 *
 * '' is still stored for "as the character has it", and it is SHOWN as the character's own
 * answer, pressed: that is what the header is. Changing the character re-dresses every part
 * left that way. The dot says which the owner chose, and the reset gives one back.
 */
$lookGroup = static function (string $choice) use ($look, $characterLook, $rowOptions, $pictogram, &$slider): string {
    // A number of the header's (its height, the logo's size, how see-through) is a slider.
    $definition = Decisions::ALL[$choice];
    if ($definition['type'] === 'number') {
        $marks = [];
        foreach ($definition['marks'] ?? [] as $mark => $value) {
            $marks[(string) $value] = t('chrome.look.' . $choice . '.' . $mark);
        }

        return $slider($choice, (float) $definition['min'], (float) $definition['max'], (float) $definition['step'], $marks, 'chrome.look.' . $choice);
    }
    $field = ChromeLook::field($choice);
    $label = t('chrome.look.' . $choice);
    $labels = [];
    foreach (Decisions::ALL[$choice]['values'] ?? [] as $option) {
        $labels[$option] = t('chrome.look.' . $choice . '.' . $option);
    }
    $current = ($look[$choice] ?? '') !== '' ? $look[$choice] : ($characterLook[$choice] ?? '');
    $options = $rowOptions($choice, $label, ['labelId' => $field . '-label', 'hint' => field_hint('hint.look.' . $choice), 'error' => '']);

    if ($pictogram($choice, $current) !== '') {
        $tiles = [];
        foreach ($labels as $option => $name) {
            $tiles[$option] = ['label' => $name, 'picture' => $pictogram($choice, (string) $option)];
        }

        return Controls::row($label, Controls::tiles($field, $tiles, $current, $field . '-label', $field . '-'), $options);
    }

    return Controls::row($label, segmented_group($field, $labels, $current, $field . '-label', $field . '-'), $options);
};

/**
 * OR A COLOUR OF YOUR OWN (PLAN.md D-076, D-111): one more answer to the question above it,
 * drawn as a role of the palette (D-074) — the swatch is the picker, the hex follows the
 * hand, one button gives it back. A colour input always carries SOME colour, so the switch
 * is what says it is meant; choosing flips it (appearance.js). Changed means set (D-158).
 *
 * WHILE IT IS NOT THE OWNER'S, the input holds the shade that place has as the page was
 * rendered — a starting point for the picker — and the row says "palette" where the hex
 * would be, because that hex does not follow the palette live.
 */
$ownColour = static function (string $key) use ($decisions, $error, $colors, $look, $characterLook, $changed, $dark, $darkColors): string {
    $id = 'design-' . $key;
    $taken = ($decisions[$key] ?? '') !== '';
    $label = t('design.' . $key);
    if ($key === 'page_background_colour') {
        $showing = $colors[$decisions['page_background']] ?? $colors['surface'];
    } else {
        $part = str_replace('_colour', '_surface', $key);
        $surface = ($look[$part] ?? '') !== '' ? $look[$part] : ($characterLook[$part] ?? 'plain');
        $showing = $colors[['plain' => 'background', 'tinted' => 'surface', 'contrast' => 'contrast', 'gradient' => 'gradient-start'][$surface] ?? 'background'];
        $showingDark = $darkColors[['plain' => 'background', 'tinted' => 'surface', 'contrast' => 'contrast', 'gradient' => 'gradient-start'][$surface] ?? 'background'] ?? $showing;
    }
    $free = t('design.by_hand.free', ['role' => $label]);
    // ITS DARK ROW (D-187): the header's and footer's own colours are colours by hand too, and
    // follow the palette's roles — shown instead of the light row while Mode is Dark.
    $darkRow = '';
    if (in_array($key, \App\Modules\Design\Vocabulary\Decisions::DARK_OWN, true)) {
        $field = 'dark_' . $key;
        $own = ($dark[$key] ?? '') !== '';
        $value = $own ? $dark[$key] : ($showingDark ?? $showing);
        $light = t('design.dark.use_light', ['role' => $label]);
        $darkRow = '<li class="role role-dark' . (in_array($field, $changed, true) ? ' is-changed' : '') . '" data-control="' . e($field) . '" data-kind="by_hand" data-default="">'
            . '<input type="color" class="role-swatch" id="design-' . e($field) . '" name="' . e($field) . '" value="' . e($value) . '" data-by-hand="' . e($field) . '" aria-label="' . e($label) . '">'
            . '<span class="role-name" aria-hidden="true" title="' . e($label) . '">' . e($label) . ' <span class="role-only-dark">' . e(t('design.dark.only')) . '</span> <span class="control-changed"></span></span>'
            . '<code class="role-value" data-colour-for="design-' . e($field) . '">' . e($value) . '</code>'
            . '<span class="role-derived">' . e(t('design.by_hand.palette')) . '</span>'
            . '<input type="checkbox" name="' . e($field) . '_on" value="1"' . ($own ? ' checked' : '') . ' data-by-hand-switch="' . e($field) . '" tabindex="-1" aria-hidden="true">'
            . '<button type="submit" form="design-form" name="action" value="colour:light:' . e($key) . '" class="icon-button role-free own-colour-free" title="' . e($light) . '">'
            . icon('history') . '<span class="visually-hidden">' . e($light) . '</span></button>'
            . $error($field)
            . '</li>';
    }

    return '<div class="field own-colour' . ($taken ? ' own-colour-taken' : '') . '">'
        . '<ul class="roles' . ($darkRow !== '' ? ' roles-palette' : '') . '" role="list"><li class="role' . (in_array($key, $changed, true) ? ' is-changed' : '') . '" data-control="' . e($key) . '" data-kind="by_hand" data-default="">'
        . '<input type="color" class="role-swatch" id="' . e($id) . '" name="' . e($key) . '"'
        . ' value="' . e($taken ? $decisions[$key] : $showing) . '" data-by-hand="' . e($key) . '" aria-label="' . e($label) . '">'
        . '<span class="role-name" aria-hidden="true" title="' . e($label) . '">' . e($label) . ' <span class="control-changed"></span></span>'
        . '<code class="role-value" data-colour-for="' . e($id) . '">' . e($taken ? $decisions[$key] : $showing) . '</code>'
        . '<span class="role-derived">' . e(t('design.by_hand.palette')) . '</span>'
        . '<input type="checkbox" name="' . e($key) . '_on" value="1"' . ($taken ? ' checked' : '')
        . ' data-by-hand-switch="' . e($key) . '" tabindex="-1" aria-hidden="true">'
        . '<button type="submit" form="design-form" name="action" value="colour:free:' . e($key) . '"'
        . ' class="icon-button role-free own-colour-free" title="' . e($free) . '">'
        . icon('history') . '<span class="visually-hidden">' . e($free) . '</span></button>'
        . '</li>' . $darkRow . '</ul>'
        . field_hint('hint.design.' . $key)
        . $error($key)
        . '</div>';
};

/**
 * One group of a section, as Controls::group() draws it, with the count of what the owner
 * changed in it. Open unless it is one that starts closed and holds no error (D-157): a
 * message inside a closed group is a message nobody reads.
 */
$group = static function (string $section, string $name, string $body, bool $open = true) use ($changed, $errors): string {
    $keys = Overrides::SECTIONS[$section][$name] ?? [];
    $hasError = array_intersect($keys, array_keys($errors)) !== [];

    return Controls::group('group-' . $section . '-' . $name, t('inspector.group.' . $section . '.' . $name), $body, [
        'open' => $open || $hasError,
        'changed' => Overrides::count($changed, $section, $name),
    ]);
};

require __DIR__ . '/fonts.php';
/** @var Closure(string): string $fontPicker */
/** @var Closure(): string $pairingTiles */

/**
 * ANY GLOBAL DECISION AS ITS CONTROL (D-164), from the one table that defines it: a number
 * is a slider with its named steps as marks under it, a closed set a row of segments (a look
 * choice through $lookGroup, which draws arrangements as tiles). Colours have rows of their own.
 */
$control = static function (string $key) use (&$slider, &$segmented, &$lookGroup): string {
    if (in_array($key, ChromeLook::keys(), true)) {
        return $lookGroup($key);
    }
    $definition = Decisions::ALL[$key];
    if ($definition['type'] === 'number') {
        $marks = [];
        foreach ($definition['marks'] ?? [] as $mark => $value) {
            $marks[(string) $value] = t('design.' . $key . '.' . $mark);
        }

        return $slider($key, (float) $definition['min'], (float) $definition['max'], (float) $definition['step'], $marks);
    }
    $labels = [];
    foreach ($definition['values'] ?? [] as $value) {
        $labels[$value] = t('design.' . $key . '.' . $value);
    }

    return $segmented($key, $labels);
};
