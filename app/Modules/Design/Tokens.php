<?php

namespace App\Modules\Design;

use App\Modules\Design\Vocabulary\Decisions;

/**
 * The global decisions checked, and what they come to (SPEC §5.4; PLAN.md D-164).
 *
 * TWO SHAPES OF ONE DESIGN. What the site STORES is the owner's own values, '' for every key
 * that follows (the character, the typeface pairing, the palette); validate() checks that
 * shape. What a page is DRAWN from is every key answered: resolve() fills each '' from the
 * character, and Derived compiles that. The rebuild's README 1.1: changing the character keeps
 * every value the owner set, and a reset is ''.
 *
 * What a decision IS — its range, its step, its closed set — is Vocabulary\Decisions, read
 * here; nothing in this file names a value.
 */
final class Tokens
{
    /** The three nudges and the step of the type scale each one moves (D-066). */
    public const NUDGES = [
        'nudge_h1' => ['step' => '4xl', 'min' => -30, 'max' => 40],
        'nudge_h2' => ['step' => '2xl', 'min' => -12, 'max' => 20],
        'nudge_sm' => ['step' => 'sm', 'min' => -3, 'max' => 5],
    ];

    /** What `caps` comes to in CSS. */
    public const CAPS = ['no' => 'none', 'yes' => 'uppercase'];

    /**
     * Checks a design as stored or posted: every key from its closed set or within its range,
     * rounded to its step, '' where it follows; then every text/background pair of the design
     * it resolves to, at WCAG AA. An invalid value is an error keyed by the decision and is
     * stored as '' — the character's — so what is returned can always be drawn.
     *
     * @param array<mixed> $input key => value; keys the vocabulary does not know are ignored
     * @param string $character the character '' resolves to for the contrast check
     * @return array{decisions: array<string, string>, errors: array<string, string>}
     */
    public static function validate(array $input, string $character = ''): array
    {
        $input = self::upgraded($input);
        $decisions = [];
        $errors = [];
        foreach (Decisions::ALL as $key => $definition) {
            $raw = $input[$key] ?? '';
            $value = Decisions::clean($key, is_string($raw) ? trim($raw) : $raw);
            if ($value === null) {
                $errors[$key] = self::message($definition);
                $value = '';
            }
            $decisions[$key] = $value;
        }

        /*
         * THE GUARANTEE MOVES FROM DERIVATION TO CHECKING (SPEC §5.4, D-063): with colours set
         * by hand, a dark mode and a second colour of the owner's, the palette can produce an
         * unreadable pair, and this is what refuses it — naming the control at fault.
         */
        $resolved = self::resolve($decisions, $character);
        foreach (PalettePairs::failures(Palette::forDecisions($resolved), $resolved['secondary'] !== '', self::byHand($resolved), self::ownChrome($resolved)) as $failure) {
            $message = t('design.error.contrast', [
                'pair' => t('design.pair.' . $failure['pair']),
                'ratio' => number_format($failure['ratio'], 2),
                'required' => number_format($failure['required'], 1),
            ]);
            $key = $failure['decision'];
            $errors[$key] = isset($errors[$key]) ? $errors[$key] . ' ' . $message : $message;
        }

        return ['decisions' => $decisions, 'errors' => $errors];
    }

