<?php

namespace App\Modules\Design;

/**
 * THE FONT LIBRARY (PLAN.md D-185): every family a design may set its headings or its text in,
 * self-hosted from public/assets/fonts/{id}/ as woff2 in a latin and a latin-ext subset, never
 * from a third party, each under the SIL Open Font License (OFL.txt beside its files) and each
 * carrying č ć đ š ž and their capitals (scenario 65 measures it).
 *
 * For each family: its name, its category (how the picker groups it), whether it is a
 * variable font, the weights there are files for (a range for a variable font, a list for a
 * static one), the CSS stack with its fallbacks, and what a heading or running text in it is
 * when the design leaves it alone: a heading's weight, letter spacing, line height and
 * capitals, and a paragraph's line height. The values of the families the six pairings used
 * are the pairings' own, so a design that names a pairing's two fonts looks as it did.
 */
final class Fonts
{
    public const CATEGORIES = ['serif', 'sans', 'display', 'mono'];

    private const SERIF = 'Georgia, "Times New Roman", serif';
    private const SANS = 'system-ui, -apple-system, "Segoe UI", sans-serif';
    private const MONO = 'ui-monospace, Menlo, Consolas, monospace';

    /**
     * id => name, category, variable, weights, stack, heading [weight, tracking em, leading,
     * caps], body leading.
     *
     * @var array<string, array{name: string, category: string, variable: bool, weights: list<int>, stack: string, heading: array{0: int, 1: string, 2: string, 3: bool}, leading: string}>
     */
    public const ALL = [
        // ---- serif ----------------------------------------------------------------------
        'playfair-display' => ['name' => 'Playfair Display', 'category' => 'serif', 'variable' => true, 'weights' => [400, 900], 'stack' => '"Playfair Display", Georgia, "Times New Roman", serif', 'heading' => [600, '-0.01', '1.1', false], 'leading' => '1.7'],
        'source-serif-4' => ['name' => 'Source Serif 4', 'category' => 'serif', 'variable' => true, 'weights' => [200, 900], 'stack' => '"Source Serif 4", Georgia, "Times New Roman", serif', 'heading' => [650, '-0.01', '1.15', false], 'leading' => '1.75'],
        'instrument-serif' => ['name' => 'Instrument Serif', 'category' => 'serif', 'variable' => false, 'weights' => [400], 'stack' => '"Instrument Serif", ' . self::SERIF, 'heading' => [400, '-0.01', '1.05', false], 'leading' => '1.6'],
        'young-serif' => ['name' => 'Young Serif', 'category' => 'serif', 'variable' => false, 'weights' => [400], 'stack' => '"Young Serif", ' . self::SERIF, 'heading' => [400, '-0.01', '1.15', false], 'leading' => '1.65'],
        'cormorant-garamond' => ['name' => 'Cormorant Garamond', 'category' => 'serif', 'variable' => true, 'weights' => [300, 700], 'stack' => '"Cormorant Garamond", ' . self::SERIF, 'heading' => [600, '0', '1.1', false], 'leading' => '1.7'],
        'bodoni-moda' => ['name' => 'Bodoni Moda', 'category' => 'serif', 'variable' => true, 'weights' => [400, 900], 'stack' => '"Bodoni Moda", ' . self::SERIF, 'heading' => [600, '-0.01', '1.1', false], 'leading' => '1.7'],
        'newsreader' => ['name' => 'Newsreader', 'category' => 'serif', 'variable' => true, 'weights' => [200, 800], 'stack' => '"Newsreader", ' . self::SERIF, 'heading' => [600, '-0.01', '1.15', false], 'leading' => '1.7'],
        'eb-garamond' => ['name' => 'EB Garamond', 'category' => 'serif', 'variable' => true, 'weights' => [400, 800], 'stack' => '"EB Garamond", ' . self::SERIF, 'heading' => [600, '0', '1.15', false], 'leading' => '1.7'],
        'libre-caslon-text' => ['name' => 'Libre Caslon Text', 'category' => 'serif', 'variable' => false, 'weights' => [400, 700], 'stack' => '"Libre Caslon Text", ' . self::SERIF, 'heading' => [700, '-0.01', '1.15', false], 'leading' => '1.75'],

        // ---- sans -----------------------------------------------------------------------
        'inter' => ['name' => 'Inter', 'category' => 'sans', 'variable' => true, 'weights' => [100, 900], 'stack' => '"Inter", system-ui, -apple-system, "Segoe UI", sans-serif', 'heading' => [650, '-0.025', '1.15', false], 'leading' => '1.6'],
        'space-grotesk' => ['name' => 'Space Grotesk', 'category' => 'sans', 'variable' => true, 'weights' => [300, 700], 'stack' => '"Space Grotesk", "Helvetica Neue", Arial, sans-serif', 'heading' => [700, '-0.035', '1', false], 'leading' => '1.55'],
        'nunito' => ['name' => 'Nunito', 'category' => 'sans', 'variable' => true, 'weights' => [200, 900], 'stack' => '"Nunito", "Segoe UI", system-ui, sans-serif', 'heading' => [800, '-0.01', '1.2', false], 'leading' => '1.7'],
        'hanken-grotesk' => ['name' => 'Hanken Grotesk', 'category' => 'sans', 'variable' => true, 'weights' => [100, 900], 'stack' => '"Hanken Grotesk", ' . self::SANS, 'heading' => [700, '-0.02', '1.1', false], 'leading' => '1.6'],
        'figtree' => ['name' => 'Figtree', 'category' => 'sans', 'variable' => true, 'weights' => [300, 900], 'stack' => '"Figtree", ' . self::SANS, 'heading' => [700, '-0.02', '1.15', false], 'leading' => '1.6'],
        'manrope' => ['name' => 'Manrope', 'category' => 'sans', 'variable' => true, 'weights' => [200, 800], 'stack' => '"Manrope", ' . self::SANS, 'heading' => [700, '-0.02', '1.15', false], 'leading' => '1.65'],
        'outfit' => ['name' => 'Outfit', 'category' => 'sans', 'variable' => true, 'weights' => [100, 900], 'stack' => '"Outfit", ' . self::SANS, 'heading' => [600, '-0.02', '1.1', false], 'leading' => '1.6'],
        'archivo' => ['name' => 'Archivo', 'category' => 'sans', 'variable' => true, 'weights' => [100, 900], 'stack' => '"Archivo", ' . self::SANS, 'heading' => [700, '-0.02', '1.1', false], 'leading' => '1.6'],
        'schibsted-grotesk' => ['name' => 'Schibsted Grotesk', 'category' => 'sans', 'variable' => true, 'weights' => [400, 900], 'stack' => '"Schibsted Grotesk", ' . self::SANS, 'heading' => [700, '-0.02', '1.1', false], 'leading' => '1.6'],
        'bricolage-grotesque' => ['name' => 'Bricolage Grotesque', 'category' => 'sans', 'variable' => true, 'weights' => [200, 800], 'stack' => '"Bricolage Grotesque", ' . self::SANS, 'heading' => [700, '-0.03', '1.05', false], 'leading' => '1.6'],
        'ibm-plex-sans' => ['name' => 'IBM Plex Sans', 'category' => 'sans', 'variable' => true, 'weights' => [100, 700], 'stack' => '"IBM Plex Sans", ' . self::SANS, 'heading' => [600, '-0.01', '1.15', false], 'leading' => '1.6'],

        // ---- display --------------------------------------------------------------------
        'big-shoulders-display' => ['name' => 'Big Shoulders Display', 'category' => 'display', 'variable' => true, 'weights' => [100, 900], 'stack' => '"Big Shoulders Display", "Arial Narrow", ' . self::SANS, 'heading' => [800, '0.01', '1', false], 'leading' => '1.5'],
        'unbounded' => ['name' => 'Unbounded', 'category' => 'display', 'variable' => true, 'weights' => [200, 900], 'stack' => '"Unbounded", ' . self::SANS, 'heading' => [700, '-0.02', '1.1', false], 'leading' => '1.6'],
        'syne' => ['name' => 'Syne', 'category' => 'display', 'variable' => true, 'weights' => [400, 800], 'stack' => '"Syne", ' . self::SANS, 'heading' => [700, '-0.02', '1.1', false], 'leading' => '1.6'],
        // Fredoka, asked for, has no č, đ, Č or Đ (measured, D-185): Baloo 2 is its round,
        // friendly stand-in, the owner to confirm.
        'baloo-2' => ['name' => 'Baloo 2', 'category' => 'display', 'variable' => true, 'weights' => [400, 800], 'stack' => '"Baloo 2", ' . self::SANS, 'heading' => [700, '-0.01', '1.15', false], 'leading' => '1.6'],

        // ---- mono -----------------------------------------------------------------------
        'ibm-plex-mono' => ['name' => 'IBM Plex Mono', 'category' => 'mono', 'variable' => false, 'weights' => [400, 700], 'stack' => '"IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace', 'heading' => [700, '0.01', '1.1', true], 'leading' => '1.6'],
        'jetbrains-mono' => ['name' => 'JetBrains Mono', 'category' => 'mono', 'variable' => true, 'weights' => [100, 800], 'stack' => '"JetBrains Mono", ' . self::MONO, 'heading' => [700, '0', '1.15', false], 'leading' => '1.6'],
        'space-mono' => ['name' => 'Space Mono', 'category' => 'mono', 'variable' => false, 'weights' => [400, 700], 'stack' => '"Space Mono", ' . self::MONO, 'heading' => [700, '-0.01', '1.15', false], 'leading' => '1.6'],
    ];

