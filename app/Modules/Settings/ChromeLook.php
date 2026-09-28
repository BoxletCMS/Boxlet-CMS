<?php

namespace App\Modules\Settings;

use App\Core\Db;
use App\Modules\Design\Composition;

/**
 * How the header and footer look (PLAN.md D-032, D-036): a closed set of choices, each
 * with a default the character supplies and an override the owner may set.
 *
 * NO FREE VALUE ANYWHERE. A surface is one of the three the sections already use, so the
 * palette's contrast guarantee holds for the chrome exactly as it does for a block; the
 * rest are sizes and arrangements the stylesheet has a rule for, nothing more.
 *
 * STORED AS "FOLLOW THE CHARACTER" UNLESS CHOSEN. An empty setting means the character
 * decides, so changing character re-dresses the chrome the way it re-composes a page,
 * while a choice the owner made stays theirs. The same split the Design screen draws
 * between a character's defaults and the decisions saved over them.
 */
final class ChromeLook
{
    /**
     * Every choice and its values; the first value is never assumed to be a default.
     *
     * TWO AXES WHERE THERE WAS ONE (PLAN.md D-112). `header_layout` held left, centred,
     * transparent and sticky — two arrangements and two behaviours in one list, so a centred
     * header could never be sticky and a header over the hero could never be centred. The
     * arrangement is where the name, the menu and the button stand; the behaviour is what the
     * bar does as the page scrolls. Five by three is fifteen headers where there were four.
     * The old values are still read: see LEGACY and modernise().
     */
    public const OPTIONS = [
        'header_arrangement' => ['left', 'inline', 'centred', 'split', 'masthead'],
        'header_behaviour' => ['static', 'sticky', 'over'],
        // Five arrangements (D-113): one column; everything centred; the words beside the
        // menu; the menu in a row above the words; three columns — words, menu, and the
        // languages with the small print.
        'footer_layout' => ['simple', 'centred', 'columns', 'menu_first', 'three'],
        // The footer's top edge (D-113): the dividers a section may carry, offered to the
        // one band that is drawn by the same machinery and was never offered them.
        'footer_edge' => ['none', 'line', 'slant', 'curve'],
        // The last row: the small print and the language switcher, side by side, centred,
        // or one under the other on the left.
        'small_print_row' => ['left', 'split', 'centred'],
        // Gradient too (D-112): the class is the sections' own and its pairs are measured.
        // Not a picture: the chrome is on every page, and a picture there is a picture
        // repeated on every page.
        'header_surface' => ['plain', 'tinted', 'contrast', 'gradient'],
        'footer_surface' => ['plain', 'tinted', 'contrast', 'gradient'],
        'density' => ['compact', 'normal', 'roomy'],
        // What separates the header from the page: nothing, a hairline, or a shadow.
        // `header_rule` on/off is read as line/none (LEGACY).
        'header_edge' => ['none', 'line', 'shadow'],
        'logo_size' => ['small', 'medium', 'large'],
        // What stands for the site: its logo, its name in the heading face, or both. A site
        // with no logo shows its name whatever this says (D-110).
        'brand' => ['logo', 'name', 'both'],
        // How the menu's words are set, and whether they take the accent or the ink.
        // Plain; small capitals; the current page on a pill; a bar under the current page and
        // under the pointer; every link a bordered chip (D-114).
        'nav_style' => ['plain', 'caps', 'pills', 'bar', 'chips'],
        'nav_ink' => ['accent', 'ink'],
        // The call to action: a filled button, an outlined one, or a plain link.
        'header_button' => ['filled', 'outline', 'text'],
        /* How many columns the footer's MENU runs in, and only when the footer is in
         * columns at all (PLAN.md D-067). The handoff asks for the footer's own grid to take
         * this number; measured against what a footer actually holds — the owner's words,
         * the menu, the switcher and the small print — three and four columns would leave
         * two of them empty. A long menu is the thing that really needs the room. */
        'footer_columns' => ['2', '3', '4'],
    ];

