<?php

namespace App\Modules\Snippets;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Modules\Pages\PageLinks;
use App\Support\Url;

/**
 * Admin: the snippets (PLAN.md D-201, SPEC §5.6), each a name and its words in every language,
 * made, written and deleted on one screen. A name is chosen once: it is in the tags on the
 * pages, which Boxlet never rewrites.
 */
final class SnippetsController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        return $this->screen([], '', []);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $name = strtolower(trim($request->input('name')));
        $values = $this->typed($request);
        $problem = Snippets::nameProblem($db, $name);
        if ($problem !== null) {
            return $this->screen(['name' => $problem], $name, $values, 422);
        }
        Snippets::save($db, $name, $values);
        Activity::record($db, 'snippet', 'created', $this->subject($name), $name);
        $this->flash(t('snippets.created', ['name' => $name]));

        return Response::redirect(Url::admin('snippets') . '#snippet-' . $name);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $name = $params['name'];
        $id = $this->subject($name);
        if ($id === null) {
            return Response::redirect(Url::admin('snippets'));
        }
        Snippets::save($db, $name, $this->typed($request));
        Activity::record($db, 'snippet', 'saved', $id, $name);
        $this->flash(t('snippets.saved', ['name' => $name]));

        return Response::redirect(Url::admin('snippets') . '#snippet-' . $name);
    }

    /**
     * Its tags on the pages stay as written and draw nothing: the owner's words are not
     * rewritten, and a snippet made again under the name stands in them again.
     *
     * @param array<string, string> $params
     */
    public function delete(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $name = $params['name'];
        $id = $this->subject($name);
        if ($id !== null) {
            Snippets::delete($db, $name);
            Activity::record($db, 'snippet', 'deleted', $id, $name);
            $this->flash(t('snippets.deleted', ['name' => $name]));
        }

        return Response::redirect(Url::admin('snippets'));
    }

    /**
     * The words posted for each of the site's languages, by code; a language not posted is
     * not touched.
     *
     * @return array<string, string>
     */
    private function typed(Request $request): array
    {
        $values = [];
        foreach ($this->locales() as $row) {
            $code = (string) $row['code'];
            if (array_key_exists('words_' . $code, $request->body)) {
                $values[$code] = $request->input('words_' . $code);
            }
        }

        return $values;
    }

    /** The snippet's first row, which the activity log names it by; null when there is none. */
    private function subject(string $name): ?int
    {
        $row = $this->db()->one('SELECT MIN(id) AS id FROM snippets WHERE name = ?', [$name]);

        return isset($row['id']) ? (int) $row['id'] : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function locales(): array
    {
        return $this->db()->all('SELECT code, label, is_primary FROM locales WHERE enabled = 1 ORDER BY sort, code');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $typed the new snippet's words, as posted
     */
    private function screen(array $errors, string $name, array $typed, int $status = 200): Response
    {
        $db = $this->db();
        $locales = $this->locales();
        $pages = [];
        foreach ($locales as $row) {
            $pages[(string) $row['code']] = PageLinks::choices($db, (string) $row['code']);
        }

        return AdminView::render($this->container, __DIR__ . '/views', 'index', [
            'title' => t('snippets.title'),
            'nav' => 'snippets',
            'styles' => ['admin-pages.css', 'admin-richtext.css', 'admin-richtext-tags.css', 'admin-snippets.css'],
            'scripts' => ['vendor/tiptap.bundle.min.js', 'richtext-link.js', 'richtext.js'],
            'snippets' => Snippets::all($db),
            'locales' => $locales,
            'linkPages' => $pages,
            'errors' => $errors,
            'name' => $name,
            'typed' => $typed,
        ], $status);
    }

    private function flash(string $message): void
    {
        $session = $this->container->get('session');
        $session->set('flash', $message);
        $session->set('flash_kind', 'success');
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
