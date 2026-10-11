<?php

namespace App\Modules\Demo;

use App\Core\Db;
use App\Modules\Media\MediaController;
use App\Modules\Media\MediaEncoder;
use App\Modules\Media\MediaMeta;
use App\Modules\Media\MediaUpload;
use App\Modules\Media\MediaVariants;
use App\Modules\Media\MediaWriter;
use Closure;

/**
 * The demo's pictures and documents (PLAN.md D-213), taken into the media library as an upload
 * would be: every size of a picture made, and an alt text in each of the demo's two languages.
 *
 * The pictures are described in a credits.json (where each comes from, under which licence,
 * and its alt text in English and Croatian); only those the pages name are taken. The documents
 * are its files/ folder's PDFs, one per language. Where they are is $root: demo_images/ in the
 * checkout (the default, for `php migrations/seed.php` and the tests), or the demo's package
 * as the installer unpacked it (D-215), which has the same layout.
 *
 * The pages name a picture as `demo-picture:<folder/name>` and a document as `demo-file:<name>`;
 * DemoSite puts the id there, or leaves the field empty when nothing was imported (the tests'
 * seed).
 */
final class DemoPictures
{
    /**
     * What stores one file and, for a picture, makes its sizes, from the site's own folders:
     * the installer and `php migrations/seed.php` both build it, the one before the container
     * exists.
     *
     * @return Closure(string, string): int the media id
     */
    public static function importer(Db $db, string $storage, string $public): Closure
    {
        $encoder = new MediaEncoder();
        $upload = new MediaUpload($db, $storage, $encoder);
        $variants = new MediaVariants($db, $encoder, new MediaWriter($encoder), $storage, $public);

        return static function (string $file, string $name) use ($upload, $variants): int {
            // A copy, because an upload is moved into storage and the package's file must stay.
            $temporary = (string) tempnam(sys_get_temp_dir(), 'demo');
            copy($file, $temporary);
            $stored = $upload->store($temporary, $name);
            @unlink($temporary);
            $id = (int) $stored['id'];
            // A document has no sizes to make (D-126).
            if (str_ends_with($name, '.pdf')) {
                return $id;
            }
            // EVERY SIZE, AND NEVER PAST THE REQUEST'S LIMIT. Each picture asks for its own
            // time where the host allows that, and where it does not, what the budget leaves
            // is finished from the library (Finish), rather than the install dying half-way
            // with a fatal error. Only a limit there is: a command line with none must not be
            // given one, which the whole test suite once inherited from this line.
            $limit = (int) ini_get('max_execution_time');
            if ($limit > 0) {
                @set_time_limit(max($limit, 60));
            }
            $variants->generate($id, MediaController::budget(microtime(true)));

            return $id;
        };
    }

    /**
     * Every picture demo_images/credits.json describes, by its name (folder/file, no
     * extension): its file and its alt text in each language.
     *
     * @return array<string, array{file: string, en: string, hr: string}>
     */
    public static function catalogue(string $root = ''): array
    {
        $root = $root !== '' ? $root : self::root();
        $credits = json_decode((string) @file_get_contents($root . '/credits.json'), true);
        $pictures = [];
        foreach (is_array($credits) ? $credits : [] as $row) {
            if (!is_array($row) || !is_string($row['file'] ?? null)) {
                continue;
            }
            $pictures[substr($row['file'], 0, -strlen('.webp'))] = [
                'file' => $root . '/' . $row['file'],
                'en' => (string) ($row['alt'] ?? ''),
                'hr' => (string) ($row['alt_hr'] ?? ''),
            ];
        }

        return $pictures;
    }

