<?php

namespace App\Modules\Design;

/**
 * Layer 0: the characters, asked for by name. A thin face on Characters (PLAN.md D-152),
 * kept because every caller already speaks to it: what a character IS — its decisions,
 * its composition, its header and footer — is in designs/core/*.json and the sets an owner
 * adds, and Characters is the one reader of them.
 *
 * The characters Boxlet ships differ in structure, not only hue. Tokens change type, text
 * size, scale, spacing, radius, shadow, content width and surface contrast; composition
 * changes the shape of the page itself: measure, vertical rhythm, alignment, section
 * boundaries and hero arrangement. The first five derive their whole palette and nudge
 * nothing: a colour set by hand, a nudged step, a colour of its own are the owner's
 * exceptions (D-063, D-066, D-076), which a set an owner imports may carry (D-153), and so do
 * the owner's own sets Boxlet ships since D-195 — Terra's sand page is Terra.
 */
final class Presets
{
    /** The character a site composes with until it chooses one; designs/core/minimal.json. */
    public const DEFAULT = 'minimal';

    /**
     * The layer-1 decisions of a character, falling back to the default character, in the
     * order validate() stores them.
     *
     * @return array<string, string>
     */
    public static function get(string $name): array
    {
        return Characters::decisions($name);
    }

    /**
     * The decisions no character makes: no colour by hand, nothing nudged, no colour of its
     * own, the standard sheet. What a design set that leaves a key out means by it, and what
     * DesignSet::export() leaves out of a file (D-152).
     *
     * @return array<string, string>
     */
    public static function neutral(): array
    {
        return Characters::neutral();
    }

    public static function exists(string $name): bool
    {
        return Characters::exists($name);
    }

    /**
     * The section edge a character uses as its accent: the shape it draws where the tone
     * changes, rather than on every boundary. 'none' when it draws no edges at all.
     *
     * A divider marks a transition, so using one everywhere is the same as using none:
     * the eye stops reading it as a boundary.
     */
    public static function dividerAccent(string $name): string
    {
        $composition = Characters::composition($name);
        // The map lists only the transitions a character actually draws, so its first
        // entry is the accent. A character that draws none falls back to its section
        // default, which is 'none' for all five Boxlet ships.
        $accents = array_values($composition['dividers']);

        return $accents === [] ? $composition['section']['divider'] : $accents[0];
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return Characters::names();
    }
}
