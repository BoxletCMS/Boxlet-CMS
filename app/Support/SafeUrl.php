<?php

namespace App\Support;

/**
 * Decides whether a URL typed by the admin may become a link: site-relative paths,
 * fragments and queries, or http, https, mailto and tel. Everything else, including
 * javascript: and scheme-relative //host, is refused.
 */
final class SafeUrl
{
    public const SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** A link to a page by its content group (PLAN.md D-034). */
    public const PAGE_REFERENCE = '~^page:([1-9][0-9]{0,9})$~';

    public static function isAllowed(string $url): bool
    {
        // Browsers ignore whitespace and control characters inside a scheme
        // ("java\tscript:"), and read "/\host" like "//host"; refuse both outright.
        if ($url === '' || preg_match('~[\x00-\x20\x7F\\\\]~', $url) || str_starts_with($url, '//')) {
            return false;
        }
        if (preg_match('~^[/#?]~', $url)) {
            return true;
        }

        return (bool) preg_match('~^(?:' . implode('|', self::SCHEMES) . '):~i', $url);
    }

    /**
     * An address as a person types it, made into the link they meant (D-039): a bare email
     * becomes mailto:, a bare phone number tel:, and a phone number's spaces, dashes, dots
     * and brackets are dropped so a phone can dial it. Everything else is returned trimmed
     * and unchanged, for isAllowed() to judge. Idempotent: a normalised address stays as it is.
     */
    public static function normalize(string $url): string
    {
        $url = trim($url);
        if (preg_match('~^[^\s@/:?#]+@[^\s@/:?#]+\.[a-z]{2,}$~i', $url) === 1) {
            return 'mailto:' . $url;
        }
        // A phone number starts with +, a digit or a bracket — never with /, so a path such
        // as /2024/05/01 is never mistaken for one — and holds at least six digits.
        if (preg_match('~^(?:tel:)?(\+?[0-9(][0-9 ()./-]*)$~i', $url, $phone) === 1
            && preg_match_all('~[0-9]~', $phone[1]) >= 6) {
            return 'tel:' . (str_starts_with($phone[1], '+') ? '+' : '') . preg_replace('~[^0-9]~', '', $phone[1]);
        }

        return $url;
    }

    /**
     * What a link may store: a typed address this class allows, or a reference to a page,
     * `page:{group}`, which is followed at render and never reaches a visitor as written
     * (PLAN.md D-034, App\Modules\Pages\PageLinks). Menus keep isAllowed(): a menu item
     * points at a page through its own column.
     */
    public static function isLink(string $url): bool
    {
        return self::isAllowed($url) || preg_match(self::PAGE_REFERENCE, $url) === 1;
    }
}
