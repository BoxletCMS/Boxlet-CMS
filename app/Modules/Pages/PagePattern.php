<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Design\Characters;
use App\Modules\Design\DesignSetPatterns;
use App\Modules\Design\SectionStyle;

/**
 * PATTERNS (PLAN.md D-163, D-169, D-173, README 2.3): a section kept to be used again — its
 * arrangement, its style and its blocks with what they say — and inserted into a page's draft
 * as a deep copy, every key new, so it is a section of that page and nothing ties it to where
 * it came from.
 *
 * Two kinds, one shape. "My patterns" are the owner's, saved from a page into `patterns`. A
 * design set's are read from the set of the site's character when they are offered (D-169),
 * their words in the page's language, else English.
 *
 * @phpstan-import-type Document from PageDocument
 * @phpstan-type Pattern array{section: array{layout: string, stack: string, style: array<string, string|int|null>}, blocks: list<array{type: string, layout: string, column: int, options: array<string, string>, content: array<string, mixed>}>}
 */
final class PagePattern
{
    /** The longest a pattern's name may be, as the column has it. */
    public const NAME = 120;

    /**
     * One section of a document as a pattern, or null when the document has no such section
     * or it holds nothing that can be drawn.
     *
     * Its anchor is left behind — an anchor names a place on one page (D-165) — and its name
     * stays, since it is what the owner called the section.
     *
     * @param Document $document
     * @return Pattern|null
     */
    public static function fromSection(array $document, string $key): ?array
    {
        $section = null;
        foreach ($document['sections'] as $candidate) {
            if ($candidate['key'] === $key) {
                $section = $candidate;
            }
        }
        if ($section === null) {
            return null;
        }
        $blocks = [];
        foreach ($document['blocks'] as $block) {
            if ($block['section'] === $key && $block['content'] !== null) {
                $blocks[] = ['type' => $block['type'], 'layout' => $block['layout'], 'column' => $block['column'], 'options' => $block['options'], 'content' => $block['content']];
            }
        }
        if ($blocks === []) {
            return null;
        }
        $style = $section['style'];
        $style[SectionStyle::ANCHOR] = '';

        return ['section' => ['layout' => $section['layout'], 'stack' => $section['stack'], 'style' => $style], 'blocks' => $blocks];
    }

    /**
     * @param Pattern $pattern
     * @return int the new pattern's id
     */
    public static function save(Db $db, string $name, array $pattern): int
    {
        $db->query(
            "INSERT INTO patterns (name, section_json, source, set_id, created_at) VALUES (?, ?, 'user', NULL, ?)",
            [mb_substr(trim($name), 0, self::NAME), (string) json_encode($pattern, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), gmdate('Y-m-d H:i:s')],
        );

        return (int) $db->lastInsertId();
    }

    /**
     * The owner's patterns, newest first: what a list needs, never the sections themselves.
     *
     * @return list<array{id: int, name: string, created_at: string}>
     */
    public static function mine(Db $db): array
    {
        $rows = [];
        foreach ($db->all("SELECT id, name, created_at FROM patterns WHERE source = 'user' ORDER BY created_at DESC, id DESC") as $row) {
            $rows[] = ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'created_at' => (string) $row['created_at']];
        }

        return $rows;
    }

    /**
     * The design set's patterns for the site's character, named in $locale.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function fromSet(string $character, string $locale): array
    {
        $patterns = [];
        foreach (Characters::patterns($character) as $pattern) {
            $patterns[] = ['id' => $pattern['id'], 'name' => $pattern['name'][$locale] ?? $pattern['name']['en'] ?? (string) reset($pattern['name'])];
        }

        return $patterns;
    }

    /**
     * One pattern by its reference — `user:{id}` for the owner's, `set:{id}` for the
     * character's — in the shape insert() takes, its words in $locale; null when it is gone.
     *
     * @return Pattern|null
     */
    public static function find(Db $db, Blocks $registry, string $reference, string $character, string $locale): ?array
    {
        if (preg_match('~^user:(\d{1,9})$~', $reference, $user) === 1) {
            $row = $db->one('SELECT section_json FROM patterns WHERE id = ?', [(int) $user[1]]);
            $decoded = $row === null ? null : json_decode((string) $row['section_json'], true);

            return is_array($decoded) ? self::read($registry, $decoded) : null;
        }
        if (preg_match('~^set:([a-z0-9][a-z0-9-]{0,63})$~', $reference, $set) === 1) {
            foreach (Characters::patterns($character) as $pattern) {
                if ($pattern['id'] === $set[1]) {
                    $blocks = [];
                    foreach ($pattern['blocks'] as $block) {
                        $blocks[] = ['content' => DesignSetPatterns::in($block['content'], $locale)] + $block;
                    }

                    return self::read($registry, ['section' => $pattern['section'], 'blocks' => $blocks]);
                }
            }
        }

        return null;
    }

    public static function delete(Db $db, int $id): void
    {
        $db->query("DELETE FROM patterns WHERE id = ? AND source = 'user'", [$id]);
    }

    /**
     * The document with the pattern inserted as a new section before the one at $before (at
     * the end when it is null or not found): a deep copy, its section and every block given a
     * key the document does not use yet and no id, so publishing makes new rows.
     *
     * @param Document $document
     * @param Pattern $pattern
     * @return Document
     */
    public static function insert(array $document, array $pattern, ?string $before = null): array
    {
        $taken = array_flip(array_merge(array_column($document['sections'], 'key'), array_column($document['blocks'], 'key')));
        $mint = static function (string $prefix) use (&$taken): string {
            $n = count($taken);
            while (isset($taken[$prefix . $n])) {
                $n++;
            }
            $taken[$prefix . $n] = true;

            return $prefix . $n;
        };
        $key = $mint('m');
        $section = ['key' => $key, 'id' => null] + $pattern['section'];
        $at = array_search($before, array_column($document['sections'], 'key'), true);
        array_splice($document['sections'], $at === false ? count($document['sections']) : (int) $at, 0, [$section]);
        foreach ($pattern['blocks'] as $block) {
            $document['blocks'][] = ['key' => $mint('n'), 'id' => null, 'type' => $block['type'], 'content' => $block['content'], 'style' => $section['style'], 'options' => $block['options'], 'layout' => $block['layout'], 'section' => $key, 'column' => $block['column']];
        }

        return $document;
    }

    /**
     * A stored or set pattern rebuilt as a document's section is (PageDocument::read): the
     * registry's shape for each block, a type this install does not have left out.
     *
     * @param array<mixed> $raw
     * @return Pattern|null
     */
    private static function read(Blocks $registry, array $raw): ?array
    {
        $section = is_array($raw['section'] ?? null) ? $raw['section'] : [];
        $document = PageDocument::read($registry, [
            'sections' => [['key' => 'm0', 'id' => null] + $section],
            'blocks' => array_map(static fn (mixed $block): mixed => is_array($block) ? ['section' => 'm0'] + $block : $block, is_array($raw['blocks'] ?? null) ? $raw['blocks'] : []),
        ]);
        if ($document === null || $document['blocks'] === []) {
            return null;
        }
        $blocks = [];
        foreach ($document['blocks'] as $block) {
            if ($block['content'] !== null) {
                $blocks[] = ['type' => $block['type'], 'layout' => $block['layout'], 'column' => $block['column'], 'options' => $block['options'], 'content' => $block['content']];
            }
        }
        $first = $document['sections'][0];

        return $blocks === [] ? null : ['section' => ['layout' => $first['layout'], 'stack' => $first['stack'], 'style' => $first['style']], 'blocks' => $blocks];
    }
}
