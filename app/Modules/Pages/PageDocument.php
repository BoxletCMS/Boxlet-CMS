<?php

namespace App\Modules\Pages;

use App\Core\BlockOptions;
use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Design\SectionStyle;
use JsonException;

/**
 * A PAGE AS ONE DOCUMENT (PLAN.md D-163, D-173): its settings, its sections with their style,
 * its blocks with content, options, layout and the column they stand in.
 *
 * One shape for every place a whole page travels: a revision (what it was), a draft (what it
 * is becoming), what the builder autosaves, and what PageRender draws. It is the shape
 * PageBlocks::editable() and PageBlocks::editableSections() have always given an editor, so the page as
 * stored and the page as drafted are read the same way — and writing one is Page::update(),
 * by id, so a block that already exists is updated rather than replaced and its translations
 * stay attached to it.
 *
 * READ AS UNTRUSTED. A document comes back from JSON — a revision row, a draft row, a request
 * body — so read() rebuilds it field by field against the registry and the closed sets, the
 * way a form is parsed: an unknown block type is left out, a style is normalized, a key that
 * is not a key is replaced. Nothing in it reaches the tables without passing here.
 *
 * @phpstan-type DocBlock array{key: string, id: int|null, type: string, content: array<string, mixed>|null, style: array<string, string|int|null>, options: array<string, string>, layout: string, section: string, column: int}
 * @phpstan-type DocSection array{key: string, id: int|null, layout: string, stack: string, style: array<string, string|int|null>}
 * @phpstan-type Document array{title: string, slug: string, parent_id: int|null, status: string, seo_json: string, blocks: list<DocBlock>, sections: list<DocSection>}
 */
final class PageDocument
{
    /** The deepest a document's JSON may nest: a block's repeater items are four levels down. */
    private const DEPTH = 32;

    /**
     * The page as it is stored — what visitors see when it is published.
     *
     * @return Document|null null when there is no such page
     */
    public static function stored(Db $db, Blocks $registry, int $pageId): ?array
    {
        $page = Page::find($db, $pageId);
        if ($page === null) {
            return null;
        }
        $blocks = [];
        foreach (PageBlocks::editable($db, $registry, $pageId) as $block) {
            $blocks[] = $block + ['options' => []];
        }

        return [
            'title' => (string) $page['title'],
            'slug' => (string) $page['slug'],
            'parent_id' => $page['parent_id'] === null ? null : (int) $page['parent_id'],
            'status' => (string) $page['status'],
            'seo_json' => (string) ($page['seo_json'] ?? '{}'),
            'blocks' => $blocks,
            'sections' => PageBlocks::editableSections($db, $pageId),
        ];
    }

