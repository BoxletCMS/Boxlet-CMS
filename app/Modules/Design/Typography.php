<?php

namespace App\Modules\Design;

/**
 * THE SIX PAIRINGS AND HOW A PAGE GETS ITS FONTS (PLAN.md D-017, D-185). A design names its two
 * families (`heading_font`, `body_font`, from the library in Fonts); a pairing is a shortcut
 * that names both and the heading and paragraph treatment that belongs with them. The faces
 * are self-hosted and under the SIL Open Font License; a page declares and preloads only the
 * two families it uses.
 */
final class Typography
{
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

    /**
     * What choosing a pairing sets (D-185): its two families, and its treatment where it is not
     * already what those families bring by themselves — '' there, so the screen shows the
     * font's own and nothing reads as changed.
     *
     * @return array<string, string>
     */
    public static function pairing(string $name): array
    {
        $pairing = self::PAIRINGS[$name] ?? self::PAIRINGS['modern'];
        $heading = Fonts::ALL[$pairing['heading']]['heading'];
        $leading = Fonts::ALL[$pairing['body']]['leading'];

        return [
            'heading_font' => $pairing['heading'],
            'body_font' => $pairing['body'],
            'heading_weight' => (int) $pairing['heading_weight'] === $heading[0] ? '' : $pairing['heading_weight'],
            'tracking' => rtrim($pairing['tracking'], 'em') === $heading[1] ? '' : rtrim($pairing['tracking'], 'em'),
            'caps' => ($pairing['transform'] === 'uppercase') === $heading[3] ? '' : ($pairing['transform'] === 'uppercase' ? 'yes' : 'no'),
            'line_height' => $pairing['leading_body'] === $leading ? '' : $pairing['leading_body'],
        ];
    }

    /** The pairing two families make, or '' for a combination of the owner's own. */
    public static function pairingOf(string $heading, string $body): string
    {
        foreach (self::PAIRINGS as $name => $pairing) {
            if ($pairing['heading'] === $heading && $pairing['body'] === $body) {
                return $name;
            }
        }

        return '';
    }

    /**
     * @font-face rules for the given families, and only those, so a page downloads nothing it
     * does not show: one face per subset of a variable font, one per weight of a static one,
     * each limited to its unicode range so latin-ext is fetched only for words that need it.
     *
     * @param list<string> $families
     * @param string $fontsUrl URL of public/assets/fonts as seen from the stylesheet
     */
    public static function fontFaces(array $families, string $fontsUrl): string
    {
        $css = '';
        foreach (array_unique(array_map([Fonts::class, 'known'], $families)) as $family) {
            $name = Fonts::ALL[$family]['name'];
            foreach (Fonts::files($family) as $file => $range) {
                foreach (['latin' => self::LATIN, 'latin-ext' => self::LATIN_EXT] as $subset => $unicodeRange) {
                    $url = rtrim($fontsUrl, '/') . '/' . self::versioned("{$family}/{$family}-{$subset}-{$file}.woff2");
                    $css .= "@font-face {\n  font-family: \"{$name}\";\n  font-style: normal;\n  font-weight: {$range};\n"
                        . "  font-display: swap;\n  src: url(\"{$url}\") format(\"woff2\");\n  unicode-range: {$unicodeRange};\n}\n";
                }
            }
        }

        return $css;
    }

    /**
     * A font file's path under public/assets/fonts with its version (D-202): a hash of what it
     * holds, so a browser may keep it a year and a changed file has a new address. The same
     * for @font-face and for the preload, which the browser matches only when the two
     * addresses are identical; a file not there is left as it is. Hashed once per request.
     */
    public static function versioned(string $path): string
    {
        static $hashes = [];
        if (!array_key_exists($path, $hashes)) {
            $file = dirname(__DIR__, 3) . '/public/assets/fonts/' . $path;
            $hash = is_file($file) ? hash_file('sha256', $file) : false;
            $hashes[$path] = $hash === false ? '' : substr($hash, 0, 12);
        }

        return $hashes[$path] === '' ? $path : $path . '?v=' . $hashes[$path];
    }

    /**
     * The latin files a page with these two families should fetch at once (D-185): the
     * heading's at its weight and the text's at 400, so the words are drawn in their own face
     * the first time. latin-ext comes only when a word asks for it.
     *
     * @return list<string> paths under public/assets/fonts
     */
    public static function preloads(string $heading, string $body, int $headingWeight): array
    {
        $paths = [];
        foreach ([[$heading, $headingWeight], [$body, 400]] as [$family, $weight]) {
            $family = Fonts::known($family);
            $file = Fonts::ALL[$family]['variable'] ? 'wght' : (string) Fonts::weight($family, $weight);
            $paths[] = "{$family}/{$family}-latin-{$file}.woff2";
        }

        return array_values(array_unique($paths));
    }
}
