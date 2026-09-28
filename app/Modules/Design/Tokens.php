<?php

namespace App\Modules\Design;

/**
 * Layer 1: eight decisions, not forty values (SPEC §5.4). Validates them and derives
 * every custom property the site uses from them.
 */
final class Tokens
{
    /*
     * THE STEP BETWEEN SIZES IS A NUMBER (PLAN.md D-066), 1.1 to 1.6.
     *
     * Six named ratios were six answers to a question with a continuum behind it, and the
     * gap between "Moderate" and "Clear" was a decision nobody could make. The five
     * characters keep the exact ratios they always had — a number is not rounded to a step
     * on the way in, only clamped — so nothing moved when the decision changed shape.
     */
    public const SCALE_MIN = 1.1;
    public const SCALE_MAX = 1.6;
    /** What the five characters use, and what the slider's marks sit on. */
    public const SCALES = ['1.125', '1.2', '1.25', '1.333', '1.414', '1.5'];

    /*
     * PER-STEP NUDGES (D-066): the type scale is a ratio, and a ratio cannot say "that
     * headline, two pixels smaller". Each is a number of pixels ADDED to one step after the
     * scale has done its work, so the scale stays the relationship it is and the nudge stays
     * the exception it is. Zero is the default and means the scale alone.
     *
     * Bounded in both directions, and asymmetrically: a heading can take a lot more than it
     * can lose before it stops being a heading.
     */
    public const NUDGES = [
        'nudge_h1' => ['step' => '4xl', 'min' => -30, 'max' => 40],
        'nudge_h2' => ['step' => '2xl', 'min' => -12, 'max' => 20],
        'nudge_sm' => ['step' => 'sm', 'min' => -3, 'max' => 5],
    ];

    /*
     * The heading treatment, which the PAIRING gives and the owner may take over (D-066).
     * '' is "as the pairing has it" — the same convention the chrome's seven choices use,
     * and for the same reason: a weight chosen by hand should survive changing the typeface,
     * and one never chosen should follow it.
     */
    public const HEADING_WEIGHTS = ['400', '500', '600', '700', '800'];
    public const TRACKING = ['tight' => '-0.03em', 'normal' => '0em', 'wide' => '0.06em'];
    public const CAPS = ['no' => 'none', 'yes' => 'uppercase'];
    /** Spacing base unit in rem; the whole spacing scale is multiples of it. */
    public const SPACING = ['compact' => 0.875, 'normal' => 1.0, 'roomy' => 1.25, 'generous' => 1.5];
    public const RADIUS = ['none', 'subtle', 'round', 'pill'];
    public const SHADOW = ['none', 'soft', 'hard', 'layered'];
    /*
     * CONTENT WIDTH IS A NUMBER, IN REM (PLAN.md D-062, SPEC §5.4).
     *
     * It was four names, and four names cannot answer "a little narrower than this". The
     * measure — how many characters fit on a line — is the single decision that most changes
     * whether a page is comfortable to read, and it was the one decision the owner could only
     * nudge in jumps of fourteen rem.
     *
     * The four names are still READ: a site saved before this loads with the width its name
     * meant, so nothing has to be migrated and nothing re-chosen.
     */
    public const CONTAINER_NAMES = ['narrow' => 42.0, 'normal' => 56.0, 'wide' => 68.0, 'full' => 80.0];
    public const CONTAINER_MIN = 36.0;
    public const CONTAINER_MAX = 88.0;
    /** Two rem at a time: finer than that is a difference nobody can see. */
    public const CONTAINER_STEP = 2.0;

    /*
     * The base text size, as a factor on the whole type scale (D-062).
     *
     * SIZE AND SCALE ARE DIFFERENT QUESTIONS. The scale is how much bigger each heading is
     * than the one below it; this is how big the text itself is. A site for people who are
     * not twenty-five needs the second, and until now the only way to get it was to pick a
     * scale that made the headings wrong.
     *
     * It moves the TYPE and nothing else — not the spacing, not the corners, not the
     * measure — because a person reaching for "larger text" is asking for larger text.
     */
    public const TEXT_SIZE = ['small' => 0.9375, 'normal' => 1.0, 'large' => 1.0625, 'larger' => 1.125];
    public const SURFACE_CONTRAST = ['low', 'medium', 'high'];