    /**
     * A PAIRING STORED BEFORE THE TWO FONTS (D-185): `typography`, one of six pairings, is
     * read as the two families and the treatment it set (Typography::pairing()), where the
     * values do not already say otherwise. Rows, kept designs and a screen's draft written
     * before keep the faces they had.
     *
     * @param array<mixed> $values
     * @return array<mixed>
     */
    public static function upgraded(array $values): array
    {
        $old = $values['typography'] ?? null;
        unset($values['typography']);
        if (!is_string($old) || !isset(Typography::PAIRINGS[$old]) || ($values['heading_font'] ?? '') !== '' || ($values['body_font'] ?? '') !== '') {
            return $values;
        }
        foreach (Typography::pairing($old) as $key => $value) {
            if (($values[$key] ?? '') === '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * EVERY KEY ANSWERED: the owner's value where there is one, the character's where it is
     * ''. Keys that follow the font or the palette stay '' — Derived and Palette read them
     * that way — and `none` for the second colour is no second colour.
     *
     * @param array<string, string> $decisions as validate() returns them
     * @param array<string, string> $ownDark the owner's dark colours (D-187), '' for none
     * @return array<string, string>
     */
    public static function resolve(array $decisions, string $character = '', array $ownDark = []): array
    {
        $id = $character !== '' ? $character : Presets::DEFAULT;
        $base = Characters::decisions($id);
        $resolved = [];
        foreach (Decisions::ALL as $key => $definition) {
            $value = $decisions[$key] ?? '';
            $resolved[$key] = $value !== '' ? $value : ($base[$key] ?? $definition['neutral']);
        }
        // IN DARK MODE (D-185, D-187, the owner): the owner's dark colour, else the colour the
        // owner holds for both modes, else the character's dark version, else what the palette
        // works out for a dark page.
        if (($resolved['mode'] ?? '') === 'dark') {
            $dark = Characters::dark($id);
            if ($dark !== []) {
                $resolved = self::inDark($resolved, $dark, array_keys(array_filter($decisions, static fn (string $v): bool => $v !== '')));
            }
            foreach (self::darkOwn($ownDark) as $key => $value) {
                if ($value !== '') {
                    $resolved[$key] = $value;
                }
            }
        }
        if (($resolved['secondary'] ?? '') === 'none') {
            $resolved['secondary'] = '';
        }

        return $resolved;
    }

    /**
     * The owner's dark colours in their one shape (D-187): each of Decisions::DARK_OWN, a colour
     * it may hold or ''. Anything else is dropped.
     *
     * @param array<mixed> $given
     * @return array<string, string>
     */
    public static function darkOwn(array $given): array
    {
        $out = [];
        foreach (Decisions::DARK_OWN as $key) {
            $value = $given[$key] ?? '';
            $out[$key] = is_string($value) && $value !== '' ? (Decisions::clean($key, $value) ?? '') : '';
        }

        return $out;
    }

    /**
     * A DESIGN IN ITS DARK VERSION (D-185): mode dark, and each key a dark version may hold
     * taken from it — a colour it leaves out is the palette's (''), anything else it leaves out
     * the light version's. Keys in `$kept` (the owner's own) are left as they are.
     *
     * @param array<string, string> $decisions
     * @param array<string, string> $dark
     * @param list<string> $kept
     * @return array<string, string>
     */
    public static function inDark(array $decisions, array $dark, array $kept = []): array
    {
        $decisions['mode'] = 'dark';
        foreach (Decisions::DARK as $key) {
            if (in_array($key, $kept, true)) {
                continue;
            }
            $colour = Decisions::ALL[$key]['type'] === 'colour' && !in_array($key, ['seed', 'secondary'], true);
            $decisions[$key] = $dark[$key] ?? ($colour ? '' : $decisions[$key]);
        }

        return $decisions;
    }

    /**
     * The same design in numbers a person reads, for the lines beside the controls (D-058):
     * pixels at the browser's default root of 16.
     *
     * @param array<string, string> $resolved every key answered
     * @return array{text: array<string, int>, text_phone: int, space: int, section: int, radius: int, container: int, container_rem: float, sheet_width: int, sheet_gap: int}
     */
    public static function readable(array $resolved): array
    {
        $px = static fn (float $rem): int => (int) floor($rem * 16 + 0.5);
        $sizes = [];
        foreach (array_keys(Derived::TYPE_STEPS) as $name) {
            $sizes[$name] = $px(Derived::sizeOf($resolved, $name));
        }
        $unit = (float) $resolved['spacing'];
        $width = (float) $resolved['container'];

        return [
            'text' => $sizes,
            // What the largest heading shrinks to on a phone, by the rule the clamp() uses.
            'text_phone' => $px(Derived::phoneSize(Derived::sizeOf($resolved, '4xl'))),
            'space' => $px($unit),
            'section' => (int) floor((float) $resolved['section_gap'] + 0.5),
            'radius' => (int) floor((float) $resolved['radius'] + 0.5),
            'container' => $px($width),
            'container_rem' => $width,
            'sheet_width' => $px((float) $resolved['sheet_width']),
            'sheet_gap' => $px($unit * (float) $resolved['sheet_gap']),
        ];
    }

    /**
     * The colours the owner has taken over, role => hex, leaving out the ones left to the
     * palette.
     *
     * @param array<string, string> $decisions
     * @return array<string, string>
     */
    public static function byHand(array $decisions): array
    {
        $byHand = [];
        foreach (Decisions::BY_HAND as $role) {
            $value = $decisions['color_' . $role] ?? '';
            if ($value !== '') {
                $byHand[$role] = $value;
            }
        }

        return $byHand;
    }

    /**
     * The colour the owner gave the header and the footer, leaving out a part still taking a
     * shade of the palette (D-076). The page background's own colour is not among them: the
     * frame around a boxed page carries no text.
     *
     * @param array<string, string> $decisions
     * @return array<string, string>
     */
    public static function ownChrome(array $decisions): array
    {
        $own = [];
        foreach (['header', 'footer'] as $part) {
            $value = $decisions[$part . '_colour'] ?? '';
            if ($value !== '') {
                $own[$part] = $value;
            }
        }

        return $own;
    }

    /**
     * What is wrong with a value, in the owner's words.
     *
     * @param array<string, mixed> $definition
     */
    private static function message(array $definition): string
    {
        return match ($definition['type']) {
            'colour' => t('design.error.color'),
            'choice' => t('design.error.choice'),
            default => t('design.error.range', [
                'min' => CssNumber::of((float) $definition['min']),
                'max' => CssNumber::of((float) $definition['max']),
            ]),
        };
    }
}
