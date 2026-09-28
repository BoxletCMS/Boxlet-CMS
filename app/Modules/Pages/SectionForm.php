<?php

namespace App\Modules\Pages;

use App\Modules\Design\SectionStyle;

/**
 * Reads the page editor's sections[key][…] input (PLAN.md D-093 step 3).
 *
 * The companion to BlockForm, and deliberately its own file for the same reason
 * SectionRender is: a section is not a block, and the moment the two are parsed by one
 * function somebody will reach for a block's field while holding a section.
 *
 * WHAT MOVED HERE. surface, rhythm, width, align, divider and the background picture used
 * to be typed at `blocks[b42][style][…]` and were written onto the section on save, because
 * a block was a section (D-095 undid that in the database; this undoes it in the form). They
 * are now typed at `sections[s7][style][…]`, once per section however many blocks it holds,
 * which is the whole point: a tinted band of three text blocks is one setting.
 *
 * A SECTION'S NAME, like a block's (D-094): `s7` for a section the database knows, `m0` for
 * one made in this session. The two namespaces are deliberately different letters — a block
 * key and a section key are read by the same JavaScript rename engine, and `b7` meaning a
 * block while `s7` means a section is a difference a reader can see at a glance.
 */
final class SectionForm
{
    /**
     * What a key may look like when it arrives from a form: s42, m7.
     *
     * Public because BlockForm matches a block's `[section]` against it: a posted key is
     * somebody's input wherever it arrives, and one shape for what a section may be called
     * beats the same regex written twice and drifting.
     */
    public const KEY = '~^[sm][0-9]{1,9}$~';

    public static function key(?int $id, int $ordinal): string
    {
        return $id === null ? 'm' . $ordinal : 's' . $id;
    }

    /**
     * Sections in submitted order, each with its arrangement and its style.
     *
     * The ORDER IS THE PAGE'S ORDER, exactly as the blocks' submitted order has always been
     * the page's order — the browser sends fields in the order they stand in the form, and
     * the form is what the author has been dragging about.
     *
     * A section id that is not this page's is treated as new rather than refused, the rule
     * BlockForm::parse() follows for a block id: a stale key makes a section of its own
     * instead of writing over a stranger's.
     *
     * AN ID IS CLAIMED ONCE. A second section sending an id an earlier one already sent is
     * treated as new (PLAN.md D-117). The editor's band copy once kept its original's id,
     * and the save then wrote both into the one row: the original held every block twice,
     * took the copy's place and style, and the copy was gone. The editor no longer sends it;
     * this is the reason a form that ever does again cannot merge two bands into one.
     *
     * @param array<int, int> $stored section id => id, for the sections this page has
     * @return list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>
     */
    public static function parse(mixed $posted, array $stored): array
    {
        $sections = [];
        $ordinal = 0;
        $claimed = [];
        foreach (is_array($posted) ? $posted : [] as $sent => $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $key = is_string($sent) && preg_match(self::KEY, $sent) === 1 ? $sent : null;
            $id = is_string($raw['id'] ?? null) && ctype_digit($raw['id']) ? (int) $raw['id'] : null;
            if ($id !== null && (!isset($stored[$id]) || isset($claimed[$id]))) {
                $id = null;
            }
            if ($id !== null) {
                $claimed[$id] = true;
            }
            $sections[] = [
                'key' => $key ?? self::key($id, $ordinal++),
                'id' => $id,
                // A KEY THAT WAS NOT SENT MEANS "LEAVE IT", not "one column". A form that
                // renders the arrangement always sends it; one that does not — an older
                // form, a hand-made request, a panel that has not grown the control yet —
                // is saying nothing about the columns, and normalising silence into `one`
                // would quietly flatten a section on the next ordinary save.
                'layout' => isset($raw['layout']) ? SectionLayout::normalize($raw['layout']) : null,
                'stack' => isset($raw['stack']) ? SectionLayout::normalizeStack($raw['stack']) : null,
                'style' => isset($raw['style']) ? SectionStyle::normalize($raw['style']) : null,
            ];
        }

        return $sections;
    }

    /**
     * The sections a page of blocks needs when the form said nothing about them.
     *
     * A save that carries blocks and no sections is not an error and never was: the demo
     * seed, the test fixtures and every caller written before columns existed mean "one
     * block, one section", which is what every page on every site is made of. Rather than
     * each of them learning a shape it has nothing to say about, the shape is made here —
     * in one place, where the rule can be read.
     *
     * THE SECTION A BLOCK ALREADY HAS IS KEPT, which is the whole delicacy of this: minting
     * a new key for every block would make every such save delete each section and write a
     * fresh one, and a section's id is what its translations, its revisions and its
     * background picture are attached to. A block the page has never seen gets a new one.
     *
     * ITS ARRANGEMENT IS LEFT ALONE, not set to one column: `null` reaches Sections::save()
     * as "leave it as it is". A caller with nothing to say about columns must not be heard
     * saying "one column" — that is how a save from the plain page editor would collapse a
     * section somebody had arranged in the builder.
     *
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string}> $blocks
     * @param array<int, int|null> $sectionOf block id => the section it is stored in
     * @return array{sections: list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>, blocks: list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string, section: string, column: int}>}
     */
    public static function oneEach(array $blocks, array $sectionOf): array
    {
        $sections = [];
        $placed = [];
        foreach ($blocks as $ordinal => $block) {
            $id = $block['id'] === null ? null : ($sectionOf[$block['id']] ?? null);
            $key = self::key($id, $ordinal);
            // The style travels on the block in this shape, because that is what a caller
            // with no sections has to say it with; it is the section's the moment it lands.
            // A block this installation cannot draw carries no style — it was never
            // rendered a field for one — so null says "leave it", which is what
            // Page::keepSection() used to do with a second read and a second write.
            $sections[] = [
                'key' => $key,
                'id' => $id,
                'layout' => null,
                'stack' => null,
                'style' => $block['content'] === null ? null : SectionStyle::normalize($block['style']),
            ];
            $block['section'] = $key;
            $block['column'] = 0;
            $placed[] = $block;
        }

        return ['sections' => $sections, 'blocks' => $placed];
    }
}
