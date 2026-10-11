<?php

namespace App\Modules\Pages;

/**
 * What a page says about itself in <head> (PLAN.md D-004, D-170): its title and description for
 * search engines, and whether they may list it — stored in `pages.seo_json`, read and written
 * here. Out of Page, the record, when it passed the size limit (D-173).
 */
final class PageSeo
{
    /**
     * What this page says about itself in <head>: a title and a description (D-004).
     * Two fields, and nothing else — no sharing image, no robots directive, no sitemap.
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
     * @return array{title: string, description: string, noindex: bool}
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
     * @param array{title: string, description: string, noindex?: bool} $seo
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

        return $stored === [] ? '{}' : json_encode($stored, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
