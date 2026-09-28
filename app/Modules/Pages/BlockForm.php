<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Modules\Design\SectionStyle;
use App\Support\RichText;
use App\Support\SafeUrl;

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
     * A BLOCK MAY SEND ITS SKELETON INSTEAD OF ITS FIELDS (PLAN.md D-081). A group the
     * author never touched posts its id, the marker _unchanged, and nothing else; its
     * content, style and layout are taken from $stored. The result is the same block this
     * would have returned had the browser sent every field, so nothing downstream — the
     * canvas, a rejected save, the write — needs to know which blocks did that.
     *
     * @param array<int, array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string}> $stored
     *        block id => the block as stored, for this page's blocks
     * @return array{blocks: list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string, section?: string, column?: int}>, errors: array<string, string>}
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
            if (($raw['_unchanged'] ?? '') === '1') {
                // Nothing to restore it from, so there is nothing it can mean. Adding an
                // empty block here would turn a lost id into content the author never wrote.
                if ($id !== null) {
                    // ITS FIELDS COME FROM STORAGE, BUT NOT ITS PLACE (D-081 meets D-098).
                    // builder-save.js lets an untouched block send a skeleton; the block may
                    // be untouched and still have been moved, so where it stands is read
                    // from what was sent and only the content is restored.
                    $blocks[] = $where === [] ? $stored[$id] : $where + $stored[$id];
                }
                continue;
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
                [$value, $error] = self::field($declared, $raw[$field] ?? null);
                $content[$field] = $value;
                if ($error !== null) {
                    // Keyed by the BLOCK, not by where it sits: a position cannot name a
                    // block once a page is a tree (D-093), and a key survives a reorder.
                    $errors["{$name}.{$field}"] = $error;
                }
            }
            $layout = $registry->layout($type, $raw['layout'] ?? null);
            $blocks[] = $where + [
                'key' => $name,
                'id' => $id,
                'type' => $type,
                'content' => self::fillRows($registry, $type, $content, $layout),
                'style' => SectionStyle::normalize($raw['style'] ?? null),
                'layout' => $layout,
            ];
        }

        return ['blocks' => $blocks, 'errors' => $errors];
    }

    /**
     * A repeater that says how many items its block's layout wants gets them (D-091).
     *
     * "Four in a row" with three columns left an empty cell in the grid and no obvious way
     * to fill it. The block declares what each row size asks for, so this has no idea that
     * "four" means four; it reads the number the block wrote down.
     *
     * TOPS UP, NEVER TRIMS. Going back to a smaller row keeps every column: a row size is a
     * choice about arrangement, and throwing away what somebody wrote is not one of its
     * consequences. Only a row that is not even filled once is topped up — seven items in
     * rows of four is a full row and a short one, which is ordinary and left alone.
     *
     * Here rather than in the save alone, because the canvas redraws through this same
     * parser: the column appears as the row size is chosen, not after the page is saved.
     *
     * @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    private static function fillRows(Blocks $registry, string $type, array $content, string $layout): array
    {
        foreach ($registry->get($type)['fields'] as $name => $field) {
            $wanted = is_array($field['per_layout'] ?? null) ? ($field['per_layout'][$layout] ?? null) : null;
            if (!is_int($wanted) || !is_array($content[$name] ?? null)) {
                continue;
            }
            $items = array_values($content[$name]);
            while (count($items) < $wanted) {
                $items[] = Blocks::emptyItem($field);
            }
            $content[$name] = $items;
        }

        return $content;
    }

    /**
     * Where the block called $key sits in this list, or null when no block does.
     *
     * The three no-JS actions below name a BLOCK rather than a position (D-094): a form
     * that was rendered before something moved would otherwise act on whatever has taken
     * that slot since. A key that names nothing does nothing, which is the honest answer to
     * a stale button.
     *
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string}> $blocks
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
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string}> $blocks
     * @return list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string}>
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
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string}> $blocks
     * @return list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string}>
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
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string}> $blocks
     * @return list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string}>
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
     * @param list<array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, layout: string}> $blocks
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

    /**
     * @param array<string, mixed> $field
     * @return array{0: mixed, 1: string|null} the cleaned value and an error, if any
     */
    private static function field(array $field, mixed $raw): array
    {
        $required = $field['required'] === true;

        /*
         * A REPEATER IS THE SAME QUESTION, ONCE PER ITEM (PLAN.md O-11).
         *
         * Each item's fields go through this very method, so a media field inside an item
         * is validated as a media field and a richtext field is sanitised per item — no
         * special case, and nothing here has to know which types exist.
         *
         * Over the maximum is REFUSED rather than trimmed, unlike on render: here somebody
         * typed those items, and silently dropping the last one is how an owner loses work
         * without being told. Blocks::normalize() trims instead, because a page must still
         * draw when a definition's maximum shrinks under it.
         */
        if ($field['type'] === 'repeater') {
            $rows = is_array($raw) ? array_values($raw) : [];
            $items = [];
            $itemError = null;
            foreach ($rows as $row) {
                if (($row['_delete'] ?? '') === '1') {
                    continue;
                }
                $item = [];
                foreach ($field['fields'] as $itemName => $itemField) {
                    [$value, $error] = self::field($itemField, is_array($row) ? ($row[$itemName] ?? null) : null);
                    $item[$itemName] = $value;
                    // The first thing wrong, named once: a message per item per field would
                    // bury the block's own errors under a list nobody reads.
                    $itemError ??= $error;
                }
                $items[] = $item;
            }

            if (count($items) > $field['max']) {
                return [array_slice($items, 0, $field['max']), t('pages.field.repeater_max', ['max' => $field['max']])];
            }
            if ($items === [] && $required) {
                return [[], t('pages.field.required')];
            }

            return [$items, $itemError];
        }

        switch ($field['type']) {
            case 'link':
                $label = is_array($raw) ? self::line($raw['label'] ?? null) : '';
                // A bare email or phone number becomes the link it was meant to be (D-039).
                $url = SafeUrl::normalize(is_array($raw) ? self::line($raw['url'] ?? null) : '');
                // A chosen page wins over a typed address (PLAN.md D-034): the address input
                // is hidden while a page is chosen, so whatever it still holds is stale.
                $page = is_array($raw) ? self::line($raw['page'] ?? null) : '';
                if (preg_match('~^[1-9][0-9]{0,9}$~', $page) === 1) {
                    $url = PageLinks::to((int) $page);
                }
                $value = ['label' => $label, 'url' => $url];
                if ($label === '' && $url === '') {
                    return [$value, $required ? t('pages.field.required') : null];
                }
                if ($url === '') {
                    return [$value, t('pages.field.link_url_missing')];
                }
                if (!SafeUrl::isLink($url)) {
                    return [$value, t('pages.field.link_url')];
                }
                // A page supplies its own title when the text is left empty; an address
                // has nothing to say about itself.
                if ($label === '' && PageLinks::reference($url) === null) {
                    return [$value, t('pages.field.link_label')];
                }

                return [$value, null];

            case 'media':
            case 'file':
            case 'form':
                $text = self::line($raw);
                if ($text === '') {
                    return [null, $required ? t('pages.field.required') : null];
                }

                return ctype_digit($text) && (int) $text > 0 ? [(int) $text, null] : [null, t('pages.field.media')];

            case 'select':
                $options = $field['options'];
                if (is_string($raw) && in_array($raw, $options, true)) {
                    return [$raw, null];
                }
                /* NOT SENT IS NOT WRONG (PLAN.md D-118). A form that has never heard of a
                   choice — an editor opened before the block gained it, a save written
                   before it existed — says nothing about it, and the choice takes its first
                   option, as a stored block without it does (Blocks::normalize). A value that
                   IS sent and is not one of the options is still refused. Adding two choices
                   to the hero failed every such save with "Choose one of the options" against
                   fields the author could not see. */
                if ($raw === null) {
                    return [$options[0], null];
                }

                return [$options[0], t('pages.field.select')];

            case 'richtext':
                $html = RichText::sanitize(is_string($raw) ? $raw : '');
                $empty = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '';

                return [$empty ? '' : $html, $empty && $required ? t('pages.field.required') : null];

            case 'textarea':
                $text = is_string($raw) ? trim(self::printable(str_replace("\r\n", "\n", $raw))) : '';

                return [$text, $text === '' && $required ? t('pages.field.required') : null];

            default: // text
                $text = self::line($raw);

                return [$text, $text === '' && $required ? t('pages.field.required') : null];
        }
    }

    /**
     * A single-line string: valid UTF-8, no control characters, no line breaks.
     */
    private static function line(mixed $raw): string
    {
        return is_string($raw) ? trim(self::printable(str_replace(["\r", "\n"], ' ', $raw))) : '';
    }

    private static function printable(string $text): string
    {
        return (string) preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]~', '', mb_scrub($text, 'UTF-8'));
    }
}
