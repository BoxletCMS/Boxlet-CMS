<?php

namespace App\Modules\Design;

/**
 * EVERY TEXT/BACKGROUND PAIR THE PALETTE PRODUCES, MEASURED (PLAN.md D-058, D-183): what the
 * Design screen's gauge shows, and what Save refuses on, naming the control at fault.
 *
 * Its own file since D-183, when the muted text on cards joined the list: Palette derives the
 * colours, this measures them, and the two had grown past one file together.
 */
final class PalettePairs
{
    /**
     * EVERY text/background pair the palette produces, with what it measures and what it
     * needs. The gauge on the Design screen is this list; failures() is a filter over it.
     *
     * One list, because two would drift: for a while the screen could only say "this fails",
     * which made a palette that passes by a hair look the same as one that passes easily —
     * and a person cannot aim at a number they are never shown.
     *
     * $decision names the choice responsible, so a failure can point at the control that
     * causes it rather than at the colour it produced.
     *
     * @param array<string, string> $colors
     * @param array<string, string> $byHand the roles the owner set, so a failure names the
     *        control that can fix it
     * @param array<string, string> $ownChrome `header` and `footer` => the colour the owner
     *        gave that part, absent for one still taking a shade of the palette (D-076)
     * @return list<array{pair: string, decision: string, ratio: float, required: float, passes: bool, foreground: string, background: string}>
     */
    public static function pairs(array $colors, bool $hasSecondary, array $byHand = [], array $ownChrome = []): array
    {
        $contrastDecision = $hasSecondary ? 'secondary' : 'seed';
        $defined = [
            ['text_on_background', 'surface_contrast', 'text', 'background'],
            ['muted_on_background', 'surface_contrast', 'muted', 'background'],
            ['text_on_card', 'surface_contrast', 'text', 'card'],
            // Every surface's muted words on its cards too (D-183): a card's own lines are muted.
            ['muted_on_card', 'surface_contrast', 'muted', 'card'],
            ['text_on_surface', 'surface_contrast', 'text', 'surface'],
            ['muted_on_surface', 'surface_contrast', 'muted', 'surface'],
            ['links_on_background', 'seed', 'link', 'background'],
            ['links_on_surface', 'seed', 'link', 'surface'],
            ['button_text_on_accent', 'seed', 'on-accent', 'accent'],
            ['text_on_contrast', $contrastDecision, 'on-contrast', 'contrast'],
            ['muted_on_contrast', $contrastDecision, 'muted-on-contrast', 'contrast'],
            ['muted_on_contrast_card', $contrastDecision, 'muted-on-contrast', 'contrast-raised'],
            ['text_on_gradient_start', 'seed', 'on-gradient', 'gradient-start'],
            ['text_on_gradient_end', 'seed', 'on-gradient', 'gradient-end'],
        ];

        $pairs = [];
        foreach ($defined as [$pair, $decision, $foreground, $background]) {
            $ratio = Color::contrast($colors[$foreground], $colors[$background]);
            $pairs[] = [
                'pair' => $pair,
                // A COLOUR SET BY HAND OWNS ITS OWN FAILURE. Otherwise "text on the
                // background is 2.1:1" would point at surface contrast, a control that
                // cannot fix it, while the control that can sits two fields above. The ink
                // is named first, because it is usually the one to move.
                'decision' => self::responsible($foreground, $background, $byHand) ?? $decision,
                'ratio' => $ratio,
                'required' => Palette::AA_BODY,
                'passes' => $ratio >= Palette::AA_BODY,
                // The two colours themselves, so the gauge can show the pair rather than
                // only name it: a row that says 3.9:1 and shows nothing is a number.
                'foreground' => $colors[$foreground],
                'background' => $colors[$background],
            ];
        }

        /*
         * THE TWO SURFACES THE PALETTE DOES NOT OWN (D-076).
         *
         * While the header and the footer could only take `plain`, `tinted` or `contrast`,
         * the pairs above already covered them: each of those three is a palette role that is
         * measured. A colour of their own is a surface nothing else measures, so it gets its
         * own rows — one for the ink, one for the muted text beside it, and one for the muted
         * text on a card raised from it (D-183).
         *
         * Both are DERIVED from the colour (inksOn), so these can only fail for a colour
         * neither of the palette's inks can be read on. That is rare and it is real, and a
         * refusal naming the control is the whole reason the check exists (D-063).
         */
        foreach (['header', 'footer'] as $part) {
            $surface = $ownChrome[$part] ?? '';
            if ($surface === '') {
                continue;
            }
            $inks = PaletteInks::inksOn($surface, $colors);
            foreach ([['text', 'text', $surface, ''], ['muted', 'muted', $surface, ''], ['muted', 'muted', $inks['raised'], '_card']] as [$which, $ink, $ground, $card]) {
                $ratio = Color::contrast($inks[$ink], $ground);
                $pairs[] = [
                    'pair' => $which . '_on_' . $part . $card,
                    'decision' => $part . '_colour',
                    'ratio' => $ratio,
                    'required' => Palette::AA_BODY,
                    'passes' => $ratio >= Palette::AA_BODY,
                    'foreground' => $inks[$ink],
                    'background' => $ground,
                ];
            }
        }

        return $pairs;
    }

    /**
     * Every pair that falls below WCAG AA, with the decision responsible for it. What Save
     * refuses on, and what the screen puts beside the control at fault.
     *
     * @param array<string, string> $colors
     * @param array<string, string> $byHand
     * @param array<string, string> $ownChrome
     * @return list<array{pair: string, decision: string, ratio: float, required: float}>
     */
    public static function failures(array $colors, bool $hasSecondary, array $byHand = [], array $ownChrome = []): array
    {
        $failures = [];
        foreach (self::pairs($colors, $hasSecondary, $byHand, $ownChrome) as $pair) {
            if (!$pair['passes']) {
                $failures[] = ['pair' => $pair['pair'], 'decision' => $pair['decision'], 'ratio' => $pair['ratio'], 'required' => $pair['required']];
            }
        }

        return $failures;
    }

    /**
     * Which control a failing pair belongs to, when one of its two colours was set by hand.
     *
     * @param array<string, string> $byHand
     */
    private static function responsible(string $foreground, string $background, array $byHand): ?string
    {
        foreach ([$foreground, $background] as $role) {
            if (in_array($role, Palette::BY_HAND, true) && ($byHand[$role] ?? '') !== '') {
                return 'color_' . $role;
            }
        }

        return null;
    }
}
