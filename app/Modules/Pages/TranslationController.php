<?php

namespace App\Modules\Pages;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Support\Url;

/**
 * Translating a page (PLAN.md D-043, step 2): the builder's language menu posts here to
 * make the page's version in another language, and is taken to it. A version that already
 * exists is simply opened, so a second press is never a second copy.
 */
final class TranslationController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function create(Request $request, string $locale, array $params): Response
    {
        $pageId = (int) $params['id'];
        $made = self::translate($this->db(), $this->container->get('blocks'), $pageId, trim($request->input('locale')));
        $session = $this->container->get('session');

        if (is_string($made['id'])) {
            $session->set('flash', $made['id']);
            $session->set('flash_kind', 'error');

            return Response::redirect(Url::admin('pages', $pageId));
        }
        if ($made['new']) {
            $session->set('flash', t('translations.created', ['language' => $made['language']]));
            // Translate with AI (D-248): the new version queued, and the screen that runs it.
            $queued = \App\Modules\Ai\AiController::queue($this->container, $request, [$made['id']]);
            if ($queued !== null) {
                return $queued;
            }
        }

        return Response::redirect(Url::admin('pages', $made['id']));
    }

    /**
     * ONE PAGE INTO ONE LANGUAGE, wherever it is asked from: the builder's language menu, a row
     * of the pages list, the list's "Copy to language" (D-247). The version that exists is
     * found; a new one is a draft copy, made from the page's draft where it was never
     * published (D-163 point 6), and recorded in the activity. Its forms come into the
     * language with it, and the language's menus take it in (D-262).
     *
     * @return array{id: int|string, new: bool, language: string} the version's id, or a
     *         message saying why there is none
     */
    public static function translate(Db $db, \App\Core\Blocks $registry, int $pageId, string $target): array
    {
        $before = Translations::of($db, $pageId);
        $result = Translations::create($db, $registry, $pageId, $target);
        $label = (string) ($db->one('SELECT label FROM locales WHERE code = ?', [$target])['label'] ?? $target);
        if (is_string($result) || isset($before[$target])) {
            return ['id' => $result, 'new' => false, 'language' => $label];
        }
        Translations::carryDraft($db, $registry, $pageId, $result);
        // What a page brings into a language beside itself (D-262): its forms in that language,
        // and its place in that language's menus, mirrored from its original's.
        \App\Modules\Forms\FormCopy::pageCopied($db, $registry, $result);
        $from = (string) ($db->one("SELECT p.locale FROM pages p JOIN pages c ON c.content_group_id = p.content_group_id WHERE c.id = ? AND p.translation_status = 'source'", [$result])['locale'] ?? '');
        if ($from !== '') {
            \App\Modules\Menus\MenuCopy::sync($db, $from, $target);
        }
        $title = (string) ($db->one('SELECT title FROM pages WHERE id = ?', [$result])['title'] ?? '');
        Activity::record($db, 'page', 'translated', $result, $title . ' · ' . $label);

        return ['id' => $result, 'new' => true, 'language' => $label];
    }

    /**
     * Records a translation's block as up to date with its source (D-043, step 3).
     *
     * @param array<string, string> $params
     */
    public function current(Request $request, string $locale, array $params): Response
    {
        $pageId = (int) $params['id'];
        $done = TranslationStatus::markCurrent($this->db(), $this->container->get('blocks'), $pageId, (int) $params['block']);
        $session = $this->container->get('session');
        $session->set('flash', t($done ? 'translations.marked_current' : 'translations.not_a_block'));
        if (!$done) {
            $session->set('flash_kind', 'error');
        }

        return Response::redirect(Url::admin('pages', $pageId));
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
