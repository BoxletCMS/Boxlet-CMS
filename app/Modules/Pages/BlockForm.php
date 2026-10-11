<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Modules\Design\SectionStyle;

/**
 * Reads the page editor's blocks[n][field] input. All validation lives here, on the
 * server; the browser only sends fields in the order they appear in the form.
 */
final class BlockForm
{
    /** What a key may look like when it arrives from a form: b42, n7. */
    public const KEY = '~^[bn][0-9]{1,9}$~';

    /**
     * A BLOCK'S NAME IN THE EDITOR (PLAN.md D-094), stable while the block exists.
     *
     * `b42` for a block the database knows, `n7` for one added in this session. It is
     * DERIVED, never stored: a saved block's key is its id, and a new block's only has to
     * last until the save that gives it one.
     *
     * It replaces the position in field names and in error keys, because a position cannot
     * name a block once a page is a tree of sections and columns (D-093). In the flat
     * editor nothing visible changes — measured in D-082, where errors were found to follow
     * blocks correctly already — and that is the point of doing it as its own step.
     *
     * @param int $ordinal only used for a block with no id, and only to tell two new blocks
     *                     apart within one render
     */
    public static function key(?int $id, int $ordinal): string
    {
        return $id === null ? 'n' . $ordinal : 'b' . $id;
    }

    /**
     * Blocks in submitted order, with every value cleaned for its field type, and errors
     * keyed "position.field". A block marked _delete is left out. An existing block keeps
     * its stored type whatever the form claims. A layout the block does not declare
     * falls back to its default rather than being stored.
     *
     * @param array<int, array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string}> $stored
     *        block id => the block as stored, for this page's blocks
     * @return array{blocks: list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string, section?: string, column?: int}>, errors: array<string, string>}
     */
    public static function parse(Blocks $registry, mixed $posted, array $stored): array
    {
        $blocks = [];
        $errors = [];
        $ordinal = 0;
        foreach (is_array($posted) ? $posted : [] as $sent => $raw) {
            /* THE KEY THE FORM SENT, if it looks like one (D-094). It is echoed back into
               the markup on a rejected save, and error keys are built from it, so it is
               matched against a shape rather than trusted. A body that does not carry keys
               at all — an older form, a hand-made request — still parses: the block is
               named from its id, or numbered. */
            $key = is_string($sent) && preg_match(self::KEY, $sent) === 1 ? $sent : null;
            if (!is_array($raw) || ($raw['_delete'] ?? '') === '1') {
                continue;
            }
            $id = is_string($raw['id'] ?? null) && ctype_digit($raw['id']) ? (int) $raw['id'] : null;
            if ($id !== null && !isset($stored[$id])) {
                $id = null; // not a block of this page: treat it as new
            }
            /* WHERE IT STANDS (D-098): the KEY of its section and the column inside it.
               Two hidden inputs rather than a nesting of every field name, for the reasons
               that decision gives. A body that carries neither — an older form, a hand-made
               request — leaves them absent, and Page::update() answers that with one
               section per block and every arrangement left as it was. */
            $where = [];
            if (is_string($raw['section'] ?? null) && preg_match(SectionForm::KEY, $raw['section']) === 1) {
                $where = [
                    'section' => $raw['section'],
                    // A column this section does not have is not refused here: the section
                    // it names may be narrowed in the same save, and clamping against a
                    // layout this function cannot see would be a guess. Page::update()
                    // clamps, where both halves are in hand.
                    'column' => is_string($raw['column'] ?? null) && ctype_digit($raw['column'])
                        ? (int) $raw['column']
                        : 0,
                ];
            }
            $type = $id !== null ? $stored[$id]['type'] : (is_string($raw['type'] ?? null) ? $raw['type'] : '');

            if (!$registry->has($type)) {
                if ($id !== null) {
                    $blocks[] = $where + ['key' => $key ?? self::key($id, $ordinal++), 'id' => $id, 'type' => $type, 'content' => null, 'style' => [], 'layout' => ''];
                }
                continue;
            }

            $name = $key ?? self::key($id, $ordinal++);
            $content = [];
            foreach ($registry->get($type)['fields'] as $field => $declared) {
                [$value, $error] = BlockValues::field($declared, $raw[$field] ?? null);
                $content[$field] = $value;
                if ($error !== null) {
                    // Keyed by the BLOCK, not by where it sits: a position cannot name a
                    // block once a page is a tree (D-093), and a key survives a reorder.
                    $errors["{$name}.{$field}"] = $error;
                }
            }
            $layout = $registry->own($type, $raw['layout'] ?? null);
            $blocks[] = $where + [
                'key' => $name,
                'id' => $id,
                'type' => $type,
                'content' => $content,
                'style' => SectionStyle::normalize($raw['style'] ?? null),
                // How it is presented, typed at blocks[key][options][name]; '' or a missing
                // one is the character's (D-166).
                'options' => \App\Core\BlockOptions::normalize($registry->get($type)['options'], $raw['options'] ?? null),
                'layout' => $layout,
            ];
        }

        return ['blocks' => $blocks, 'errors' => $errors];
    }

