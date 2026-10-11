<?php

namespace App\Modules\Appearance;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\Activity;
use App\Modules\Design\Characters;
use App\Modules\Design\Composition;
use App\Modules\Design\DesignSet;
use App\Modules\Design\DesignVocabulary;
use App\Modules\Pages\Slug;
use App\Support\Url;
use Closure;

/**
 * Designs as files, in and out of the Appearance screen (PLAN.md D-152).
 *
 * OUT: the design on the screen, a kept one, or a character, as a `boxlet-design-set` file.
 * IN: a file through a form of its own, checked by DesignSet::parse() — the same reader a
 * custom file and an imported character go through — and then a question: add it as a
 * character, load it into the screen, or neither. Until the answer, the set waits in the
 * session as the text export() wrote, and is read again when it is used; nothing about the
 * site changes until the owner publishes.
 *
 * Every address here is without an extension, though what it answers is JSON: managed nginx
 * answers an address ending in .json from the disk and never asks PHP (routing_test.php), so
 * the file's name travels in Content-Disposition instead. And every one is a plain GET or a
 * form post: nothing here needs a script.
 *
 * @phpstan-import-type ParsedSet from DesignSet
 */
final class DesignTransferController
{
    private const PENDING = 'design_import';

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * The JSON Schema of a design set, generated from the code that validates one. For a
     * person writing a set by hand, an editor that checks one, and later a model asked for
     * one; designs/design-set.schema.json is the same text, kept in the repository.
     *
     * @param array<string, string> $params
     */
    public function schema(Request $request, string $locale, array $params): Response
    {
        return new Response(DesignVocabulary::schemaJson($this->container->get('blocks')), 200, [
            'Content-Type' => 'application/schema+json; charset=utf-8',
            'Content-Disposition' => 'inline; filename="design-set.schema.json"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * A character as a file: its decisions, its header and footer, its composition, its
     * words.
     *
     * @param array<string, string> $params
     */
    public function exportCharacter(Request $request, string $locale, array $params): Response
    {
        $id = $params['id'];
        if (!Characters::exists($id)) {
            return Response::admin(e(t('appearance.export_missing')), 404);
        }
        $label = Characters::label($id);
        $hint = Characters::hint($id);

        return $this->file($id, $label, DesignSet::export(
            $id,
            ['en' => $label],
            $hint === '' ? [] : ['en' => $hint],
            Characters::decisions($id),
            Characters::look($id),
            Characters::composition($id),
            Characters::source($id) === 'core' ? 'Boxlet' : '',
            Characters::patterns($id),
            Characters::dark($id),
            Characters::tags($id),
        ));
    }

    /**
     * A kept design as a file, with the composition of the character it was made from when
     * that character is still here (the handoff, §4).
     *
     * @param array<string, string> $params
     */
    public function exportLibrary(Request $request, string $locale, array $params): Response
    {
        $saved = DesignLibrary::find($this->db(), (int) $params['id']);
        if ($saved === null) {
            return Response::admin(e(t('appearance.export_missing')), 404);
        }

        return $this->exported(self::slug($saved['name']), $saved['name'], $saved['decisions'], $saved['look'], $saved['character'], $saved['dark']);
    }

    /**
     * A file chosen in the rail, read and checked, and the question of what to do with it.
     *
     * @param array<string, string> $params
     */
    public function import(Request $request, string $locale, array $params): Response
    {
        $file = $request->files['design'] ?? null;
        $text = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_string($file['tmp_name'] ?? null)
            ? (string) file_get_contents($file['tmp_name'], false, null, 0, DesignSet::MAX_BYTES + 1)
            : '';
        if ($text === '') {
            return $this->appearance()->withImport(null, [t('appearance.import_no_file')]);
        }

        $read = DesignSet::parse($text, $this->container->get('blocks'));
        $set = $read['set'];
        if ($set === null) {
            return $this->appearance()->withImport(null, $read['errors']);
        }
        $this->container->get('session')->set(self::PENDING, [
            'json' => DesignSet::export($set['id'], $set['name'], $set['description'], $set['decisions'], $set['look'], $set['composition'], $set['author'], $set['patterns'], $set['dark'], $set['tags']),
            'warnings' => $read['warnings'],
        ]);

        return $this->appearance()->withImport(['set' => $set, 'warnings' => $read['warnings']], []);
    }

    /**
     * The waiting set kept as a character, under an id no character has yet (D-152).
     *
     * @param array<string, string> $params
     */
    public function add(Request $request, string $locale, array $params): Response
    {
        $session = $this->container->get('session');
        $set = $this->pending();
        if ($set === null || $set['composition'] === null) {
            $session->set('flash', t('appearance.import_gone'));

            return Response::redirect(Url::admin('appearance'));
        }
        $db = $this->db();
        Characters::addImported($db, $set);
        $name = $set['name']['en'] ?? (string) reset($set['name']);
        Activity::record($db, 'design', 'imported', null, $name);
        $session->remove(self::PENDING);
        $session->set('flash', t('appearance.import_added', ['name' => $name]));
        $session->set('flash_kind', 'success');

        return Response::redirect(Url::admin('appearance'));
    }

    /**
     * What the Appearance form's own buttons ask of a design as a file: export what is on the
     * screen, load the waiting import into it, or delete an imported character. Null for any
     * other action, which the form's controller answers itself.
     *
     * @param array{decisions: array<string, string>, look: array<string, string>, dark?: array<string, string>, errors: array<string, string>} $state
     * @param Closure(array{decisions: array<string, string>, look: array<string, string>, dark?: array<string, string>}, array<string, string>, ?string, int, string): Response $screen
     */
    public function fromScreen(string $action, array $state, string $character, Closure $screen): ?Response
    {
        $db = $this->db();
        if ($action === 'export') {
            if ($state['errors'] !== []) {
                return $screen($state, $state['errors'], t('design.not_saved'), 422, $character);
            }

            return $this->exported('my-design', t('appearance.export_name'), $state['decisions'], $state['look'], $character !== '' ? $character : Composition::active($db), $state['dark'] ?? []);
        }

        if ($action === 'import:load') {
            $set = $this->pending();
            if ($set === null) {
                return $screen($state, [], t('appearance.import_gone'), 200, $character);
            }
            $name = $set['name']['en'] ?? (string) reset($set['name']);
            Activity::record($db, 'design', 'imported', null, $name);
            $this->container->get('session')->remove(self::PENDING);

            // As loading a character does: the design and its header and footer, and the
            // site's own menu and words kept. A set that is a character already here names
            // it, so publishing can offer its composition.
            return $screen(
                ['decisions' => $set['decisions'], 'look' => $set['look']] + $state,
                [],
                t('appearance.import_loaded', ['name' => $name]),
                200,
                Characters::exists($set['id']) ? $set['id'] : '',
            );
        }

        if (str_starts_with($action, 'character:delete:')) {
            $slug = substr($action, strlen('character:delete:'));
            $name = Characters::label($slug);
            if (Characters::deleteImported($db, $slug)) {
                Activity::record($db, 'design', 'character_deleted', null, $name);

                return $screen($state, [], t('appearance.character_deleted', ['name' => $name]), 200, $character === $slug ? '' : $character);
            }

            return $screen($state, [], null, 404, $character);
        }

        return null;
    }

    /**
     * A design made concrete as a file. With the composition of the character it is drawn
     * from, it is a character, and a character sets every header and footer choice: a choice
     * the design left to its character is written as what that character gives.
     *
     * Its dark version is the character's with the owner's dark colours over it (D-187): what
     * dark mode draws, made the set's own.
     *
     * @param array<string, string> $decisions
     * @param array<string, string> $look
     * @param array<string, string> $ownDark the owner's dark colours
     */
    private function exported(string $id, string $name, array $decisions, array $look, string $character, array $ownDark = []): Response
    {
        $dark = Characters::exists($character) ? Characters::dark($character) : [];
        $ownDark = array_filter(\App\Modules\Design\Tokens::darkOwn($ownDark), static fn (string $v): bool => $v !== '');
        if ($dark !== [] || $ownDark !== []) {
            // On the site a value the owner holds for both modes stands over the character's
            // dark one; in the file the dark version says so, or it would win there.
            foreach (\App\Modules\Design\Vocabulary\Decisions::DARK as $key) {
                if (($decisions[$key] ?? '') !== '') {
                    $dark[$key] = $decisions[$key];
                }
            }
            $dark = $ownDark + $dark;
        }
        $composition = null;
        if (Characters::exists($character)) {
            $composition = Characters::composition($character);
            foreach (Characters::look($character) as $choice => $given) {
                $look[$choice] = ($look[$choice] ?? '') !== '' ? $look[$choice] : $given;
            }
        }

        return $this->file($id, $name, DesignSet::export($id, ['en' => $name], [], $decisions, $look, $composition, '', [], $dark));
    }

    /** The file itself, to be saved rather than shown. */
    private function file(string $id, string $name, string $json): Response
    {
        Activity::record($this->db(), 'design', 'exported', null, $name);

        return new Response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $id . '.json"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * The set waiting for an answer, read again through the format's reader.
     *
     * @return ParsedSet|null
     */
    private function pending(): ?array
    {
        $pending = $this->container->get('session')->get(self::PENDING);
        if (!is_array($pending) || !is_string($pending['json'] ?? null)) {
            return null;
        }

        return DesignSet::parse($pending['json'], $this->container->get('blocks'))['set'];
    }

    /** A kept design's name as a set's id: a slug, at most 32 characters, or my-design. */
    private static function slug(string $name): string
    {
        $slug = trim(substr(Slug::fromTitle($name), 0, 32), '-');

        return preg_match(DesignSet::ID_PATTERN, $slug) === 1 ? $slug : 'my-design';
    }

    private function appearance(): AppearanceScreen
    {
        return new AppearanceScreen($this->container);
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
