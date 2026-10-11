<?php

namespace App\Modules\Design;

/**
 * Derives the whole palette from one or two seed colours. The inks that must be read on a
 * colour are PaletteInks' to work out; every text/background pair is measured against WCAG
 * AA by PalettePairs.
 *
 * On a light page seeds are used as given and never nudged to pass: the seed is the accent,
 * the button colour and the link colour, so a seed too light to read as text fails, naming
 * the pair, instead of being silently turned into a different colour. On a dark page the
 * links and buttons take the seed's hue lifted until it reads (D-184, the owner): a seed is
 * chosen for one page, and a dark page is the other.
 */
final class Palette
{
    /**
     * How far the tinted surface sits from the page background, in OKLCH lightness, at the
     * points of the 0–100 surface contrast the step's old names stood for; between them it is
     * interpolated (D-164).
     */
    private const SURFACE_CURVE = [0 => 0.0, 20 => 0.03, 50 => 0.065, 80 => 0.11, 100 => 0.14];

    /** The page's lightness in each mode, when no background is set by hand. */
    private const PAGE = ['light' => 0.99, 'dark' => 0.17];

    /** WCAG AA for normal-size text. Every pair below can hold body text, so all use it. */
    public const AA_BODY = 4.5;

    /**
     * THE SEVEN ROLES AN OWNER MAY SET BY HAND (PLAN.md D-063, the link's since), and no others.
     *
     * These are the INDEPENDENT ones: the page's own colours and the ink on them. Everything
     * else is either already theirs — the accent and the contrast surface are the two seeds —
     * or is COMPUTED FOR READABILITY and must stay computed. `on-accent`, `on-contrast`,
     * `muted-on-contrast`, `contrast-raised` and `on-gradient` are the palette choosing which
     * of two inks can be read on a colour; handing those over would be handing over the one
     * decision that keeps text legible, dressed as a choice.
     */
    public const BY_HAND = ['background', 'card', 'surface', 'border', 'text', 'muted', 'link'];

    /**
     * The palette of a design with every key answered (Tokens::resolve()).
     *
     * @param array<string, string> $resolved
     * @return array<string, string>
     */
    public static function forDecisions(array $resolved): array
    {
        return self::colors($resolved['seed'], $resolved['secondary'], (float) $resolved['surface_contrast'], Tokens::byHand($resolved), $resolved['mode'] ?? 'light');
    }

    /** The surface step for a surface contrast of 0–100: straight lines between the points. */
    public static function surfaceStep(float $contrast): float
    {
        $contrast = max(0.0, min(100.0, $contrast));
        $from = 0.0;
        $step = 0.0;
        foreach (self::SURFACE_CURVE as $at => $to) {
            $at = (float) $at;
            if ($contrast <= $at) {
                return $at <= $from ? $to : $step + ($to - $step) * ($contrast - $from) / max(0.001, $at - $from);
            }
            $from = $at;
            $step = $to;
        }

        return $step;
    }