    /*
     * The page itself (PLAN.md D-031).
     *
     * Two values for the header, not a second four-value container decision: the header
     * either holds the same measure as the content or runs edge to edge.
     *
     * BOXED IS 'no'/'yes' RATHER THAN A BOOLEAN, so it is one of the closed-set decisions
     * like every other: design_tokens stores JSON scalars, choices() lists allowed strings
     * and the screen renders a select from them. A bool would be the single exception in
     * all three.
     *
     * The page background is a SHADE FROM THE PALETTE, never a free colour — the reasoning
     * §5.4 uses to refuse a free colour per section. It shows only around a boxed page, so
     * no text ever sits on it and Palette::failures() gains no pairs.
     */
    /* THREE SINCE D-123, in the owner's words: the header's contents line up with the TEXT
       (`content`), with the BOX (`full`, the sheet's edges on a boxed page), or with the
       window, FULL (`window`) — the one a boxed page could not have before, a bar across
       the window whose contents run across it too. The stored names are the old ones, so
       nothing already saved changes meaning. */
    public const HEADER_WIDTH = ['content', 'full', 'window'];
    /** The footer's contents likewise (D-116, D-123): the same three answers. */
    public const FOOTER_WIDTH = ['content', 'full', 'window'];
    public const BOXED = ['no', 'yes'];
    public const PAGE_BACKGROUND = ['surface', 'border', 'contrast'];

    /*
     * OR A COLOUR OF YOUR OWN (PLAN.md D-076, handoff §C3).
     *
     * Three places where the shades of the palette are a choice and not the only one: what
     * surrounds a boxed page, and the two bands the page is held between. Empty until the
     * owner sets one, and a hex when they have — the same convention the seven hand-set
     * roles use (D-063), for the same reason: a default nobody chose is not a value.
     *
     * THESE ARE NOT A FREE COLOUR PER SECTION, which §5.4 refuses and this does not open. A
     * section takes a surface from the palette because a page of arbitrary bands is a page
     * with no palette left. The page's own frame and its chrome are three places, decided
     * once for the whole site, and each one is measured: the frame carries no text at all,
     * and the ink on the other two is DERIVED from the colour (Palette::inksOn) rather than
     * left to whatever the palette happened to hold.
     */
    public const OWN_COLOURS = ['page_background_colour', 'header_colour', 'footer_colour'];

    /*
     * THE SHEET, AND WHAT BREAKS OUT OF IT (PLAN.md D-067, handoff §3.3).
     *
     * The frame used to be hard-coded at three spacing units and wrapped the WHOLE page, so
     * the header and footer were inset with everything else and could not reach the edge of
     * the window. It wraps the sheet alone now, and the two bleed decisions say which side
     * of it the chrome renders on.
     *
     * The bleeds are NOT TOKENS. They decide which slot the chrome renders into, so the
     * layout reads them and the CSS never asks "is this boxed" — which is the property the
     * frame's docblock has always been proud of.
     */
    public const FRAME = ['thin' => 1.0, 'narrow' => 2.0, 'normal' => 3.0, 'wide' => 5.0];
    /*
     * A BOXED PAGE IS A BOX OF A WIDTH (PLAN.md D-116). The frame gave the sheet a margin
     * and the sheet took the rest of the window, which at 1600px is a wide sheet with a thin
     * edge — not the boxed layout anyone means by the word. `sheet_width` is the sheet's own
     * width in rem, centred in the window; `sheet_gap` is the room ABOVE and BELOW it in
     * spacing units, apart from the sides, so a header and footer that break out of the
     * sheet (D-067) can sit glued to it at 0. Both are zero when the page is not boxed, for
     * the reason the frame is.
     */
    public const SHEET_WIDTH_MIN = 40.0;
    public const SHEET_WIDTH_MAX = 120.0;
    public const SHEET_WIDTH_STEP = 2.0;
    public const SHEET_GAP_MAX = 8.0;
    public const SHEET_RADIUS = ['square', 'soft', 'round'];
    public const SHEET_SHADOW = ['none', 'shadow', 'hairline'];
    public const BLEED = ['sheet', 'full'];

