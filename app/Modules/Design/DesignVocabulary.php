<?php

namespace App\Modules\Design;

use App\Core\Blocks;
use App\Modules\Design\Vocabulary\Decisions;
use App\Modules\Design\Vocabulary\Descriptions;

/**
 * Every key a design set may hold and every value it may take, read off the code that
 * validates them (PLAN.md D-152) — and the JSON Schema written from that.
 *
 * ONE SOURCE. Vocabulary\Decisions, SectionStyle and the block registry are what DesignSet
 * checks a file against; this reads the same ones, so the
 * schema cannot list a value the validator refuses or miss one it takes. It is what
 * GET /admin/appearance/schema answers, what designs/design-set.schema.json is held equal to
 * (tests/design_set_test.php), and what a model will be told when it is asked for a design.
 *
 * Split from DesignSet: that reads and writes files, this describes them.
 */
final class DesignVocabulary
{
    /** What Color::normalizeHex() takes: #rgb or #rrggbb, either case. */
    private const HEX = '^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$';
    private const HEX_OR_EMPTY = '^(#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}))?$';

    /**
     * @return array{decisions: array<string, array<string, mixed>>, look: array<string, array<string, mixed>>, composition: array<string, mixed>}
     */
    public static function vocabulary(Blocks $registry): array
    {
        // Each part of the file from the one table of decisions (D-164), in its order.
        $rules = static function (string $part): array {
            $out = [];
            foreach (Decisions::ALL as $key => $definition) {
                if ($definition['part'] !== $part) {
                    continue;
                }
                $rule = ['type' => $definition['type']] + array_intersect_key($definition, array_flip(['values', 'min', 'max', 'step', 'unit']));
                if ($key === 'seed') {
                    $rule['required'] = true;
                }
                $out[$key] = $rule;
            }

            return $out;
        };
        $decisions = $rules('decisions');

        $layouts = [];
        $options = [];
        foreach ($registry->types() as $type) {
            $layouts[$type] = array_values($registry->get($type)['layouts']);
            foreach ($registry->get($type)['options'] as $name => $spec) {
                $options[$type][$name] = $spec;
            }
        }
        $surfaces = array_values(array_diff(SectionStyle::OPTIONS['surface'], [SectionStyle::IMAGE]));

        return [
            'decisions' => $decisions,
            'look' => $rules('look'),
            'composition' => [
                'section' => ['surface' => $surfaces] + SectionStyle::OPTIONS + SectionStyle::NUMBERS,
                'surfaces' => $surfaces,
                'dividers' => SectionStyle::OPTIONS['divider'],
                'layouts' => $layouts,
                'options' => $options,
            ],
        ];
    }

