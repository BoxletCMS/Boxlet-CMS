<?php

namespace App\Modules\Snippets;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Pages\Alternates;
use App\Modules\Pages\Page;
use App\Support\Dates;
use DateTimeImmutable;
use DateTimeZone;

/**
 * REPLACEMENT TAGS (SPEC §5.6, PLAN.md D-201): the closed list `{{year}}`, `{{lang:switcher}}`
 * and `{{snippet:name}}`, written in rich text and drawn as what they stand for. Inline only:
 * anything structural is a block.
 *
 * STORED AS WRITTEN, drawn on the way out. A page keeps `{{year}}`, never 2026, so next year
 * draws itself; a snippet changed once changes every page it stands in.
 *
 * A strict pattern, one pass, nothing evaluated: a tag is matched by the expression below and
 * replaced by a value worked out here, never by anything the owner typed being run, and what
 * a snippet's words say is not read for tags again. A tag nobody can draw — a snippet that is
 * not there — draws nothing. Only between elements, never inside one's attributes.
 *
 * IN THE EDITOR each draws as a chip with what a visitor will see in it, which the editor
 * keeps as the tag (richtext-tags.js), so writing never turns a tag into its value.
 */
final class Tags
{
    /** The closed list. */
    public const PATTERN = '~\{\{(year|lang:switcher|snippet:(' . Snippets::NAME . '))\}\}~';

    /**
     * What every tag on a page in $locale draws: worked out once per page.
     *
     * @return array{year: string, switcher: string, snippets: array<string, string>, editor: bool}
     */
    public static function context(Db $db, string $locale, ?int $pageId, bool $editor): array
    {
        $locales = $db->all('SELECT code, label, is_primary FROM locales WHERE enabled = 1 ORDER BY sort, code');
        $primary = '';
        foreach ($locales as $row) {
            if ((int) $row['is_primary'] === 1) {
                $primary = (string) $row['code'];
            }
        }
        $page = $pageId === null ? null : Page::find($db, $pageId);

        return [
            'year' => (new DateTimeImmutable('now', new DateTimeZone(Dates::zone($db))))->format('Y'),
            // In the editor the switcher's chip says what it is, in the admin's words, as the
            // chip the editor itself makes does (richtext-tags.js).
            'switcher' => $editor ? t('tags.switcher') : self::switcher(Alternates::for($db, $page, $locales), $locale),
            'snippets' => Snippets::forLocale($db, $locale, $primary),
            'editor' => $editor,
        ];
    }

    /**
     * What the admin's editors need to draw a tag as a chip (richtext-tags.js): the year, the
     * switcher's name, every snippet's words in plain text per language, and the words of
     * the Insert list.
     *
     * @return array{year: string, switcher: string, primary: string, snippets: array<string, array<string, string>>, words: array<string, string>}
     */
    public static function editorData(Db $db): array
    {
        $primary = (string) ($db->one('SELECT code FROM locales WHERE is_primary = 1')['code'] ?? '');
        $snippets = [];
        // None before migration 0041: the update screen is drawn while it waits.
        try {
            $all = Snippets::all($db);
        } catch (\Throwable) {
            $all = [];
        }
        foreach ($all as $name => $values) {
            foreach ($values as $code => $html) {
                $snippets[$name][$code] = self::plain($html);
            }
        }

        return [
            'year' => (new DateTimeImmutable('now', new DateTimeZone(Dates::zone($db))))->format('Y'),
            'switcher' => t('tags.switcher'),
            'primary' => $primary,
            'snippets' => $snippets,
            'words' => ['year' => t('tags.year'), 'switcher' => t('tags.switcher'), 'snippet' => t('tags.snippet')],
        ];
    }

    /**
     * Every rich text field of a block's content with its tags drawn, an item's in a repeater
     * too, as PageLinks follows links.
     *
     * @param array<mixed> $content
     * @param array{year: string, switcher: string, snippets: array<string, string>, editor: bool} $context
     * @return array<mixed>
     */
    public static function content(Blocks $registry, string $type, array $content, array $context): array
    {
        return self::apply($registry->get($type)['fields'], $content, $context);
    }

    /**
     * @param array{year: string, switcher: string, snippets: array<string, string>, editor: bool} $context
     */
    public static function expand(string $html, array $context): string
    {
        if (!str_contains($html, '{{')) {
            return $html;
        }
        // Text between elements only: a tag in an attribute is not one.
        $parts = preg_split('~(<[^>]*>)~', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        foreach ($parts as $i => $part) {
            if ($part === '' || $part[0] === '<') {
                continue;
            }
            $parts[$i] = (string) preg_replace_callback(self::PATTERN, static fn (array $m): string => self::draw($m[1], $context), $part);
        }

        return implode('', $parts);
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @param array<mixed> $content
     * @param array{year: string, switcher: string, snippets: array<string, string>, editor: bool} $context
     * @return array<mixed>
     */
    private static function apply(array $fields, array $content, array $context): array
    {
        foreach ($fields as $name => $field) {
            $value = $content[$name] ?? null;
            if (($field['type'] ?? '') === 'richtext' && is_string($value)) {
                $content[$name] = self::expand($value, $context);
            } elseif (($field['type'] ?? '') === 'repeater' && is_array($value) && is_array($field['fields'] ?? null)) {
                foreach ($value as $i => $item) {
                    if (is_array($item)) {
                        $value[$i] = self::apply($field['fields'], $item, $context);
                    }
                }
                $content[$name] = $value;
            }
        }

        return $content;
    }

    /**
     * One tag as a visitor sees it, or as the editor's chip around that.
     *
     * @param array{year: string, switcher: string, snippets: array<string, string>, editor: bool} $context
     */
    private static function draw(string $tag, array $context): string
    {
        $drawn = match (true) {
            $tag === 'year' => e($context['year']),
            $tag === 'lang:switcher' => $context['switcher'],
            default => $context['snippets'][substr($tag, 8)] ?? '',
        };
        if (!$context['editor']) {
            return $drawn;
        }
        // The chip: kept by the editor as the tag it stands for, showing what it draws in plain
        // words, or the tag itself where it draws nothing — the same words as the chip the
        // editor makes when the field is written in (richtext-tags.js).
        $plain = $tag === 'lang:switcher' ? $context['switcher'] : self::plain($drawn);
        $shown = e($plain !== '' ? $plain : '{{' . $tag . '}}');

        return '<span class="bx-tag" data-tag="' . e($tag) . '" contenteditable="false">' . $shown . '</span>';
    }

    /** A snippet's words as a chip shows them: plain text, a line break a space. */
    private static function plain(string $html): string
    {
        return trim(html_entity_decode(strip_tags(str_replace('<br>', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * The page in each of the site's languages, as words in a sentence: the footer's switcher
     * is a <nav>, which a paragraph cannot hold. Nothing on a site of one language.
     *
     * @param list<array{code: string, label: string, url: string, canonical: string, translation: bool}> $alternates
     */
    private static function switcher(array $alternates, string $locale): string
    {
        if (count($alternates) < 2) {
            return '';
        }
        $links = array_map(static fn (array $a): string => '<a href="' . e($a['url']) . '" hreflang="' . e($a['code']) . '" lang="' . e($a['code']) . '"' . ($a['code'] === $locale ? ' aria-current="true"' : '') . '>' . e($a['label']) . '</a>', $alternates);

        return '<span class="locale-links">' . implode(' · ', $links) . '</span>';
    }
}