    /**
     * @param array<string, string> $byHand role => #rrggbb for a role the owner set, '' or
     *        absent for one the palette works out
     * @param string $mode light or dark (D-164): which way the page and its inks go
     * @return array<string, string> colour name => #rrggbb, emitted as --color-{name}
     */
    public static function colors(string $seed, string $secondary, float $surfaceContrast, array $byHand = [], string $mode = 'light'): array
    {
        [, $seedChroma, $hue] = Color::toOklch($seed);
        $step = self::surfaceStep($surfaceContrast);
        // Neutrals carry a trace of the seed's hue, so greys belong to the palette.
        $tint = min($seedChroma, 0.14) * 0.1;
        $ink = min($seedChroma, 0.08) * 0.35;

        /*
         * THE NEUTRALS FOLLOW THE PAGE, not an assumption about it (D-063).
         *
         * They used to be fixed lightnesses — a near-white page, a near-black text — which
         * was true of every palette the seed could produce. A background SET BY HAND can be
         * dark, and against a dark page those numbers give grey text on black and a tinted
         * surface lighter than nothing: measured at 2.6:1 for muted text, which the check
         * then refuses. The page's own lightness decides the direction, so setting one
         * colour gives a coherent palette rather than a list of refusals.
         *
         * DARK MODE IS THE SAME BRANCH (D-164): the page starts dark instead of near-white,
         * and every neutral and ink walks the other way, measured by the same pairs.
         */
        $page = self::PAGE[$mode] ?? self::PAGE['light'];
        if (($byHand['background'] ?? '') !== '') {
            [$page] = Color::toOklch($byHand['background']);
        }
        $dark = $page < 0.5;
        $away = static fn (float $by): float => max(0.0, min(1.0, $dark ? $page + $by : $page - $by));

        $colors = [
            'background' => Color::fromOklch($page, $tint * 0.4, $hue),
            // CARDS AND PANELS ARE THEIR OWN ROLE (D-067). They used to take the tinted
            // surface's colour on a plain section, which made a card on the page and a
            // tinted band the same tone — so a card sitting inside a tinted section had
            // nothing to be distinct from. It sits half a step from the page, between the
            // two.
            'card' => Color::fromOklch($away($step * 0.5), $tint * 0.7, $hue),
            'surface' => Color::fromOklch($away($step), $tint, $hue),
            'border' => Color::fromOklch($away($step + 0.1), $tint * 1.5, $hue),
            'text' => Color::fromOklch($dark ? 0.95 : 0.2, $ink, $hue),
            'muted' => Color::fromOklch($dark ? 0.72 : 0.45, $ink, $hue),
            'accent' => $seed,
            'link' => $seed,
        ];
        /*
         * THE OWNER'S COLOURS GO IN HERE, BEFORE ANYTHING DEPENDS ON THEM.
         *
         * This is the whole reason this round was left until last. `on-accent`,
         * `on-contrast` and `on-gradient` are worked out FROM the background and the text —
         * apply a hand-set background after them and they are answers to a question nobody
         * asked any more: still legible against the colour that has gone, and possibly
         * unreadable against the one that arrived. Overriding first makes a stale dependent
         * colour impossible rather than unlikely.
         */
        foreach (self::BY_HAND as $role) {
            if (($byHand[$role] ?? '') !== '') {
                $colors[$role] = $byHand[$role];
            }
        }

        /*
         * ON A DARK PAGE THE MAIN COLOUR IS LIFTED (D-184, the owner): a seed chosen for a light
         * page — every character's — read at 1.9-3.1:1 as a link on a dark one, and Save
         * refused dark mode under all five. The links and the buttons take a lighter variant,
         * the same hue walked up in OKLCH until it reads at 4.5:1 on the page, a card and the
         * tinted surface, as a muted ink is walked; a link colour set by hand the same. A seed
         * that already reads is kept as it is; one with almost no hue takes the text's light
         * ink instead (D-185). The gradient goes the other way (PaletteInks::gradient, D-194).
         */
        if ($dark) {
            $grounds = [$colors['background'], $colors['card'], $colors['surface']];
            $colors['accent'] = PaletteInks::lifted($colors['accent'], $grounds, $colors['text']);
            $colors['link'] = PaletteInks::lifted($colors['link'], $grounds, $colors['text']);
        }

        $colors['on-accent'] = PaletteInks::readableOn([$colors['accent']], $colors);

        // The contrast surface is the second seed when given, else a deep shade of the first.
        $contrast = $secondary !== '' ? $secondary : Color::fromOklch(0.27, min($seedChroma, 0.1), $hue);
        $colors['contrast'] = $contrast;
        $inks = PaletteInks::inksOn($contrast, $colors);
        $colors['on-contrast'] = $inks['text'];
        $colors['muted-on-contrast'] = $inks['muted'];
        $colors['contrast-raised'] = $inks['raised'];

        [$colors['gradient-start'], $colors['gradient-end']] = PaletteInks::gradient($seed, $dark ? $colors['text'] : '');
        $colors['on-gradient'] = PaletteInks::readableOn([$colors['gradient-start'], $colors['gradient-end']], $colors);

        return $colors;
    }
}
