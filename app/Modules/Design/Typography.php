<?php

namespace App\Modules\Design;

/**
 * The curated typography pairings. Fonts are self-hosted from public/assets/fonts, never
 * loaded from a third party, and every family is under the SIL Open Font License
 * (OFL.txt beside its files).
 */
final class Typography
{
    /** Family => display name, CSS stack with fallbacks, and whether it is a variable font. */
    public const FAMILIES = [
        'inter' => ['name' => 'Inter', 'stack' => '"Inter", system-ui, -apple-system, "Segoe UI", sans-serif', 'variable' => true],
        'playfair-display' => ['name' => 'Playfair Display', 'stack' => '"Playfair Display", Georgia, "Times New Roman", serif', 'variable' => true],
        'source-serif-4' => ['name' => 'Source Serif 4', 'stack' => '"Source Serif 4", Georgia, "Times New Roman", serif', 'variable' => true],
        'space-grotesk' => ['name' => 'Space Grotesk', 'stack' => '"Space Grotesk", "Helvetica Neue", Arial, sans-serif', 'variable' => true],
        'nunito' => ['name' => 'Nunito', 'stack' => '"Nunito", "Segoe UI", system-ui, sans-serif', 'variable' => true],
        'ibm-plex-mono' => ['name' => 'IBM Plex Mono', 'stack' => '"IBM Plex Mono", ui-monospace, Menlo, Consolas, monospace', 'variable' => false],
    ];

    /**
     * Heading and body families known to work together, with the heading treatment that
     * belongs to each: weight, letter spacing, case and line heights.
     */
    public const PAIRINGS = [
        'editorial' => ['heading' => 'playfair-display', 'body' => 'source-serif-4', 'heading_weight' => '600', 'body_weight' => '400', 'tracking' => '-0.01em', 'transform' => 'none', 'leading_body' => '1.75', 'leading_heading' => '1.1'],
        'classic' => ['heading' => 'source-serif-4', 'body' => 'inter', 'heading_weight' => '650', 'body_weight' => '400', 'tracking' => '-0.01em', 'transform' => 'none', 'leading_body' => '1.65', 'leading_heading' => '1.15'],
        'modern' => ['heading' => 'inter', 'body' => 'inter', 'heading_weight' => '650', 'body_weight' => '400', 'tracking' => '-0.025em', 'transform' => 'none', 'leading_body' => '1.6', 'leading_heading' => '1.15'],
        'grotesk' => ['heading' => 'space-grotesk', 'body' => 'inter', 'heading_weight' => '700', 'body_weight' => '400', 'tracking' => '-0.035em', 'transform' => 'none', 'leading_body' => '1.55', 'leading_heading' => '1'],
        'rounded' => ['heading' => 'nunito', 'body' => 'nunito', 'heading_weight' => '800', 'body_weight' => '400', 'tracking' => '-0.01em', 'transform' => 'none', 'leading_body' => '1.7', 'leading_heading' => '1.2'],
        'mono' => ['heading' => 'ibm-plex-mono', 'body' => 'ibm-plex-mono', 'heading_weight' => '700', 'body_weight' => '400', 'tracking' => '0.01em', 'transform' => 'uppercase', 'leading_body' => '1.6', 'leading_heading' => '1.1'],
    ];

    private const LATIN = 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD';
    private const LATIN_EXT = 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF';

    public static function stack(string $family): string
    {
        return self::FAMILIES[$family]['stack'];
    }

    /**
     * @font-face rules for the families a pairing uses, and only those, so a page
     * downloads nothing it does not show.
     *
     * @param string $fontsUrl URL of public/assets/fonts as seen from the stylesheet
     */
    public static function fontFaces(string $pairing, string $fontsUrl): string
    {
        $css = '';
        $families = array_unique([self::PAIRINGS[$pairing]['heading'], self::PAIRINGS[$pairing]['body']]);
        foreach ($families as $family) {
            $name = self::FAMILIES[$family]['name'];
            $weights = self::FAMILIES[$family]['variable'] ? ['wght' => '100 1000'] : ['400' => '400', '700' => '700'];
            foreach ($weights as $file => $range) {
                foreach (['latin' => self::LATIN, 'latin-ext' => self::LATIN_EXT] as $subset => $unicodeRange) {
                    $url = rtrim($fontsUrl, '/') . "/{$family}/{$family}-{$subset}-{$file}.woff2";
                    $css .= "@font-face {\n  font-family: \"{$name}\";\n  font-style: normal;\n  font-weight: {$range};\n"
                        . "  font-display: swap;\n  src: url(\"{$url}\") format(\"woff2\");\n  unicode-range: {$unicodeRange};\n}\n";
                }
            }
        }

        return $css;
    }
}
