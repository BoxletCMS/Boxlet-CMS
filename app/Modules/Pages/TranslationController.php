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
        $db = $this->db();
        $pageId = (int) $params['id'];
        $target = trim($request->input('locale'));
        $before = Translations::of($db, $pageId);
        $result = Translations::create($db, $this->container->get('blocks'), $pageId, $target);
        $session = $this->container->get('session');

        if (is_string($result)) {
            $session->set('flash', $result);
            $session->set('flash_kind', 'error');

            return Response::redirect(Url::admin('pages', $pageId));
        }
        if (!isset($before[$target])) {
            $label = (string) ($db->one('SELECT label FROM locales WHERE code = ?', [$target])['label'] ?? $target);
            $title = (string) ($db->one('SELECT title FROM pages WHERE id = ?', [$result])['title'] ?? '');
            Activity::record($db, 'page', 'translated', $result, $title . ' · ' . $label);
            $session->set('flash', t('translations.created', ['language' => $label]));
        }

        return Response::redirect(Url::admin('pages', $result));
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
