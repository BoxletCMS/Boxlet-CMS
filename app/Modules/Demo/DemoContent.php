<?php

namespace App\Modules\Demo;

use App\Core\BlockOptions;
use App\Core\Blocks;
use App\Modules\Design\Composition;
use App\Modules\Design\SectionStyle;
use App\Modules\Pages\PageLinks;
use App\Modules\Pages\SectionForm;
use App\Support\RichText;

/**
 * A demo page as Page::update takes it (PLAN.md D-213): its sections and blocks written as the
 * builder writes them, every marker replaced — links by page references, pictures and
 * documents by their ids, forms by theirs — and each style and option stored only where it
 * differs from the demo's character. DemoSite decides what is seeded and in which order.
 */
final class DemoContent
{
    /**
     * A page's blocks and sections as Page::update takes them, its markers replaced: links by
     * page references, pictures and documents by their ids, forms by theirs.
     *
     * @param array{key: string, parent?: string, slug: string, title: string, description: string, menu: string, sections: list<array{style: array<string, string>, layout: string, stack?: string, blocks: list<array{string, array<string, mixed>, string, array<string, string>, int}>}>} $page
     * @param array<string, int> $ids page key => id
     * @param array{pictures: array<string, int>, files: array<string, int>, forms: array<string, int>} $media
     * @return array{list<array{key: string, id: null, type: string, content: array<string, mixed>, style: array<string, int|string|null>, options: array<string, string>, layout: string, section: string, column: int}>, list<array{key: string, id: null, layout: string, stack: string|null, style: array<string, int|string|null>}>}
     */
    public static function content(Blocks $registry, array $page, array $ids, array $media): array
    {
        $reference = static fn (array $match): string => isset($ids[$match[1]]) ? PageLinks::to($ids[$match[1]]) : $match[0];
        $sections = [];
        $blocks = [];
        foreach ($page['sections'] as $at => $section) {
            $key = SectionForm::key(null, $at);
            $sections[] = [
                'key' => $key,
                'id' => null,
                'layout' => $section['layout'],
                'stack' => $section['stack'] ?? null,
                // A picture behind the section is named like a block's (D-213).
                'style' => self::ownStyle(DemoPictures::place($section['style'], $media['pictures']), array_column($section['blocks'], 0)),
            ];
            foreach ($section['blocks'] as [$type, $content, $layout, $options, $column]) {
                $content = DemoPictures::place(self::link($registry->get($type)['fields'], $content, $reference), $media['pictures'], $media['files']);
                if (is_string($content['form'] ?? null) && str_starts_with($content['form'], 'demo:form:')) {
                    $content['form'] = $media['forms'][substr($content['form'], 10)] ?? null;
                }
                $blocks[] = [
                    // Never rendered in an editor: the key only has to exist and differ.
                    'key' => \App\Modules\Pages\BlockForm::key(null, count($blocks)),
                    'id' => null,
                    'type' => $type,
                    'content' => $registry->normalize($type, $content),
                    'style' => SectionStyle::normalize([]),
                    // The page of every block keeps every option it shows, as it keeps every
                    // layout (D-191, the owner): it shows them under every character.
                    'options' => $page['key'] === 'blocks' ? BlockOptions::normalize($registry->get($type)['options'], $options) : self::ownOptions($registry, $type, $options),
                    // '' follows the character (D-176, D-191): stored as '', so it changes with
                    // the character; any other is kept by hand, through Apply too.
                    'layout' => $registry->own($type, $layout),
                    'section' => $key,
                    'column' => $column,
                ];
            }
        }

        return [$blocks, $sections];
    }

    /**
     * A section's style as an owner who means it would leave it (D-165, the owner's review of
     * D-170): the keys the page names, less every one that only says what the demo's character
     * composes anyway. Stored, such a value is "styled by hand" — loading another character
     * asked about thirty sections on a fresh install, and kept them looking like Soft.
     *
     * @param array<string, string> $style
     * @param list<string> $types the section's block types
     * @return array<string, string|int|null>
     */
    private static function ownStyle(array $style, array $types): array
    {
        $own = SectionStyle::normalize($style);
        $composed = Composition::section(DemoSite::CHARACTER, $types);
        foreach ($composed as $name => $value) {
            if (array_key_exists($name, $own) && $own[$name] !== '' && (string) $own[$name] === (string) $value) {
                $own[$name] = '';
            }
        }

        return $own;
    }

    /**
     * A block's options the same way: only those the character would not answer so already,
     * its composition's for the type or else the option's own default.
     *
     * @param array<string, string> $options
     * @return array<string, string>
     */
    private static function ownOptions(Blocks $registry, string $type, array $options): array
    {
        $specs = $registry->get($type)['options'];
        $composed = Composition::options(DemoSite::CHARACTER, $type);
        $own = [];
        foreach (BlockOptions::normalize($specs, $options) as $name => $value) {
            $answer = BlockOptions::clean($specs[$name], $composed[$name] ?? '');
            if ($value !== '' && $value !== ($answer !== '' ? $answer : $specs[$name]['default'])) {
                $own[$name] = $value;
            }
        }

        return $own;
    }

    /**
     * The seed's `demo:{slug}` links turned into page references, in link fields and rich
     * text alike, and inside a repeater's items the same way as at the top: the Columns
     * block's links would otherwise have been stored as `demo:about`, which no link rule
     * accepts, and drawn as nothing.
     *
     * @param array<string, array<string, mixed>> $fields
     * @param array<string, mixed> $content
     * @param callable(array<int|string, string>): string $reference
     * @return array<string, mixed>
     */
    private static function link(array $fields, array $content, callable $reference): array
    {
        foreach ($fields as $name => $field) {
            if ($field['type'] === 'link' && is_array($content[$name] ?? null) && is_string($content[$name]['url'] ?? null)) {
                $content[$name]['url'] = (string) preg_replace_callback('~^demo:([a-z0-9-]*)$~', $reference, $content[$name]['url']);
            }
            if ($field['type'] === 'richtext' && is_string($content[$name] ?? null)) {
                $linked = (string) preg_replace_callback('~(?<=href=")demo:([a-z0-9-]*)(?=")~', $reference, $content[$name]);
                $content[$name] = RichText::sanitize($linked);
            }
            if ($field['type'] === 'repeater' && is_array($content[$name] ?? null)) {
                foreach ($content[$name] as $i => $item) {
                    $content[$name][$i] = is_array($item) ? self::link($field['fields'], $item, $reference) : $item;
                }
            }
        }

        return $content;
    }
}
