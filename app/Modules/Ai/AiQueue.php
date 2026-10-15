<?php

namespace App\Modules\Ai;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Pages\PageDraft;

/**
 * WHAT IS LEFT TO TRANSLATE (PLAN.md D-248): one row per part of a page, the page's own words
 * ('page'), one block ('block:{key}'), its pictures' alt text (D-260, 'pictures'), a form on it
 * ('form:{id}') or its language's menus and header and footer words ('navigation', D-262), waiting, done or failed. Kept in the database, not in a
 * request or a browser, so a translation that stops — the tab closed, the connection lost, the
 * provider busy — carries on from where it was, and a step is never more than one part long:
 * within any shared host's time limit.
 */
final class AiQueue
{
    /**
     * Every part of a translation queued: its own words, then each block that has words, in the
     * order the page draws them, then its pictures when any has no alt in its language (D-260),
     * its forms still in the original's words, and its language's menus and header and footer
     * words when they want it (D-262). A page already waiting is not queued twice. A new piece of work
     * clears the last one's finished rows, once nothing of it is waiting.
     */
    public static function page(Db $db, Blocks $registry, int $pageId): int
    {
        $current = PageDraft::current($db, $registry, $pageId);
        if ($current === null) {
            return 0;
        }
        self::fresh($db);
        $parts = ['page'];
        foreach ($current['document']['blocks'] as $block) {
            if ($block['content'] !== null && AiWork::hasWords($registry, $block['type'], $block['content'])) {
                $parts[] = 'block:' . $block['key'];
            }
        }
        $page = $db->one('SELECT locale, content_group_id FROM pages WHERE id = ?', [$pageId]);
        $source = $page === null ? null : $db->one("SELECT locale FROM pages WHERE content_group_id = ? AND translation_status = 'source' AND id <> ?", [(int) ($page['content_group_id'] ?? $pageId), $pageId]);
        if ($page !== null && $source !== null && AiPictures::missing($db, $registry, $current['document'], (string) $source['locale'], (string) $page['locale']) !== []) {
            $parts[] = 'pictures';
        }
        // Its forms of this language that are still the original's words (D-262), each once.
        foreach ($current['document']['blocks'] as $block) {
            $form = $block['type'] === 'form' && is_array($block['content']) && is_int($block['content']['form'] ?? null) ? $block['content']['form'] : null;
            if ($form !== null && AiForms::untouched($db, $form)
                && $db->one("SELECT id FROM ai_queue WHERE part = ? AND state = 'pending'", ['form:' . $form]) === null) {
                $parts[] = 'form:' . $form;
            }
        }
        // The language's menus and header and footer words (D-262): once for the language,
        // with the first page that finds them wanting.
        if ($page !== null && $source !== null
            && AiNavigation::missing($db, (string) $source['locale'], (string) $page['locale']) !== []
            && $db->one("SELECT q.id FROM ai_queue q JOIN pages p ON p.id = q.page_id WHERE q.part = 'navigation' AND q.state = 'pending' AND p.locale = ?", [(string) $page['locale']]) === null) {
            $parts[] = 'navigation';
        }
        $queued = 0;
        foreach ($parts as $part) {
            $queued += self::add($db, $pageId, $part);
        }

        return $queued;
    }

    /** One block of a translation queued again: the "Translate again" beside a stale block. */
    public static function block(Db $db, int $pageId, string $key): int
    {
        self::fresh($db);

        return self::add($db, $pageId, 'block:' . $key);
    }

    /**
     * The oldest part still waiting, or null when none is.
     *
     * @return array{id: int, page_id: int, part: string}|null
     */
    public static function next(Db $db): ?array
    {
        $row = $db->one("SELECT id, page_id, part FROM ai_queue WHERE state = 'pending' ORDER BY id LIMIT 1");

        return $row === null ? null : ['id' => (int) $row['id'], 'page_id' => (int) $row['page_id'], 'part' => (string) $row['part']];
    }

    public static function finish(Db $db, int $id, string $state, string $message = ''): void
    {
        $db->query('UPDATE ai_queue SET state = ?, message = ?, updated_at = ? WHERE id = ?', [$state, mb_substr($message, 0, 500), gmdate('Y-m-d H:i:s'), $id]);
    }

    /** The failed parts waiting again, for "Try again". */
    public static function retry(Db $db): void
    {
        $db->query("UPDATE ai_queue SET state = 'pending', message = '', updated_at = ? WHERE state = 'failed'", [gmdate('Y-m-d H:i:s')]);
    }

    /** What is waiting taken away, and with it everything finished: "Stop". */
    public static function clear(Db $db): void
    {
        $db->query('DELETE FROM ai_queue');
    }

    /**
     * How far it is: parts done, failed and waiting, and the pages they belong to.
     *
     * @return array{done: int, failed: int, pending: int, total: int, pages: list<array{id: int, title: string, locale: string, pending: int, failed: int}>, messages: list<string>}
     */
    public static function progress(Db $db): array
    {
        $done = 0;
        $failed = 0;
        $pending = 0;
        $pages = [];
        $messages = [];
        foreach ($db->all('SELECT q.page_id, q.state, q.message, p.title, p.locale FROM ai_queue q LEFT JOIN pages p ON p.id = q.page_id ORDER BY q.id') as $row) {
            $state = (string) $row['state'];
            match ($state) {
                'done' => $done++,
                'failed' => $failed++,
                default => $pending++,
            };
            $id = (int) $row['page_id'];
            $pages[$id] ??= ['id' => $id, 'title' => (string) ($row['title'] ?? ''), 'locale' => (string) ($row['locale'] ?? ''), 'pending' => 0, 'failed' => 0];
            if ($state === 'pending' || $state === 'failed') {
                $pages[$id][$state]++;
            }
            if ($state === 'failed' && $row['message'] !== '' && !in_array($row['message'], $messages, true)) {
                $messages[] = (string) $row['message'];
            }
        }

        return ['done' => $done, 'failed' => $failed, 'pending' => $pending, 'total' => $done + $failed + $pending, 'pages' => array_values($pages), 'messages' => $messages];
    }

    /** The finished rows of the last piece of work, cleared when nothing of it waits any more. */
    private static function fresh(Db $db): void
    {
        if ($db->one("SELECT id FROM ai_queue WHERE state = 'pending' LIMIT 1") === null) {
            $db->query('DELETE FROM ai_queue');
        }
    }

    private static function add(Db $db, int $pageId, string $part): int
    {
        if ($db->one("SELECT id FROM ai_queue WHERE page_id = ? AND part = ? AND state = 'pending'", [$pageId, $part]) !== null) {
            return 0;
        }
        $now = gmdate('Y-m-d H:i:s');
        $db->query(
            "INSERT INTO ai_queue (page_id, part, state, message, created_at, updated_at) VALUES (?, ?, 'pending', '', ?, ?)",
            [$pageId, $part, $now, $now],
        );

        return 1;
    }
}
