<?php

namespace App\Modules\Ai;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Pages\Page;
use App\Modules\Pages\PageDocument;
use App\Modules\Pages\PageDraft;
use App\Modules\Pages\PagePaths;
use App\Modules\Pages\PageSeo;
use App\Modules\Pages\Slug;
use App\Modules\Pages\TranslationStatus;
use Closure;

/**
 * ONE PART OF A PAGE TRANSLATED (PLAN.md D-248): the page's own words (its title, what it tells
 * search engines, and the address of a page never published) or one block's, taken from the
 * source page in its language, translated, and written into the translation's DRAFT, as the
 * owner's typing would be. Nothing is published: the owner reads it, changes what they like,
 * and publishes.
 *
 * From the source, not from the copy: a block marked stale is translated from what the original
 * says now, and a fresh copy holds the source's words anyway. A block the translation has and the
 * source does not (added to the translation alone) is translated from its own words.
 */
final class AiWork
{
    /**
     * Does the part, or throws AiFailure. Returns '' when it went through whole, else a sentence
     * saying what was kept in the original's words.
     *
     * @param array{keep: string, voice: string} $words
     * @param Closure(string, string): array{text: string, in: int, out: int} $ask
     */
    public static function run(Db $db, Blocks $registry, int $pageId, string $part, array $words, Closure $ask): string
    {
        $page = Page::find($db, $pageId);
        $current = $page === null ? null : PageDraft::current($db, $registry, $pageId);
        if ($page === null || $current === null) {
            throw new AiFailure(t('ai.error.page_gone'));
        }
        $group = (int) ($page['content_group_id'] ?? $page['id']);
        $source = $db->one("SELECT * FROM pages WHERE content_group_id = ? AND translation_status = 'source' AND id <> ?", [$group, $pageId]);
        if ($source === null) {
            throw new AiFailure(t('ai.error.no_source'));
        }
        $from = self::language($db, (string) $source['locale']);
        $to = self::language($db, (string) $page['locale']);
        $document = $current['document'];
        $sourceDocument = PageDocument::stored($db, $registry, (int) $source['id']);

        if ($part === 'page') {
            $seo = PageSeo::of(['seo_json' => (string) ($sourceDocument['seo_json'] ?? $source['seo_json'] ?? '{}')]);
            $texts = [
                'title' => ['text' => (string) ($sourceDocument['title'] ?? $source['title']), 'html' => false],
                'seo_title' => ['text' => $seo['title'], 'html' => false],
                'seo_description' => ['text' => $seo['description'], 'html' => false],
            ];
            $result = AiTranslator::translate($texts, $from, $to, $words, $ask);
            AiSettings::addUsage($db, $result['in'], $result['out']);
            $own = PageSeo::of(['seo_json' => $document['seo_json']]);
            if (isset($result['done']['title'])) {
                $document['title'] = mb_substr($result['done']['title'], 0, 255);
                // A page never published may take an address in its own language; one visitors
                // already know keeps its own (the owner changes it, and a redirect is kept).
                if ($page['published_at'] === null && $document['slug'] !== '' && $document['slug'] === (string) $source['slug']) {
                    $document['slug'] = Slug::unique($db, (string) $page['locale'], $document['title'], $pageId);
                }
            }
            $own['title'] = $result['done']['seo_title'] ?? $own['title'];
            $own['description'] = $result['done']['seo_description'] ?? $own['description'];
            $document['seo_json'] = PageSeo::json($own);
            self::save($db, $pageId, $document, $current['version']);
            // A page never published is its draft: the list, the progress screen and an address
            // taken by another page read its row, which said the original's title and address
            // until it was published (the owner, D-253). Written there too; nothing of it is live,
            // unless a page below it was published, whose address holds this one's slug.
            if ($page['published_at'] === null) {
                $slug = self::liveBelow($db, $pageId) ? (string) $page['slug'] : $document['slug'];
                $db->query('UPDATE pages SET title = ?, slug = ?, seo_json = ? WHERE id = ?', [$document['title'], $slug, $document['seo_json'], $pageId]);
                PagePaths::changed();
            }

            return $result['failed'] === [] ? '' : tn('ai.kept_original', count($result['failed']));
        }

        // The pictures' alt text, into the library (D-260).
        if ($part === 'pictures') {
            $kept = AiPictures::run($db, $registry, $document, (string) $source['locale'], (string) $page['locale'], $from, $to, $words, $ask);

            return $kept === 0 ? '' : tn('ai.kept_alt', $kept);
        }

        // A form on the page, and the language's menus and header and footer words (D-262).
        if (str_starts_with($part, 'form:')) {
            $kept = AiForms::run($db, (int) substr($part, 5), $from, $to, $words, $ask);

            return $kept === 0 ? '' : tn('ai.kept_form', $kept);
        }
        if ($part === 'navigation') {
            $kept = AiNavigation::run($db, (string) $source['locale'], (string) $page['locale'], $from, $to, $words, $ask);

            return $kept === 0 ? '' : tn('ai.kept_navigation', $kept);
        }

        // One block, by its key in the document.
        $key = substr($part, 6);
        $at = null;
        foreach ($document['blocks'] as $i => $block) {
            if ($block['key'] === $key) {
                $at = $i;
            }
        }
        if ($at === null || $document['blocks'][$at]['content'] === null || !$registry->has($document['blocks'][$at]['type'])) {
            throw new AiFailure(t('ai.error.block_gone'));
        }
        $block = $document['blocks'][$at];
        $fields = $registry->get($block['type'])['fields'];
        $from_content = $block['content'];
        if ($block['id'] !== null && $sourceDocument !== null) {
            $row = $db->one('SELECT block_group_id FROM page_blocks WHERE id = ?', [$block['id']]);
            $groupId = (int) ($row['block_group_id'] ?? 0);
            foreach ($sourceDocument['blocks'] as $sourceBlock) {
                if ($sourceBlock['id'] === $groupId && $sourceBlock['type'] === $block['type'] && $sourceBlock['content'] !== null) {
                    $from_content = $sourceBlock['content'];
                }
            }
        }
        $texts = [];
        self::texts($fields, $from_content, '', $texts);
        $result = AiTranslator::translate($texts, $from, $to, $words, $ask);
        AiSettings::addUsage($db, $result['in'], $result['out']);
        // The source's arrangement of items where it was translated from the source: a repeater
        // the translation had fewer items in gets the source's, translated.
        $content = $from_content === $block['content'] ? $block['content'] : self::withSourceItems($fields, $block['content'], $from_content);
        foreach ($result['done'] as $path => $text) {
            $content = self::put($content, explode('.', $path), $text);
        }
        $document['blocks'][$at]['content'] = $registry->normalize($block['type'], $content);
        self::save($db, $pageId, $document, $current['version']);
        // Translated from what the original says now: up to date with it (D-043, step 3).
        if ($block['id'] !== null) {
            TranslationStatus::markCurrent($db, $registry, $pageId, (int) $block['id']);
        }

        return $result['failed'] === [] ? '' : tn('ai.kept_original', count($result['failed']));
    }