    /**
     * The choices that were stored before D-112 and what each of their values means now.
     * Read wherever a look is loaded — the settings, a kept design's row — and never written
     * again, so a site or a library saved under the old names keeps the header it had.
     */
    public const LEGACY = [
        'header_layout' => [
            'left' => ['header_arrangement' => 'left', 'header_behaviour' => 'static'],
            'centred' => ['header_arrangement' => 'centred', 'header_behaviour' => 'static'],
            'transparent' => ['header_arrangement' => 'left', 'header_behaviour' => 'over'],
            'sticky' => ['header_arrangement' => 'left', 'header_behaviour' => 'sticky'],
        ],
        'header_rule' => [
            'on' => ['header_edge' => 'line'],
            'off' => ['header_edge' => 'none'],
        ],
    ];

    /**
     * What each character gives its chrome. Kept beside the choices rather than in
     * Presets, which holds what a character does to the page: the header and footer are
     * their own screen and their own decision (D-028), and this is the only reader.
     *
     * Every choice is demonstrated by at least one character (D-112): a control no
     * character demonstrates is a control nobody finds (D-062).
     */
    public const CHARACTER = [
        // A masthead: the name in its own row, the menu in small capitals under it, a
        // hairline under both, air around everything.
        'editorial' => ['header_arrangement' => 'masthead', 'header_behaviour' => 'static', 'footer_layout' => 'simple', 'footer_edge' => 'line', 'small_print_row' => 'left', 'header_surface' => 'plain', 'footer_surface' => 'tinted', 'density' => 'roomy', 'header_edge' => 'line', 'logo_size' => 'medium', 'brand' => 'logo', 'nav_style' => 'caps', 'nav_ink' => 'ink', 'header_button' => 'outline', 'footer_columns' => '2'],
        // Everything on one axis and as little of it as possible; the footer centred too.
        'minimal' => ['header_arrangement' => 'centred', 'header_behaviour' => 'static', 'footer_layout' => 'centred', 'footer_edge' => 'none', 'small_print_row' => 'centred', 'header_surface' => 'plain', 'footer_surface' => 'plain', 'density' => 'normal', 'header_edge' => 'none', 'logo_size' => 'small', 'brand' => 'logo', 'nav_style' => 'plain', 'nav_ink' => 'accent', 'header_button' => 'text', 'footer_columns' => '2'],
        // The header over the first section, which is where Bold spends its colour; the
        // current page a pill, the footer a gradient.
        'bold' => ['header_arrangement' => 'left', 'header_behaviour' => 'over', 'footer_layout' => 'columns', 'footer_edge' => 'none', 'small_print_row' => 'split', 'header_surface' => 'plain', 'footer_surface' => 'gradient', 'density' => 'normal', 'header_edge' => 'none', 'logo_size' => 'large', 'brand' => 'logo', 'nav_style' => 'pills', 'nav_ink' => 'ink', 'header_button' => 'filled', 'footer_columns' => '3'],
        // Always within reach, on a soft tint, the menu beside the name, the name beside
        // the logo, with room to breathe; a footer in three columns under a curved edge.
        'soft' => ['header_arrangement' => 'inline', 'header_behaviour' => 'sticky', 'footer_layout' => 'three', 'footer_edge' => 'curve', 'small_print_row' => 'split', 'header_surface' => 'tinted', 'footer_surface' => 'tinted', 'density' => 'roomy', 'header_edge' => 'none', 'logo_size' => 'medium', 'brand' => 'both', 'nav_style' => 'plain', 'nav_ink' => 'ink', 'header_button' => 'filled', 'footer_columns' => '2'],
        // A slab of contrast, the name in the middle of its menu, packed tight, a shadow
        // under it; the footer's menu first, under a slanted edge.
        'brutalist' => ['header_arrangement' => 'split', 'header_behaviour' => 'static', 'footer_layout' => 'menu_first', 'footer_edge' => 'slant', 'small_print_row' => 'left', 'header_surface' => 'contrast', 'footer_surface' => 'contrast', 'density' => 'compact', 'header_edge' => 'shadow', 'logo_size' => 'large', 'brand' => 'logo', 'nav_style' => 'caps', 'nav_ink' => 'accent', 'header_button' => 'outline', 'footer_columns' => '3'],
    ];

