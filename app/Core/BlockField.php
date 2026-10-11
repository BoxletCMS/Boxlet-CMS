<?php

namespace App\Core;

/**
 * ONE FIELD OF A BLOCK DEFINITION, checked (SPEC §5.3): its type from the closed set, its
 * flags, and for a repeater the fields of one item. Split from BlockDefinition when the
 * definition grew options and pictograms (D-166): the block's shape is checked there, each
 * field's here.
 */
final class BlockField
{
    private const FIELD_KEYS = ['type', 'required', 'translatable', 'options', 'sample', 'inline', 'allow'];

    /** A repeater's own fields cannot hold another repeater, and it must say how many items it takes. */
    private const REPEATER_KEYS = ['type', 'required', 'translatable', 'fields', 'max', 'inline'];

    /**
     * What a field is edited in place on the canvas by default (README 1.4, D-166): the words,
     * the pictures and the items people see. A link's label is its words; the field type
     * `link` is inline for that reason and its address stays in the panel.
     */
    private const INLINE_TYPES = ['text', 'textarea', 'richtext', 'media', 'link', 'repeater'];

    /** A t() key: dotted lower-case segments, like preview.hero.heading. */
    private const LANG_KEY = '~^[a-z][a-z0-9_]*(\\.[a-z][a-z0-9_]*)+$~';

    /**
     * Names the page editor uses for its own inputs inside blocks[n] (`id`, `type`), and the
     * segments the admin's label keys use beside a field's (D-200): block.{type}.layout.…,
     * .option.…, .summary, and a repeater's .item. A field of one of those names would read
     * another label as its own.
     */
    private const RESERVED_FIELD_NAMES = ['id', 'type', 'layout', 'option', 'summary', 'item'];

