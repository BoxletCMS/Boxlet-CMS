<?php

namespace App\Core;

use RuntimeException;

/**
 * The block contract (SPEC §5.3): what a block.php must contain, checked once.
 *
 * Split out of Blocks, which reached the 300-line limit when a section learned to draw a
 * background picture. The seam is a real one and not a line count: everything here is
 * static, touches no registry state, and runs exactly once per block at discovery — while
 * what remains in Blocks is a live registry that answers questions and renders. Rendering
 * could not have moved instead: it needs the definitions and the template directory, so
 * extracting it would have meant inventing a collaborator to carry them.
 *
 * Every problem is fatal and names the block and the key. A malformed definition throws at
 * boot, never silently at render.
 */
final class BlockDefinition
{
    /** The closed set of field types in SPEC §5.3. */
    public const FIELD_TYPES = ['text', 'textarea', 'richtext', 'media', 'media_multi', 'file', 'link', 'select', 'toggle', 'number', 'repeater', 'form'];

    /** The subset implemented so far. The rest arrive when a block needs them. `file` is a
        file for visitors to download from the library (PLAN.md D-127). */
    public const SUPPORTED_FIELD_TYPES = ['text', 'textarea', 'richtext', 'media', 'file', 'link', 'select', 'repeater', 'form'];

    /** A repeater's own fields cannot hold another repeater, and it must say how many items it takes. */
    private const REPEATER_KEYS = ['type', 'required', 'translatable', 'fields', 'max', 'per_layout'];

    public const NAME = '~^[a-z][a-z0-9_]*$~';
    public const SLUG = '~^[a-z][a-z0-9_-]*$~';

    private const KEYS = ['type', 'icon', 'group', 'version', 'fields', 'layouts', 'defaults'];

    /**
     * OPTIONAL, because the site's chrome goes through this too. A header and a footer are
     * blocks by every other measure — fields, layouts, a template — and they are the two
     * that can never be ADDED, so a shelf in the library is a thing they cannot have. They
     * say so by leaving it out; a page block that leaves it out is caught by a test, where
     * a made-up shelf would be caught by nobody.
     */
    private const OPTIONAL = ['group'];

    /**
     * WHICH SHELF A BLOCK SITS ON in the library (PLAN.md D-104, and the design artifact).
     *
     * A closed set, for the reason every other closed set here exists: a free string would
     * let one block say "Media" and the next "media", and the library would grow a shelf
     * for each. Five is what the artifact names, and a block that fits none of them is a
     * question about the block rather than about the list.
     */
    public const GROUPS = ['text', 'media', 'layout', 'marketing', 'embed'];
    /** A t() key: dotted lower-case segments, like preview.hero.heading. */
    private const LANG_KEY = '~^[a-z][a-z0-9_]*(\\.[a-z][a-z0-9_]*)+$~';

    private const FIELD_KEYS = ['type', 'required', 'translatable', 'options', 'sample'];

    /** Names the page editor uses for its own inputs inside blocks[n]. */
    private const RESERVED_FIELD_NAMES = ['id', 'type'];

