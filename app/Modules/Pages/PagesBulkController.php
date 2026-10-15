<?php

namespace App\Modules\Pages;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Support\Url;

/**
 * SEVERAL PAGES DELETED AT ONCE (PLAN.md D-218, the owner), copied into another language
 * (D-247), published (D-254) or unpublished (D-265): the pages ticked in the list, each
 * deleted as the row's own Delete deletes it — its blocks, drafts and revisions with it, its
 * old addresses and its menu items (Page::delete) — and recorded one by one in the activity.
 * The way the owner clears the demo, with Media's after it: a picture is free to delete once
 * no page shows it.
 */
final class PagesBulkController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, string $locale, array $params): Response
    {
        $db = $this->container->get('db');
        $deleted = 0;
        foreach (self::ids($request) as $id) {
            $page = Page::find($db, $id);
            if ($page === null) {
                continue;
            }
            Page::delete($db, $id);
            Activity::record($db, 'page', 'deleted', $id, (string) $page['title']);
            $deleted++;
        }
        if ($deleted > 0) {
            Sitemap::refresh($this->container);
        }
        $session = $this->container->get('session');
        $session->set('flash', $deleted === 0
            ? t('pages.bulk.none')
            : tn('pages.bulk.deleted_many', $deleted));
        if ($deleted === 0) {
            $session->set('flash_kind', 'warning');
        }

        // Back to the list as it was filtered.
        $query = array_filter(['lang' => $request->input('lang'), 'q' => $request->input('q')]);

        return Response::redirect(Url::withQuery(Url::admin('pages'), $query));
    }

    /**
     * SEVERAL PAGES COPIED INTO ANOTHER LANGUAGE AT ONCE (PLAN.md D-247, the owner): each ticked
     * page's version in that language made as a draft copy, as the builder's Translate makes one
     * (TranslationController::translate). A page above its subpages first, so each subpage lands
     * under its parent's new version (Translations::parentIn). A page already in that language,
     * or one whose version there exists, is passed over and counted.
     *
     * @param array<string, string> $params
     */
    public function copy(Request $request, string $locale, array $params): Response
    {
        $db = $this->container->get('db');
        $to = trim($request->input('to'));
        $session = $this->container->get('session');
        $query = array_filter(['lang' => $request->input('lang'), 'q' => $request->input('q')]);
        $back = Response::redirect(Url::withQuery(Url::admin('pages'), $query));
        $label = $db->one('SELECT label FROM locales WHERE code = ? AND enabled = 1', [$to]);
        $ids = self::ids($request);
        if ($label === null || $ids === []) {
            $session->set('flash', $label === null ? t('translations.unknown_language') : t('pages.bulk.copy_none'));
            $session->set('flash_kind', 'warning');

            return $back;
        }

        $ids = self::parentsFirst($db, $ids);
        $made = 0;
        $passed = 0;
        $new = [];
        // With "and translate with AI" (D-252), a version there already is translated again
        // rather than passed over: what the owner ticked, they asked to have in that language.
        $again = [];
        foreach ($ids as $id) {
            $page = Page::find($db, $id);
            if ($page === null) {
                continue;
            }
            $result = $page['locale'] === $to ? null : TranslationController::translate($db, $this->container->get('blocks'), $id, $to);
            if ($result !== null && $result['new'] && is_int($result['id'])) {
                $made++;
                $new[] = $result['id'];
            } else {
                $passed++;
                $there = $page['locale'] === $to ? $id : (Translations::of($db, $id)[$to] ?? null);
                if ($there !== null && $db->one("SELECT id FROM pages WHERE id = ? AND translation_status <> 'source'", [$there]) !== null) {
                    $again[] = $there;
                }
            }
        }
        $said = $made > 0 ? tn('pages.bulk.copied_many', $made, ['language' => (string) $label['label']]) : t('pages.bulk.copied_none', ['language' => (string) $label['label']]);
        if ($passed > 0) {
            $said .= ' ' . tn('pages.bulk.copy_passed_many', $passed, ['language' => (string) $label['label']]);
        }
        $session->set('flash', $said);
        if ($made === 0) {
            $session->set('flash_kind', 'warning');
        }

        // "and translate with AI" (D-248): what was made, queued, and what was there (D-252).
        // Said as what is happening, not as copies waiting for the owner's words: the screen it
        // leads to is translating them (the owner, D-259).
        $again = array_values(array_unique($again));
        $queued = \App\Modules\Ai\AiController::queue($this->container, $request, array_values(array_unique(array_merge($new, $again))));
        if ($queued !== null) {
            $language = ['language' => (string) $label['label']];
            $parts = [];
            if ($made > 0) {
                $parts[] = tn('ai.bulk_copied', $made, $language);
            }
            if ($again !== []) {
                $parts[] = tn('ai.bulk_again', count($again), $language);
            }
            if ($passed - count($again) > 0) {
                $parts[] = tn('pages.bulk.copy_passed_many', $passed - count($again), $language);
            }
            $parts[] = t('ai.bulk_read');
            $session->set('flash', implode(' ', $parts));
            $session->set('flash_kind', 'success');
        }

        return $queued ?? $back;
    }

    /**
     * SEVERAL PAGES PUBLISHED AT ONCE (PLAN.md D-254, the owner): each ticked page published as
     * the row's own Publish publishes it (PagePublish::publish) — its draft when it has one, else
     * the page as stored. A page above its subpages first. One already live with no draft is
     * passed over; one the publish refuses is named, with the first reason, and left a draft.
     *
     * @param array<string, string> $params
     */
    public function publish(Request $request, string $locale, array $params): Response
    {
        $db = $this->container->get('db');
        $session = $this->container->get('session');
        $query = array_filter(['lang' => $request->input('lang'), 'q' => $request->input('q')]);
        $back = Response::redirect(Url::withQuery(Url::admin('pages'), $query));
        $ids = self::ids($request);
        if ($ids === []) {
            $session->set('flash', t('pages.bulk.publish_none'));
            $session->set('flash_kind', 'warning');

            return $back;
        }

        $published = 0;
        $live = 0;
        $refused = [];
        foreach (self::parentsFirst($db, $ids) as $id) {
            $page = Page::find($db, $id);
            if ($page === null) {
                continue;
            }
            if ($page['status'] === 'published' && !PageDraft::exists($db, $id)) {
                $live++;
                continue;
            }
            $errors = PagePublish::publish($this->container, $id);
            if ($errors === []) {
                $published++;
            } else {
                $refused[] = t('pages.bulk.publish_refused', ['title' => (string) $page['title'], 'problem' => (string) reset($errors)]);
            }
        }
        $said = $published > 0 ? tn('pages.bulk.published_many', $published) : t('pages.bulk.published_none');
        if ($live > 0) {
            $said .= ' ' . tn('pages.bulk.publish_live_many', $live);
        }
        if ($refused !== []) {
            $said .= ' ' . implode(' ', $refused);
        }
        $session->set('flash', $said);
        if ($published === 0 || $refused !== []) {
            $session->set('flash_kind', 'warning');
        }

        return $back;
    }

    /**
     * SEVERAL PAGES TAKEN OFF THE SITE AT ONCE (PLAN.md D-265, the owner): each ticked page that
     * is on the site unpublished as the row's own button does (PagePublish::unpublish: what it
     * said becomes its draft, and it leaves the sitemap). A draft ticked with them is passed
     * over and counted.
     *
     * @param array<string, string> $params
     */
    public function unpublish(Request $request, string $locale, array $params): Response
    {
        $db = $this->container->get('db');
        $session = $this->container->get('session');
        $query = array_filter(['lang' => $request->input('lang'), 'q' => $request->input('q')]);
        $back = Response::redirect(Url::withQuery(Url::admin('pages'), $query));
        $done = 0;
        $drafts = 0;
        foreach (self::ids($request) as $id) {
            $page = Page::find($db, $id);
            if ($page === null) {
                continue;
            }
            if ($page['status'] !== 'published') {
                $drafts++;
                continue;
            }
            PagePublish::unpublish($this->container, $id);
            Activity::record($db, 'page', 'unpublished', $id, (string) $page['title']);
            $done++;
        }
        if ($done === 0 && $drafts === 0) {
            $session->set('flash', t('pages.bulk.unpublish_none'));
            $session->set('flash_kind', 'warning');

            return $back;
        }
        $said = $done > 0 ? tn('pages.bulk.unpublished_many', $done) : t('pages.bulk.unpublished_nothing');
        if ($drafts > 0) {
            $said .= ' ' . tn('pages.bulk.unpublish_drafts_many', $drafts);
        }
        $session->set('flash', $said);
        if ($done === 0) {
            $session->set('flash_kind', 'warning');
        }

        return $back;
    }

    /**
     * The ids with a page above its subpages, then in the order they were ticked: a copy lands
     * under its parent's new version, and a parent is live before the pages under it.
     *
     * @param list<int> $ids
     * @return list<int>
     */
    private static function parentsFirst(\App\Core\Db $db, array $ids): array
    {
        $parents = [];
        foreach ($db->all('SELECT id, parent_id FROM pages') as $row) {
            $parents[(int) $row['id']] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
        }
        $depth = static function (int $id) use ($parents): int {
            $n = 0;
            for ($at = $parents[$id] ?? null; $at !== null && $n < 50; $at = $parents[$at] ?? null) {
                $n++;
            }

            return $n;
        };
        $order = array_flip($ids);
        usort($ids, static fn (int $a, int $b): int => [$depth($a), $order[$a]] <=> [$depth($b), $order[$b]]);

        return $ids;
    }

    /**
     * The ticked ids, each once, each a positive integer; anything else is ignored.
     *
     * @return list<int>
     */
    public static function ids(Request $request): array
    {
        $raw = $request->body['ids'] ?? [];
        $ids = [];
        foreach (is_array($raw) ? $raw : [] as $value) {
            if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
                $ids[(int) $value] = (int) $value;
            }
        }

        return array_values($ids);
    }
}