    /**
     * Checks one field and returns it with its optional flags filled in.
     *
     * @return array<string, mixed>
     */
    public static function validate(string $type, int|string $name, mixed $field): array
    {
        $at = "field '{$name}'";
        if (!is_string($name) || !preg_match(BlockDefinition::NAME, $name)) {
            BlockDefinition::fail($type, "{$at}: field names must match [a-z][a-z0-9_]*");
        }
        if (in_array($name, self::RESERVED_FIELD_NAMES, true)) {
            BlockDefinition::fail($type, "{$at}: the name is reserved by the page editor and the admin's labels");
        }
        if (!is_array($field)) {
            BlockDefinition::fail($type, "{$at}: must be an array");
        }
        /*
         * THE TYPE IS READ BEFORE THE KEYS ARE CHECKED, because which keys are allowed
         * depends on it: a repeater takes 'fields' and 'max' and no 'options', everything
         * else is the other way round. Checking first and reading after rejected every
         * repeater ever written as "unknown key 'max'" — caught by measuring the contract
         * rather than by reading it back.
         */
        $fieldType = $field['type'] ?? null;
        $allowed = $fieldType === 'repeater' ? self::REPEATER_KEYS : self::FIELD_KEYS;
        foreach (array_keys($field) as $key) {
            if (in_array($key, $allowed, true)) {
                continue;
            }
            // Say which rule was broken, not merely that something was. "unknown key 'max'"
            // sends the reader looking for a typo; "only a repeater takes 'max'" does not.
            if (in_array($key, ['fields', 'max'], true)) {
                BlockDefinition::fail($type, "{$at}: only a repeater takes '{$key}'");
            }
            if ($key === 'options') {
                BlockDefinition::fail($type, "{$at}: a repeater does not take 'options'");
            }
            BlockDefinition::fail($type, "{$at}: unknown key '{$key}'");
        }
        if (!is_string($fieldType) || !in_array($fieldType, BlockDefinition::FIELD_TYPES, true)) {
            BlockDefinition::fail($type, "{$at}: 'type' must be one of " . implode(', ', BlockDefinition::FIELD_TYPES));
        }
        if (!in_array($fieldType, BlockDefinition::SUPPORTED_FIELD_TYPES, true)) {
            BlockDefinition::fail($type, "{$at}: field type '{$fieldType}' is in the closed set but not implemented yet");
        }
        foreach (['required', 'translatable'] as $flag) {
            if (array_key_exists($flag, $field) && !is_bool($field[$flag])) {
                BlockDefinition::fail($type, "{$at}: '{$flag}' must be true or false");
            }
        }
        /*
         * WHAT THIS FIELD SAYS WHEN THE BLOCK IS NEW (PLAN.md D-083, D-176, SPEC §5.3).
         *
         * Optional, and a LANGUAGE KEY rather than words: a new block starts with it in the
         * page's language (lang/{code}/samples.php, English where a language has none), and
         * from then on it is the owner's text. Without it the field starts empty. A link
         * field's sample is not used: a link's words without an address is an error.
         */
        if (array_key_exists('sample', $field)
            && (!is_string($field['sample']) || !preg_match(self::LANG_KEY, $field['sample']))) {
            BlockDefinition::fail($type, "{$at}: 'sample' must be a language key, like 'preview.hero.heading'");
        }

        if (array_key_exists('inline', $field) && !is_bool($field['inline'])) {
            BlockDefinition::fail($type, "{$at}: 'inline' must be true or false");
        }
        $normalized = [
            'type' => $fieldType,
            'required' => $field['required'] ?? false,
            'translatable' => $field['translatable'] ?? false,
            'sample' => is_string($field['sample'] ?? null) ? $field['sample'] : null,
            // Edited in place on the canvas, or only in the panel (D-166).
            'inline' => $field['inline'] ?? in_array($fieldType, self::INLINE_TYPES, true),
        ];

        // What a rich text field may hold (D-163): one list, read by the sanitiser on save
        // and by the canvas's toolbar, so the toolbar never offers what the save would strip.
        if ($fieldType === 'richtext') {
            $allow = $field['allow'] ?? \App\Support\RichText::FEATURES;
            if (!is_array($allow) || $allow === [] || !array_is_list($allow) || array_diff($allow, \App\Support\RichText::FEATURES) !== []) {
                BlockDefinition::fail($type, "{$at}: 'allow' must be a list from " . implode(', ', \App\Support\RichText::FEATURES));
            }
            $normalized['allow'] = $allow;
        } elseif (array_key_exists('allow', $field)) {
            BlockDefinition::fail($type, "{$at}: only rich text fields take 'allow'");
        }

        if ($fieldType === 'select') {
            $options = $field['options'] ?? null;
            if (!is_array($options) || $options === [] || !array_is_list($options)) {
                BlockDefinition::fail($type, "{$at}: a select needs 'options', a non-empty list of values");
            }
            foreach ($options as $option) {
                if (!is_string($option) || !preg_match(BlockDefinition::SLUG, $option)) {
                    BlockDefinition::fail($type, "{$at}: option values must match [a-z][a-z0-9_-]*");
                }
            }
            $normalized['options'] = $options;
        } elseif (array_key_exists('options', $field)) {
            BlockDefinition::fail($type, "{$at}: only select fields take 'options'");
        }

        /*
         * A REPEATER DECLARES ONE ITEM AND HOW MANY OF THEM (PLAN.md O-11).
         *
         * Its `fields` are ordinary field declarations, checked by this same method — so a
         * media field inside an item is a media field, a richtext field is sanitised like
         * any other, and nothing here has to know which types exist.
         *
         * ONE LEVEL ONLY. A repeater inside a repeater is a table, and a block editor that
         * nests groups without end is one nobody can read — the same reasoning that caps a
         * menu at one level of submenu (D-028). Refused at boot, where every other malformed
         * definition is refused, rather than discovered at render.
         */
        if ($fieldType === 'repeater') {
            $max = $field['max'] ?? null;
            if (!is_int($max) || $max < 1) {
                BlockDefinition::fail($type, "{$at}: a repeater needs 'max', how many items it takes, at least 1");
            }

            $itemFields = $field['fields'] ?? null;
            if (!is_array($itemFields) || $itemFields === []) {
                BlockDefinition::fail($type, "{$at}: a repeater needs 'fields', the fields of one item");
            }

            $items = [];
            foreach ($itemFields as $itemName => $itemField) {
                if (is_array($itemField) && ($itemField['type'] ?? '') === 'repeater') {
                    BlockDefinition::fail($type, "{$at}: field '{$itemName}': a repeater cannot hold another repeater — "
                        . 'groups nested without end are a table, not a block, and nobody can read an editor '
                        . 'built that way (the reasoning that stops a menu at one level of submenu)');
                }
                $items[(string) $itemName] = self::validate($type, $itemName, $itemField);
            }

            $normalized['fields'] = $items;
            $normalized['max'] = $max;
        }

        return $normalized;
    }
}
