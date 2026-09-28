<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Db;

/**
 * Which blocks of a translation have fallen behind their source (SPEC §5.2, PLAN.md D-043,
 * step 3). A block is STALE when the hash of its source block's translatable fields today
 * differs from the one recorded when it was translated — so editing one block of the
 * original marks that block, in every translation, and nothing else. Re-translation is
 * never all-or-nothing.
 *
 * Computed when asked, never stored: the source's hash changes the moment the source is
 * saved, and a stored "stale" flag would be one more thing a save had to remember to set
 * in every translation.
 */
final class TranslationStatus
{
    /**
     * A translation's standing against its source: its stale blocks, each with the source
     * block's words today, and how many of the source's blocks it does not have at all.
     * Empty for a page that is its group's source, or has none.
     *
     * @return array{source: array<string, mixed>|null, stale: array<int, array{source: int, type: string, content: array<string, mixed>}>, missing: int}
     */
    public static function of(Db $db, Blocks $registry, int $pageId): array
    {
        $none = ['source' => null, 'stale' => [], 'missing' => 0];
        $page = $db->one('SELECT id, content_group_id, translation_status FROM pages WHERE id = ?', [$pageId]);
        if ($page === null || $page['translation_status'] === 'source') {
            return $none;
        }
        $source = $db->one(
            "SELECT * FROM pages WHERE content_group_id = ? AND translation_status = 'source' AND id <> ?",
            [(int) ($page['content_group_id'] ?? $page['id']), $pageId],
        );
        if ($source === null) {
            return $none;
        }

        $original = [];
        foreach ($db->all('SELECT id, block_group_id, block_type, content_json FROM page_blocks WHERE page_id = ?', [(int) $source['id']]) as $block) {
            $type = (string) $block['block_type'];
            $content = json_decode((string) $block['content_json'], true);
            if (!$registry->has($type) || !is_array($content)) {
                continue;
            }
            $original[(int) ($block['block_group_id'] ?? $block['id'])] = [
                'source' => (int) $block['id'],
                'type' => $type,
                'content' => $content,
                'hash' => Translations::blockHash($registry, $type, $content),
            ];
        }

        $stale = [];
        $present = [];
        foreach ($db->all('SELECT id, block_group_id, source_hash FROM page_blocks WHERE page_id = ?', [$pageId]) as $block) {
            $group = (int) ($block['block_group_id'] ?? $block['id']);
            $present[$group] = true;
            $from = $original[$group] ?? null;
            if ($from !== null && $block['source_hash'] !== $from['hash']) {
                $stale[(int) $block['id']] = ['source' => $from['source'], 'type' => $from['type'], 'content' => $from['content']];
            }
        }

        return ['source' => $source, 'stale' => $stale, 'missing' => count(array_diff_key($original, $present))];
    }

    /**
     * How many stale blocks each translation on the site has, keyed by page id; pages with
     * none are left out.
     *
     * @return array<int, int>
     */
    public static function counts(Db $db, Blocks $registry): array
    {
        $counts = [];
        foreach ($db->all("SELECT id FROM pages WHERE translation_status <> 'source'") as $row) {
            $stale = count(self::of($db, $registry, (int) $row['id'])['stale']);
            if ($stale > 0) {
                $counts[(int) $row['id']] = $stale;
            }
        }

        return $counts;
    }

    /**
     * Records that a translation's block is up to date with its source as it is now.
     * Returns false when the block is not a translated block of that page.
     */
    public static function markCurrent(Db $db, Blocks $registry, int $pageId, int $blockId): bool
    {
        $stale = self::of($db, $registry, $pageId)['stale'][$blockId] ?? null;
        if ($stale === null) {
            // Already current, or not a translated block of this page: nothing to record,
            // and nothing to refuse either — the owner's intent is already the state.
            return $db->one('SELECT id FROM page_blocks WHERE id = ? AND page_id = ?', [$blockId, $pageId]) !== null;
        }
        $db->query(
            'UPDATE page_blocks SET source_hash = ? WHERE id = ? AND page_id = ?',
            [Translations::blockHash($registry, $stale['type'], $stale['content']), $blockId, $pageId],
        );

        return true;
    }

    /**
     * The source block's words as the inspector shows them beside a stale block: each
     * translatable field with its label, rich text as plain text, a link by its label, and
     * a repeater's items numbered.
     *
     * @param array<string, mixed> $content
     * @return list<array{label: string, text: string}>
     */
    public static function words(Blocks $registry, string $type, array $content): array
    {
        return self::wordsIn($registry->get($type)['fields'], $content, 'block.' . $type, '');
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @param array<string, mixed> $content
     * @return list<array{label: string, text: string}>
     */
    private static function wordsIn(array $fields, array $content, string $key, string $prefix): array
    {
        $words = [];
        foreach ($fields as $name => $field) {
            $value = $content[$name] ?? null;
            if ($field['type'] === 'repeater') {
                foreach (is_array($value) ? array_values($value) : [] as $n => $item) {
                    $words = array_merge($words, self::wordsIn(
                        $field['fields'],
                        is_array($item) ? $item : [],
                        $key . '.' . $name,
                        t('pages.field.repeater_item', ['number' => (string) ($n + 1)]) . ' · ',
                    ));
                }
                continue;
            }
            if (($field['translatable'] ?? false) !== true) {
                continue;
            }
            $text = match (true) {
                is_array($value) => (string) ($value['label'] ?? ''),
                is_string($value) => trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>'], ["</p>\n", "\n"], $value)))),
                default => '',
            };
            if ($text !== '') {
                $words[] = ['label' => $prefix . t($key . '.' . $name), 'text' => $text];
            }
        }

        return $words;
    }
}
