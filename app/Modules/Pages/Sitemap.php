<?php

namespace App\Modules\Pages;

use App\Core\Container;
use App\Core\Db;
use App\Support\Url;

/**
 * The sitemap search engines read (SPEC §8 Slice 8, PLAN.md D-049): every published page in
 * every language that is switched on, each with its translations as hreflang alternates.
 *
 * A REAL FILE, public/sitemap.xml, written again whenever what it lists changes. Managed
 * nginx answers any address ending in .xml from disk and never asks PHP, so a route could
 * not serve it there; a file is served on every host, without PHP, like a picture. Where
 * public/ cannot be written, the same document is at /sitemap, which the owner can give a
 * search engine instead.
 *
 * robots.txt is written beside it to point at it — but only a robots.txt this site wrote
 * itself, marked on its first line. One the owner put there is never touched.
 */
final class Sitemap
{
    private const MARK = '# Written by Boxlet. Delete this line to keep your own changes.';

    public static function xml(Db $db): string
    {
        $rows = $db->all(
            "SELECT p.id, p.content_group_id, p.locale, p.slug, p.updated_at
             FROM pages p JOIN locales l ON l.code = p.locale
             WHERE p.status = 'published' AND l.enabled = 1
             ORDER BY l.is_primary DESC, l.sort, p.locale, p.sort, p.id"
        );
        $groups = [];
        foreach ($rows as $row) {
            $groups[(int) ($row['content_group_id'] ?? $row['id'])][(string) $row['locale']] = (string) $row['slug'];
        }

        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($rows as $row) {
            $out .= "  <url>\n    <loc>" . self::x(Url::canonical((string) $row['locale'], (string) $row['slug'])) . "</loc>\n"
                . '    <lastmod>' . self::x(substr((string) $row['updated_at'], 0, 10)) . "</lastmod>\n";
            $versions = $groups[(int) ($row['content_group_id'] ?? $row['id'])];
            if (count($versions) > 1) {
                foreach ($versions as $locale => $slug) {
                    $out .= '    <xhtml:link rel="alternate" hreflang="' . self::x($locale) . '" href="' . self::x(Url::canonical($locale, $slug)) . '"/>' . "\n";
                }
            }
            $out .= "  </url>\n";
        }

        return $out . "</urlset>\n";
    }

    /**
     * Writes sitemap.xml, and robots.txt unless the owner has one of their own. Returns
     * false when public/ cannot be written, which leaves /sitemap as the way to it.
     */
    public static function publish(Db $db, string $publicPath): bool
    {
        if (!self::write($publicPath . '/sitemap.xml', self::xml($db))) {
            return false;
        }
        $robots = $publicPath . '/robots.txt';
        $current = is_file($robots) ? (string) file_get_contents($robots) : '';
        if ($current === '' || str_starts_with($current, self::MARK)) {
            self::write($robots, self::MARK . "\nUser-agent: *\nDisallow: /admin\n\nSitemap: " . Url::withOrigin(Url::asset('sitemap.xml')) . "\n");
        }

        return true;
    }

    /**
     * publish() into the site's public directory, after anything that changes what the
     * sitemap lists: a page saved, published, unpublished or deleted, a language switched
     * on or off or removed. The directory comes from configuration so tests write into
     * their own, never the development site's public/.
     */
    public static function refresh(Container $container): bool
    {
        return self::publish($container->get('db'), (string) (($container->get('config')->get('app', []))['public_path'] ?? ''));
    }

    private static function write(string $file, string $contents): bool
    {
        // Asked first rather than left to fail: a host whose public/ is read-only is
        // ordinary, not an error, and /sitemap is there for it.
        if (!is_dir(dirname($file)) || !is_writable(dirname($file)) || (is_file($file) && !is_writable($file))) {
            return false;
        }
        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temporary, $contents) === false) {
            return false;
        }
        if (!@rename($temporary, $file)) {
            @unlink($temporary);

            return false;
        }

        return true;
    }

    private static function x(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
