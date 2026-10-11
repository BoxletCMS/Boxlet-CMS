<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Db;

/**
 * WHAT TYPING ON THE PAGE NEEDS TO KNOW (PLAN.md D-178, README 4.4), handed to builder-inline.js
 * with the document: each block type's fields — their type, whether they are required and, for
 * rich text, what they allow — the item a repeater's "+" adds, and the pages a link may lead
 * to. The canvas names a field by its path (`heading`, `items.2.heading`); this says what kind
 * of field that is, so the browser never guesses from the markup.
 */
final class InlineFields
{
    /**
     * @return array{fields: array<string, array<string, mixed>>, items: array<string, array<string, mixed>>, pages: list<array{ref: string, title: string, depth: int, url: string}>}
     */
    public static function of(Db $db, Blocks $registry, string $locale): array
    {
        $say = static fn (string $key): string => site_t($key, $locale, 'samples');
        $fields = [];
        $items = [];
        foreach ($registry->types() as $type) {
            foreach ($registry->get($type)['fields'] as $name => $field) {
                $fields[$type][$name] = self::describe($type, (string) $name, $field);
                if ($field['type'] === 'repeater') {
                    // What one of them is called: the block's bar names the selected one, "Logo 2" (D-188).
                    $fields[$type][$name]['item'] = t('block.' . $type . '.' . $name . '.item');
                    foreach ($field['fields'] as $sub => $declared) {
                        $fields[$type][$name]['fields'][$sub] = self::describe($type, $name . '.' . $sub, $declared);
                    }
                    $items[$type][$name] = self::item($type, $field, $say);
                }
            }
        }
        $pages = [];
        foreach (PageLinks::choices($db, $locale) as $group => $choice) {
            $pages[] = ['ref' => PageLinks::to($group), 'title' => $choice['title'], 'depth' => $choice['depth'], 'url' => $choice['url']];
        }

        return ['fields' => $fields, 'items' => $items, 'pages' => $pages];
    }

    /**
     * What "+" adds to a repeater, on the page and in All content (D-179): an empty item whose
     * words say only that it is new — "New card", "A short description." — from the samples'
     * `item.<type>.<field>` keys, in the page's language. Not a new block's first item, which
     * reads as one of a set ("One of the three") and is wrong as the fourth.
     *
     * @param array<string, mixed> $field a validated repeater declaration
     * @param \Closure(string): string $say
     * @return array<string, mixed>
     */
    private static function item(string $type, array $field, \Closure $say): array
    {
        $item = Blocks::emptyItem($field);
        foreach ($field['fields'] as $sub => $declared) {
            $key = 'item.' . $type . '.' . $sub;
            $words = $say($key);
            if ($words === $key || !in_array($declared['type'], ['text', 'textarea', 'richtext'], true)) {
                continue;
            }
            $item[$sub] = $declared['type'] === 'richtext' ? '<p>' . htmlspecialchars($words, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' : $words;
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    private static function describe(string $type, string $path, array $field): array
    {
        return [
            'type' => $field['type'],
            'required' => $field['required'] ?? false,
            'inline' => $field['inline'] ?? false,
            'label' => t('block.' . $type . '.' . $path),
        ] + (isset($field['allow']) ? ['allow' => $field['allow']] : []) + (isset($field['max']) ? ['max' => $field['max']] : []);
    }
}