    /**
     * Whether a block has any words to translate.
     *
     * @param array<string, mixed> $content
     */
    public static function hasWords(Blocks $registry, string $type, array $content): bool
    {
        if (!$registry->has($type)) {
            return false;
        }
        $texts = [];
        self::texts($registry->get($type)['fields'], $content, '', $texts);

        return array_filter($texts, static fn (array $t): bool => trim(strip_tags($t['text'])) !== '') !== [];
    }

    /**
     * Every translatable text in a block's content, by its path: `heading`, `cta.label`,
     * `items.2.question`.
     *
     * @param array<string, array<string, mixed>> $fields
     * @param array<string, mixed> $content
     * @param array<string, array{text: string, html: bool}> $texts
     */
    private static function texts(array $fields, array $content, string $prefix, array &$texts): void
    {
        foreach ($fields as $name => $field) {
            $value = $content[$name] ?? null;
            if ($field['type'] === 'repeater') {
                foreach (is_array($value) ? $value : [] as $i => $item) {
                    self::texts($field['fields'], is_array($item) ? $item : [], $prefix . $name . '.' . $i . '.', $texts);
                }
            } elseif (($field['translatable'] ?? false) === true) {
                if ($field['type'] === 'link') {
                    if (is_array($value) && is_string($value['label'] ?? null)) {
                        $texts[$prefix . $name . '.label'] = ['text' => $value['label'], 'html' => false];
                    }
                } elseif (is_string($value)) {
                    $texts[$prefix . $name] = ['text' => $value, 'html' => $field['type'] === 'richtext'];
                }
            }
        }
    }

    /**
     * $content with a value set at $path, where the path leads somewhere that is there.
     *
     * @param array<string, mixed> $content
     * @param list<string> $path
     * @return array<string, mixed>
     */
    private static function put(array $content, array $path, string $value): array
    {
        $name = array_shift($path);
        if ($name === null) {
            return $content;
        }
        if ($path === []) {
            $content[$name] = $value;

            return $content;
        }
        if (!is_array($content[$name] ?? null)) {
            return $content;
        }
        $content[$name] = self::put($content[$name], $path, $value);

        return $content;
    }

    /**
     * The translation's block with each repeater's items the source's, where their number
     * differs: what the source has now is what is translated into it. Every other field the
     * translation's own: a picture swapped in the translation stays swapped.
     *
     * @param array<string, array<string, mixed>> $fields
     * @param array<string, mixed> $own
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private static function withSourceItems(array $fields, array $own, array $source): array
    {
        foreach ($fields as $name => $field) {
            if ($field['type'] === 'repeater' && is_array($source[$name] ?? null)
                && count($source[$name]) !== count(is_array($own[$name] ?? null) ? $own[$name] : [])) {
                $own[$name] = $source[$name];
            }
        }

        return $own;
    }

    /** "Hrvatski (hr)": what the model is told the language is. */
    private static function language(Db $db, string $code): string
    {
        $label = (string) ($db->one('SELECT label FROM locales WHERE code = ?', [$code])['label'] ?? '');

        return ($label !== '' ? $label . ' ' : '') . '(' . $code . ')';
    }

    /** Whether any page below this one, at any depth, has ever been published. */
    private static function liveBelow(Db $db, int $pageId): bool
    {
        $level = [$pageId];
        while ($level !== []) {
            $marks = implode(', ', array_fill(0, count($level), '?'));
            $rows = $db->all("SELECT id, published_at FROM pages WHERE parent_id IN ($marks)", $level);
            foreach ($rows as $row) {
                if ($row['published_at'] !== null) {
                    return true;
                }
            }
            $level = array_map(fn (array $row): int => (int) $row['id'], $rows);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $document
     */
    private static function save(Db $db, int $pageId, array $document, int $version): void
    {
        /** @phpstan-ignore argument.type (the document came from PageDraft::current and keeps its shape) */
        if (PageDraft::save($db, $pageId, $document, $version) === null) {
            throw new AiFailure(t('ai.error.busy_page'));
        }
    }
}