    /**
     * Every `demo-picture:` and `demo-file:` name the pages hold, in their order, once each.
     *
     * @param array<mixed> $pages
     * @return array{pictures: list<string>, files: list<string>}
     */
    public static function named(array $pages): array
    {
        $found = ['pictures' => [], 'files' => []];
        array_walk_recursive($pages, static function (mixed $value) use (&$found): void {
            if (is_string($value) && str_starts_with($value, 'demo-picture:')) {
                $found['pictures'][] = substr($value, 13);
            } elseif (is_string($value) && str_starts_with($value, 'demo-file:')) {
                $found['files'][] = substr($value, 10);
            }
        });

        return ['pictures' => array_values(array_unique($found['pictures'])), 'files' => array_values(array_unique($found['files']))];
    }

    /**
     * Each named picture stored, with its alt text in the site's language ($lang's words).
     *
     * @param Closure(string, string): int $store
     * @param list<string> $names
     * @return array<string, int> name => media id
     */
    public static function import(Db $db, Closure $store, string $locale, string $lang, array $names, string $root = ''): array
    {
        $catalogue = self::catalogue($root);
        $ids = [];
        foreach ($names as $name) {
            $picture = $catalogue[$name] ?? null;
            if ($picture === null) {
                error_log('Demo picture ' . $name . ': not in demo_images/credits.json');
                continue;
            }
            // A server that cannot take a picture (no image extension) still gets the demo:
            // that picture's field stays empty, as it would without the pictures at all.
            try {
                $id = $store($picture['file'], basename($picture['file']));
            } catch (\Throwable $e) {
                error_log('Demo picture ' . $name . ': ' . $e->getMessage());
                continue;
            }
            MediaMeta::save($db, $id, $locale, $lang === 'hr' ? $picture['hr'] : $picture['en'], '');
            $ids[$name] = $id;
        }

        return $ids;
    }

    /**
     * Each named document stored, in the site's language: demo_images/files/{name}-{lang}.pdf.
     *
     * @param Closure(string, string): int $store
     * @param list<string> $names
     * @return array<string, int> name => media id
     */
    public static function documents(Closure $store, string $lang, array $names, string $root = ''): array
    {
        $ids = [];
        foreach ($names as $name) {
            $file = ($root !== '' ? $root : self::root()) . '/files/' . $name . '-' . $lang . '.pdf';
            try {
                $ids[$name] = $store($file, basename($file));
            } catch (\Throwable $e) {
                error_log('Demo document ' . $name . ': ' . $e->getMessage());
            }
        }

        return $ids;
    }

    /**
     * Each picture's alt text in one more language, once the site has it: a description
     * names its language, and the demo's translation adds that language after the pictures.
     *
     * @param array<string, int> $ids name => media id
     */
    public static function translate(Db $db, array $ids, string $locale, string $root = ''): void
    {
        $catalogue = self::catalogue($root);
        foreach ($ids as $name => $id) {
            if (isset($catalogue[$name])) {
                MediaMeta::save($db, $id, $locale, $locale === 'hr' ? $catalogue[$name]['hr'] : $catalogue[$name]['en'], '');
            }
        }
    }

    /**
     * Content with each `demo-picture:<name>` and `demo-file:<name>` replaced by its id, or
     * by nothing when there is no such picture or file — in its fields and in its repeaters'
     * items, and in a section's style the same way.
     *
     * @param array<string, mixed> $content
     * @param array<string, int> $pictures
     * @param array<string, int> $files
     * @return array<string, mixed>
     */
    public static function place(array $content, array $pictures, array $files = []): array
    {
        foreach ($content as $name => $value) {
            if (is_string($value) && str_starts_with($value, 'demo-picture:')) {
                $content[$name] = $pictures[substr($value, 13)] ?? null;
            } elseif (is_string($value) && str_starts_with($value, 'demo-file:')) {
                $content[$name] = $files[substr($value, 10)] ?? null;
            } elseif (is_array($value)) {
                $content[$name] = self::place($value, $pictures, $files);
            }
        }

        return $content;
    }

    /** Where the demo's pictures and documents are: demo_images/ in the checkout. */
    private static function root(): string
    {
        return dirname(__DIR__, 3) . '/demo_images';
    }
}