    /** Type steps as powers of the scale ratio, from small print to the largest heading. */
    /**
     * The closed decisions and their allowed values. The two colours are open (#rrggbb).
     *
     * @return array<string, list<string>>
     */
    public static function choices(): array
    {
        return [
            'typography' => array_keys(Typography::PAIRINGS),
            'spacing' => array_keys(self::SPACING),
            'radius' => self::RADIUS,
            'shadow' => self::SHADOW,
            'text_size' => array_keys(self::TEXT_SIZE),
            'surface_contrast' => self::SURFACE_CONTRAST,
            'header_width' => self::HEADER_WIDTH,
            'footer_width' => self::FOOTER_WIDTH,
            'boxed' => self::BOXED,
            'page_background' => self::PAGE_BACKGROUND,
            'frame' => array_keys(self::FRAME),
            'sheet_radius' => self::SHEET_RADIUS,
            'sheet_shadow' => self::SHEET_SHADOW,
            'header_bleed' => self::BLEED,
            'footer_bleed' => self::BLEED,
        ];
    }

    /**
     * Checks submitted decisions: colours as #rrggbb, everything else one of its choices,
     * and every text/background pair of the resulting palette at WCAG AA. Errors are keyed
     * by the decision that caused them; invalid values are replaced by the default preset's
     * so the returned decisions can always be previewed.
     *
     * @param array<mixed> $input
     * @return array{decisions: array<string, string>, errors: array<string, string>}
     */
    public static function validate(array $input): array
    {
        $fallback = Presets::get(Presets::DEFAULT);
        $errors = [];

        $seed = Color::normalizeHex(is_string($input['seed'] ?? null) ? $input['seed'] : '');
        if ($seed === null) {
            $errors['seed'] = t('design.error.color');
        }
        $secondaryInput = is_string($input['secondary'] ?? null) ? trim($input['secondary']) : '';
        $secondary = $secondaryInput === '' ? '' : Color::normalizeHex($secondaryInput);
        if ($secondary === null) {
            $errors['secondary'] = t('design.error.color');
        }
        $decisions = ['seed' => $seed ?? $fallback['seed'], 'secondary' => $secondary ?? ''];

        // The heading treatment: each may be left to the pairing, so '' is a value here
        // rather than a missing one.
        foreach (['heading_weight' => self::HEADING_WEIGHTS, 'tracking' => array_keys(self::TRACKING), 'caps' => array_keys(self::CAPS)] as $key => $allowed) {
            $value = $input[$key] ?? '';
            if (!is_string($value) || ($value !== '' && !in_array($value, $allowed, true))) {
                $errors[$key] = t('design.error.choice');
                $value = '';
            }
            $decisions[$key] = $value;
        }

        foreach (self::choices() as $key => $allowed) {
            $value = $input[$key] ?? null;
            if (!is_string($value) || !in_array($value, $allowed, true)) {
                $errors[$key] = t('design.error.choice');
                $value = $fallback[$key];
            }
            $decisions[$key] = $value;
        }

        /*
         * COLOURS SET BY HAND (D-063), one decision per role: '' means "work it out", and a
         * hex means the owner has taken that role over. Empty is the default and the whole
         * palette is derived, exactly as before — this adds a way to disagree, not a new
         * thing to fill in.
         */
        foreach (Palette::BY_HAND as $role) {
            $key = 'color_' . $role;
            $typed = is_string($input[$key] ?? null) ? trim($input[$key]) : '';
            $colour = $typed === '' ? '' : Color::normalizeHex($typed);
            if ($colour === null) {
                $errors[$key] = t('design.error.color');
            }
            $decisions[$key] = $colour ?? '';
        }

        // The three places that may take a colour of their own instead of a shade of the
        // palette (D-076). Same shape as the roles above: '' is "use the palette's".
        foreach (self::OWN_COLOURS as $key) {
            $typed = is_string($input[$key] ?? null) ? trim($input[$key]) : '';
            $colour = $typed === '' ? '' : Color::normalizeHex($typed);
            if ($colour === null) {
                $errors[$key] = t('design.error.color');
            }
            $decisions[$key] = $colour ?? '';
        }

        // The step between sizes, and the three nudges: numbers, each with its own bounds.
        $scale = self::bounded($input['scale'] ?? null, self::SCALE_MIN, self::SCALE_MAX);
        if ($scale === null) {
            $errors['scale'] = t('design.error.scale', ['min' => self::number(self::SCALE_MIN), 'max' => self::number(self::SCALE_MAX)]);
        }
        $decisions['scale'] = self::number($scale ?? (float) $fallback['scale']);
        foreach (self::NUDGES as $key => $bounds) {
            $nudge = self::bounded($input[$key] ?? null, (float) $bounds['min'], (float) $bounds['max']);
            if ($nudge === null) {
                $errors[$key] = t('design.error.nudge', ['min' => (string) $bounds['min'], 'max' => (string) $bounds['max']]);
            }
            $decisions[$key] = self::number((float) (int) round($nudge ?? 0.0));
        }

        // The sheet's width and the room above and below it (D-116): numbers, bounded.
        $sheetWidth = self::bounded($input['sheet_width'] ?? null, self::SHEET_WIDTH_MIN, self::SHEET_WIDTH_MAX);
        if ($sheetWidth === null) {
            $errors['sheet_width'] = t('design.error.width', ['min' => self::number(self::SHEET_WIDTH_MIN), 'max' => self::number(self::SHEET_WIDTH_MAX)]);
        }
        $decisions['sheet_width'] = self::number(round(($sheetWidth ?? (float) $fallback['sheet_width']) / self::SHEET_WIDTH_STEP) * self::SHEET_WIDTH_STEP);
        $sheetGap = self::bounded($input['sheet_gap'] ?? null, 0.0, self::SHEET_GAP_MAX);
        if ($sheetGap === null) {
            $errors['sheet_gap'] = t('design.error.nudge', ['min' => '0', 'max' => self::number(self::SHEET_GAP_MAX)]);
        }
        $decisions['sheet_gap'] = self::number((float) (int) round($sheetGap ?? (float) $fallback['sheet_gap']));

        // The one decision that is a number rather than one of a closed set.
        $width = self::width($input['container'] ?? null);
        if ($width === null) {
            $errors['container'] = t('design.error.width', ['min' => self::number(self::CONTAINER_MIN), 'max' => self::number(self::CONTAINER_MAX)]);
        }
        $decisions['container'] = self::number($width ?? self::width($fallback['container']) ?? 56.0);

        /*
         * THE GUARANTEE MOVES FROM DERIVATION TO CHECKING (SPEC §5.4, D-063).
         *
         * Until a colour could be set by hand, the palette could not produce an unreadable
         * pair: every ink was chosen against the surface it would sit on. With hand-set
         * colours it can, so the check below is no longer a formality about the seed — it is
         * the only thing standing between the owner and a site nobody can read. It refuses
         * the same way it always did, and the message now names the control at fault.
         */
        $byHand = self::byHand($decisions);
        $colors = Palette::colors($decisions['seed'], $decisions['secondary'], $decisions['surface_contrast'], $byHand);
        foreach (Palette::failures($colors, $decisions['secondary'] !== '', $byHand, self::ownChrome($decisions)) as $failure) {
            $message = t('design.error.contrast', [
                'pair' => t('design.pair.' . $failure['pair']),
                'ratio' => number_format($failure['ratio'], 2),
                'required' => number_format($failure['required'], 1),
            ]);
            $key = $failure['decision'];
            $errors[$key] = isset($errors[$key]) ? $errors[$key] . ' ' . $message : $message;
        }

        /*
         * ONE CANONICAL ORDER, whatever order the loops above happened to fill them in.
         * What is stored — and what a test compares against a character — should not depend
         * on which validation ran first; `container` stopped being one of the closed sets
         * (D-062) and moved to the end of the array without anything intending it to.
         *
         * `+ $decisions` keeps anything this list forgets, so a decision added later is
         * mis-ordered rather than lost.
         */
        $order = array_merge(
            ['seed', 'secondary'],
            array_map(static fn (string $role): string => 'color_' . $role, Palette::BY_HAND),
            ['typography', 'text_size', 'scale'],
            array_keys(self::NUDGES),
            ['heading_weight', 'tracking', 'caps', 'spacing', 'radius', 'shadow', 'container',
                'surface_contrast', 'header_width', 'boxed', 'page_background', 'page_background_colour',
                'frame', 'sheet_width', 'sheet_gap', 'sheet_radius', 'sheet_shadow', 'header_bleed', 'footer_bleed',
                // With the sheet's decisions, where Presets::SHEET puts it: the presets test
                // asserts a character survives validation in the order it was written.
                'footer_width',
                'header_colour', 'footer_colour'],
        );
        $ordered = [];
        foreach ($order as $key) {
            if (array_key_exists($key, $decisions)) {
                $ordered[$key] = $decisions[$key];
            }
        }

        return ['decisions' => $ordered + $decisions, 'errors' => $errors];
    }

