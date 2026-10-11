<?php

namespace App\Modules\Pages;

use App\Core\Db;
use App\Core\Settings;
use App\Support\Url;

/**
 * /llms.txt: what the site is, in Markdown, for AI tools that read the web (PLAN.md D-151).
 *
 * The proposal at llmstxt.org: an H1 with the site's name, a one-line summary in a quote,
 * then sections of links, each with what the page is about. Made from what the owner has
 * already written — the site's name, the home page's description, every published page's
 * title and description — so there is nothing new to fill in.
 *
 * ONE FILE FOR EVERY LANGUAGE (the owner's choice): the main language's pages under
 * "## Pages", then a section for each other language that is switched on, named in that
 * language.
 *
 * Written beside sitemap.xml, by the same calls and for the same reason: a real file in
 * public/ is served on every host without PHP. There is no route for it: managed nginx
 * answers an address ending in .txt from disk and never asks PHP (tests/routing_test.php),
 * and a tool looks for /llms.txt and nothing else. So where public/ cannot be written there
 * is no llms.txt, and the Settings panel says so. On by default; the owner can switch it off
 * in Settings, which removes the file. A file the owner put there
 * themselves — one without Boxlet's mark on its last line — is never touched.
 */
final class LlmsTxt
{
    public const MARK = '<!-- Written by Boxlet. Delete this line to keep your own changes. -->';

    public static function on(Db $db): bool
    {
        return Settings::get($db, 'llms_txt', '1') !== '0';
    }

    public static function text(Db $db): string
    {
        $rows = $db->all(
            "SELECT p.id, p.parent_id, p.locale, p.slug, p.title, p.seo_json, l.label, l.is_primary
             FROM pages p JOIN locales l ON l.code = p.locale
             WHERE p.status = 'published' AND l.enabled = 1
             ORDER BY l.is_primary DESC, l.sort, p.locale, CASE WHEN p.slug = '' THEN 0 ELSE 1 END, p.sort, p.id"
        );
        // And out of what AI tools are told the site holds (D-170).
        $rows = array_values(array_filter($rows, static fn (array $row): bool => !PageSeo::of($row)['noindex']));

        $name = self::line(Settings::text($db, 'site_name'));
        $out = '# ' . ($name !== '' ? $name : self::line((string) parse_url(Url::withOrigin('/'), PHP_URL_HOST))) . "\n";

        // The summary is the main language's home page description, when it has one.
        foreach ($rows as $row) {
            if ((int) $row['is_primary'] === 1 && (string) $row['slug'] === '') {
                $summary = self::line(PageSeo::of($row)['description']);
                if ($summary !== '') {
                    $out .= "\n> " . $summary . "\n";
                }
                break;
            }
        }

        // A section per language, and in it the pages as the page tree holds them: a page under
        // another is listed under it, one step in. A page whose parent is not published stands
        // at the top of its section rather than disappearing with it.
        $byLocale = [];
        foreach ($rows as $row) {
            $byLocale[(string) $row['locale']][] = $row;
        }
        foreach ($byLocale as $locale => $pages) {
            $out .= "\n## " . ((int) $pages[0]['is_primary'] === 1 ? 'Pages' : self::line((string) $pages[0]['label'])) . "\n\n";
            $listed = array_column($pages, null, 'id');
            $children = [];
            foreach ($pages as $page) {
                $parent = (int) ($page['parent_id'] ?? 0);
                $children[isset($listed[$parent]) ? $parent : 0][] = $page;
            }
            $out .= self::items($children, 0, 0, $locale);
        }

        return $out . "\n" . self::MARK . "\n";
    }

    /**
     * @param array<int, list<array<string, mixed>>> $children pages by the parent they are listed under
     */
    private static function items(array $children, int $parent, int $depth, string $locale): string
    {
        $out = '';
        foreach ($children[$parent] ?? [] as $page) {
            $description = self::line(PageSeo::of($page)['description']);
            $out .= str_repeat('  ', $depth) . '- [' . self::link((string) $page['title']) . '](' . Url::canonical($locale, (string) $page['slug']) . ')'
                . ($description !== '' ? ': ' . $description : '') . "\n";
            // Ten deep at most: the tree has no cycles, and this makes sure of it.
            if ($depth < 10) {
                $out .= self::items($children, (int) $page['id'], $depth + 1, $locale);
            }
        }

        return $out;
    }

    /**
     * Writes llms.txt, or removes it when the owner switched it off — in both cases only a
     * file this site wrote itself. Returns false when public/ cannot be written.
     */
    public static function publish(Db $db, string $publicPath): bool
    {
        $file = $publicPath . '/llms.txt';
        $current = is_file($file) ? (string) file_get_contents($file) : '';
        if ($current !== '' && !str_contains($current, self::MARK)) {
            return true;
        }
        if (!self::on($db)) {
            return !is_file($file) || @unlink($file);
        }
        if (!is_dir($publicPath) || !is_writable($publicPath) || (is_file($file) && !is_writable($file))) {
            return false;
        }
        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temporary, self::text($db)) === false) {
            return false;
        }
        if (!@rename($temporary, $file)) {
            @unlink($temporary);

            return false;
        }

        return true;
    }

    /** Whether public/ holds an llms.txt the owner wrote, which Boxlet leaves alone. */
    public static function ownersOwn(string $publicPath): bool
    {
        $file = $publicPath . '/llms.txt';

        return is_file($file) && !str_contains((string) file_get_contents($file), self::MARK);
    }

    /** One line: what an owner typed may hold line breaks, which would end the list item. */
    private static function line(string $text): string
    {
        return trim((string) preg_replace('~\s+~u', ' ', $text));
    }

    /** A link's text, with the brackets that would end it escaped. */
    private static function link(string $text): string
    {
        return str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], self::line($text));
    }
}
