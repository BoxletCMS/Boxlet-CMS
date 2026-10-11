<?php

namespace App\Modules\Design;

use App\Core\Blocks;
use App\Core\Db;
use App\Core\Settings;
use App\Modules\Admin\Activity;
use App\Modules\Pages\PageDraft;

/**
 * Layer 0 reaching layers 2 and 3: the composition a character gives a site.
 *
 * The character a site is composed with is remembered in settings. Every section reads it
 * as it is drawn (D-165): a section style holds only what its owner set, and every key
 * left '' is this character's answer for the blocks the section holds. Changing character
 * re-dresses every section nobody touched; apply() hands back the ones somebody did.
 */
final class Composition
{
    private const SETTING = 'design_character';

    /**
     * The character new blocks start from. A site that never chose one composes like the
     * default character, whose tokens it is already rendering with.
     */
    public static function active(Db $db): string
    {
        $name = Settings::get($db, self::SETTING);

        return is_string($name) && Presets::exists($name) ? $name : Presets::DEFAULT;
    }

    /**
     * The character this site was composed with, when it is gone — a custom file deleted, an
     * import removed — or null while it exists (PLAN.md D-156).
     *
     * ONLY THE ADMIN ASKS. A visitor's page falls back to the default character through
     * active(), silently, and writes nothing: no request of a visitor's writes to the database
     * for this. The Appearance screen and the Overview ask here, and the first time a missing
     * character is seen it goes into the activity log, once, remembered in a setting of its
     * own so the log does not repeat it on every visit.
     */
    public static function missing(Db $db): ?string
    {
        $name = Settings::get($db, self::SETTING);
        if (!is_string($name) || $name === '' || Characters::exists($name)) {
            return null;
        }
        if (Settings::get($db, self::SETTING . '_missing') !== $name) {
            Activity::record($db, 'design', 'character_missing', null, $name);
            Settings::set($db, self::SETTING . '_missing', $name);
        }

        return $name;
    }

    public static function remember(Db $db, string $character): void
    {
        if (!Presets::exists($character)) {
            return;
        }
        Settings::set($db, self::SETTING, $character);
    }

    /**
     * What this character composes for a section holding one block of this type: every
     * composed key answered (SectionStyle::DEFAULTS' shape), the padding '' where it is the
     * design's section gap.
     *
     * @return array<string, string>
     */
    public static function style(?string $character, string $blockType): array
    {
        // An id that is no character composes as nothing in particular, as it always has; not
        // as the default character, which is what Characters::composition() would give.
        if ($character === null || !Characters::exists($character)) {
            return SectionStyle::DEFAULTS;
        }
        $composition = Characters::composition($character);
        $style = $composition['section'];
        $surface = $composition['surfaces'][$blockType] ?? null;
        if (is_string($surface)) {
            $style['surface'] = $surface;
        }
        // A divider is an accent on the transitions a character chooses, not a default
        // for every boundary, so only the named block types carry one.
        $divider = $composition['dividers'][$blockType] ?? null;
        if (is_string($divider)) {
            $style['divider'] = $divider;
        }

        return self::answered($style);
    }

    /**
     * What this character composes for a SECTION (PLAN.md D-096): the type its blocks agree
     * on, or the character's own section language when they do not.
     *
     * WHY THIS AND NOT THE FIRST BLOCK'S TYPE. A band of three text blocks composes as text,
     * which is what made bands of several blocks possible at all; only two different kinds
     * of block side by side fall back, and they fall back to something the character states
     * about sections rather than to a guess. An empty section composes here too — it has no
     * type to agree on.
     *
     * Since D-165 this is what a section IS wherever its owner has said nothing, at the
     * moment it is drawn — not a value copied into it when it was made.
     *
     * @param list<string> $types the block types the section holds, in any order
     * @return array<string, string>
     */
    public static function section(?string $character, array $types): array
    {
        $distinct = array_values(array_unique($types));
        if (count($distinct) === 1) {
            return self::style($character, $distinct[0]);
        }
        if ($character === null || !Characters::exists($character)) {
            return SectionStyle::DEFAULTS;
        }

        return self::answered(Characters::composition($character)['section']);
    }

