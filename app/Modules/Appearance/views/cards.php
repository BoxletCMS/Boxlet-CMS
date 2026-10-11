<?php

use App\Modules\Design\Palette;
use App\Modules\Design\Vocabulary\Decisions;

/**
 * How a whole design is shown in small: three swatches of its palette, and for the question
 * an import asks, a card with its name and what it is (PLAN.md D-152, D-157). Required by
 * appearance.php, in its scope, after $swatch.
 *
 * @var Closure(string, string): string $swatch
 */

/**
 * Three colours that say which design this is: its accent, its contrast band, its surface.
 * Worked out from its own decisions, so a tile cannot show a palette the design does not have.
 *
 * @param array<string, string> $decisions
 */
$chips = static function (array $decisions, string $key) use ($swatch): string {
    // Every key answered: a design that leaves one out means what the vocabulary gives it.
    $palette = Palette::forDecisions($decisions + Decisions::neutral());

    return '<span class="design-chips" aria-hidden="true">'
        . $swatch($palette['accent'], $key . '-accent')
        . $swatch($palette['contrast'], $key . '-contrast')
        . $swatch($palette['surface'], $key . '-surface')
        . '</span>';
};

/**
 * A design in one line, BUILT FROM ITS OWN DECISIONS: "modern · 56rem · normal · full bleed".
 * Not a sentence somebody wrote about it — a sentence that cannot go out of date.
 *
 * @param array<string, string> $decisions
 */
$summary = static fn (array $decisions, string $header): string => implode(' · ', array_filter([
    // The heading family by name (D-185); a design kept before names its pairing instead.
    \App\Modules\Design\Fonts::ALL[\App\Modules\Design\Fonts::known((string) (\App\Modules\Design\Tokens::upgraded($decisions)['heading_font'] ?? 'inter'))]['name'],
    ($decisions['container'] ?? '60') . 'rem',
    t(($decisions['boxed'] ?? 'no') === 'yes' ? 'appearance.boxed' : 'appearance.full_bleed'),
    $header === '' ? '' : t('chrome.look.header_arrangement.' . $header),
]));

/**
 * The design an import brings, shown before anything is done with it.
 *
 * @param array<string, string> $decisions
 */
$card = static fn (array $decisions, string $header, string $name, string $key): string => '<div class="design-card">'
    . $chips($decisions, $key)
    . '<span class="design-card-name" title="' . e($name) . '">' . e($name) . '</span>'
    . '<p class="design-card-shape">' . e($summary($decisions, $header)) . '</p>'
    . '</div>';
