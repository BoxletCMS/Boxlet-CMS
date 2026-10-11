<?php

namespace App\Modules\Design;

use App\Core\BlockOptions;
use App\Core\Blocks;
use App\Modules\Design\Vocabulary\Decisions;

/**
 * The three bodies of a design set — decisions, look, composition — each checked by the
 * code that owns its values (PLAN.md D-152).
 *
 * Split from DesignSet, which reads the envelope (format, version, id, names) and writes
 * files. The seam is the one the format itself has: the envelope is this file format's own,
 * while each body is a thing Boxlet already validates elsewhere, and this only hands it to
 * that code — Tokens, ChromeLook, SectionStyle and the block registry — and reports what it
 * said, field by field.
 *
 * Errors and warnings are appended to the caller's lists, each naming its field as the
 * file spells it (`decisions.seed`, `look.nav_style`, `composition.layouts.hero`).
 */
final class DesignSetParts
{
    /**
     * Every decision, validated, in the order design_tokens stores them. Keys a set leaves
     * out take the neutral defaults.
     *
     * A KEY THIS BOXLET DOES NOT KNOW IS LEFT OUT WITH A WARNING (D-183). It was refused, as a
     * decision whose meaning could be got wrong. Since the format froze, v2 grows only by
     * optional keys with a default, so an unknown key is one a later Boxlet added, and leaving
     * it out is that default: refusing it would make every set written later unreadable here.
     *
     * WITH A DARK VERSION BOTH ARE CHECKED (D-185, the owner): the decisions as the light one,
     * and the dark one as a site in dark mode draws it, every pair of each at 4.5:1, a
     * failure of the dark one named `dark.<key>`.
     *
     * @param list<string> $errors
     * @param list<string> $warnings
     * @param array<string, string> $dark the set's dark version, read by dark()
     * @return array<string, string>
     */
    public static function decisions(mixed $raw, array &$errors, array &$warnings = [], array $dark = []): array
    {
        if (!is_array($raw)) {
            $errors[] = self::field('decisions', t('designset.missing'));

            return [];
        }
        $known = Decisions::keys('decisions');
        $given = [];
        foreach ($raw as $key => $value) {
            $key = (string) $key;
            if (!in_array($key, $known, true)) {
                $warnings[] = t('designset.unknown_key', ['key' => 'decisions.' . $key]);
                continue;
            }
            if (is_string($value)) {
                $given[$key] = $value;
            } elseif ((is_int($value) || is_float($value)) && DesignSet::numeric($key)) {
                $given[$key] = (string) $value;
            } else {
                $errors[] = self::field('decisions.' . $key, t('design.error.choice'));
            }
        }
        if (!isset($given['seed'])) {
            $errors[] = self::field('decisions.seed', t('designset.missing'));

            return [];
        }

        // Contrast failures arrive here like any other error, and refuse the set (D-154). A
        // key the set leaves out means what the vocabulary gives it, not the default
        // character's: a set is drawn from itself.
        $light = $dark !== [] ? ['mode' => 'light'] + $given : $given;
        $result = Tokens::validate($light + Decisions::neutral());
        foreach ($result['errors'] as $key => $message) {
            $errors[] = self::field('decisions.' . $key, $message);
        }
        if ($dark !== []) {
            foreach (Tokens::validate(Tokens::inDark($given + Decisions::neutral(), $dark))['errors'] as $key => $message) {
                $errors[] = self::field('dark.' . $key, $message);
            }
        }

        return array_intersect_key($result['decisions'], array_flip($known));
    }

