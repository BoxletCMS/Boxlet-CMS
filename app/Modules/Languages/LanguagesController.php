<?php

namespace App\Modules\Languages;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Pages\Sitemap;
use App\Support\Url;

/**
 * The Languages panel on the Settings screen (PLAN.md D-043): add a language, switch one
 * on or off, change the order, remove one that holds nothing. Every action answers with a
 * redirect back to the panel, carrying what happened as the flash message — a refusal as
 * an error, so it is never read as a confirmation.
 */
final class LanguagesController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function add(Request $request, string $locale, array $params): Response
    {
        $code = trim($request->input('code'));
        $error = Locales::add($this->db(), $code);
        if ($error === null) {
            Activity::record($this->db(), 'settings', 'language_added', null, Locales::known()[$code] ?? $code);
        }

        return $this->back($error, t('languages.added', ['language' => Locales::known()[$code] ?? $code]));
    }

    /**
     * @param array<string, string> $params
     */
    public function enabled(Request $request, string $locale, array $params): Response
    {
        $on = $request->input('enabled') === '1';
        $error = Locales::setEnabled($this->db(), $params['code'], $on);
        if ($error === null) {
            Activity::record($this->db(), 'settings', $on ? 'language_on' : 'language_off', null, $this->label($params['code']));
        }
        Sitemap::refresh($this->container);

        return $this->back($error, t($on ? 'languages.switched_on' : 'languages.switched_off', ['language' => $this->label($params['code'])]));
    }

    /**
     * @param array<string, string> $params
     */
    public function move(Request $request, string $locale, array $params): Response
    {
        Locales::move($this->db(), $params['code'], $request->input('move') === 'up' ? 'up' : 'down');

        return $this->back(null, t('languages.reordered'));
    }

    /**
     * @param array<string, string> $params
     */
    public function remove(Request $request, string $locale, array $params): Response
    {
        $label = $this->label($params['code']);
        $error = Locales::remove($this->db(), $params['code']);
        if ($error === null) {
            Activity::record($this->db(), 'settings', 'language_removed', null, $label);
        }
        Sitemap::refresh($this->container);

        return $this->back($error, t('languages.removed', ['language' => $label]));
    }

    private function back(?string $error, string $done): Response
    {
        $session = $this->container->get('session');
        $session->set('flash', $error ?? $done);
        if ($error !== null) {
            $session->set('flash_kind', 'error');
        }

        return Response::redirect(Url::admin('settings') . '#languages');
    }

    private function label(string $code): string
    {
        return (string) ($this->db()->one('SELECT label FROM locales WHERE code = ?', [$code])['label'] ?? $code);
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