    /**
     * A block's content from a DOCUMENT (PLAN.md D-173) — a draft's JSON, the autosave's body —
     * through the very checks a form's fields pass: rich text sanitised to its field's list,
     * a line kept to one printable line, a choice to its options. The store is the security
     * boundary; templates trust what it holds, so a document must not be a way around it.
     *
     * A document may be unfinished, so nothing is refused: an address that is no link is
     * emptied rather than kept, and a required field may be empty until Publish asks.
     *
     * @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    public static function clean(Blocks $registry, string $type, array $content): array
    {
        $clean = [];
        foreach ($registry->get($type)['fields'] as $name => $declared) {
            [$value] = BlockValues::field($declared, BlockValues::asSent($declared, $content[$name] ?? null));
            $clean[$name] = BlockValues::linked($declared, $value);
        }

        return $clean;
    }

    /**
     * Where the block called $key sits in this list, or null when no block does.
     *
     * The three no-JS actions below name a BLOCK rather than a position (D-094): a form
     * that was rendered before something moved would otherwise act on whatever has taken
     * that slot since. A key that names nothing does nothing, which is the honest answer to
     * a stale button.
     *
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string}> $blocks
     */
    private static function at(array $blocks, string $key): ?int
    {
        foreach ($blocks as $position => $block) {
            if ($block['key'] === $key) {
                return $position;
            }
        }

        return null;
    }

    /**
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string}> $blocks
     * @return list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string}>
     */
    public static function move(array $blocks, string $key, string $direction): array
    {
        $position = self::at($blocks, $key);
        if ($position === null) {
            return $blocks;
        }
        $target = $direction === 'up' ? $position - 1 : $position + 1;
        if (isset($blocks[$position], $blocks[$target])) {
            [$blocks[$position], $blocks[$target]] = [$blocks[$target], $blocks[$position]];
        }

        return $blocks;
    }

    /**
     * One repeater item moved within its block — D-011's pattern one level down, where the
     * same route serves the drag and the buttons a browser without JavaScript uses.
     *
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string}> $blocks
     * @return list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string}>
     */
    public static function moveItem(Blocks $registry, array $blocks, string $key, string $field, int $item, string $direction): array
    {
        $position = self::at($blocks, $key);
        [$content, $declared] = $position === null ? [null, null] : self::repeaterAt($registry, $blocks, $position, $field);
        if ($content === null || $declared === null || $position === null) {
            return $blocks;
        }
        $items = is_array($content[$field] ?? null) ? array_values($content[$field]) : [];
        $target = $direction === 'up' ? $item - 1 : $item + 1;
        if (!isset($items[$item], $items[$target])) {
            return $blocks;
        }
        [$items[$item], $items[$target]] = [$items[$target], $items[$item]];
        $content[$field] = $items;
        // The WHOLE element back, never an assignment into its 'content' offset: writing
        // through a nested offset of a list narrows that element to the one key written,
        // and the block shape this method promises is lost with it.
        $updated = $blocks[$position];
        $updated['content'] = $content;
        $blocks[$position] = $updated;

        return $blocks;
    }

    /**
     * An empty item appended to a repeater, for the Add button without JavaScript.
     *
     * Refuses past the maximum rather than growing the list and letting the save reject
     * it: the button that cannot do anything should do nothing, not hand back an error
     * for something the editor itself just did.
     *
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string}> $blocks
     * @return list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string}>
     */
    public static function addItem(Blocks $registry, array $blocks, string $key, string $field): array
    {
        $position = self::at($blocks, $key);
        [$content, $declared] = $position === null ? [null, null] : self::repeaterAt($registry, $blocks, $position, $field);
        if ($content === null || $declared === null || $position === null) {
            return $blocks;
        }
        $items = is_array($content[$field] ?? null) ? array_values($content[$field]) : [];
        if (count($items) >= $declared['max']) {
            return $blocks;
        }
        $items[] = Blocks::emptyItem($declared);
        $content[$field] = $items;
        // The whole element back, for the reason given in moveItem().
        $updated = $blocks[$position];
        $updated['content'] = $content;
        $blocks[$position] = $updated;

        return $blocks;
    }

    /**
     * One block's content and the declaration of a repeater field on it, or [null, null]
     * when the position, the block's type or the field name names no repeater.
     *
     * THE FIELD NAME COMES FROM A FORM, so it is a key only once the registry agrees it is
     * one. An action naming a field the block does not declare moves nothing rather than
     * reaching into stored content with whatever was posted.
     *
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options?: array<string, string>, layout: string}> $blocks
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>|null}
     */
    private static function repeaterAt(Blocks $registry, array $blocks, int $position, string $field): array
    {
        $block = $blocks[$position] ?? null;
        if ($block === null || !is_array($block['content']) || !$registry->has($block['type'])) {
            return [null, null];
        }
        $declared = $registry->get($block['type'])['fields'][$field] ?? null;
        if (!is_array($declared) || ($declared['type'] ?? '') !== 'repeater') {
            return [null, null];
        }

        return [$block['content'], $declared];
    }
}