    /**
     * A set's dark version (D-185): only the keys of Decisions::DARK, each checked as the
     * decision it stands for; a key it does not know is left out with a warning, as anywhere.
     *
     * @param list<string> $errors
     * @param list<string> $warnings
     * @return array<string, string>
     */
    public static function dark(mixed $raw, array &$errors, array &$warnings): array
    {
        if ($raw === null) {
            return [];
        }
        if (!is_array($raw)) {
            $errors[] = self::field('dark', t('designset.missing'));

            return [];
        }
        $out = [];
        foreach ($raw as $key => $value) {
            $key = (string) $key;
            if (!in_array($key, Decisions::DARK, true)) {
                $warnings[] = t('designset.unknown_key', ['key' => 'dark.' . $key]);
                continue;
            }
            $clean = Decisions::clean($key, is_int($value) || is_float($value) ? (string) $value : $value);
            if ($clean === null) {
                $errors[] = self::field('dark.' . $key, Decisions::ALL[$key]['type'] === 'colour' ? t('design.error.color') : t('design.error.choice'));
                continue;
            }
            if ($clean !== '') {
                $out[$key] = $clean;
            }
        }

        // In the vocabulary's order, as export writes it.
        $ordered = [];
        foreach (Decisions::DARK as $key) {
            if (isset($out[$key])) {
                $ordered[$key] = $out[$key];
            }
        }

        return $ordered;
    }

    /**
     * Every header and footer choice, each from its closed set or within its range. '' — or
     * a choice left out — follows: for a design, the character; for a character, what the
     * vocabulary gives (D-164). A choice this Boxlet does not know is left out with a
     * warning (D-183).
     *
     * @param list<string> $errors
     * @param list<string> $warnings
     * @return array<string, string>
     */
    public static function look(mixed $raw, array &$errors, array &$warnings = []): array
    {
        $look = array_fill_keys(Decisions::keys('look'), '');
        $raw = $raw ?? [];
        if (!is_array($raw)) {
            $errors[] = self::field('look', t('designset.missing'));

            return $look;
        }
        foreach ($raw as $key => $value) {
            $key = (string) $key;
            if (!array_key_exists($key, $look)) {
                // As a decision (D-183): a later v2's choice, left out with a warning.
                $warnings[] = t('designset.unknown_key', ['key' => 'look.' . $key]);
                continue;
            }
            $clean = Decisions::clean($key, is_int($value) || is_float($value) ? (string) $value : $value);
            if ($clean === null) {
                $errors[] = self::field('look.' . $key, t('design.error.choice'));
                continue;
            }
            $look[$key] = $clean;
        }

        return $look;
    }

    /**
     * Layers 2 and 3: the section style every block starts from, and what a block type
     * does differently. A block type this site does not have is left out with a warning,
     * and so is a layout a block does not offer — the set still means the same everywhere
     * else. A surface is never `image`: that would be a picture, and pictures are content.
     *
     * @param list<string> $errors
     * @param list<string> $warnings
     * @return array{section: array<string, string>, surfaces: array<string, string>, dividers: array<string, string>, layouts: array<string, string>, options: array<string, array<string, string>>}|null
     */
    public static function composition(mixed $raw, Blocks $registry, array &$errors, array &$warnings): ?array
    {
        if (!is_array($raw) || !is_array($raw['section'] ?? null)) {
            $errors[] = self::field('composition.section', t('designset.missing'));

            return null;
        }
        foreach (array_keys($raw) as $key) {
            if (!in_array($key, ['section', 'surfaces', 'dividers', 'layouts', 'options'], true)) {
                $warnings[] = t('designset.unknown_key', ['key' => 'composition.' . $key]);
            }
        }

        $surfaces = array_values(array_diff(SectionStyle::OPTIONS['surface'], [SectionStyle::IMAGE]));
        $style = SectionStyle::DEFAULTS;
        foreach ($raw['section'] as $key => $value) {
            $key = (string) $key;
            $value = is_int($value) || is_float($value) ? (string) $value : $value;
            if (!array_key_exists($key, SectionStyle::DEFAULTS)) {
                $warnings[] = t('designset.unknown_key', ['key' => 'composition.section.' . $key]);
            } elseif ($value === '' && in_array($key, ['pad_top', 'pad_bottom'], true)) {
                // The padding may be left to the design's section gap (D-165).
                $style[$key] = '';
            } elseif (!is_string($value) || SectionStyle::clean($key, $value) === '' || ($key === 'surface' && !in_array($value, $surfaces, true))) {
                $errors[] = self::field('composition.section.' . $key, isset(SectionStyle::NUMBERS[$key])
                    ? t('design.error.range', SectionStyle::NUMBERS[$key])
                    : t('design.error.choice'));
            } else {
                $style[$key] = SectionStyle::clean($key, $value);
            }
        }

        // Every composed key in SectionStyle's order; a key left out is its default. Never the
        // picture, the name, the anchor or the visibility: a character has no opinion on them.
        $out = [
            'section' => $style,
            'surfaces' => self::byType($raw['surfaces'] ?? [], 'surfaces', $surfaces, $registry, $errors, $warnings),
            'dividers' => self::byType($raw['dividers'] ?? [], 'dividers', SectionStyle::OPTIONS['divider'], $registry, $errors, $warnings),
            'layouts' => [],
        ];
        foreach (is_array($raw['layouts'] ?? null) ? $raw['layouts'] : [] as $type => $layout) {
            $type = (string) $type;
            if (!$registry->has($type)) {
                $warnings[] = t('designset.block_unknown', ['field' => 'composition.layouts.' . $type]);
            } elseif (!is_string($layout) || !in_array($layout, $registry->get($type)['layouts'], true)) {
                $warnings[] = t('designset.layout_unknown', ['field' => 'composition.layouts.' . $type]);
            } else {
                $out['layouts'][$type] = $layout;
            }
        }
        $out['options'] = self::options($raw['options'] ?? [], $out['layouts'], $registry, $warnings);

        return $out;
    }

