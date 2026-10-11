<?php

namespace App\Core;

use App\Support\Pictogram;
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

    public const NAME = '~^[a-z][a-z0-9_]*$~';
    public const SLUG = '~^[a-z][a-z0-9_-]*$~';

    private const KEYS = ['type', 'icon', 'group', 'version', 'fields', 'layouts', 'defaults', 'options', 'across'];

    /**
     * OPTIONAL, because the site's chrome goes through this too. A header and a footer are
     * blocks by every other measure — fields, layouts, a template — and they are the two
     * that can never be ADDED, so a shelf in the library is a thing they cannot have. They
     * say so by leaving it out; a page block that leaves it out is caught by a test, where
     * a made-up shelf would be caught by nobody.
     */
    private const OPTIONAL = ['group', 'options', 'across'];

    /**
     * WHICH SHELF A BLOCK SITS ON in the library (PLAN.md D-104, and the design artifact).
     *
     * A closed set, for the reason every other closed set here exists: a free string would
     * let one block say "Media" and the next "media", and the library would grow a shelf
     * for each. Five is what the artifact names, and a block that fits none of them is a
     * question about the block rather than about the list.
     */
    public const GROUPS = ['text', 'media', 'layout', 'marketing', 'embed'];

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
            $fields[(string) $name] = BlockField::validate($type, $name, $field);
        }

        // Each layout with the drawing that stands for it (D-166): layout name => parts, the
        // shape Pictogram::svg() draws. A map, so a name cannot be there twice.
        $declared = $definition['layouts'];
        if (!is_array($declared) || $declared === [] || array_is_list($declared)) {
            self::fail($type, "'layouts' must map each layout name to its pictogram");
        }
        $layouts = [];
        $pictograms = [];
        foreach ($declared as $layout => $parts) {
            if (!is_string($layout) || !preg_match(self::SLUG, $layout)) {
                self::fail($type, "layout names must match [a-z][a-z0-9_-]*");
            }
            // block.{type}.layout.short.{layout} is a short name's key (D-179, D-200).
            if ($layout === 'short') {
                self::fail($type, "layout 'short' is reserved for the admin's short names");
            }
            $layouts[] = $layout;
            $pictograms[$layout] = Pictogram::validate($type . ' layout ' . $layout, $parts);
        }
        $defaults = $definition['defaults'];
        if (!is_array($defaults) || array_keys($defaults) !== ['layout'] || !in_array($defaults['layout'], $layouts, true)) {
            self::fail($type, "'defaults' must be ['layout' => one of 'layouts']");
        }

        // HOW MANY STAND SIDE BY SIDE in a layout of a fixed count across (D-189): layout =>
        // columns, for a block whose items are one repeater. Not what a layout wants of the
        // content (D-186 removed that): the inspector only says when the items leave one alone.
        $across = $definition['across'] ?? [];
        if (!is_array($across)) {
            self::fail($type, "'across' must map layouts to how many stand side by side");
        }
        foreach ($across as $layout => $count) {
            if (!in_array($layout, $layouts, true) || !is_int($count) || $count < 2) {
                self::fail($type, "'across' must map layouts to how many stand side by side");
            }
        }
        if ($across !== [] && count(array_filter($fields, static fn (array $f): bool => $f['type'] === 'repeater')) !== 1) {
            self::fail($type, "'across' needs exactly one repeater to count");
        }

        return [
            'type' => $type,
            'icon' => $definition['icon'],
            // Which shelf it sits on in the library, or null for the site's chrome (D-104).
            'group' => $definition['group'],
            'version' => $definition['version'],
            'fields' => $fields,
            'layouts' => $layouts,
            'pictograms' => $pictograms,
            'defaults' => $defaults,
            // How the block is presented, '' following the character (D-166).
            'options' => BlockOptions::validate($type, $definition['options'] ?? [], $layouts),
            'across' => $across,
        ];
    }

    public static function fail(string $type, string $message): never
    {
        throw new RuntimeException("Block {$type}: {$message}");
    }
}
