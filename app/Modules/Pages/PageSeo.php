<?php

namespace App\Modules\Pages;

use App\Core\Db;

/**
 * What a page says about itself in <head> (PLAN.md D-004, D-170, D-242): its title and
 * description for search engines, whether they may list it, and the picture a link to it is
 * shared with — stored in `pages.seo_json`, read and written here. Out of Page, the record,
 * when it passed the size limit (D-173).
 */
final class PageSeo
{
    /**
     * What this page says about itself in <head>: a title and a description (D-004), whether
     * search engines may list it (D-170), and its own sharing picture, a media id, null for the
     * site's (D-242).
     *
     * WHAT IS STORED, NOT WHAT IS SHOWN. Neither field falls back here, deliberately.
     * The title's fallback to the page title belongs where it is rendered, because a
     * fallback applied here would be written straight back on the next save: the page
     * title would become an explicit SEO title and would stop following the title from
     * then on. The editors want the raw value too, or an owner cannot tell a field they
     * set from one they inherited.
     *
     * A page created before this existed — and every new page, since create() does not
     * write the column — has NULL rather than '{}', so both have to decode to nothing.
     *
     * @param array<string, mixed> $page a row from find() or published()
     * @return array{title: string, description: string, noindex: bool, image: int|null}
     */
    public static function of(array $page): array
    {
        $stored = json_decode((string) ($page['seo_json'] ?? ''), true);
        $stored = is_array($stored) ? $stored : [];

        return [
            'title' => is_string($stored['title'] ?? null) ? trim($stored['title']) : '',
            'description' => is_string($stored['description'] ?? null) ? trim($stored['description']) : '',
            // Kept out of search engines, the sitemap and llms.txt (D-170).
            'noindex' => ($stored['noindex'] ?? false) === true,
            // A media id; whether it still names a picture is the renderer's question
            // (SiteChrome::shareImage), as a deleted picture is for every other reference.
            'image' => is_int($stored['image'] ?? null) && $stored['image'] > 0 ? $stored['image'] : null,
        ];
    }

    /**
     * The inverse of of(), kept beside it: both halves of one stored shape belong
     * together, and the alternative is json_encode's flags copied into a controller,
     * where they drift.
     *
     * An empty field is dropped rather than stored as an empty string, so a page with no
     * SEO of its own holds {} however it arrived there.
     *
     * @param array{title: string, description: string, noindex?: bool, image?: int|null} $seo
     */
    public static function json(array $seo): string
    {
        $stored = array_filter(
            ['title' => trim($seo['title']), 'description' => trim($seo['description'])],
            static fn (string $value): bool => $value !== '',
        );
        if (($seo['noindex'] ?? false) === true) {
            $stored['noindex'] = true;
        }
        if (is_int($seo['image'] ?? null) && $seo['image'] > 0) {
            $stored['image'] = $seo['image'];
        }

        return $stored === [] ? '{}' : json_encode($stored, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * THE TITLE A PAGE IS SHOWN WITH in a search result and a browser tab (D-004, D-245, the
     * owner): the search title the owner wrote, exactly as written; else the page's title, and
     * on a subpage the site's name after it, "Design · Boxlet CMS", so a result names the site
     * it belongs to. A home page stands for the site already, and a title that already names
     * the site is not given it twice. builder-page.js shows the same in its search preview.
     */
    public static function shownTitle(string $seoTitle, string $pageTitle, string $siteName, bool $home): string
    {
        if ($seoTitle !== '') {
            return $seoTitle;
        }
        $siteName = trim($siteName);
        if ($home || $siteName === '' || mb_stripos($pageTitle, $siteName) !== false) {
            return $pageTitle;
        }

        return $pageTitle . ' · ' . $siteName;
    }

    /**
     * The pages whose own sharing picture is $mediaId, id => title (D-242): for the Media
     * library's "Used on" and its refusal to delete a picture a page still uses. The LIKE
     * narrows, the decode decides, as for a block's picture: "image":7 is also "image":70.
     *
     * @return array<int, string>
     */
    public static function pagesUsing(Db $db, int $mediaId): array
    {
        $pages = [];
        foreach ($db->all('SELECT id, title, seo_json FROM pages WHERE seo_json LIKE ?', ['%"image":' . $mediaId . '%']) as $row) {
            if (self::of($row)['image'] === $mediaId) {
                $pages[(int) $row['id']] = (string) $row['title'];
            }
        }

        return $pages;
    }

    /**
     * Every page's own sharing picture, page id => media id: the same, for the whole library.
     *
     * @return array<int, int>
     */
    public static function images(Db $db): array
    {
        $images = [];
        foreach ($db->all("SELECT id, seo_json FROM pages WHERE seo_json LIKE '%\"image\":%'") as $row) {
            $image = self::of($row)['image'];
            if ($image !== null) {
                $images[(int) $row['id']] = $image;
            }
        }

        return $images;
    }
}
