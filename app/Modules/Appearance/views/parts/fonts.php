<?php

use App\Modules\Design\Fonts;
use App\Modules\Design\Typography;
use App\Modules\Design\Vocabulary\Decisions;
use App\Support\Controls;

/**
 * THE TWO FAMILIES AND THE PAIRINGS (PLAN.md D-185). Required by parts/controls.php, in its scope.
 *
 * A PICKER FOR EACH FAMILY: the one chosen, set in its own face, and opened, the library
 * grouped by category with a search over it, every name set in its face — the one place the
 * admin shows the site's fonts, because they are what is being chosen (the faces come from
 * /admin/appearance/typefaces). Radio inputs inside a <details>, so it submits with no script.
 * Each option carries what a heading or a paragraph in it is when left alone, for the script
 * that moves the sliders still following the family (appearance-fonts.js).
 *
 * THE PAIRINGS ARE SHORTCUTS: each sets both families and the treatment that goes with them.
 * Buttons for that script, not fields, so they need it (js-only).
 *
 * @var array<string, string> $decisions
 * @var list<string> $pinned the keys following a family that the character pins
 * @var Closure(string, string, array<string, mixed>=): array<string, mixed> $rowOptions
 */
$fontPicker = static function (string $key) use ($decisions, $rowOptions, $pinned): string {
    $id = 'design-' . $key;
    $label = t('design.' . $key);
    $chosen = Fonts::known($decisions[$key] ?? '');
    $sample = static fn (string $font): string => '<span class="font-sample" data-font="' . e($font) . '">' . e(Fonts::ALL[$font]['name']) . '</span>';
    $html = '<details class="font-picker" data-font-picker>'
        . '<summary>' . $sample($chosen) . '<span class="font-category" data-font-category>' . e(t('design.font_category.' . Fonts::ALL[$chosen]['category'])) . '</span></summary>'
        . '<div class="font-panel">'
        . '<input type="search" class="font-search" data-font-search placeholder="' . e(t('design.font_search')) . '" aria-label="' . e(t('design.font_search')) . '">'
        . '<div class="font-groups" role="radiogroup" aria-labelledby="' . e($id) . '-label">';
    foreach (Fonts::CATEGORIES as $category) {
        $html .= '<div class="font-group" data-font-group><p class="font-group-name">' . e(t('design.font_category.' . $category)) . '</p>';
        foreach (Fonts::ALL as $font => $definition) {
            if ($definition['category'] !== $category) {
                continue;
            }
            $follows = $key === 'heading_font'
                ? ['heading_weight' => Decisions::fromFont('heading_weight', $font, ''), 'tracking' => Decisions::fromFont('tracking', $font, ''), 'caps' => Decisions::fromFont('caps', $font, '')]
                : ['line_height' => Decisions::fromFont('line_height', 'inter', $font)];
            $follows = array_diff_key($follows, array_flip($pinned));
            $html .= '<label class="font-option" data-font-name="' . e(strtolower($definition['name'])) . '">'
                . '<input type="radio" name="' . e($key) . '" value="' . e($font) . '"' . ($font === $chosen ? ' checked' : '')
                . ' data-category="' . e(t('design.font_category.' . $category)) . '" data-follows="' . e((string) json_encode($follows)) . '">'
                . $sample($font) . '</label>';
        }
        $html .= '</div>';
    }
    $html .= '</div></div></details>';

    return Controls::row($label, $html, $rowOptions($key, $label, ['labelId' => $id . '-label']));
};

$pairingTiles = static function () use ($decisions): string {
    $current = Typography::pairingOf(Fonts::known($decisions['heading_font'] ?? ''), Fonts::known($decisions['body_font'] ?? ''));
    $html = '<div class="pairings js-only" role="group" aria-label="' . e(t('design.pairings')) . '">';
    foreach (Typography::PAIRINGS as $name => $pairing) {
        $heading = Fonts::ALL[$pairing['heading']]['name'];
        $body = Fonts::ALL[$pairing['body']]['name'];
        $html .= '<button type="button" class="pairing-tile" data-pairing="' . e((string) json_encode(Typography::pairing($name))) . '" aria-pressed="' . ($name === $current ? 'true' : 'false') . '">'
            . '<span class="font-sample pairing-sample" data-font="' . e($pairing['heading']) . '" aria-hidden="true">Aa</span>'
            . '<span class="pairing-name">' . e(t('design.typography.' . $name)) . '<span>' . e($heading === $body ? $heading : $heading . ' / ' . $body) . '</span></span></button>';
    }

    return $html . '</div>';
};
