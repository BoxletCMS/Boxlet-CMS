<?php

namespace App\Modules\Design;

use App\Core\Blocks;
use App\Modules\Design\Vocabulary\Decisions;

/**
 * A whole design as a portable file: the `boxlet-design-set` format, version 2 (PLAN.md
 * D-152, D-183; SPEC §5.8).
 *
 * ONE VALIDATOR FOR EVERY WAY IN. A file an owner imports, one dropped in designs/custom/
 * over FTP, and — later — one a model writes all arrive here, and nothing reaches a site
 * that has not passed the checks the Appearance screen itself applies: Tokens::validate()
 * for the decisions, contrast included (D-154); ChromeLook's closed sets for the look;
 * SectionStyle's for the composition, and the block registry for layouts. A file can say
 * nothing those could not.
 *
 * NEVER IN A SET: menus, words, CSS, font or picture URLs, free markup. The decisions are
 * closed sets and bounded numbers, the fonts are the font library's families (Fonts), and a composition
 * surface is never `image`: a picture is content, not design.
 *
 * REFUSED OR WARNED. Anything that would change what the design means is refused, with
 * the field and the reason. Anything that only cannot be carried over — a key this Boxlet
 * does not have, a block type this site does not have, a layout a block does not offer —
 * is left out with a warning, because a set may come from a site with more blocks than
 * this one.
 *
 * @phpstan-type SetComposition array{section: array<string, string>, surfaces: array<string, string>, dividers: array<string, string>, layouts: array<string, string>, options: array<string, array<string, string>>}
 * @phpstan-type ParsedSet array{id: string, name: array<string, string>, description: array<string, string>, author: string, tags: list<string>, decisions: array<string, string>, dark: array<string, string>, look: array<string, string>, composition: SetComposition|null, patterns: list<Pattern>}
 * @phpstan-import-type Pattern from DesignSetPatterns
 */
final class DesignSet
{
    public const FORMAT = 'boxlet-design-set';
    /** 2 since the global decisions became numbers (D-164); no other version is read. */
    public const VERSION = 2;
    /** Larger than any honest design by two orders of magnitude. */
    public const MAX_BYTES = 65536;
    /** Deep enough for a pattern's words in a card of a block of a pattern (D-169). */
    public const MAX_DEPTH = 12;
    public const ID_PATTERN = '~^[a-z0-9][a-z0-9-]{0,31}$~';

    /** Every top-level key, in the order export() writes them. */
    private const KEYS = ['$schema', 'format', 'version', 'id', 'name', 'description', 'author', 'tags', 'decisions', 'dark', 'look', 'composition', 'patterns'];


