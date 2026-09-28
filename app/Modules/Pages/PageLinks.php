<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Db;
use App\Support\SafeUrl;
use App\Support\Url;

/**
 * A link that points at a page rather than at a typed address (PLAN.md D-034).
 *
 * STORED AS `page:{group}`: the value of a link field's url and the href of a rich text
 * link alike, where the group is the page's content_group_id. A block is copied into a
 * translation verbatim, so a reference by group follows the visitor's language without
 * anyone editing the copy.
 *
 * RESOLVED BEFORE RENDER, NEVER DURING — the rule pictures follow (MediaPicture). Every
 * reference on a page is found, looked up in one query, and replaced in the content handed
 * to the template, which therefore only ever sees a plain URL. A reference that cannot be
 * followed (the page was deleted, is not published, or has no version in this language)
 * becomes no link at all: an empty url, which every template already skips, or, in rich
 * text, the link's own words without the link.
 */
final class PageLinks
{
    /**
     * @return int|null the page group a stored url refers to, or null for a typed address
     */
    public static function reference(string $url): ?int
    {
        return preg_match(SafeUrl::PAGE_REFERENCE, $url, $match) === 1 ? (int) $match[1] : null;
    }

    public static function to(int $group): string
    {
        return 'page:' . $group;
    }

    /**
     * What a link may point at in $locale: every page, in the order of the page tree, by
     * group. Drafts are offered and marked, because a link is often written before the
     * page it points at is published, and it starts working the moment that page is.
     *
     * Each carries the address it has today, which the editor shows beside the choice
     * (D-038); what is stored is still the reference.
     *
     * @return array<int, array{title: string, depth: int, published: bool, url: string}>
     */
    public static function choices(Db $db, string $locale): array
    {
        $rows = [];
        foreach ($db->all('SELECT id, content_group_id, status, slug FROM pages WHERE locale = ?', [$locale]) as $row) {
            $rows[(int) $row['id']] = $row;
        }

        $choices = [];
        foreach (PageTree::parentOptions($db, $locale, null) as $option) {
            $row = $rows[$option['id']] ?? null;
            if ($row !== null) {
                $choices[(int) $row['content_group_id']] = [
                    'title' => $option['title'],
                    'depth' => $option['depth'],
                    'published' => $row['status'] === 'published',
                    'url' => Url::page($locale, (string) $row['slug']),
                ];
            }
        }

        return $choices;
    }

    /**
     * Where every page reference in these blocks leads, found and looked up in one query.
     * Hand the result to content() for each block as it renders.
     *
     * @param list<array{type: string, content: array<mixed>|null}> $blocks
     * @return array<int, array{url: string, title: string}>
     */
    public static function targets(Db $db, Blocks $registry, string $locale, array $blocks): array
    {
        $groups = [];
        foreach ($blocks as $block) {
            if (is_array($block['content']) && $registry->has($block['type'])) {
                $groups = array_merge($groups, self::groupsIn($registry->get($block['type'])['fields'], $block['content']));
            }
        }

        return self::resolve($db, $locale, $groups);
    }

    /**
     * One block's content with every page reference replaced by a URL, or by nothing.
     *
     * @param array<mixed> $content
     * @param array<int, array{url: string, title: string}> $targets from targets()
     * @return array<mixed>
     */
    public static function content(Blocks $registry, string $type, array $content, array $targets): array
    {
        return $registry->has($type) ? self::apply($registry->get($type)['fields'], $content, $targets) : $content;
    }

    /**
     * Every page group referred to inside one block's content: its link fields, its rich
     * text, and both of those inside repeater items.
     *
     * @param array<string, array<string, mixed>> $fields the block's field declarations
     * @param array<mixed> $content
     * @return list<int>
     */
    public static function groupsIn(array $fields, array $content): array
    {
        $groups = [];
        foreach ($fields as $name => $field) {
            $value = $content[$name] ?? null;
            switch ($field['type'] ?? '') {
                case 'link':
                    $group = is_array($value) && is_string($value['url'] ?? null) ? self::reference($value['url']) : null;
                    if ($group !== null) {
                        $groups[] = $group;
                    }
                    break;
                case 'richtext':
                    if (is_string($value) && preg_match_all('~<a href="page:([1-9][0-9]{0,9})">~', $value, $matches)) {
                        $groups = array_merge($groups, array_map('intval', $matches[1]));
                    }
                    break;
                case 'repeater':
                    foreach (is_array($value) ? $value : [] as $item) {
                        if (is_array($item) && is_array($field['fields'] ?? null)) {
                            $groups = array_merge($groups, self::groupsIn($field['fields'], $item));
                        }
                    }
                    break;
            }
        }

        return $groups;
    }

