<?php

namespace App\Modules\Pages;

use App\Core\Db;

/**
 * Page addresses. A slug is one path segment: lowercase letters, digits and single
 * hyphens. '' is the home page of its locale. Every ISO 639-1 code is reserved, enabled
 * or not, so a page can never collide with a locale enabled later (SPEC §5.1).
 */
final class Slug
{
    /** Top-level paths the application itself answers. */
    // "uploads" stays reserved although /uploads/ is no longer a public directory
    // (D-020 moved originals to storage/uploads/). An install that predates that change
    // may still have files there, and a page at /uploads would be shadowed by them on
    // disk. Reserving a name costs nothing; un-reserving one breaks those sites quietly.
    // 'sitemap' is the route a host without a writable public/ serves the sitemap from, which
    // a page there would never be reached past; 'download' leads nested addresses (D-129)
    // into /download/{id}/{name}, a file's.
    public const SYSTEM = ['admin', 'assets', 'cache', 'uploads', 'm', 'install', '_boxlet', 'download', 'sitemap'];

    public const MAX_LENGTH = 100;

    private const PATTERN = '~^[a-z0-9]+(?:-[a-z0-9]+)*$~';

    private const TRANSLITERATION = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'æ' => 'ae', 'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ĉ' => 'c', 'ď' => 'd', 'đ' => 'd', 'ð' => 'd',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
        'ğ' => 'g', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'į' => 'i', 'ı' => 'i',
        'ł' => 'l', 'ľ' => 'l', 'ĺ' => 'l', 'ñ' => 'n', 'ń' => 'n', 'ň' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o', 'œ' => 'oe',
        'ŕ' => 'r', 'ř' => 'r', 'ś' => 's', 'š' => 's', 'ş' => 's', 'ș' => 's', 'ß' => 'ss',
        'ť' => 't', 'ţ' => 't', 'ț' => 't', 'þ' => 'th',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u',
        'ý' => 'y', 'ÿ' => 'y', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z', '&' => ' ',
    ];

    /**
     * A slug derived from a title, or '' when nothing usable is left (a title in a
     * non-Latin script, for example).
     */
    public static function fromTitle(string $title): string
    {
        $ascii = strtr(mb_strtolower($title, 'UTF-8'), self::TRANSLITERATION);
        $slug = trim((string) preg_replace('~[^a-z0-9]+~', '-', $ascii), '-');

        return trim(substr($slug, 0, self::MAX_LENGTH - 10), '-');
    }

    /**
     * Why $slug cannot be the address of a page in $locale, or null when it can.
     */
    public static function problem(Db $db, string $locale, string $slug, ?int $exceptPageId): ?string
    {
        if ($slug !== '') {
            if (strlen($slug) > self::MAX_LENGTH || !preg_match(self::PATTERN, $slug)) {
                return t('pages.slug.invalid', ['max' => self::MAX_LENGTH]);
            }
            $languages = require dirname(__DIR__) . '/I18n/languages.php';
            if (isset($languages[$slug])) {
                return t('pages.slug.reserved_language', ['slug' => $slug, 'language' => $languages[$slug]]);
            }
            if (in_array($slug, self::SYSTEM, true)) {
                return t('pages.slug.reserved_system', ['slug' => $slug]);
            }
        }

        $row = $db->one('SELECT id FROM pages WHERE locale = ? AND slug = ?', [$locale, $slug]);
        if ($row !== null && (int) $row['id'] !== $exceptPageId) {
            return $slug === '' ? t('pages.slug.home_taken') : t('pages.slug.taken', ['slug' => $slug]);
        }

        return null;
    }

    /**
     * A free slug for $title: "about", else "about-2", "about-3" and so on.
     */
    public static function unique(Db $db, string $locale, string $title, ?int $exceptPageId): string
    {
        $base = self::fromTitle($title);
        if ($base === '') {
            $base = 'page';
        }
        $candidate = $base;
        for ($n = 2; self::problem($db, $locale, $candidate, $exceptPageId) !== null; $n++) {
            $candidate = $base . '-' . $n;
        }

        return $candidate;
    }
}