    /**
     * A document from decoded JSON, rebuilt against the registry, or null when it is not one.
     *
     * The bands first, then the blocks; a block naming no band that came with it is given one
     * of its own, at the end, carrying the style it brings — what a page of blocks with
     * nothing said about its sections has always meant (PageBuilderController, D-098).
     *
     * @return Document|null
     */
    public static function read(Blocks $registry, mixed $data): ?array
    {
        // A document without its bands is one written before D-098, and none is read any
        // more (D-162): refused, like any value of another shape.
        if (!is_array($data) || !is_array($data['blocks'] ?? null) || !is_array($data['sections'] ?? null)) {
            return null;
        }

        $sections = [];
        $known = [];
        foreach ($data['sections'] as $at => $section) {
            if (!is_array($section)) {
                continue;
            }
            $key = is_string($section['key'] ?? null) && preg_match(SectionForm::KEY, $section['key']) === 1
                ? $section['key']
                : SectionForm::key(null, 1000 + (int) $at);
            if (isset($known[$key])) {
                continue;
            }
            $known[$key] = true;
            $sections[] = [
                'key' => $key,
                // Kept for the reason a block's is: a band that still exists is written to
                // rather than replaced, so its picture and its place stay attached to it.
                // Sections::save() checks it belongs to the page before believing it.
                'id' => isset($section['id']) && is_int($section['id']) ? $section['id'] : null,
                'layout' => SectionLayout::normalize($section['layout'] ?? null),
                'stack' => SectionLayout::normalizeStack($section['stack'] ?? null),
                'style' => SectionStyle::normalize($section['style'] ?? null),
            ];
        }

        $blocks = [];
        $ordinal = 0;
        $keys = [];
        foreach ($data['blocks'] as $block) {
            if (!is_array($block) || !is_string($block['type'] ?? null)) {
                continue;
            }
            $type = $block['type'];
            $id = isset($block['id']) && is_int($block['id']) ? $block['id'] : null;
            $knownType = $registry->has($type);
            // A block whose type this install no longer has: one that is stored is KEPT, with
            // no content, so Page::update() leaves its row as it is — left out, a draft's
            // publish would delete it. One that never was stored has nothing to keep.
            if (!$knownType && $id === null) {
                continue;
            }
            $key = is_string($block['key'] ?? null) && preg_match(BlockForm::KEY, $block['key']) === 1 && !isset($keys[$block['key']])
                ? $block['key']
                : BlockForm::key($id, 1000 + $ordinal);
            $keys[$key] = true;
            $section = is_string($block['section'] ?? null) && isset($known[$block['section']]) ? $block['section'] : null;
            $style = SectionStyle::normalize($block['style'] ?? null);
            if ($section === null) {
                $section = SectionForm::key(null, 2000 + $ordinal);
                $known[$section] = true;
                $sections[] = ['key' => $section, 'id' => null, 'layout' => SectionLayout::ONE, 'stack' => SectionLayout::DEFAULT_STACK, 'style' => $style];
            }
            $blocks[] = [
                // A key names a block for one visit (D-094); one that is not a key, or that
                // another block already answers to, is minted.
                'key' => $key,
                // The id is kept so a block that still exists is UPDATED rather than
                // duplicated; Page::update() checks it is this page's before believing it.
                'id' => $id,
                'type' => $type,
                // Through the form's own checks, so a document is never a way around the
                // sanitiser: the store is what templates trust (BlockForm::clean).
                'content' => $knownType ? BlockForm::clean($registry, $type, is_array($block['content'] ?? null) ? $block['content'] : []) : null,
                'style' => $style,
                'options' => $knownType ? BlockOptions::normalize($registry->get($type)['options'], $block['options'] ?? null) : [],
                'layout' => $knownType ? $registry->own($type, $block['layout'] ?? null) : '',
                'section' => $section,
                'column' => isset($block['column']) && is_int($block['column']) ? max(0, $block['column']) : 0,
            ];
            $ordinal++;
        }

        return [
            'title' => is_string($data['title'] ?? null) ? mb_substr(trim($data['title']), 0, 255) : '',
            'slug' => is_string($data['slug'] ?? null) ? mb_substr(trim($data['slug']), 0, 100) : '',
            'parent_id' => isset($data['parent_id']) && is_int($data['parent_id']) ? $data['parent_id'] : null,
            'status' => ($data['status'] ?? '') === 'published' ? 'published' : 'draft',
            'seo_json' => is_string($data['seo_json'] ?? null) ? PageSeo::json(PageSeo::of(['seo_json' => $data['seo_json']])) : '{}',
            'blocks' => $blocks,
            'sections' => self::anchored($sections),
        ];
    }

    /**
     * Every anchor once (D-165), as a form's sections have them (SectionForm::uniqueAnchors).
     *
     * @param list<DocSection> $sections
     * @return list<DocSection>
     */
    private static function anchored(array $sections): array
    {
        $styles = SectionForm::uniqueAnchors(array_column($sections, 'style'));
        $anchored = [];
        foreach ($sections as $at => $section) {
            $section['style'] = $styles[$at] ?? $section['style'];
            $anchored[] = $section;
        }

        return $anchored;
    }

    /**
     * A document from its JSON text, or null when the text is not one.
     *
     * @return Document|null
     */
    public static function decode(Blocks $registry, string $json): ?array
    {
        try {
            return self::read($registry, json_decode($json, true, self::DEPTH, JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * The document as stored text, or null when it cannot be written down.
     *
     * @param Document $document
     */
    public static function encode(array $document): ?string
    {
        try {
            return json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Writes the document into the page's tables: the one way a page becomes what visitors
     * see. Page::update() does the work, by id, with its media resolution, its redirects for
     * a changed address and its pruning of empty bands.
     *
     * @param Document $document
     */
    public static function write(Db $db, Blocks $registry, int $pageId, array $document, ?string $status = null): void
    {
        Page::update($db, $registry, $pageId, [
            'title' => $document['title'],
            'slug' => $document['slug'],
            'parent_id' => $document['parent_id'],
            'status' => $status ?? $document['status'],
            'seo_json' => $document['seo_json'],
        ], $document['blocks'], $document['sections']);
    }
}
