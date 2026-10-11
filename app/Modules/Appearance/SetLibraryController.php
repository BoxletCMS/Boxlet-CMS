<?php

namespace App\Modules\Appearance;

use App\Core\Blocks;
use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Modules\Design\Characters;
use App\Modules\Design\SetLibrary;
use App\Support\Url;

/**
 * BROWSE LIBRARY (PLAN.md D-195, the owner): the sets in designs/library/ as cards, each added
 * as a character in one press — what Import → "Add as character" does with a file, without the
 * file. A set already among the characters says so instead of offering itself twice.
 */
final class SetLibraryController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function browse(Request $request, string $locale, array $params): Response
    {
        $sets = [];
        foreach (SetLibrary::all($this->registry()) as $id => $set) {
            $sets[] = ['id' => $id, 'set' => $set, 'added' => Characters::exists($set['id']) && Characters::source($set['id']) !== 'core'];
        }

        return AdminView::render($this->container, __DIR__ . '/views', 'browse', [
            'title' => t('browse.title'),
            'nav' => 'appearance',
            'styles' => ['admin-appearance-browse.css'],
            'sets' => $sets,
        ]);
    }

    /**
     * One set kept as a character, under an id no character has yet (D-152), and back to
     * Appearance with it among the tiles.
     *
     * @param array<string, string> $params
     */
    public function add(Request $request, string $locale, array $params): Response
    {
        $session = $this->container->get('session');
        $set = SetLibrary::find($params['id'], $this->registry());
        if ($set === null) {
            $session->set('flash', t('browse.gone'));
            $session->set('flash_kind', 'error');

            return Response::redirect(Url::admin('appearance', 'browse'));
        }
        $db = $this->db();
        Characters::addImported($db, $set, 'library');
        $name = $set['name']['en'] ?? (string) reset($set['name']);
        Activity::record($db, 'design', 'imported', null, $name);
        $session->set('flash', t('appearance.import_added', ['name' => $name]));
        $session->set('flash_kind', 'success');

        return Response::redirect(Url::admin('appearance'));
    }

    private function registry(): Blocks
    {
        return $this->container->get('blocks');
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