    /**
     * The same decisions in numbers a person can read, for the lines under the controls
     * (PLAN.md D-058).
     *
     * WHAT WAS THERE BEFORE WAS CSS. The screen printed
     * `clamp(2.038rem, 1.508rem + 1.759vw, 2.827rem)` and a row of rem values, which is the
     * compiler's own language leaking onto the owner's screen: it told a person who has
     * never written a stylesheet nothing, and it told one who has nothing they could not
     * read off the page itself.
     *
     * PIXELS AT THE BROWSER'S DEFAULT of 16px to the rem. That is an assumption, and it is
     * the right one to make here: a reader who has changed it is reading a page whose every
     * size moves with their setting, and the number is a sense of scale, not a promise.
     *
     * The specimen is the preview beside these lines — the admin cannot show one, because
     * its own type is fixed by the --ui-* set and must never follow the site's (SPEC §5.4).
     *
     * @param array<string, string> $decisions validated decisions
     * @return array{text: array<string, int>, text_phone: int, space: int, section: int, radius: int, container: int, container_rem: float, sheet_width: int, sheet_gap: int}
     */
    public static function readable(array $decisions): array
    {
        $px = static fn (float $rem): int => (int) round($rem * 16);
        // Asked, not worked out again: the screen's numbers and the stylesheet's sizes come
        // from one formula (D-066). They did not, for about a minute, and every readout in
        // the Type tab was wrong the moment a nudge was used.
        $sizes = [];
        foreach (array_keys(Derived::TYPE_STEPS) as $name) {
            $sizes[$name] = $px(Derived::sizeOf($decisions, $name));
        }
        $unit = self::SPACING[$decisions['spacing']];
        $width = self::width($decisions['container']) ?? 56.0;

        return [
            'text' => $sizes,
            // What the largest heading shrinks to on a narrow screen, by the same rule
            // typeScale() uses to build the clamp.
            // What the largest heading shrinks to on a narrow screen, by the same rule the
            // stylesheet's clamp() is built from.
            'text_phone' => $px(max(1.25, Derived::sizeOf($decisions, '4xl') * 0.72)),
            'space' => $px($unit),
            'section' => $px($unit * Derived::SPACE_STEPS['2xl']),
            'radius' => $px((float) rtrim(Derived::RADII[$decisions['radius']]['m'], 'rem')),
            'container' => $px($width),
            'container_rem' => $width,
            'sheet_width' => $px((float) $decisions['sheet_width']),
            'sheet_gap' => $px($unit * (float) $decisions['sheet_gap']),
        ];
    }

