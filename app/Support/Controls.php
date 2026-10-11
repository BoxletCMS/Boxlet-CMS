<?php

namespace App\Support;

/**
 * THE ADMIN'S CONTROLS AS PARTS (PLAN.md D-157): a row with its name, a "changed" dot, what
 * the value comes to and a way back; a slider with marks under it; a group that folds.
 *
 * WRITTEN FOR THE APPEARANCE SCREEN AND NOT OWNED BY IT. Those were closures inside its view,
 * which only that view could call; the page builder that is rebuilt next asks the same
 * questions about a band — this value, is it the default, put it back — and must not reach
 * into another screen's template to do it, nor copy it and drift. So each part takes
 * everything it needs as arguments and knows nothing of designs, characters or the form it
 * sits in. segmented_group() (helpers.php, D-107) is the fourth part and stays where it is,
 * with the API the Section panel already uses.
 *
 * NOTHING HERE NEEDS A SCRIPT. A reset is an ordinary submit button carrying an action
 * value, a group is a <details>, and the dot is drawn by the server from the state it was
 * handed. A script may keep them live (appearance-overrides.js does), reading the same
 * attributes this prints: data-control, data-default, and the class `is-changed`.
 *
 * No style attribute anywhere: the admin's CSP forbids them, and the marks under a slider
 * are placed by SVG attributes rather than by a width written into the markup.
 * admin-controls.css draws all of it, and every admin page links that file.
 */
final class Controls
{
    /** The narrowest track a slider's marks stand under, in px: the builder's inspector. */
    private const MARK_TRACK = 300;
    /** What a character of a mark is taken to be, in px, at the marks' 11px — on the wide side. */
    private const MARK_CHARACTER = 6.4;
    /** Room kept between two marks, in px. */
    private const MARK_GAP = 6;

    /**
     * One control with its framing: the name, the dot, the readout and the reset on one line,
     * then the control itself, its hint and its error.
     *
     * @param string $label the control's name, plain text
     * @param string $control the control's own markup (a segmented group, a slider, …)
     * @param array<string, mixed> $options
     *   key (string): what data-control names, for a script that keeps the dot live;
     *   for: the id a <label for> points at; labelId: else the id of a plain name, for a group's
     *   aria-labelledby; readout: what the value comes to, in words; readoutKey: data-readout,
     *   so an answer from the server can rewrite it; readoutFor: makes the readout an <output>
     *   for that control; changed: draws the dot and offers the reset; default: printed as
     *   data-default, omitted when null; reset: the submit button that puts the value back;
     *   hint and error: markup already escaped, placed under the control; following: a
     *   slider whose value may be the character's, whose readout then says so (D-176).
     */
    public static function row(string $label, string $control, array $options = []): string
    {
        $text = static fn (string $name): string => is_scalar($options[$name] ?? null) ? (string) $options[$name] : '';
        $changed = ($options['changed'] ?? false) === true;
        $attributes = ['class' => 'control-row' . ($changed ? ' is-changed' : '') . ($text('class') !== '' ? ' ' . $text('class') : '')];
        if ($text('key') !== '') {
            $attributes['data-control'] = $text('key');
        }
        if (is_string($options['default'] ?? null)) {
            $attributes['data-default'] = $options['default'];
        }
        foreach (is_array($options['attributes'] ?? null) ? $options['attributes'] : [] as $name => $value) {
            $attributes[(string) $name] = is_scalar($value) ? (string) $value : '';
        }

        $html = '<div' . self::attributes($attributes) . '><div class="control-head">';
        if ($text('for') !== '') {
            $html .= '<label class="control-label" for="' . e($text('for')) . '">' . e($label) . '</label>';
        } else {
            $html .= '<span class="control-label"' . ($text('labelId') !== '' ? ' id="' . e($text('labelId')) . '"' : '') . '>' . e($label) . '</span>';
        }
        // THE DOT IS ALWAYS IN THE MARKUP and shown by the row's class, so a script that
        // learns the value moved has a class to set rather than an element to build. Its
        // words are for a screen reader: a dot alone is a colour somebody has to be taught.
        $html .= '<span class="control-changed" title="' . e(t('controls.changed')) . '"><span class="visually-hidden">' . e(t('controls.changed')) . '</span></span>';

        if ($text('readout') !== '' || $text('readoutKey') !== '') {
            // The whole phrase in the title as well: a readout gives way to its label (D-110),
            // onto a second line since D-195, never cut.
            $for = $text('readoutFor');
            $tag = $for !== '' ? 'output' : 'span';
            $html .= '<' . $tag . ' class="readout"'
                . ($text('readoutKey') !== '' ? ' data-readout="' . e($text('readoutKey')) . '"' : '')
                . ($for !== '' ? ' id="' . e($for) . '-value" for="' . e($for) . '"' : '')
                // "80 px · character" while the row has no dot: the word is added by CSS from
                // here, so the dot and the word come and go together, by the row's class.
                . (($options['following'] ?? false) === true ? ' data-from="' . e(t('controls.from_word')) . '"' : '')
                . ' title="' . e($text('readout')) . '">' . e($text('readout')) . '</' . $tag . '>';
        }

        $reset = $options['reset'] ?? null;
        if (is_array($reset)) {
            $part = static fn (string $name): string => is_string($reset[$name] ?? null) ? $reset[$name] : '';
            $html .= '<button type="submit" form="' . e($part('form')) . '" name="' . e($part('name')) . '" value="' . e($part('value')) . '"'
                . ' class="icon-button control-reset" title="' . e($part('title')) . '">'
                . icon('history') . '<span class="visually-hidden">' . e($part('title')) . '</span></button>';
        }

        return $html . '</div>' . $control . $text('hint') . $text('error') . '</div>';
    }

