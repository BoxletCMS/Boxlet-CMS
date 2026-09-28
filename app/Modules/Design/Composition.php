<?php

namespace App\Modules\Design;

use App\Core\Blocks;
use App\Core\Db;
use App\Core\Settings;

/**
 * Layer 0 reaching layers 2 and 3: the composition a character gives a site.
 *
 * The character a site last applied is remembered in settings, because new blocks have
 * to start from it. That is all it is used for: the saved design is still only the
 * layer-1 decisions in design_tokens, and existing blocks keep the section styles their
 * author chose until the author asks for them to be reset (SPEC §5.4).
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

    public static function remember(Db $db, string $character): void
    {
        if (!Presets::exists($character)) {
            return;
        }
        Settings::set($db, self::SETTING, $character);
    }

    /**
     * The section style a block of this type starts from under this character.
     *
     * @return array<string, string|int|null>
     */
    public static function style(?string $character, string $blockType): array
    {
        if ($character === null || !isset(Presets::COMPOSITION[$character])) {
            return SectionStyle::DEFAULTS;
        }
        $composition = Presets::COMPOSITION[$character];
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

        return SectionStyle::normalize($style);
    }

    /**
     * The section style a SECTION starts from under this character (PLAN.md D-096).
     *
     * A character says what it does to a block TYPE — "Editorial gives a hero a tinted
     * surface, a form a plain one". A section holding a hero and a form has no type, so
     * the sentence has no answer, and the rule the owner chose is: compose from the type
     * the section's blocks AGREE on, and fall back to the character's own section
     * defaults when they do not.
     *
     * WHY THIS AND NOT THE FIRST BLOCK'S TYPE. Every page that exists is made of sections
     * holding one block, so "the type they agree on" is that block's type and not a single
     * page composes differently — the rhythm of tinted and plain bands that makes a
     * character feel designed survives untouched. A band of three text blocks still
     * composes as text, which is the arrangement this whole step exists to make possible.
     * Only two different kinds of block side by side fall back, and they fall back to
     * something the character states about sections rather than to a guess.
     *
     * @param list<string> $types the block types the section holds, in any order
     * @return array<string, string|int|null>
     */
    public static function section(?string $character, array $types): array
    {
        $distinct = array_values(array_unique($types));
        if (count($distinct) === 1) {
            return self::style($character, $distinct[0]);
        }
        if ($character === null || !isset(Presets::COMPOSITION[$character])) {
            return SectionStyle::DEFAULTS;
        }

        // Nothing laid over the character's section language: no surface from one of the
        // types and no divider from another, because choosing between them is the guess
        // this rule exists to refuse. An empty section composes here too — it has no type
        // to agree on, and the character's own defaults are the only honest answer.
        return SectionStyle::normalize(Presets::COMPOSITION[$character]['section']);
    }

    /**
     * The layout a block of this type starts from, always one the block declares.
     */
    public static function layout(Blocks $registry, ?string $character, string $blockType): string
    {
        $layout = $character === null ? null : (Presets::COMPOSITION[$character]['layouts'][$blockType] ?? null);

        return $registry->layout($blockType, $layout);
    }

    /**
     * Rewrites the section style and layout of every block on the site to this
     * character's composition. Destructive: it discards per-section choices, so it only
     * ever runs when the user picked it explicitly over saving the design alone.
     *
     * @return int the number of SECTIONS restyled, which is what the message reports
     */
    public static function apply(Db $db, Blocks $registry, string $character): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $changed = 0;

        /*
         * SECTION BY SECTION, COMPOSED FROM THE TYPES IT HOLDS (D-095, D-096).
         *
         * This used to be one UPDATE per block type, site-wide. The style now lives on the
         * section, and a character composes layer 2 from a BLOCK TYPE, so the two meet only
         * through the blocks a section holds: one type, or several of the same type, compose
         * as that type; a section of mixed types composes from the character's own section
         * language and takes no surface or divider from either (D-096).
         *
         * A block this installation cannot draw is left out of the reckoning AND left alone.
         * Its stored style is the only record of what it was, and a type nobody can render
         * is not evidence about what the section should look like — so a section holding one
         * uninstalled block is skipped entirely, exactly as the per-type loop skipped it.
         */
        $sections = [];
        foreach ($db->all(
            'SELECT s.id, b.block_type FROM page_sections s
             LEFT JOIN page_blocks b ON b.section_id = s.id
             ORDER BY s.id, b.column_index, b.sort, b.id',
        ) as $row) {
            $id = (int) $row['id'];
            $sections[$id] ??= [];
            $type = (string) ($row['block_type'] ?? '');
            if ($registry->has($type)) {
                $sections[$id][] = $type;
            }
        }

        foreach ($sections as $id => $types) {
            if ($types === []) {
                continue;
            }
            // SECTIONS, because that is the word the message uses: "…:count sections were
            // reset to the Editorial composition" (lang/en/design.php). While a section held
            // one block the two counts were the same number and nothing said which it was.
            // They stop being the same the moment a section holds two, and a number that
            // quietly means something else than the sentence around it is worse than no
            // number.
            $changed += 1;
            $db->query(
                'UPDATE page_sections SET style_json = ?, updated_at = ? WHERE id = ?',
                [json_encode(self::section($character, $types), JSON_THROW_ON_ERROR), $now, $id],
            );
            // The layout is the block's own layer 3 and stays on the block row, so it is
            // composed per type however many types the section turned out to hold.
            //
            // A COVER HERO KEEPS ITS COVER (PLAN.md D-120). Its arrangement says what its
            // picture IS — the thing behind the words — and that is content, which a character
            // does not own. Reset with the rest, it went to the character's `center` and the
            // picture chosen to stand behind the words dropped under them; the owner met
            // exactly that applying Minimal. Every other arrangement is still reset, as the
            // button says.
            foreach (array_unique($types) as $type) {
                $db->query(
                    'UPDATE page_blocks SET layout = ?, updated_at = ? WHERE section_id = ? AND block_type = ?'
                    . ($type === 'hero' ? " AND layout NOT LIKE 'cover-%'" : ''),
                    [self::layout($registry, $character, $type), $now, $id, $type],
                );
            }
        }

        return $changed;
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