    /**
     * The colours the owner has taken over, role => hex, leaving out the ones left to the
     * palette. One reading of the decisions, so nothing has to know the key's shape twice.
     *
     * @param array<string, string> $decisions
     * @return array<string, string>
     */
    public static function byHand(array $decisions): array
    {
        $byHand = [];
        foreach (Palette::BY_HAND as $role) {
            $value = $decisions['color_' . $role] ?? '';
            if ($value !== '') {
                $byHand[$role] = $value;
            }
        }

        return $byHand;
    }

    /**
     * The colour the owner gave the header and the footer, leaving out a part still taking a
     * shade of the palette (D-076). One reading of the decisions, as byHand() is: the key's
     * shape is known here and nowhere else.
     *
     * The page background's own colour is NOT among them. These two are surfaces that carry
     * text and so have ink derived for them and pairs measured on them; the frame around a
     * boxed page carries none, which is what has always made that decision harmless.
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
     * A number within bounds, or null for anything that is not one — which is what makes it
     * an error rather than a silent default. Shared by every decision that is a number.
     */
    public static function bounded(mixed $value, float $min, float $max): ?float
    {
        if (is_bool($value) || is_array($value) || $value === null || !is_numeric($value)) {
            return null;
        }
        $number = (float) $value;

        return $number < $min || $number > $max ? null : $number;
    }

    /**
     * A content width as a number of rem: one of the four old names, or a number within the
     * bounds, rounded to the step. Null for anything else, which is what makes it an error
     * rather than a silent default.
     */
    public static function width(mixed $value): ?float
    {
        if (is_string($value) && isset(self::CONTAINER_NAMES[$value])) {
            return self::CONTAINER_NAMES[$value];
        }
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $rem = (float) $value;
        if ($rem < self::CONTAINER_MIN || $rem > self::CONTAINER_MAX) {
            return null;
        }

        return round($rem / self::CONTAINER_STEP) * self::CONTAINER_STEP;
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
