<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Container;
use App\Core\Db;
use App\Modules\Admin\Activity;

/**
 * What changes the PUBLISHED page (PLAN.md D-163, D-173, README 2.1): Publish, Discard,
 * Unpublish, and a revision brought back into the draft.
 *
 * PUBLISH validates the draft, records the page as it was (a revision), writes the draft into
 * the published tables in one transaction (Page::update, by id), and deletes the draft. A page
 * with no draft is published as it is stored. DISCARD deletes the draft, and is offered only
 * where there is a published page to go back to. UNPUBLISH takes the page off the site; with
 * no draft, the published version becomes the draft first, so what the page said is never
 * only in tables nobody edits (D-163 point 7).
 *
 * @phpstan-import-type Document from PageDocument
 */
final class PagePublish
{
    /**
     * Publishes the page: its draft when it has one, else the page as stored.
     *
     * @return array<string, string> what stops it, by field; empty when it is published
     */
    public static function publish(Container $container, int $pageId): array
    {
        $db = self::db($container);
        $registry = self::registry($container);
        $page = Page::find($db, $pageId);
        if ($page === null) {
            return ['page' => t('pages.not_found')];
        }
        $draft = PageDraft::find($db, $registry, $pageId);
        if ($draft === null) {
            Page::setStatus($db, $pageId, true);
            Activity::record($db, 'page', 'published', $pageId, (string) $page['title']);
            Sitemap::refresh($container);

            return [];
        }

        $errors = self::problems($db, $page, $draft['document']);
        if ($errors !== []) {
            return $errors;
        }
        // What the page was, before it stops being that (D-088): a revision is the published
        // page, recorded where it is replaced.
        if ($page['published_at'] !== null) {
            PageRevision::record($db, $registry, $pageId);
        }
        $db->transaction(static function () use ($db, $registry, $pageId, $draft): void {
            PageDocument::write($db, $registry, $pageId, $draft['document'], 'published');
            PageDraft::discard($db, $pageId);
        });
        Activity::record($db, 'page', 'published', $pageId, $draft['document']['title']);
        Sitemap::refresh($container);

        return [];
    }

    /**
     * Deletes the draft, back to the published page. Nothing to go back to — a page never
     * published — and nothing happens: deleting such a page is the page list's (D-163 point 7).
     */
    public static function discard(Container $container, int $pageId): bool
    {
        $db = self::db($container);
        $page = Page::find($db, $pageId);
        if ($page === null || $page['status'] !== 'published' || !PageDraft::exists($db, $pageId)) {
            return false;
        }
        PageDraft::discard($db, $pageId);
        Activity::record($db, 'page', 'discarded', $pageId, (string) $page['title']);

        return true;
    }

    /**
     * Takes the page off the site. With no draft, what is published becomes the draft, so
     * the editors go on showing the page and a later Publish puts the same page back.
     */
    public static function unpublish(Container $container, int $pageId): void
    {
        $db = self::db($container);
        $registry = self::registry($container);
        if (!PageDraft::exists($db, $pageId)) {
            $stored = PageDocument::stored($db, $registry, $pageId);
            if ($stored !== null) {
                PageDraft::save($db, $pageId, $stored, 0, self::admin($container));
            }
        }
        Page::setStatus($db, $pageId, false);
        Sitemap::refresh($container);
    }

    /**
     * A revision brought back INTO THE DRAFT (README 2.1), never straight onto the live page:
     * the owner sees what it was, and Publish is what makes it the page again.
     *
     * @return bool false when the revision is not this page's, or the draft moved on
     */
    public static function restore(Container $container, int $pageId, int $revisionId, int $from): bool
    {
        $db = self::db($container);
        $registry = self::registry($container);
        $revision = PageRevision::find($db, $registry, $pageId, $revisionId);
        if ($revision === null) {
            return false;
        }
        // The page's state is the page's, not the revision's: a page published now stays
        // published while its draft holds last Tuesday's words.
        $page = Page::find($db, $pageId);
        $revision['status'] = (string) ($page['status'] ?? 'draft');

        return PageDraft::save($db, $pageId, $revision, $from, self::admin($container)) !== null;
    }

    /**
     * What stops a document being published: the checks a save has always made — a title, an
     * address free in its language, a parent the page may have.
     *
     * @param array<string, mixed> $page the pages row
     * @param Document $document
     * @return array<string, string>
     */
    public static function problems(Db $db, array $page, array $document): array
    {
        $errors = [];
        if ($document['title'] === '') {
            $errors['title'] = t('pages.title_required');
        }
        $slug = Slug::problem($db, (string) $page['locale'], $document['slug'], (int) $page['id']);
        if ($slug !== null) {
            $errors['slug'] = $slug;
        }
        if ($document['parent_id'] !== null) {
            $allowed = array_column(PageTree::parents($db, (string) $page['locale'], (int) $page['id']), 'id');
            if (!in_array($document['parent_id'], $allowed, true)) {
                $errors['parent'] = t('pages.parent_invalid');
            }
        }

        return $errors;
    }

    /** The administrator making the change, for the draft's updated_by. */
    public static function admin(Container $container): ?int
    {
        $id = $container->get('session')->get('admin_id');

        return is_int($id) ? $id : (is_string($id) && ctype_digit($id) ? (int) $id : null);
    }

    private static function db(Container $container): Db
    {
        return $container->get('db');
    }

    private static function registry(Container $container): Blocks
    {
        return $container->get('blocks');
    }
}