    /**
     * A file's text, checked and cleaned, or the reasons it was refused.
     *
     * The set returned holds every decision, validated and in the order design_tokens
     * stores them; the look with every choice ('' where a design follows its character);
     * and the composition, normalized, or null.
     *
     * @return array{set: ParsedSet|null, errors: list<string>, warnings: list<string>}
     */
    public static function parse(string $json, Blocks $registry): array
    {
        if (strlen($json) > self::MAX_BYTES) {
            return self::refused(t('designset.too_large', ['size' => (string) (self::MAX_BYTES / 1024)]));
        }
        try {
            $raw = json_decode($json, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return self::refused($e->getCode() === JSON_ERROR_DEPTH ? t('designset.too_deep', ['depth' => (string) self::MAX_DEPTH]) : t('designset.not_json'));
        }
        if (!is_array($raw) || ($raw['format'] ?? null) !== self::FORMAT) {
            return self::refused(t('designset.not_a_design'));
        }
        // Only the current version is read: there is no older file to keep reading (D-162).
        if (($raw['version'] ?? null) !== self::VERSION) {
            // As the file spells it: "2" in quotes is text, not the number this reads (D-200).
            return self::refused(t('designset.version', ['version' => is_scalar($raw['version'] ?? null) ? (string) json_encode($raw['version']) : '?']));
        }

        return self::check($raw, $registry);
    }

    /**
     * A set as the file it is written to: keys in their canonical order, the decisions that
     * only repeat the neutral defaults left out so a file says what is particular to it.
     *
     * @param array<string, string> $name locale => name
     * @param array<string, string> $description locale => description
     * @param array<string, string> $decisions validated decisions
     * @param array<string, string> $look choice => value, '' following the character
     * @param array<string, mixed>|null $composition section, surfaces, dividers, layouts
     * @param list<array<string, mixed>> $patterns the set's starter sections (D-169)
     * @param array<string, string> $dark the set's dark version (D-185), none if it has none
     * @param list<string> $tags the set's own words for what it is; until D-200 read and never
     *        written, so a set added as a character lost them
     */
    public static function export(string $id, array $name, array $description, array $decisions, array $look, ?array $composition, string $author = '', array $patterns = [], array $dark = [], array $tags = []): string
    {
        $neutral = Decisions::neutral();
        $kept = [];
        foreach (Decisions::keys('decisions') as $key) {
            $value = (string) ($decisions[$key] ?? '');
            // The seed always: it is the one decision a set must make (the schema requires it),
            // even where it happens to be the neutral one.
            if ($value !== '' && ($value !== $neutral[$key] || $key === 'seed')) {
                $kept[$key] = $value;
            }
        }
        // Every header and footer choice, in the vocabulary's order; '' follows the
        // character, and a character's own look answers every one.
        $ordered = [];
        foreach (Decisions::keys('look') as $choice) {
            $ordered[$choice] = (string) ($look[$choice] ?? '');
        }

        $set = ['format' => self::FORMAT, 'version' => self::VERSION, 'id' => $id, 'name' => $name];
        if ($description !== []) {
            $set['description'] = $description;
        }
        if ($author !== '') {
            $set['author'] = $author;
        }
        if ($tags !== []) {
            $set['tags'] = $tags;
        }
        $set['decisions'] = $kept;
        // The dark version, in the vocabulary's order, only what it answers.
        $darkKept = array_filter(array_replace(array_fill_keys(Decisions::DARK, ''), array_intersect_key($dark, array_flip(Decisions::DARK))), static fn (string $v): bool => $v !== '');
        if ($darkKept !== []) {
            $set['dark'] = $darkKept;
        }
        $set['look'] = $ordered;
        if ($composition !== null) {
            $set['composition'] = [
                // Every key a character composes, in SectionStyle's order (D-165).
                'section' => array_intersect_key(array_replace(SectionStyle::DEFAULTS, $composition['section'] ?? []), SectionStyle::DEFAULTS),
                // Objects even when empty: [] would be read back as a list.
                'surfaces' => (object) ($composition['surfaces'] ?? []),
                'dividers' => (object) ($composition['dividers'] ?? []),
                'layouts' => (object) ($composition['layouts'] ?? []),
                // Each block type's options the character answers (D-166), when it answers any.
                'options' => (object) array_map(static fn (array $options): object => (object) $options, $composition['options'] ?? []),
            ];
        }

        if ($patterns !== []) {
            $set['patterns'] = array_map(static fn (array $pattern): array => [
                'id' => $pattern['id'],
                'name' => $pattern['name'],
                'section' => [
                    'layout' => $pattern['section']['layout'],
                    // Only what the pattern sets: the rest is the character's.
                    'style' => (object) array_filter(
                        $pattern['section']['style'],
                        static fn (mixed $v): bool => $v !== '' && $v !== null,
                    ),
                ],
                'blocks' => array_map(static fn (array $block): array => [
                    'type' => $block['type'],
                    'layout' => $block['layout'],
                    'column' => $block['column'],
                    'options' => (object) $block['options'],
                    'content' => (object) $block['content'],
                ], $pattern['blocks']),
            ], $patterns);
        }

        return json_encode($set, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @param array<mixed> $raw a set of the current version
     * @return array{set: ParsedSet|null, errors: list<string>, warnings: list<string>}
     */
    private static function check(array $raw, Blocks $registry): array
    {
        $errors = [];
        $warnings = [];
        foreach (array_keys($raw) as $key) {
            if (!in_array($key, self::KEYS, true)) {
                $warnings[] = t('designset.unknown_key', ['key' => (string) $key]);
            }
        }

        $id = $raw['id'] ?? null;
        if (!is_string($id) || preg_match(self::ID_PATTERN, $id) !== 1) {
            $errors[] = t('designset.id');
        }
        $name = self::localized($raw['name'] ?? null, 80);
        if ($name === []) {
            $errors[] = t('designset.name');
        }
        $description = self::localized($raw['description'] ?? null, 300);
        $author = is_string($raw['author'] ?? null) ? self::clean($raw['author'], 80) : '';
        $tags = [];
        foreach (is_array($raw['tags'] ?? null) ? array_slice($raw['tags'], 0, 12) : [] as $tag) {
            if (is_string($tag) && ($clean = self::clean($tag, 32)) !== '') {
                $tags[] = $clean;
            }
        }

        $composition = null;
        if (array_key_exists('composition', $raw)) {
            $composition = DesignSetParts::composition($raw['composition'], $registry, $errors, $warnings);
        }
        $patterns = DesignSetPatterns::read($raw['patterns'] ?? null, $registry, $errors, $warnings);
        $dark = DesignSetParts::dark($raw['dark'] ?? null, $errors, $warnings);
        $decisions = DesignSetParts::decisions($raw['decisions'] ?? null, $errors, $warnings, $dark);
        $look = DesignSetParts::look($raw['look'] ?? null, $errors, $warnings);

        if ($errors !== []) {
            return ['set' => null, 'errors' => $errors, 'warnings' => $warnings];
        }

        return [
            'set' => [
                'id' => (string) $id,
                'name' => $name,
                'description' => $description,
                'author' => $author,
                'tags' => $tags,
                'decisions' => $decisions,
                'dark' => $dark,
                'look' => $look,
                'composition' => $composition,
                'patterns' => $patterns,
            ],
            'errors' => [],
            'warnings' => $warnings,
        ];
    }

    /**
     * Locale => text, each text one cleaned line. Anything that is not such a pair is left
     * out rather than refused: a name in one language is enough.
     *
     * @return array<string, string>
     */
    private static function localized(mixed $value, int $length): array
    {
        $out = [];
        foreach (is_array($value) ? $value : [] as $locale => $text) {
            if (is_string($locale) && preg_match('~^[a-z]{2}(-[A-Z]{2})?$~', $locale) === 1 && is_string($text)) {
                $clean = self::clean($text, $length);
                if ($clean !== '') {
                    $out[$locale] = $clean;
                }
            }
        }

        return $out;
    }

    /** Trimmed, without control characters, at most $length characters. */
    public static function clean(string $text, int $length): string
    {
        $text = (string) preg_replace('~[\x00-\x1F\x7F]+~u', ' ', $text);

        return trim(mb_substr(trim((string) preg_replace('~\s+~u', ' ', $text)), 0, $length));
    }

    /**
     * @return array{set: null, errors: list<string>, warnings: list<string>}
     */
    private static function refused(string $reason): array
    {
        return ['set' => null, 'errors' => [$reason], 'warnings' => []];
    }

    /** Whether a decision may arrive as a JSON number. */
    public static function numeric(string $key): bool
    {
        return (Decisions::ALL[$key]['type'] ?? '') === 'number';
    }
}