    /** A family's id, or Inter's for one the library does not have. */
    public static function known(string $id): string
    {
        return isset(self::ALL[$id]) ? $id : 'inter';
    }

    public static function stack(string $id): string
    {
        return self::ALL[self::known($id)]['stack'];
    }

    /**
     * A heading weight a family can draw: the asked one within a variable font's range, or the
     * nearest weight a static one has a file for, so a browser never fakes a bold.
     */
    public static function weight(string $id, int $asked): int
    {
        $font = self::ALL[self::known($id)];
        if ($font['variable']) {
            return max($font['weights'][0], min($font['weights'][count($font['weights']) - 1], $asked));
        }
        $best = $font['weights'][0];
        foreach ($font['weights'] as $weight) {
            if (abs($weight - $asked) < abs($best - $asked)) {
                $best = $weight;
            }
        }

        return $best;
    }

    /**
     * The files of a family, subset => weight label => font-weight value: one file per subset
     * for a variable font (`wght`, the whole range), one per weight for a static one.
     *
     * @return array<string, string>
     */
    public static function files(string $id): array
    {
        $font = self::ALL[self::known($id)];

        return $font['variable']
            ? ['wght' => $font['weights'][0] . ' ' . $font['weights'][count($font['weights']) - 1]]
            : array_combine(array_map('strval', $font['weights']), array_map('strval', $font['weights']));
    }
}