    /**
     * What a character answers for each block type's options (D-166): type => option =>
     * value. Like a layout, an option this site's block does not have, or a value it cannot
     * hold, is left out with a warning rather than refusing the set: it is a set made for a
     * site with other blocks, not a broken one.
     *
     * One that does nothing in the layout the set gives that block type — its own, else the
     * block's default — is kept, for a page that chooses another, and said (D-187).
     *
     * @param array<string, string> $layouts the set's layout for each block type
     * @param list<string> $warnings
     * @return array<string, array<string, string>>
     */
    private static function options(mixed $raw, array $layouts, Blocks $registry, array &$warnings): array
    {
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $type => $options) {
            $type = (string) $type;
            if (!$registry->has($type)) {
                $warnings[] = t('designset.block_unknown', ['field' => 'composition.options.' . $type]);
                continue;
            }
            $specs = $registry->get($type)['options'];
            foreach (is_array($options) ? $options : [] as $name => $value) {
                $name = (string) $name;
                $clean = isset($specs[$name]) ? BlockOptions::clean($specs[$name], is_int($value) ? (string) $value : $value) : '';
                if ($clean === '') {
                    $warnings[] = t('designset.option_unknown', ['field' => 'composition.options.' . $type . '.' . $name]);
                    continue;
                }
                $out[$type][$name] = $clean;
                $layout = $layouts[$type] ?? $registry->get($type)['defaults']['layout'];
                if (!BlockOptions::applies($specs[$name], $layout)) {
                    $warnings[] = t('designset.option_idle', ['field' => 'composition.options.' . $type . '.' . $name, 'layout' => $layout]);
                }
            }
        }

        return $out;
    }

    /**
     * A block type => value map, each value from its closed set.
     *
     * @param list<string> $allowed
     * @param list<string> $errors
     * @param list<string> $warnings
     * @return array<string, string>
     */
    private static function byType(mixed $raw, string $name, array $allowed, Blocks $registry, array &$errors, array &$warnings): array
    {
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $type => $value) {
            $type = (string) $type;
            if (!$registry->has($type)) {
                $warnings[] = t('designset.block_unknown', ['field' => 'composition.' . $name . '.' . $type]);
            } elseif (!is_string($value) || !in_array($value, $allowed, true)) {
                $errors[] = self::field('composition.' . $name . '.' . $type, t('design.error.choice'));
            } else {
                $out[$type] = $value;
            }
        }

        return $out;
    }

    private static function field(string $field, string $reason): string
    {
        return t('designset.field', ['field' => $field, 'reason' => $reason]);
    }
}