    /**
     * What the owner chose, '' for every choice left to the character.
     *
     * @return array<string, string>
     */
    public static function stored(Db $db): array
    {
        return self::modernise(SiteChrome::look($db, array_merge(array_keys(self::OPTIONS), array_keys(self::LEGACY))));
    }

    /**
     * A look as it was stored — by this version or an older one — as the choices of this
     * version, each from its closed set or '' (PLAN.md D-112).
     *
     * AN OLD NAME COUNTS ONLY WHERE THE NEW ONES ARE SILENT. A site that saved
     * `header_layout: sticky` before D-112 and nothing since gets left + sticky, exactly the
     * header it had; the moment it saves either new choice, that choice is the answer and
     * the old row is a fact about the past. The same for a kept design's row, which is why
     * this is one function with two readers rather than a mapping copied into each.
     *
     * @param array<mixed> $raw choice => stored value, old names included
     * @return array<string, string>
     */
    public static function modernise(array $raw): array
    {
        $look = [];
        foreach (self::OPTIONS as $name => $options) {
            $value = $raw[$name] ?? '';
            $look[$name] = is_string($value) && in_array($value, $options, true) ? $value : '';
        }
        foreach (self::LEGACY as $old => $meanings) {
            $value = $raw[$old] ?? '';
            if (!is_string($value) || !isset($meanings[$value])) {
                continue;
            }
            foreach ($meanings[$value] as $name => $meant) {
                if ($look[$name] === '') {
                    $look[$name] = $meant;
                }
            }
        }

        return $look;
    }

    /**
     * The look to draw, in three levels: what is being TRIED, then what the owner SAVED,
     * then what the CHARACTER gives.
     *
     * $overrides is what an admin preview is showing without having saved it, and it holds
     * only the choices that request actually named — see fromRequest(). $character is the
     * one being previewed, so a choice left to the character follows the character on the
     * screen rather than the one the site is published with.
     *
     * @param array<string, string> $overrides choice => value, '' meaning follow the character
     * @return array<string, string>
     */
    public static function resolve(Db $db, array $overrides = [], string $character = ''): array
    {
        $defaults = self::CHARACTER[$character !== '' ? $character : Composition::active($db)] ?? self::CHARACTER['minimal'];
        $look = [];
        foreach (self::stored($db) as $name => $stored) {
            $value = $overrides[$name] ?? $stored;
            $look[$name] = $value !== '' ? $value : $defaults[$name];
        }

        return $look;
    }

    /** The form field that carries one choice. A field name is not a settings key. */
    public static function field(string $choice): string
    {
        return 'look_' . $choice;
    }

    /**
     * The look choices a request is trying, for a preview. Each is a value from its own
     * closed set, or '' for "follow the character"; anything else falls back to ''.
     *
     * ONLY THE CHOICES THE REQUEST NAMED. A request that is silent about a choice means the
     * owner's saved one, not a reset — and the difference matters: the design preview sends
     * no look at all, so returning all seven as '' would make it draw chrome the site does
     * not have.
     *
     * READS A REQUEST AND WRITES NOTHING. It takes an array and returns an array; it has no
     * database to write to. Saving stays SiteChrome::saveLook(), reached only through a POST
     * with a CSRF token.
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function fromRequest(array $input): array
    {
        $look = [];
        foreach (self::OPTIONS as $name => $options) {
            if (!array_key_exists(self::field($name), $input)) {
                continue;
            }
            $value = $input[self::field($name)];
            $look[$name] = is_string($value) && in_array($value, $options, true) ? $value : '';
        }

        return $look;
    }

    /**
     * Writes the owner's choices. Anything outside a closed set is stored as '' — follow
     * the character — rather than refused: every value here comes from a select the form
     * drew, so an unknown one is a stale form or a hand-made request, not a typing error
     * worth a message.
     *
     * @param array<string, string> $values
     */
    public static function save(Db $db, array $values): void
    {
        $look = [];
        foreach (self::OPTIONS as $name => $options) {
            $value = $values[$name] ?? '';
            $look[$name] = in_array($value, $options, true) ? $value : '';
        }
        SiteChrome::saveLook($db, $look);
    }
}