    /**
     * Checks one definition against SPEC §5.3 and returns it with the optional field
     * flags filled in.
     *
     * @return array<string, mixed>
     */
    public static function validate(string $type, mixed $definition): array
    {
        if (!is_array($definition)) {
            self::fail($type, 'block.php must return an array');
        }
        foreach (self::KEYS as $key) {
            if (!array_key_exists($key, $definition) && !in_array($key, self::OPTIONAL, true)) {
                self::fail($type, "missing key '{$key}'");
            }
        }
        foreach (array_keys($definition) as $key) {
            if (!in_array($key, self::KEYS, true)) {
                self::fail($type, "unknown key '{$key}'");
            }
        }
        if (!preg_match(self::NAME, $type) || $definition['type'] !== $type) {
            self::fail($type, "'type' must equal the directory name and match [a-z][a-z0-9_]*");
        }
        if (!is_string($definition['icon']) || trim($definition['icon']) === '') {
            self::fail($type, "'icon' must be a non-empty string");
        }
        $definition['group'] = $definition['group'] ?? null;
        if ($definition['group'] !== null
            && (!is_string($definition['group']) || !in_array($definition['group'], self::GROUPS, true))) {
            self::fail($type, "'group' must be one of: " . implode(', ', self::GROUPS));
        }
        if (!is_int($definition['version']) || $definition['version'] < 1) {
            self::fail($type, "'version' must be an integer of at least 1");
        }

        if (!is_array($definition['fields']) || $definition['fields'] === []) {
            self::fail($type, "'fields' must be a non-empty array");
        }
        $fields = [];
        foreach ($definition['fields'] as $name => $field) {
            $fields[(string) $name] = self::validateField($type, $name, $field);
        }

        $layouts = $definition['layouts'];
        if (!is_array($layouts) || $layouts === [] || !array_is_list($layouts)) {
            self::fail($type, "'layouts' must be a non-empty list");
        }
        foreach ($layouts as $layout) {
            if (!is_string($layout) || !preg_match(self::SLUG, $layout)) {
                self::fail($type, "layout names must match [a-z][a-z0-9_-]*");
            }
        }
        if (count(array_unique($layouts)) !== count($layouts)) {
            self::fail($type, "'layouts' contains a duplicate");
        }
        // A per_layout naming a layout this block does not have would silently never apply,
        // which is the kind of typo that is found months later by somebody wondering why a
        // control does nothing. Checked here because it needs both halves.
        foreach ($fields as $name => $field) {
            foreach (array_keys($field['per_layout'] ?? []) as $layout) {
                if (!in_array($layout, $layouts, true)) {
                    self::fail($type, "field '{$name}': 'per_layout' names '{$layout}', which is not one of this block's layouts");
                }
            }
        }
        $defaults = $definition['defaults'];
        if (!is_array($defaults) || array_keys($defaults) !== ['layout'] || !in_array($defaults['layout'], $layouts, true)) {
            self::fail($type, "'defaults' must be ['layout' => one of 'layouts']");
        }

        return [
            'type' => $type,
            'icon' => $definition['icon'],
            // Which shelf it sits on in the library, or null for the site's chrome (D-104).
            'group' => $definition['group'],
            'version' => $definition['version'],
            'fields' => $fields,
            'layouts' => $layouts,
            'defaults' => $defaults,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function validateField(string $type, int|string $name, mixed $field): array
    {
        $at = "field '{$name}'";
        if (!is_string($name) || !preg_match(self::NAME, $name)) {
            self::fail($type, "{$at}: field names must match [a-z][a-z0-9_]*");
        }
        if (in_array($name, self::RESERVED_FIELD_NAMES, true)) {
            self::fail($type, "{$at}: the name is reserved by the page editor");
        }
        if (!is_array($field)) {
            self::fail($type, "{$at}: must be an array");
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
                self::fail($type, "{$at}: only a repeater takes '{$key}'");
            }
            if ($key === 'options') {
                self::fail($type, "{$at}: a repeater does not take 'options'");
            }
            self::fail($type, "{$at}: unknown key '{$key}'");
        }
        if (!is_string($fieldType) || !in_array($fieldType, self::FIELD_TYPES, true)) {
            self::fail($type, "{$at}: 'type' must be one of " . implode(', ', self::FIELD_TYPES));
        }
        if (!in_array($fieldType, self::SUPPORTED_FIELD_TYPES, true)) {
            self::fail($type, "{$at}: field type '{$fieldType}' is in the closed set but not implemented yet");
        }
        foreach (['required', 'translatable'] as $flag) {
            if (array_key_exists($flag, $field) && !is_bool($field[$flag])) {
                self::fail($type, "{$at}: '{$flag}' must be true or false");
            }
        }
        /*
         * WHAT THIS FIELD SAYS IN A LIBRARY PREVIEW (PLAN.md D-083, SPEC §5.3).
         *
         * Optional, and a LANGUAGE KEY rather than words: a preview is drawn in the admin's
         * language and sample copy is copy. Without it the preview falls back to the
         * generic sample for the field's type, which is what every field had and why all
         * five cards read the same sentence.
         */
        if (array_key_exists('sample', $field)
            && (!is_string($field['sample']) || !preg_match(self::LANG_KEY, $field['sample']))) {
            self::fail($type, "{$at}: 'sample' must be a language key, like 'preview.hero.heading'");
        }

        $normalized = [
            'type' => $fieldType,
            'required' => $field['required'] ?? false,
            'translatable' => $field['translatable'] ?? false,
            'sample' => is_string($field['sample'] ?? null) ? $field['sample'] : null,
        ];

        if ($fieldType === 'select') {
            $options = $field['options'] ?? null;
            if (!is_array($options) || $options === [] || !array_is_list($options)) {
                self::fail($type, "{$at}: a select needs 'options', a non-empty list of values");
            }
            foreach ($options as $option) {
                if (!is_string($option) || !preg_match(self::SLUG, $option)) {
                    self::fail($type, "{$at}: option values must match [a-z][a-z0-9_-]*");
                }
            }
            $normalized['options'] = $options;
        } elseif (array_key_exists('options', $field)) {
            self::fail($type, "{$at}: only select fields take 'options'");
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
                self::fail($type, "{$at}: a repeater needs 'max', how many items it takes, at least 1");
            }

            $itemFields = $field['fields'] ?? null;
            if (!is_array($itemFields) || $itemFields === []) {
                self::fail($type, "{$at}: a repeater needs 'fields', the fields of one item");
            }

            $items = [];
            foreach ($itemFields as $itemName => $itemField) {
                if (is_array($itemField) && ($itemField['type'] ?? '') === 'repeater') {
                    self::fail($type, "{$at}: field '{$itemName}': a repeater cannot hold another repeater — "
                        . 'groups nested without end are a table, not a block, and nobody can read an editor '
                        . 'built that way (the reasoning that stops a menu at one level of submenu)');
                }
                $items[(string) $itemName] = self::validateField($type, $itemName, $itemField);
            }

            /*
             * HOW MANY ITEMS A LAYOUT WANTS (PLAN.md D-091, SPEC §5.3).
             *
             * Optional. Choosing "four in a row" on a Columns block with three columns left
             * an empty cell and no way to fill it except knowing to press Add; the owner
             * read that as the control not working, and he was right. With this the block
             * SAYS what a layout asks for, so nothing generic has to guess that "four"
             * means four — the editor and the save both read it from here.
             *
             * Only ever tops up. Going back to "two in a row" keeps the four columns, since
             * a row size is a choice about arrangement and deleting somebody's writing is
             * not one of its consequences.
             */
            $perLayout = $field['per_layout'] ?? null;
            if ($perLayout !== null) {
                if (!is_array($perLayout) || $perLayout === []) {
                    self::fail($type, "{$at}: 'per_layout' must be a map of layout name => how many items it wants");
                }
                foreach ($perLayout as $layout => $wanted) {
                    if (!is_string($layout) || !preg_match(self::SLUG, $layout)) {
                        self::fail($type, "{$at}: 'per_layout' keys must be layout names matching [a-z][a-z0-9_-]*");
                    }
                    if (!is_int($wanted) || $wanted < 1 || $wanted > $max) {
                        self::fail($type, "{$at}: 'per_layout[{$layout}]' must be an integer between 1 and the repeater's max of {$max}");
                    }
                }
            }

            $normalized['fields'] = $items;
            $normalized['max'] = $max;
            $normalized['per_layout'] = is_array($perLayout) ? $perLayout : [];
        }

        return $normalized;
    }

    public static function fail(string $type, string $message): never
    {
        throw new RuntimeException("Block {$type}: {$message}");
    }
}
