<?php

namespace App\Modules\Scripts;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Support\Url;

/**
 * Admin: the owner's scripts and the cookies they set (PLAN.md O-73). The list, with the one
 * question a visitor is asked and whether they are asked at all; a new script, recognised as it
 * arrives (ScriptCatalogue); one script edited, its cookies a list of rows; deleting one.
 */
final class ScriptsController
{
    /** Empty cookie rows drawn under the filled ones, to add to without a script. */
    private const SPARE_ROWS = 2;

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        return $this->list([], ['name' => '', 'code' => ''], 200);
    }

    /**
     * A new script: its name and code, recognised, then opened to be checked.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $parsed = Scripts::parse(['name' => $request->input('name'), 'code' => $request->input('code'), 'enabled' => '1']);
        if ($parsed['errors'] !== []) {
            return $this->list($parsed['errors'], ['name' => $parsed['script']['name'], 'code' => $parsed['script']['code']], 422);
        }
        $script = $parsed['script'];
        $known = ScriptCatalogue::recognise($script['code'], $this->mainLocale());
        if ($known !== null) {
            $script['kind'] = $known['kind'];
            $script['cookies'] = $known['cookies'];
        }
        $id = Scripts::save($db, null, $script);
        Activity::record($db, 'script', 'created', $id, $script['name']);
        $session = $this->container->get('session');
        $session->set('flash', $known === null
            ? t('scripts.added_unknown')
            : t($known['cookieless'] ? 'scripts.added_cookieless' : 'scripts.added_known', ['service' => $known['name']]));

        return Response::redirect(Url::admin('scripts', $id));
    }

    /**
     * @param array<string, string> $params
     */
    public function edit(Request $request, string $locale, array $params): Response
    {
        $script = Scripts::find($this->db(), (int) $params['id']);
        if ($script === null) {
            return Response::admin(e(t('scripts.not_found')), 404);
        }

        return $this->screen($script, [], 200);
    }

    /**
     * Saves the script; "Use the known cookies" adds the ones its service sets that it does not
     * list yet, and saves too.
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $stored = Scripts::find($db, (int) $params['id']);
        if ($stored === null) {
            return Response::admin(e(t('scripts.not_found')), 404);
        }
        $parsed = Scripts::parse($request->body);
        $script = $parsed['script'];
        if ($request->input('action') === 'known') {
            $known = ScriptCatalogue::recognise($script['code'], $this->mainLocale());
            $listed = array_column($script['cookies'], 'name');
            foreach ($known['cookies'] ?? [] as $cookie) {
                if (!in_array($cookie['name'], $listed, true)) {
                    $script['cookies'][] = $cookie;
                }
            }
        }
        if ($parsed['errors'] !== []) {
            return $this->screen(['id' => $stored['id'], 'position' => $stored['position']] + $script, $parsed['errors'], 422);
        }
        Scripts::save($db, $stored['id'], $script);
        Activity::record($db, 'script', 'saved', $stored['id'], $script['name']);
        $this->container->get('session')->set('flash', t('scripts.saved'));

        return Response::redirect(Url::admin('scripts', $stored['id']));
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $script = Scripts::find($db, (int) $params['id']);
        if ($script !== null) {
            Scripts::delete($db, $script['id']);
            Activity::record($db, 'script', 'deleted', $script['id'], $script['name']);
        }
        $this->container->get('session')->set('flash', t('scripts.deleted'));

        return Response::redirect(Url::admin('scripts'));
    }

    /**
     * @param array<string, string> $errors
     * @param array{name: string, code: string} $old what was typed into a refused new script
     */
    private function list(array $errors, array $old, int $status): Response
    {
        $scripts = Scripts::all($this->db());
        $asked = [];
        foreach ($scripts as $script) {
            if ($script['enabled'] && in_array($script['kind'], Scripts::CONSENTED, true)) {
                $asked[$script['kind']] = true;
            }
        }

        return AdminView::render($this->container, __DIR__ . '/views', 'index', [
            'title' => t('scripts.title'),
            'nav' => 'scripts',
            'wide' => true,
            'styles' => ['admin-scripts.css'],
            // Not 'scripts': that key is the page's own JavaScript files to AdminView.
            'rows' => $scripts,
            // What a visitor is asked about, in the banner's order; none, and there is no banner.
            'asked' => array_values(array_filter(Scripts::CONSENTED, static fn (string $kind): bool => isset($asked[$kind]))),
            'errors' => $errors,
            'old' => $old,
        ], $status);
    }

    /**
     * @param array{id: int, name: string, code: string, place: string, kind: string, cookies: list<array{name: string, provider: string, purpose: string, duration: string}>, enabled: bool, position: int} $script
     * @param array<string, string> $errors
     */
    private function screen(array $script, array $errors, int $status): Response
    {
        $listed = [];
        foreach (Scripts::all($this->db()) as $each) {
            array_push($listed, ...array_column($each['cookies'], 'name'));
        }

        return AdminView::render($this->container, __DIR__ . '/views', 'edit', [
            'title' => $script['name'] !== '' ? $script['name'] : t('scripts.title'),
            'nav' => 'scripts',
            'styles' => ['admin-scripts.css'],
            'scripts' => ['admin-scripts-check.js'],
            'script' => $script,
            'known' => ScriptCatalogue::recognise($script['code'], $this->mainLocale()),
            'spare' => self::SPARE_ROWS,
            // Every name any script lists, so the check can tell what no script names yet.
            'listed' => array_values(array_unique($listed)),
            'errors' => $errors,
        ], $status);
    }

    /** The language a known service's cookies are described in: the site's main one. */
    private function mainLocale(): string
    {
        return Url::primaryLocale() !== '' ? Url::primaryLocale() : 'en';
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