    /**
     * The vocabulary as a JSON Schema (draft 2020-12), for editors, people and models. The
     * PHP validator stays the authority: this describes it and never decides.
     *
     * NEVER STRICTER THAN THE VALIDATOR (D-200): a key it does not know, a layout or an option
     * value it leaves out with a warning, a secondary colour of `none`, a pattern's name given
     * as one string, a dark version's empty seed, and more tags, patterns or blocks than are
     * read (the rest left out) are all taken by Boxlet, so none is refused here. Until D-200 the schema refused each, and export() wrote `none` it then refused.
     *
     * @return array<string, mixed>
     */
    public static function schema(Blocks $registry): array
    {
        $vocabulary = self::vocabulary($registry);

        // '' follows (the character, the pairing, the palette) for every key but the seed. Each
        // says what it does and what it is when left out (D-183), from Descriptions and the
        // table of decisions.
        $property = static function (string $key, array $rule): array {
            $neutral = Decisions::ALL[$key]['neutral'];
            $default = $rule['type'] === 'number' && $neutral !== '' ? (float) $neutral + 0 : $neutral;
            $described = Descriptions::KEYS[$key] . ($rule['type'] === 'number' ? ' (' . self::range($rule) . ')' : '');

            return match ($rule['type']) {
                'colour' => ['type' => 'string', 'pattern' => match ($key) {
                    'seed' => self::HEX,
                    // No second colour at all (Decisions::clean), which export() writes.
                    'secondary' => '^(#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})|none)?$',
                    default => self::HEX_OR_EMPTY,
                }],
                'choice' => ['enum' => array_merge([''], $rule['values'])],
                'number' => ['type' => ['string', 'number']],
                default => ['type' => 'string'],
            } + ['description' => $described] + ($key === 'seed' ? [] : ['default' => is_float($default) && floor($default) === $default ? (int) $default : $default]);
        };
        $decisions = [];
        foreach ($vocabulary['decisions'] as $key => $rule) {
            $decisions[$key] = $property($key, $rule);
        }
        $look = [];
        foreach ($vocabulary['look'] as $key => $rule) {
            $look[$key] = $property($key, $rule);
        }
        $section = [];
        foreach ($vocabulary['composition']['section'] as $key => $values) {
            // A stepped number, or for the padding '' — the design's section gap (D-165).
            $section[$key] = isset($values['step'])
                ? ['type' => ['string', 'number'], 'description' => Descriptions::SECTION[$key] . ' (number ' . ($key === 'min_height' ? '%' : 'px') . ', ' . $values['min'] . ' – ' . $values['max'] . ', step ' . $values['step'] . ')']
                : ['enum' => $values, 'description' => Descriptions::SECTION[$key]];
        }
        $layouts = [];
        foreach ($vocabulary['composition']['layouts'] as $type => $values) {
            // A layout a block does not offer is left out with a warning, never refused: the
            // names are offered, any string is read.
            $layouts[$type] = ['anyOf' => [['enum' => $values], ['type' => 'string']]];
        }
        // Each block type's options (D-166): a closed set, or a number on its step.
        $options = [];
        foreach ($vocabulary['composition']['options'] as $type => $specs) {
            $properties = [];
            foreach ($specs as $name => $spec) {
                $properties[$name] = $spec['type'] === 'choice'
                    ? ['anyOf' => [['enum' => $spec['values']], ['type' => 'string']]]
                    : ['type' => ['string', 'number'], 'description' => 'number, ' . $spec['min'] . ' – ' . $spec['max'] . ', step ' . $spec['step']];
            }
            $options[$type] = ['type' => 'object', 'properties' => $properties];
        }
        $localized = static fn (int $length): array => [
            'type' => 'object',
            'minProperties' => 1,
            'propertyNames' => ['pattern' => '^[a-z]{2}(-[A-Z]{2})?$'],
            'additionalProperties' => ['type' => 'string', 'maxLength' => $length],
        ];

        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$id' => 'boxlet-design-set-v' . DesignSet::VERSION,
            'title' => 'Boxlet design set (v' . DesignSet::VERSION . ')',
            'description' => 'A portable Boxlet design: decisions, header and footer look, and optionally a composition, which makes it a character. Generated from the code that validates it; Boxlet\'s own validator decides. Keys left out of decisions take the neutral defaults. A key Boxlet does not know is left out with a warning.',
            'type' => 'object',
            'required' => ['format', 'version', 'id', 'name', 'decisions'],
            'properties' => [
                '$schema' => ['type' => 'string'],
                'format' => ['const' => DesignSet::FORMAT],
                'version' => ['const' => DesignSet::VERSION],
                'id' => ['type' => 'string', 'pattern' => trim(DesignSet::ID_PATTERN, '~')],
                'name' => $localized(80),
                'description' => $localized(300),
                'author' => ['type' => 'string', 'maxLength' => 80],
                'tags' => ['type' => 'array', 'description' => 'The first 12 are read.', 'items' => ['type' => 'string', 'maxLength' => 32]],
                'decisions' => [
                    'type' => 'object',
                    'required' => ['seed'],
                    'properties' => $decisions,
                ],
                'dark' => [
                    'type' => 'object',
                    'description' => 'The set\'s dark version (D-185): in dark mode these stand for the light ones. A colour left out is worked out by the palette; anything else left out is the light version\'s. With it, both versions are checked for contrast.',
                    // An empty seed leaves the dark version's main colour to the light one's.
                    'properties' => ['seed' => ['pattern' => self::HEX_OR_EMPTY] + $decisions['seed']] + array_intersect_key($decisions, array_flip(Decisions::DARK)),
                ],
                'look' => [
                    'type' => 'object',
                    'description' => '\'\' or a choice left out follows the character.',
                    'properties' => $look,
                ],
                'patterns' => [
                    'type' => 'array',
                    'description' => 'Starter sections, the one place a set carries words: per language ({"en": …, "hr": …}, or one string), English the fallback. No pictures, files or forms. The first ' . DesignSetPatterns::MAX . ' are read.',
                    'items' => [
                        'type' => 'object',
                        'required' => ['id', 'name', 'blocks'],
                        'properties' => [
                            'id' => ['type' => 'string', 'pattern' => trim(DesignSet::ID_PATTERN, '~')],
                            'name' => ['anyOf' => [$localized(80), ['type' => 'string', 'maxLength' => 80]]],
                            'section' => ['type' => 'object', 'properties' => ['layout' => ['type' => 'string'], 'style' => ['type' => 'object']]],
                            'blocks' => ['type' => 'array', 'minItems' => 1, 'description' => 'The first ' . DesignSetPatterns::MAX_BLOCKS . ' are read.', 'items' => ['type' => 'object', 'required' => ['type']]],
                        ],
                    ],
                ],
                'composition' => [
                    'type' => 'object',
                    'description' => 'Present: the set can be a character. Block types this site does not have, and layouts a block does not offer, are left out with a warning.',
                    'required' => ['section'],
                    'properties' => [
                        'section' => ['type' => 'object', 'properties' => $section],
                        'surfaces' => ['type' => 'object', 'additionalProperties' => ['enum' => $vocabulary['composition']['surfaces']]],
                        'dividers' => ['type' => 'object', 'additionalProperties' => ['enum' => $vocabulary['composition']['dividers']]],
                        'layouts' => ['type' => 'object', 'properties' => $layouts, 'additionalProperties' => ['type' => 'string']],
                        'options' => ['type' => 'object', 'properties' => (object) $options, 'additionalProperties' => ['type' => 'object']],
                    ],
                ],
            ],
        ];
    }

    /** The schema as the file in designs/ holds it, byte for byte. */
    public static function schemaJson(Blocks $registry): string
    {
        return json_encode(self::schema($registry), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * A number's range in words: "1.1 – 1.6", "integer px, -30 – 40", "rem, 36 – 88, step 2".
     *
     * @param array<string, mixed> $rule
     */
    private static function range(array $rule): string
    {
        return 'number'
            . (isset($rule['unit']) ? ' ' . $rule['unit'] : '')
            . ', ' . $rule['min'] . ' – ' . $rule['max']
            . (isset($rule['step']) ? ', step ' . $rule['step'] : '');
    }
}