    /**
     * A number as a slider, with what it comes to shown by row(), and named marks under it.
     *
     * THE MARKS ARE WHERE THEIR VALUES ARE. A row of words spread evenly would put "Normal" in
     * the middle of a range whose normal is not in the middle, and a mark that points at the
     * wrong place is worse than none. They are SVG text at a percentage of the width, which
     * is an attribute and not a style, inset by the thumb's half-width in the stylesheet.
     *
     * A MARK THAT WOULD TOUCH ANOTHER IS LEFT OUT (D-171). Marks are where their values are,
     * so two near values wrote one word over the other ("SquaSubtle" under Corners). Placed
     * from the middle of the range outwards, a mark is drawn only if it clears every mark
     * already placed, at the narrowest track a slider stands in; the readout above still says
     * the value. The width of a word is estimated, since the server cannot measure it.
     *
     * @param array<array-key, string> $marks value => what it is called; values outside the
     *        range are left out. array-key: PHP turns the key '56' into 56
     * @param array<string, string> $attributes more attributes for the input
     */
    public static function slider(string $name, string $id, string $value, float $min, float $max, float $step, array $marks = [], array $attributes = []): string
    {
        $html = '<input' . self::attributes([
            'type' => 'range',
            'id' => $id,
            'name' => $name,
            'min' => self::number($min),
            'max' => self::number($max),
            'step' => self::number($step),
            'value' => $value,
        ] + $attributes) . '>';
        if ($marks === [] || $max <= $min) {
            return $html;
        }
        $placed = [];
        $middle = ($min + $max) / 2;
        $order = array_keys($marks);
        usort($order, static fn (int|string $a, int|string $b): int => abs((float) $a - $middle) <=> abs((float) $b - $middle));
        foreach ($order as $at) {
            $value = (float) $at;
            if ($value < $min || $value > $max) {
                continue;
            }
            $share = ($value - $min) / ($max - $min) * 100;
            // The two ends hang inward, so a word at 0% or 100% is not cut by the edge.
            $anchor = $share < 8 ? 'start' : ($share > 92 ? 'end' : 'middle');
            $width = mb_strlen($marks[$at]) * self::MARK_CHARACTER;
            $from = $share / 100 * self::MARK_TRACK - ($anchor === 'start' ? 0 : ($anchor === 'end' ? $width : $width / 2));
            foreach ($placed as [$left, $right]) {
                if ($from < $right + self::MARK_GAP && $from + $width + self::MARK_GAP > $left) {
                    continue 2;
                }
            }
            $placed[(string) $at] = [$from, $from + $width, $share, $anchor];
        }
        // Drawn in the order of their values, as a screen reader would meet them if it could.
        uksort($placed, static fn (string $a, string $b): int => (float) $a <=> (float) $b);
        $html .= '<svg class="slider-marks" width="100%" height="14" aria-hidden="true" focusable="false">';
        foreach ($placed as $at => [, , $share, $anchor]) {
            $html .= '<text x="' . e(self::number(round($share, 2))) . '%" y="11" text-anchor="' . $anchor . '">' . e($marks[$at]) . '</text>';
        }

        return $html . '</svg>';
    }

