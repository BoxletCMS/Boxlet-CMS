<?php

namespace App\Modules\Pages;

use App\Core\Db;
use App\Support\Url;

/**
 * Where a page is in each of the site's languages, for the language switcher and for the
 * hreflang links in <head> (PLAN.md D-043, step 4).
 *
 * A language the page is translated into leads to that translation, published. A language
 * it is not translated into leads to that language's home page — the D-043 rule is that an
 * untranslated page is not shown in that language, so the switcher does not pretend it is,
 * and a visitor who asked for Croatian still gets Croatian. A language with neither is not
 * offered at all: a link to a page that answers "not found" is worse than no link.
 *
 * Only real translations are hreflang alternates. A home page offered in place of a
 * translation is not "this page in another language", and telling search engines it is
 * would be the kind of claim hreflang exists to make precise.
 */
final class Alternates
{
    /**
     * @param array<string, mixed>|null $page the page being drawn, or null on an error page
     * @param array<int, array<string, mixed>> $locales enabled locales, in the order offered
     * @return list<array{code: string, label: string, url: string, canonical: string, translation: bool}>
     */
    public static function for(Db $db, ?array $page, array $locales): array
    {
        $versions = [];
        if ($page !== null) {
            $group = (int) ($page['content_group_id'] ?? $page['id']);
            foreach ($db->all("SELECT locale, slug FROM pages WHERE content_group_id = ? AND status = 'published'", [$group]) as $row) {
                $versions[(string) $row['locale']] = (string) $row['slug'];
            }
        }
        $homes = [];
        foreach ($db->all("SELECT locale FROM pages WHERE slug = '' AND status = 'published'") as $row) {
            $homes[(string) $row['locale']] = true;
        }

        $alternates = [];
        foreach ($locales as $locale) {
            $code = (string) $locale['code'];
            if (isset($versions[$code])) {
                $alternates[] = ['code' => $code, 'label' => (string) $locale['label'], 'url' => Url::page($code, $versions[$code]), 'canonical' => Url::canonical($code, $versions[$code]), 'translation' => true];
            } elseif (isset($homes[$code])) {
                $alternates[] = ['code' => $code, 'label' => (string) $locale['label'], 'url' => Url::page($code), 'canonical' => Url::canonical($code), 'translation' => false];
            }
        }

        return $alternates;
    }

    /**
     * The hreflang links for <head>: every published translation, the page itself among
     * them, and x-default at the main language's version. Nothing for a page that exists
     * in one language only.
     *
     * @param list<array{code: string, label: string, url: string, canonical: string, translation: bool}> $alternates
     * @return list<array{hreflang: string, href: string}>
     */
    public static function hreflang(array $alternates, string $primary): array
    {
        $translations = array_values(array_filter($alternates, static fn (array $a): bool => $a['translation']));
        if (count($translations) < 2) {
            return [];
        }
        $links = [];
        foreach ($translations as $alternate) {
            $links[] = ['hreflang' => $alternate['code'], 'href' => $alternate['canonical']];
            if ($alternate['code'] === $primary) {
                $links[] = ['hreflang' => 'x-default', 'href' => $alternate['canonical']];
            }
        }

        return $links;
    }
}