    /**
     * What this character answers for a block type's options (D-166): its composition's
     * `options` for that type, empty where it says nothing. BlockOptions::effective() takes
     * the owner's over these, and the option's default where neither says.
     *
     * @return array<string, string>
     */
    public static function options(?string $character, string $blockType): array
    {
        if ($character === null || !Characters::exists($character)) {
            return [];
        }

        return Characters::composition($character)['options'][$blockType] ?? [];
    }

    /**
     * The layout a block of this type starts from, always one the block declares.
     */
    public static function layout(Blocks $registry, ?string $character, string $blockType): string
    {
        $layout = $character === null || !Characters::exists($character) ? null : (Characters::composition($character)['layouts'][$blockType] ?? null);

        return $registry->layout($blockType, $layout);
    }

    /**
     * Hands every section on the site back to the character: each composed key the owner set
     * goes back to '' (SectionStyle::reset). The picture, a section's name, its anchor and
     * where it is hidden stay — they are not the character's to have an opinion on.
     *
     * A BLOCK'S LAYOUT AND OPTIONS ARE NOT TOUCHED (PLAN.md D-191, the owner, in place of
     * D-166's "every option back"): one that follows the character ('') follows it already,
     * and one the owner chose is theirs, given back on the block by "Reset to character". The
     * page of every block keeps every layout it shows under every character.
     *
     * Destructive, so it only ever runs when the owner picked it over publishing the design
     * alone. It is no longer what changing character needs: a section nobody touched follows
     * the character already (D-165). It is the way to undo the touching.
     *
     * @return int the number of SECTIONS that had a style of the owner's to give back: what
     *         the message reports
     */
    public static function apply(Db $db, Blocks $registry, string $character): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $changed = 0;
        foreach ($db->all('SELECT id, style_json FROM page_sections') as $row) {
            $stored = json_decode((string) $row['style_json'], true);
            $style = SectionStyle::normalize(is_array($stored) ? $stored : []);
            if (SectionStyle::overridden($style)) {
                $db->query(
                    'UPDATE page_sections SET style_json = ?, updated_at = ? WHERE id = ?',
                    [json_encode(SectionStyle::reset($style), JSON_THROW_ON_ERROR), $now, (int) $row['id']],
                );
                $changed += 1;
            }
        }
        // And every draft the same way: what the owner is preparing would otherwise publish the
        // old styling back over the page just handed back (D-163 point 5, D-173).
        PageDraft::handBack($db, $registry);

        return $changed;
    }

    /**
     * How many blocks on the site have a layout of the owner's own, not the character's
     * (D-191): what Apply keeps, said beside what it hands back, for information.
     */
    public static function ownLayouts(Db $db, Blocks $registry): int
    {
        $own = 0;
        foreach ($db->all("SELECT block_type FROM page_blocks WHERE layout <> ''") as $row) {
            $own += $registry->has((string) $row['block_type']) ? 1 : 0;
        }

        return $own;
    }

    /**
     * A composition's section style with every composed key answered, in DEFAULTS' order.
     *
     * @param array<string, string> $style
     * @return array<string, string>
     */
    private static function answered(array $style): array
    {
        $answered = [];
        foreach (SectionStyle::DEFAULTS as $key => $default) {
            $answered[$key] = $style[$key] ?? $default;
        }

        return $answered;
    }

    /**
     * How many sections on the site hold something their owner set over the character: what
     * applying a character's composition would hand back, said in the question that offers
     * it (the successor of D-161's "replaces N of your changes", D-165).
     */
    public static function styledByHand(Db $db): int
    {
        $styled = [];
        foreach ($db->all('SELECT id, style_json FROM page_sections') as $row) {
            $style = json_decode((string) $row['style_json'], true);
            if (SectionStyle::overridden(SectionStyle::normalize(is_array($style) ? $style : []))) {
                $styled[(int) $row['id']] = true;
            }
        }
        // A block's option of the owner's is no longer counted: Apply keeps it (D-191).

        return count($styled);
    }

    /**
     * Whether the site has any block at all: what decides if applying a character has
     * anything to overwrite, and so whether the choice has to be offered.
     */
    public static function hasBlocks(Db $db): bool
    {
        return (int) ($db->one('SELECT COUNT(*) AS n FROM page_blocks')['n'] ?? 0) > 0;
    }
}