    /**
     * ONE OF A FEW, AS PICTURES (D-161): a closed set where each answer is easier seen than
     * named — how a header is arranged, what a footer holds — three to a row, each a small
     * drawing over its name. Radio inputs, as segmented_group()'s are: they submit with no
     * script, arrow keys move between them, and the posted name and values are the same, so
     * swapping one for the other changes nothing a save reads.
     *
     * @param array<array-key, array{label: string, picture: string, title?: string, name?: string, tag?: string}> $options value
     *        => its name, its drawing as markup (an SVG built from attributes, never a style),
     *        its full name when the name shown is a short one (D-179), what it is when its
     *        value does not say (D-191), and a word shown on it (D-195)
     * @param string $form the form the radios belong to, when it is not the one they stand in
     */
    public static function tiles(string $name, array $options, string $current, string $labelledBy, string $idPrefix, string $form = ''): string
    {
        $html = '<div class="tile-choice" role="radiogroup" aria-labelledby="' . e($labelledBy) . '">';
        foreach ($options as $value => $option) {
            $value = (string) $value;
            // A short visible label keeps its full name as the tile's tooltip and its name read aloud.
            $title = ($option['title'] ?? '') !== '' && $option['title'] !== $option['label'] ? $option['title'] : '';
            // A tile whose value is not its name (a layout that follows the character is '',
            // D-191) still says which it is.
            $html .= '<label class="tile-option"' . ($title !== '' ? ' title="' . e($title) . '"' : '') . '><input type="radio" id="' . e($idPrefix . ($option['name'] ?? $value)) . '" name="' . e($name) . '" value="' . e($value) . '"'
                . (isset($option['name']) ? ' data-tile="' . e($option['name']) . '"' : '')
                . ($title !== '' ? ' aria-label="' . e($title) . '"' : '')
                . ($form !== '' ? ' form="' . e($form) . '"' : '') . ($value === $current ? ' checked' : '') . '>'
                . '<span class="tile-picture" aria-hidden="true">' . $option['picture'] . '</span>'
                . '<span class="tile-label">' . e($option['label']) . '</span>'
                // A word on the tile itself, never a change: the layout a block gets when it
                // follows its character is its "Default" (D-195).
                . (($option['tag'] ?? '') !== '' ? '<span class="tile-tag">' . e($option['tag']) . '</span>' : '')
                . '</label>';
        }

        return $html . '</div>';
    }

    /**
     * A group of controls that folds: a <details>, so it opens and closes with no script.
     *
     * The count beside the title is how many of its controls are changed. It is printed even
     * at zero and hidden by the class, so a script can keep it without building it.
     *
     * @param array{open?: bool, changed?: int, class?: string} $options
     */
    public static function group(string $id, string $title, string $body, array $options = []): string
    {
        $changed = $options['changed'] ?? 0;
        $class = 'control-group' . ($changed > 0 ? ' has-changes' : '') . (($options['class'] ?? '') !== '' ? ' ' . $options['class'] : '');

        return '<details class="' . e($class) . '" id="' . e($id) . '" data-group="' . e($id) . '"' . (($options['open'] ?? true) ? ' open' : '') . '>'
            . '<summary class="control-group-head">'
            . '<span class="control-group-title">' . e($title) . '</span>'
            . '<span class="control-group-changed" title="' . e(t('controls.changed_count')) . '"><span class="control-changed" aria-hidden="true"></span>'
            . '<span data-group-count>' . $changed . '</span><span class="visually-hidden"> ' . e(t('controls.changed_count')) . '</span></span>'
            . '<span class="control-group-toggle" aria-hidden="true"></span>'
            . '</summary>'
            . '<div class="control-group-body">' . $body . '</div>'
            . '</details>';
    }

    /** @param array<string, string> $attributes */
    private static function attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            $html .= ' ' . $name . '="' . e($value) . '"';
        }

        return $html;
    }

    /** A float as the shortest text that says it: 2.0 as "2", 0.005 as "0.005". */
    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
