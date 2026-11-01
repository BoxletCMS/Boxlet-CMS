<?php

namespace App\Modules\Mcp;

use App\Core\Blocks;
use App\Core\Container;
use App\Core\Db;
use App\Modules\Admin\Activity;
use App\Modules\Design\SectionStyle;
use App\Modules\Pages\BlockValues;
use App\Modules\Pages\Page;
use App\Modules\Pages\PageDocument;
use App\Modules\Pages\PageDraft;
use App\Modules\Pages\PagePublish;
use App\Modules\Pages\SectionLayout;
use App\Modules\Pages\Slug;
use App\Support\RichText;
use App\Support\Url;
use RuntimeException;

/**
 * Pages over MCP (PLAN.md O-78), by the editor's own way: a document read with PageDocument::read,
 * which cleans every block as a form's fields are cleaned and is the boundary a draft must pass;
 * saved as the page's draft (PageDraft), so the published page changes only when it is
 * published; published by PagePublish, as the Publish button does.
 *
 * @phpstan-import-type Document from PageDocument
 */
final class McpPages
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(string $locale): array
    {
        if ($locale !== '' && !in_array($locale, $this->locales(), true)) {
            throw new RuntimeException("The site has no language {$locale}; get_site lists them.");
        }
        $pages = [];
        $rows = $locale === ''
            ? $this->db()->all('SELECT id, locale, title, slug, status, parent_id FROM pages ORDER BY locale, sort, title, id')
            : $this->db()->all('SELECT id, locale, title, slug, status, parent_id FROM pages WHERE locale = ? ORDER BY sort, title, id', [$locale]);
        foreach ($rows as $row) {
            $pages[] = [
                'id' => (int) $row['id'],
                'locale' => (string) $row['locale'],
                'title' => (string) $row['title'],
                'url' => Url::canonical((string) $row['locale'], (string) $row['slug']),
                'published' => (string) $row['status'] === 'published',
                'parent_id' => $row['parent_id'] === null ? null : (int) $row['parent_id'],
                'has_draft' => PageDraft::exists($this->db(), (int) $row['id']),
            ];
        }

        return $pages;
    }

    /**
     * @return array<string, mixed>
     */
    public function one(int $id): array
    {
        $page = Page::find($this->db(), $id);
        $current = $page === null ? null : PageDraft::current($this->db(), $this->registry(), $id);
        if ($page === null || $current === null) {
            throw new RuntimeException("There is no page {$id}; list_pages lists them.");
        }

        return [
            'id' => $id,
            'locale' => (string) $page['locale'],
            'url' => Url::canonical((string) $page['locale'], (string) $page['slug']),
            'published' => (string) $page['status'] === 'published',
            'version' => $current['version'],
            'drafted' => $current['drafted'],
            'document' => $current['document'],
        ];
    }

    /**
     * Every block type and what it holds, and what a section may be.
     *
     * @return array<string, mixed>
     */
    public function blockTypes(): array
    {
        $registry = $this->registry();
        $types = [];
        foreach ($registry->types() as $type) {
            $definition = $registry->get($type);
            $types[$type] = [
                'label' => t('block.' . $type),
                'fields' => self::fields($definition['fields']),
                'layouts' => array_values($definition['layouts'] ?? []),
                'default_layout' => $definition['defaults']['layout'] ?? null,
                'options' => $definition['options'] ?? [],
            ];
        }

        return [
            'blocks' => $types,
            'richtext' => 'HTML of p, br, and where the field allows them ' . implode(', ', RichText::FEATURES) . ' (strong, em, a href, h2–h4, ul/ol/li, blockquote). Anything else is removed.',
            'sections' => [
                'layouts' => SectionLayout::LAYOUTS,
                'stacks' => SectionLayout::STACKS,
                'style' => SectionStyle::OPTIONS + SectionStyle::NUMBERS + ['image' => 'a picture id, behind the section', 'name' => 'the owner\'s label, never shown', 'anchor' => 'the section\'s #id on the page'],
            ],
        ];
    }

    /**
     * @param array<mixed> $arguments
     * @return array<string, mixed>
     */
    public function create(array $arguments): array
    {
        $db = $this->db();
        $locale = is_string($arguments['locale'] ?? null) ? $arguments['locale'] : '';
        $title = is_string($arguments['title'] ?? null) ? mb_substr(trim($arguments['title']), 0, 255) : '';
        if (!in_array($locale, $this->locales(), true)) {
            throw new RuntimeException("The site has no language {$locale}; get_site lists them.");
        }
        if ($title === '') {
            throw new RuntimeException('A page needs a title.');
        }
        $slug = is_string($arguments['slug'] ?? null) && trim($arguments['slug']) !== '' ? Slug::fromTitle($arguments['slug']) : Slug::unique($db, $locale, $title, null);
        $problem = Slug::problem($db, $locale, $slug, null);
        if ($problem !== null) {
            throw new RuntimeException("The address {$slug}: {$problem}");
        }
        $id = Page::create($db, $this->registry(), $locale, $title, $slug, null, []);
        Activity::record($db, 'page', 'ai_created', $id, $title);
        $version = 0;
        $problems = [];
        if (is_array($arguments['blocks'] ?? null) && $arguments['blocks'] !== []) {
            $parent = is_int($arguments['parent_id'] ?? null) && Page::find($db, $arguments['parent_id']) !== null ? $arguments['parent_id'] : null;
            [$version, $problems] = $this->draft($id, 0, ['title' => $title, 'slug' => $slug, 'parent_id' => $parent, 'status' => 'draft', 'seo_json' => '{}'], $arguments);
        }

        return ['id' => $id, 'url' => Url::canonical($locale, $slug), 'version' => $version, 'problems' => $problems, 'review' => Url::withOrigin(Url::admin('pages', $id))];
    }

    /**
     * @param array<mixed> $arguments
     * @return array<string, mixed>
     */
    public function update(array $arguments): array
    {
        $id = is_int($arguments['id'] ?? null) ? $arguments['id'] : 0;
        $current = PageDraft::current($this->db(), $this->registry(), $id);
        if ($current === null) {
            throw new RuntimeException("There is no page {$id}; list_pages lists them.");
        }
        if (is_int($arguments['version'] ?? null) && $arguments['version'] !== $current['version']) {
            throw new RuntimeException("The page changed after version {$arguments['version']}; it is at {$current['version']} now. Read it again with get_page.");
        }
        $was = $current['document'];
        $title = is_string($arguments['title'] ?? null) && trim($arguments['title']) !== '' ? $arguments['title'] : $was['title'];
        // The address stays as it is: a published page's address never changes by itself, and
        // renaming one is the owner's, where its old address is kept (D-129).
        [$version, $problems] = $this->draft($id, $current['version'], ['title' => $title, 'slug' => $was['slug'], 'parent_id' => $was['parent_id'], 'status' => $was['status'], 'seo_json' => $was['seo_json']], $arguments);
        Activity::record($this->db(), 'page', 'ai_drafted', $id, $title);

        return ['id' => $id, 'version' => $version, 'problems' => $problems, 'review' => Url::withOrigin(Url::admin('pages', $id))];
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(int $id): array
    {
        $errors = PagePublish::publish($this->container, $id);
        if ($errors !== []) {
            throw new RuntimeException('Not published: ' . implode(' ', $errors));
        }
        // What the admin's own POST does on its way out (PageCache::after): every kept page
        // emptied, so visitors are served the page as published now.
        \App\Support\PageCache::clear();
        $page = Page::find($this->db(), $id) ?? throw new RuntimeException("There is no page {$id}.");

        return ['id' => $id, 'published' => true, 'url' => Url::canonical((string) $page['locale'], (string) $page['slug'])];
    }

    /**
     * The document read as the editor's is, saved as the draft from $from. What a field holds
     * that Publish would refuse is said, the draft kept all the same: a draft may be unfinished.
     *
     * @param array{title: string, slug: string, parent_id: int|null, status: string, seo_json: string} $page
     * @param array<mixed> $arguments
     * @return array{int, list<string>}
     */
    private function draft(int $id, int $from, array $page, array $arguments): array
    {
        $given = is_array($arguments['blocks'] ?? null) ? $arguments['blocks'] : [];
        $document = PageDocument::read($this->registry(), $page + ['sections' => is_array($arguments['sections'] ?? null) ? $arguments['sections'] : [], 'blocks' => $given]);
        if ($document === null) {
            throw new RuntimeException('The document could not be read: sections and blocks must be arrays.');
        }
        $version = PageDraft::save($this->db(), $id, $document, $from);
        if ($version === null) {
            throw new RuntimeException('The page changed while this was written. Read it again with get_page.');
        }
        $problems = [];
        if (count($document['blocks']) < count($given)) {
            $problems[] = (count($given) - count($document['blocks'])) . ' block(s) left out: their type is not one list_block_types names.';
        }
        foreach ($document['blocks'] as $block) {
            foreach ($block['content'] === null ? [] : $this->registry()->get($block['type'])['fields'] as $name => $field) {
                [, $error] = BlockValues::field($field, BlockValues::asSent($field, $block['content'][$name] ?? null));
                if (is_string($error)) {
                    $problems[] = "{$block['key']} ({$block['type']}) {$name}: {$error}";
                }
            }
        }

        return [$version, $problems];
    }

    /**
     * A block's fields as a model needs them: type, required, choices, items.
     *
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    private static function fields(array $fields): array
    {
        $out = [];
        foreach ($fields as $name => $field) {
            $out[$name] = array_intersect_key($field, array_flip(['type', 'required', 'options', 'max', 'allow']))
                + (isset($field['fields']) && is_array($field['fields']) ? ['item' => self::fields($field['fields'])] : []);
        }

        return $out;
    }

    /** @return list<string> */
    private function locales(): array
    {
        return array_values(array_map(static fn (array $l): string => (string) $l['code'], $this->container->get('locales')));
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }

    private function registry(): Blocks
    {
        return $this->container->get('blocks');
    }
}