    /**
     * Where each group leads in $locale, and what that page is called there. Only a
     * published page is a target: a draft is not something a visitor may be sent to.
     *
     * @param list<int> $groups
     * @return array<int, array{url: string, title: string}>
     */
    public static function resolve(Db $db, string $locale, array $groups): array
    {
        $groups = array_values(array_unique($groups));
        if ($groups === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($groups), '?'));
        $rows = $db->all(
            "SELECT content_group_id, slug, title FROM pages
             WHERE locale = ? AND status = 'published' AND content_group_id IN ({$placeholders})",
            array_merge([$locale], $groups),
        );

        $targets = [];
        foreach ($rows as $row) {
            $targets[(int) $row['content_group_id']] = [
                'url' => Url::page($locale, (string) $row['slug']),
                'title' => (string) $row['title'],
            ];
        }

        return $targets;
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @param array<mixed> $content
     * @param array<int, array{url: string, title: string}> $targets
     * @return array<mixed>
     */
    public static function apply(array $fields, array $content, array $targets): array
    {
        foreach ($fields as $name => $field) {
            $value = $content[$name] ?? null;
            switch ($field['type'] ?? '') {
                case 'link':
                    if (is_array($value) && is_string($value['url'] ?? null)) {
                        $label = is_string($value['label'] ?? null) ? $value['label'] : '';
                        $content[$name] = self::link(['label' => $label, 'url' => $value['url']], $targets);
                    }
                    break;
                case 'richtext':
                    if (is_string($value)) {
                        $content[$name] = self::richText($value, $targets);
                    }
                    break;
                case 'repeater':
                    if (is_array($value) && is_array($field['fields'] ?? null)) {
                        foreach ($value as $i => $item) {
                            if (is_array($item)) {
                                $value[$i] = self::apply($field['fields'], $item, $targets);
                            }
                        }
                        $content[$name] = $value;
                    }
                    break;
            }
        }

        return $content;
    }

    /**
     * A link field with its reference followed. An empty label takes the page's title in
     * this language; a reference that leads nowhere leaves the url empty, and a template
     * draws no button for an empty url.
     *
     * @param array{label: string, url: string} $link
     * @param array<int, array{url: string, title: string}> $targets
     * @return array{label: string, url: string}
     */
    public static function link(array $link, array $targets): array
    {
        $group = self::reference($link['url']);
        if ($group === null) {
            return $link;
        }

        $target = $targets[$group] ?? null;

        return [
            'label' => $link['label'] !== '' ? $link['label'] : ($target['title'] ?? ''),
            'url' => $target['url'] ?? '',
        ];
    }

    /**
     * Rich text with each `<a href="page:n">` pointed at its page, or reduced to its words.
     *
     * A pattern rather than a parser, and safe as one: this runs on what RichText::sanitize()
     * stored, which serialises every link as exactly `<a href="…">`, never nests one inside
     * another, and escapes any quote inside an href. The words inside are left as they are.
     *
     * @param array<int, array{url: string, title: string}> $targets
     */
    private static function richText(string $html, array $targets): string
    {
        if (!str_contains($html, 'href="page:')) {
            return $html;
        }

        return (string) preg_replace_callback(
            '~<a href="page:([1-9][0-9]{0,9})">(.*?)</a>~s',
            static function (array $match) use ($targets): string {
                $target = $targets[(int) $match[1]] ?? null;

                return $target === null ? $match[2] : '<a href="' . e($target['url']) . '">' . $match[2] . '</a>';
            },
            $html,
        );
    }
}
