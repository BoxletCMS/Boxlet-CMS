<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\View;
use App\Modules\Design\Composition;
use RuntimeException;

/**
 * The picture of a block in the library.
 *
 * Previews are rendered from the block itself, never drawn by hand. A hand-made
 * thumbnail stops matching its block the first time the block changes and nobody
 * notices for months; a rendered one cannot lie.
 *
 * Each is a complete little page written to public/cache/previews, so the library loads
 * them as static files and no request touches PHP. The file name carries a hash of the
 * block definition and of the site's compiled stylesheet, so changing either the block
 * or the design produces a new name and the old file is removed.
 */
final class BlockPreview
{
    private const DIRECTORY = 'previews';

    /** The id a sampled form field takes, resolved below to a form of sample questions. */
    private const SAMPLE_FORM = 1;

    /**
     * The preview file for one block type, generated if it is not already there.
     *
     * @param string $stylesheet the site's compiled tokens file name, so a design change
     *                           regenerates every preview
     */
    public static function file(Blocks $registry, string $type, string $stylesheet, string $cacheDirectory): string
    {
        $definition = $registry->get($type);
        // Hashed against what the preview is RENDERED FROM, which includes the sample. The
        // hash covered only the definition and the stylesheet, so changing how a field is
        // sampled left every already-generated file in place — a preview kept showing the
        // old sample until the block or the design happened to change. That is how a
        // corrected preview would have failed to reach the installs that already had one.
        $hash = substr(hash('sha256', json_encode(
            [$definition, self::sample($definition), $stylesheet],
            JSON_THROW_ON_ERROR,
        )), 0, 12);
        $file = $type . '.' . $hash . '.html';
        $directory = $cacheDirectory . '/' . self::DIRECTORY;
        $target = $directory . '/' . $file;

        if (is_file($target)) {
            return $file;
        }

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create directory {$directory}");
        }
        self::write($target, self::document($registry, $type));
        foreach (glob($directory . '/' . $type . '.*.html') ?: [] as $old) {
            if (basename($old) !== $file) {
                unlink($old);
            }
        }

        return $file;
    }

    /**
     * Every block type's preview file, keyed by type.
     *
     * @return array<string, string>
     */
    public static function all(Blocks $registry, string $stylesheet, string $cacheDirectory): array
    {
        $files = [];
        foreach ($registry->types() as $type) {
            $files[$type] = self::file($registry, $type, $stylesheet, $cacheDirectory);
        }

        return $files;
    }

    /**
     * Sample content for a block, built from its own field types so a block added later
     * gets a preview without anyone writing one.
     *
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    public static function sample(array $definition): array
    {
        return self::sampleFields($definition['fields']);
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    private static function sampleFields(array $fields): array
    {
        $content = [];
        foreach ($fields as $name => $field) {
            /*
             * A FIELD MAY SAY WHAT IT SHOWS IN A PREVIEW (SPEC §5.3, D-083).
             *
             * Every text field was mapped to the one generic sample, so all five cards read
             * "A heading sits here" and the pictures told them apart only by shape. A block
             * added later still needs to write nothing: without a sample the generic one
             * stands, which is what makes the library work for a block nobody has thought
             * about yet.
             */
            $own = is_string($field['sample'] ?? null) ? t($field['sample']) : null;
            $content[$name] = match ($field['type']) {
                // Null, never an id. A preview is a picture of the BLOCK, and an id here
                // makes it a picture of whichever photograph happens to hold that number —
                // on a fresh install nothing, and on a used one somebody's holiday snap.
                // Every layout that reserves a picture area draws its placeholder anyway.
                'media' => null,
                // A FORM FIELD IS DIFFERENT, because a form block whose form is missing
                // draws no form at all — so the card for it showed a heading, a sentence
                // and then nothing, under a hint that promises "the block as this site
                // renders it" (D-083). It samples to an id that document() then resolves
                // to a form made of air. The special case belongs to the field TYPE, like
                // the one above it, not to any block: any block that takes a form gets it.
                'form' => self::SAMPLE_FORM,
                'link' => ['label' => $own ?? t('preview.link'), 'url' => '#'],
                'select' => $field['options'][0],
                'richtext' => '<p>' . e($own ?? t('preview.body')) . '</p>',
                'textarea' => $own ?? t('preview.body'),
                // Three items, sampled like any other fields: one row of a grid, which is
                // the shape a repeater gives a block. A string here drew nothing at all.
                'repeater' => array_fill(0, min(3, $field['max']), self::sampleFields($field['fields'])),
                default => $own ?? t('preview.heading'),
            };
        }

        return $content;
    }

    /**
     * A form of sample questions, shaped exactly as FormBlocks resolves a real one.
     *
     * Not a form from the database: a preview must render the same on a fresh install and
     * on a full one, and reaching for whatever form happens to exist would make the card a
     * picture of somebody's newsletter sign-up. The questions are the three nearly every
     * contact form asks, so the card shows the SHAPE a form block takes — which is the only
     * thing it can honestly say about a form chosen later.
     *
     * action is '#' and the token empty because nothing here is sendable: the preview is a
     * static file with no session behind it.
     *
     * @return array<string, mixed>
     */
    private static function sampleForm(): array
    {
        $field = static fn (string $key, string $type, bool $required): array => [
            'key' => $key,
            'label' => t('preview.form.' . $key),
            'type' => $type,
            'required' => $required,
            'options' => [],
        ];

        return [
            'form' => [
                'id' => self::SAMPLE_FORM,
                'settings' => ['success' => '', 'submit' => t('preview.form.submit')],
                'fields' => [
                    $field('name', 'text', true),
                    $field('email', 'email', true),
                    $field('message', 'textarea', true),
                ],
            ],
            'errors' => [],
            'old' => [],
            'sent' => false,
            'notice' => '',
            'action' => '#',
            'token' => '',
            'page' => null,
        ];
    }

    private static function document(Blocks $registry, string $type): string
    {
        $definition = $registry->get($type);
        $html = $registry->render(
            $type,
            self::sample($definition),
            // Neutral section defaults on purpose: a preview shows what the block is, not
            // where on a page it might land. What ties it to this site is the compiled
            // stylesheet it links, which is also what its file name is hashed against.
            Composition::style(null, $type),
            (string) $definition['defaults']['layout'],
            [],
            false,
            'section',
            ['forms' => [self::SAMPLE_FORM => self::sampleForm()]],
        );

        return (new View(__DIR__ . '/views'))->render('admin/preview', 'en', [
            'title' => t('block.' . $type),
            'blockHtml' => $html,
        ], null);
    }

    private static function write(string $target, string $contents): void
    {
        $temporary = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temporary, $contents) === false || !rename($temporary, $target)) {
            throw new RuntimeException("Cannot write {$target}");
        }
    }
}
