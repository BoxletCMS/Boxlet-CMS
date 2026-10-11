<?php

namespace App\Modules\Design;

use App\Core\BlockOptions;
use App\Core\Blocks;
use App\Modules\Pages\SectionLayout;
use App\Support\RichText;
use App\Support\SafeUrl;

/**
 * A design set's PATTERNS (README 1.5, PLAN.md D-169): starter sections a set offers — a hero
 * with a button, three cards, text beside a quotation — each a section's style and layout and
 * its blocks with placeholder words.
 *
 * THE ONE PLACE A SET CARRIES TEXT, and the exception is deliberate: a pattern is inserted
 * into a page and its words become the owner's content from that moment, to be typed over.
 * They are per language — `{"en": "…", "hr": "…"}`, or one string for every language — and a
 * page in a language the set has no words for takes the English (D-163 point 4).
 *
 * A set carries no pictures, files or forms (DesignSet: no media ids anywhere), so a field of
 * those kinds is left out with a warning. A block type this site does not have leaves its
 * pattern out with a warning, as a layout it does not offer does in a composition.
 *
 * @phpstan-type Localized array<string, string>
 * @phpstan-type Pattern array{id: string, name: Localized, section: array{layout: string, style: array<string, string|int|null>}, blocks: list<array{type: string, layout: string, column: int, options: array<string, string>, content: array<string, mixed>}>}
 */
final class DesignSetPatterns
{
    public const MAX = 12;
    public const MAX_BLOCKS = 4;
    private const TEXT = 500;
    private const RICH = 4000;

    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     * @return list<Pattern>
     */
    public static function read(mixed $raw, Blocks $registry, array &$errors, array &$warnings): array
    {
        if ($raw === null) {
            return [];
        }
        if (!is_array($raw) || !array_is_list($raw)) {
            $errors[] = 'patterns: ' . t('designset.patterns_list');

            return [];
        }
        $patterns = [];
        $ids = [];
        foreach (array_slice($raw, 0, self::MAX) as $at => $pattern) {
            $field = 'patterns.' . $at;
            $id = is_array($pattern) ? ($pattern['id'] ?? null) : null;
            if (!is_string($id) || preg_match(DesignSet::ID_PATTERN, $id) !== 1 || isset($ids[$id])) {
                $errors[] = $field . '.id: ' . t('designset.pattern_id');
                continue;
            }
            $ids[$id] = true;
            $name = self::localized($pattern['name'] ?? null, 80);
            if ($name === []) {
                $errors[] = $field . '.name: ' . t('designset.name');
                continue;
            }
            $section = is_array($pattern['section'] ?? null) ? $pattern['section'] : [];
            $layout = SectionLayout::normalize($section['layout'] ?? null);
            $style = SectionStyle::normalize($section['style'] ?? []);
            // A pattern names its section; a picture it cannot have.
            $style[SectionStyle::IMAGE] = null;

            $blocks = [];
            foreach (is_array($pattern['blocks'] ?? null) ? array_slice($pattern['blocks'], 0, self::MAX_BLOCKS) : [] as $b => $block) {
                $type = is_array($block) && is_string($block['type'] ?? null) ? $block['type'] : '';
                if (!$registry->has($type)) {
                    $warnings[] = t('designset.block_unknown', ['field' => $field . '.blocks.' . $b]);
                    continue 2;
                }
                $definition = $registry->get($type);
                $blockLayout = $registry->layout($type, $block['layout'] ?? null);
                $options = array_filter(BlockOptions::normalize($definition['options'], $block['options'] ?? []), static fn (string $v): bool => $v !== '');
                // An option its layout does nothing with is kept, and said (D-187).
                foreach (array_keys($options) as $option) {
                    if (!BlockOptions::applies($definition['options'][$option], $blockLayout)) {
                        $warnings[] = t('designset.option_idle', ['field' => $field . '.blocks.' . $b . '.options.' . $option, 'layout' => $blockLayout]);
                    }
                }
                $blocks[] = [
                    'type' => $type,
                    'layout' => $blockLayout,
                    'column' => SectionLayout::clamp(is_int($block['column'] ?? null) ? $block['column'] : 0, $layout),
                    'options' => $options,
                    'content' => self::content($definition['fields'], is_array($block['content'] ?? null) ? $block['content'] : [], $field . '.blocks.' . $b, $warnings),
                ];
            }
            if ($blocks === []) {
                $errors[] = $field . '.blocks: ' . t('designset.pattern_blocks');
                continue;
            }
            $patterns[] = ['id' => $id, 'name' => $name, 'section' => ['layout' => $layout, 'style' => $style], 'blocks' => $blocks];
        }

        return $patterns;
    }

