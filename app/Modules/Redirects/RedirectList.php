<?php

namespace App\Modules\Redirects;

use App\Core\Db;
use App\Support\SafeUrl;
use App\Support\Url;

/**
 * The Redirects screen's side of the table (PLAN.md D-129): what the owner types, judged
 * and stored, and the rows listed with where each leads now. Redirects itself is what a
 * request meets and what a page save writes.
 */
final class RedirectList
{
    /**
     * An address as the owner may type it into the form: a whole URL from the old site is
     * cut to its path and query, and a path without its first slash gets one.
     */
    public static function fromTyped(string $typed): string
    {
        $typed = trim($typed);
        if (preg_match('~^https?://~i', $typed) === 1) {
            $path = (string) parse_url($typed, PHP_URL_PATH);
            $query = (string) parse_url($typed, PHP_URL_QUERY);

            return Redirects::normalize($path === '' ? '/' : $path, $query);
        }
        [$path, $query] = array_pad(explode('?', $typed, 2), 2, '');

        return $typed === '' ? '' : Redirects::normalize(rawurldecode($path), $query);
    }

    /**
     * Why a rule for $from would be refused, in words, or null. It would never be used where
     * something else answers first: a live page, the admin, the site's own files, or another
     * rule for the same address.
     */
    public static function fromProblem(Db $db, string $from): ?string
    {
        if ($from === '' || $from === '/') {
            return t('redirects.from_required');
        }
        $path = explode('?', $from, 2)[0];
        foreach (['/admin', '/m/', '/download/', '/assets/', '/cache/', '/form/'] as $system) {
            if ($path === rtrim($system, '/') || str_starts_with($path, rtrim($system, '/') . '/')) {
                return t('redirects.from_system');
            }
        }
        if ($db->one('SELECT id FROM redirects WHERE kind = ? AND path = ?', [Redirects::RULE, $from]) !== null) {
            return t('redirects.from_taken');
        }
        if (!str_contains($from, '?')) {
            foreach ($db->all("SELECT title, locale, slug FROM pages WHERE status = 'published'") as $page) {
                if (strtolower(Url::page((string) $page['locale'], (string) $page['slug'])) === $path) {
                    return t('redirects.from_live', ['page' => (string) $page['title']]);
                }
            }
        }

        return null;
    }

    /** An address a rule may send a visitor to: one on this site, or an http(s) one. */
    public static function urlAllowed(string $url): bool
    {
        return SafeUrl::isAllowed($url)
            && (str_starts_with($url, '/') || preg_match('~^https?://[^/\s]+~i', $url) === 1);
    }

    public static function addRule(Db $db, string $from, ?int $pageId, ?string $url): int
    {
        $db->query(
            'INSERT INTO redirects (kind, locale, path, page_id, url, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [Redirects::RULE, '', $from, $pageId, $url, gmdate('Y-m-d H:i:s')],
        );

        return (int) $db->lastInsertId();
    }

    /**
     * The owner's rules, newest first, each with where it leads now.
     *
     * @return list<array{id: int, from: string, page: string|null, pageUrl: string|null, pageId: int|null, url: string|null, hits: int, lastHit: string|null}>
     */
    public static function rules(Db $db): array
    {
        $rows = [];
        foreach ($db->all(
            'SELECT r.id, r.path, r.page_id, r.url, r.hits, r.last_hit_at, p.title, p.locale, p.slug, p.status
             FROM redirects r LEFT JOIN pages p ON p.id = r.page_id
             WHERE r.kind = ? ORDER BY r.created_at DESC, r.id DESC',
            [Redirects::RULE],
        ) as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'from' => (string) $row['path'],
                'page' => $row['title'] === null ? null : (string) $row['title'],
                'pageUrl' => $row['title'] === null ? null : Url::page((string) $row['locale'], (string) $row['slug']),
                'pageId' => $row['page_id'] === null ? null : (int) $row['page_id'],
                'url' => $row['url'] === null || $row['url'] === '' ? null : (string) $row['url'],
                'hits' => (int) $row['hits'],
                'lastHit' => $row['last_hit_at'] === null ? null : (string) $row['last_hit_at'],
            ];
        }

        return $rows;
    }

    /**
     * The old addresses Boxlet kept, newest first. pageUrl is null while the page is not
     * published, when the old address answers 404 as the page itself does.
     *
     * @return list<array{id: int, from: string, page: string, pageUrl: string|null, pageId: int, hits: int, lastHit: string|null}>
     */
    public static function kept(Db $db): array
    {
        $rows = [];
        foreach ($db->all(
            'SELECT r.id, r.locale AS old_locale, r.path, r.page_id, r.hits, r.last_hit_at, p.title, p.locale, p.slug, p.status
             FROM redirects r JOIN pages p ON p.id = r.page_id
             WHERE r.kind = ? ORDER BY r.created_at DESC, r.id DESC',
            [Redirects::HISTORY],
        ) as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'from' => Url::page((string) $row['old_locale'], (string) $row['path']),
                'page' => (string) $row['title'],
                'pageUrl' => $row['status'] === 'published' ? Url::page((string) $row['locale'], (string) $row['slug']) : null,
                'pageId' => (int) $row['page_id'],
                'hits' => (int) $row['hits'],
                'lastHit' => $row['last_hit_at'] === null ? null : (string) $row['last_hit_at'],
            ];
        }

        return $rows;
    }

    /**
     * Every page a rule may point at, drafts included and marked: a rule is often made
     * before the page it leads to is published.
     *
     * @return list<array{id: int, locale: string, title: string, url: string, published: bool}>
     */
    public static function pageChoices(Db $db): array
    {
        $choices = [];
        foreach ($db->all('SELECT id, locale, title, slug, status FROM pages ORDER BY locale, title, id') as $row) {
            $choices[] = [
                'id' => (int) $row['id'],
                'locale' => (string) $row['locale'],
                'title' => (string) $row['title'],
                'url' => Url::page((string) $row['locale'], (string) $row['slug']),
                'published' => $row['status'] === 'published',
            ];
        }

        return $choices;
    }

    /**
     * What the owner is shown for a row: a rule's address as typed, a kept slug as the
     * address it was.
     *
     * @param array<string, mixed> $row
     */
    public static function shownPath(array $row): string
    {
        return $row['kind'] === Redirects::HISTORY ? Url::page((string) $row['locale'], (string) $row['path']) : (string) $row['path'];
    }
}
