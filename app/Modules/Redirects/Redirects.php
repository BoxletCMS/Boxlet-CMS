<?php

namespace App\Modules\Redirects;

use App\Core\Db;
use App\Core\Request;
use App\Support\Url;

/**
 * Addresses that used to lead somewhere (PLAN.md D-129, migration 0030).
 *
 * Two kinds, one table:
 *
 *   history   an old slug of a published page, kept by Boxlet when the slug changed
 *   rule      an address the owner typed, usually from the site this one replaced
 *
 * Both are looked up ONLY AFTER NOTHING ELSE ANSWERED — from the 404 — so a live page always
 * wins, and an ordinary request never pays for this. Both point at a page rather than an
 * address, so the target is wherever that page is now: renamed twice, both old slugs lead
 * straight to the current one.
 */
final class Redirects
{
    public const HISTORY = 'history';
    public const RULE = 'rule';

    /**
     * A page's slug changed from $old to $new. The old one is kept when anybody outside
     * could have known it — the page had been published before this save — and the new
     * one, now a live page's, stops being anyone's old address.
     */
    public static function slugChanged(Db $db, int $pageId, string $locale, string $old, string $new, bool $wasPublished): void
    {
        self::claimed($db, $locale, $new);
        // The home page's empty slug is not an address anyone can lose: '/' is always home.
        if (!$wasPublished || $old === '' || $old === $new) {
            return;
        }
        $db->query('DELETE FROM redirects WHERE kind = ? AND locale = ? AND path = ?', [self::HISTORY, $locale, $old]);
        $db->query(
            'INSERT INTO redirects (kind, locale, path, page_id, created_at) VALUES (?, ?, ?, ?, ?)',
            [self::HISTORY, $locale, $old, $pageId, gmdate('Y-m-d H:i:s')],
        );
    }

    /** A live page now holds $slug, so it is nobody's old address any more. */
    public static function claimed(Db $db, string $locale, string $slug): void
    {
        $db->query('DELETE FROM redirects WHERE kind = ? AND locale = ? AND path = ?', [self::HISTORY, $locale, $slug]);
    }

    /**
     * A deleted page's old addresses go with it and answer 404. Rules pointing at it stay,
     * pointing nowhere (the foreign key), because the owner typed them.
     */
    public static function pageDeleted(Db $db, int $pageId): void
    {
        $db->query('DELETE FROM redirects WHERE kind = ? AND page_id = ?', [self::HISTORY, $pageId]);
    }

    /**
     * Where a request nothing answered should go instead, or null for a real 404. The
     * owner's rules first, as typed, then the old slugs of the request's language, matched
     * by its last segment. A use is counted.
     */
    public static function target(Db $db, Request $request, string $locale): ?string
    {
        return self::follow($db, self::rule($db, $request) ?? self::history($db, $request, $locale));
    }

    /**
     * A rule for the home page's address WITH ITS QUERY, which the home page asks before it
     * draws itself: an old WordPress site's `/?p=12` is the home page's address, so no 404
     * would ever ask. Only a rule naming that exact query answers; `/` alone never does.
     */
    public static function queryTarget(Db $db, Request $request): ?string
    {
        if ($request->query === []) {
            return null;
        }

        return self::follow($db, $db->one(
            'SELECT * FROM redirects WHERE kind = ? AND path = ?',
            [self::RULE, self::normalize($request->path, http_build_query($request->query))],
        ));
    }

    /**
     * Where a row leads, counting the use, or null when it leads nowhere a visitor may go.
     *
     * @param array<string, mixed>|null $row
     */
    private static function follow(Db $db, ?array $row): ?string
    {
        if ($row === null) {
            return null;
        }

        $target = null;
        if ($row['page_id'] !== null) {
            // An unpublished page answers 404 on its own address; it does not become
            // reachable through an old one.
            $page = $db->one("SELECT locale, slug FROM pages WHERE id = ? AND status = 'published'", [(int) $row['page_id']]);
            $target = $page === null ? null : Url::page((string) $page['locale'], (string) $page['slug']);
        } elseif ((string) ($row['url'] ?? '') !== '') {
            $target = (string) $row['url'];
        }
        if ($target === null) {
            return null;
        }

        $db->query('UPDATE redirects SET hits = hits + 1, last_hit_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), (int) $row['id']]);

        return $target;
    }

    /**
     * An address as a rule stores it and as a request is compared with it: lower case, no
     * trailing slash, and its query put in one order, so `?b=2&a=1` is `?a=1&b=2`.
     */
    public static function normalize(string $path, string $query = ''): string
    {
        $path = strtolower('/' . ltrim($path, '/'));
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        if ($query === '') {
            return $path;
        }
        parse_str($query, $pairs);
        ksort($pairs);

        return $pairs === [] ? $path : $path . '?' . http_build_query($pairs);
    }

    /**
     * The rule for this address with its query if there is one, else the rule for the
     * address alone: `/usluge.html` answers `/usluge.html?utm_source=x` as well.
     *
     * @return array<string, mixed>|null
     */
    private static function rule(Db $db, Request $request): ?array
    {
        $bare = self::normalize($request->path);
        $withQuery = self::normalize($request->path, http_build_query($request->query));
        $rows = $db->all('SELECT * FROM redirects WHERE kind = ? AND path IN (?, ?)', [self::RULE, $withQuery, $bare]);
        usort($rows, static fn (array $a, array $b): int => strlen((string) $b['path']) <=> strlen((string) $a['path']));

        return $rows[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function history(Db $db, Request $request, string $locale): ?array
    {
        $segments = array_values(array_filter(explode('/', $request->path), static fn (string $s): bool => $s !== ''));
        $last = $segments === [] ? '' : strtolower((string) end($segments));
        if ($last === '') {
            return null;
        }

        return $db->one('SELECT * FROM redirects WHERE kind = ? AND locale = ? AND path = ?', [self::HISTORY, $locale, $last]);
    }

}