    /**
     * A pattern's words in one language, ready to be a block's content: the language's own,
     * else the English, else the first there is.
     *
     * @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    public static function in(array $content, string $locale): array
    {
        $out = [];
        foreach ($content as $name => $value) {
            if (self::isLocalized($value)) {
                $out[$name] = $value[$locale] ?? $value['en'] ?? (string) reset($value);
            } elseif (is_array($value)) {
                $out[$name] = array_is_list($value)
                    ? array_map(static fn (mixed $item): mixed => is_array($item) ? self::in($item, $locale) : $item, $value)
                    : self::in($value, $locale);
            } else {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    /**
     * A block's content as a pattern may carry it: words per language, a link's address, a
     * choice from a field's own set, and a repeater's items the same way.
     *
     * @param array<string, array<string, mixed>> $fields
     * @param array<mixed> $raw
     * @param list<string> $warnings
     * @return array<string, mixed>
     */
    private static function content(array $fields, array $raw, string $at, array &$warnings): array
    {
        $out = [];
        foreach ($raw as $name => $value) {
            $name = (string) $name;
            $field = $fields[$name] ?? null;
            if ($field === null || in_array($field['type'], ['media', 'file', 'form'], true)) {
                $warnings[] = t('designset.pattern_field', ['field' => $at . '.content.' . $name]);
                continue;
            }
            $clean = match ($field['type']) {
                'text', 'textarea' => self::localized($value, self::TEXT),
                'richtext' => self::rich($value, $field['allow'] ?? RichText::FEATURES),
                'link' => self::link($value),
                'select' => is_string($value) && in_array($value, $field['options'], true) ? $value : null,
                'repeater' => array_values(array_map(
                    static fn (mixed $item): array => self::content($field['fields'], is_array($item) ? $item : [], $at . '.content.' . $name, $warnings),
                    is_array($value) ? array_slice($value, 0, $field['max']) : [],
                )),
                default => null,
            };
            if ($clean === null || $clean === []) {
                continue;
            }
            $out[$name] = $clean;
        }

        return $out;
    }

    /**
     * Words per language: an object of locale => text, or one string for every language,
     * which is kept as English.
     *
     * @return Localized
     */
    private static function localized(mixed $value, int $length): array
    {
        if (is_string($value)) {
            $value = ['en' => $value];
        }
        $out = [];
        foreach (is_array($value) ? $value : [] as $locale => $text) {
            if (is_string($locale) && preg_match('~^[a-z]{2}(-[A-Z]{2})?$~', $locale) === 1 && is_string($text)) {
                $clean = trim(mb_substr(str_replace("\r\n", "\n", $text), 0, $length));
                if ($clean !== '') {
                    $out[$locale] = $clean;
                }
            }
        }

        return $out;
    }

    /**
     * Rich text per language, through the sanitiser and the field's own list (D-166).
     *
     * @param list<string> $allow
     * @return Localized
     */
    private static function rich(mixed $value, array $allow): array
    {
        $out = [];
        foreach (self::localized($value, self::RICH) as $locale => $html) {
            $clean = RichText::sanitize($html, RichText::allowedFor($allow));
            if (trim(strip_tags($clean)) !== '') {
                $out[$locale] = $clean;
            }
        }

        return $out;
    }

    /**
     * A link: its words per language, and an address any link field would accept.
     *
     * @return array{label: Localized, url: string}|null
     */
    private static function link(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        $url = SafeUrl::normalize(is_string($value['url'] ?? null) ? $value['url'] : '');
        $label = self::localized($value['label'] ?? null, 120);
        if ($label === [] || ($url !== '' && !SafeUrl::isAllowed($url))) {
            return null;
        }

        return ['label' => $label, 'url' => $url];
    }

    /** Whether a value is words per language, rather than a link or a list of items. */
    private static function isLocalized(mixed $value): bool
    {
        if (!is_array($value) || $value === [] || array_is_list($value)) {
            return false;
        }
        foreach ($value as $locale => $text) {
            if (!is_string($locale) || preg_match('~^[a-z]{2}(-[A-Z]{2})?$~', $locale) !== 1 || !is_string($text)) {
                return false;
            }
        }

        return true;
    }
}
