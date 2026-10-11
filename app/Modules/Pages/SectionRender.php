<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Modules\Design\Composition;
use App\Modules\Design\SetMeasure;
use App\Modules\Design\SectionStyle;
use App\Modules\Media\MediaPicture;

/**
 * Drawing one section: the band, its picture, its container, its columns, its blocks
 * (PLAN.md D-093 step 3).
 *
 * Its own file rather than another method on Sections, which is the record: that class
 * answers what a page's sections ARE and this one answers what they look like, and the
 * two have no state in common — this one never touches a database.
 *
 * @phpstan-import-type Picture from MediaPicture
 */
final class SectionRender
{
    /**
     * One section drawn: the band, its picture, its container, its columns, its blocks.
     *
     * TWO SHAPES, AND THE SECOND ONE IS THE NEW ONE. A section of one column holding one
     * block is the same element as that block — `<section class="block block-hero …">`,
     * exactly the markup every page has had since the first commit. Anything else draws a
     * column container inside the section and gives each block a div of its own.
     *
     * The second shape is not introduced everywhere for tidiness, and that is the whole
     * argument: the first shape is what every existing page uses, so keeping it means the
     * owner's site cannot move by a pixel, and it can be PROVEN not to have moved —
     * tools/browser-suite/compare.mjs can only compare elements that both captures have,
     * so a wrapper added around every block on every page is exactly the change it cannot
     * check. The cost is two shapes in one method; it is paid once, here, and no
     * stylesheet has to know which one it is looking at, because every block rule is a
     * descendant selector.
     *
     * @param array{layout: string, stack: string, style: array<string, string|int|null>} $section
     * @param list<array<string, mixed>> $blocks each with type, content, layout and column
     *        in the order they are drawn, already filtered to types this install can render
     * @param array<int, Picture> $media
     * THE EDITOR ASKS FOR THE SECOND SHAPE ALWAYS (PLAN.md D-103). A page keeps both, for
     * the reason above; the editor cannot, because a column is the thing a block is dragged
     * into and a band that draws none has nowhere to drop one. One boolean in one function,
     * so the two shapes go on being described in a single place rather than the editor
     * growing a copy of them.
     *
     * NOTHING A STYLESHEET READS CHANGES between the two: every rule that reads `layout-*`
     * is a descendant selector and `block-{type}` selects only chrome (D-093's measurement),
     * so both classes may sit a level lower and no rule stops matching. What differs is a
     * wrapper element, which is why the page — where it can be PROVEN nothing moved — keeps
     * the shape it has always had.
     *
     * THE STYLE IS COMPOSED HERE, AS IT IS DRAWN (D-165). What a section stores is what its
     * owner set; every key left '' is the character's answer for the blocks it holds, asked
     * now rather than copied in when the section was made — so a section nobody touched
     * follows the character it is drawn under.
     *
     * @param string $character the character the page is drawn under
     * @param bool  $eager whether this is the first section drawn on the page
     * @param array<string, mixed> $resolved what the renderer resolved for these templates
     * @param array<string, string> $design the design's resolved decisions, which say how wide
     *        a column comes out (composed()); none, and no section is widened for its columns
     */
    public static function draw(
        Blocks $registry,
        string $character,
        array $section,
        array $blocks,
        array $media,
        bool $eager,
        array $resolved = [],
        string $locale = '',
        bool $asColumns = false,
        array $design = [],
    ): string {
        $layout = SectionLayout::normalize($section['layout']);
        $style = self::style($character, $section['style'], $blocks, $layout, $design);

        if (!$asColumns && $layout === SectionLayout::ONE && count($blocks) === 1) {
            $block = $blocks[0];

            return $registry->render(
                (string) $block['type'],
                is_array($block['content']) ? $block['content'] : [],
                $style,
                (string) $block['layout'],
                $media,
                $eager,
                'section',
                $resolved,
                $locale,
                [],
                self::options($registry, $character, $block),
            );
        }

        // Every column is drawn, including the empty ones. A layout is a shape before it is
        // a container: three columns with the middle one empty is an arrangement somebody
        // chose, and dropping it would both close the gap they made and leave the editor
        // with nowhere to drop the block they are about to put there.
        $columns = array_fill(0, SectionLayout::columns($layout), '');
        $first = $eager;
        foreach ($blocks as $block) {
            $at = SectionLayout::clamp((int) $block['column'], $layout);
            $drawn = $registry->render(
                (string) $block['type'],
                is_array($block['content']) ? $block['content'] : [],
                $style,
                (string) $block['layout'],
                $media,
                $first,
                'none',
                $resolved,
                $locale,
                [],
                self::options($registry, $character, $block),
            );
            /* THE EDITOR'S NAME FOR THE BLOCK, on the block (PLAN.md D-117). The panel holds
               the same block's fields under the same key, and the key is the only thing the
               two sides agree on once anything moves: the canvas is in the page's order and
               the form is not. Pairing them by counting deleted the wrong block. Only in the
               editor's shape — a visitor's page carries no editor marks. */
            if ($asColumns && is_string($block['key'] ?? null) && $block['key'] !== '') {
                $drawn = (string) preg_replace('~^(\s*<div)\b~', '$1 data-bx-key="' . e($block['key']) . '"', $drawn, 1);
            }
            $columns[$at] .= $drawn;
            $first = false;
        }

        $inner = '';
        foreach ($columns as $html) {
            $inner .= '<div class="section-column">' . "\n" . $html . "</div>\n";
        }

        $classes = implode(' ', array_merge(['block'], SectionStyle::classes($style)));
        $cols = implode(' ', SectionLayout::classes($layout, $section['stack']));

        return '<section class="' . e($classes) . '"' . SectionStyle::attributes($style) . ">\n"
            . Blocks::sectionPicture($style, $media, $eager)
            . "<div class=\"container\">\n"
            . '<div class="' . e($cols) . "\">\n" . $inner . "</div>\n"
            . "</div>\n</section>\n";
    }

    /**
     * A block's options as it is drawn: the owner's over its character's (D-166).
     *
     * @param array<string, mixed> $block
     * @return array<string, string>
     */
    private static function options(Blocks $registry, string $character, array $block): array
    {
        $type = (string) $block['type'];
        $specs = $registry->get($type)['options'];

        return \App\Core\BlockOptions::effective($specs, \App\Core\BlockOptions::normalize($specs, $block['options'] ?? []), Composition::options($character, $type));
    }

    /**
     * A section's style as it is drawn: the owner's values over what the character composes
     * for the block types it holds.
     *
     * @param array<string, string|int|null> $stored
     * @param list<array<string, mixed>> $blocks
     * @param array<string, string> $design resolved decisions (composed())
     * @return array<string, string|int|null>
     */
    public static function style(string $character, array $stored, array $blocks, string $layout = SectionLayout::ONE, array $design = []): array
    {
        return SectionStyle::effective(SectionStyle::normalize($stored), self::composed($character, $blocks, $layout, $design));
    }

    /**
     * What the character composes for a section holding these blocks, and one rule of the
     * blocks' own (the owner, D-177): A SPLIT HERO TAKES THE WIDE MEASURE. Words and a picture
     * side by side in a narrow character's column left the heading six lines tall and the
     * picture a thumbnail (Editorial, measured on the demo). Only where the character would
     * draw it narrower; a section with a width of its own keeps it, since this is what ''
     * comes to.
     *
     * AND A SECTION OF COLUMNS TAKES IT WHERE ITS COLUMNS NEED IT (D-184, the owner): when the
     * design's content width cannot give every column 16rem, as Editorial's 42rem gave a
     * wide-left section's narrow column 13.2rem. The same arithmetic as bin/check-set.php
     * (SetMeasure); sections-columns.css grows the wide measure to what the columns need, and
     * stacks them only where even that cannot be had.
     *
     * @param list<array<string, mixed>> $blocks
     * @param array<string, string> $design the design's resolved decisions; none, and no
     *        section is widened for its columns
     * @return array<string, string>
     */
    public static function composed(string $character, array $blocks, string $layout = SectionLayout::ONE, array $design = []): array
    {
        $composed = Composition::section($character, array_map(static fn (array $block): string => (string) $block['type'], $blocks));
        foreach ($blocks as $block) {
            // With a picture to stand beside; without one a split hero is words alone, drawn as
            // Left is, at the character's own measure (D-182). In the canvas it keeps the place
            // for the picture it has not got, so it is wide there either way.
            $picture = is_array($block['content'] ?? null) && ($block['content']['image'] ?? null) !== null;
            // Following the character, the layout it composes (D-191).
            $layoutOf = (string) ($block['layout'] ?? '') !== '' ? (string) $block['layout'] : (string) (\App\Modules\Design\Characters::composition($character)['layouts'][(string) ($block['type'] ?? '')] ?? '');
            if (($block['type'] ?? '') === 'hero' && $layoutOf === 'split' && ($picture || \App\Support\Editing::on()) && in_array($composed['width'] ?? 'normal', ['narrow', 'normal'], true)) {
                $composed['width'] = 'wide';
            }
        }
        $width = $composed['width'] ?? 'normal';
        $count = SectionLayout::columns($layout);
        if ($design !== [] && $count >= 2 && in_array($width, ['narrow', 'normal'], true)) {
            $narrowest = min(array_map(static fn (int $column): float => (float) SetMeasure::words($design, $width, $layout, $column, 'text', 'single'), range(0, $count - 1)));
            if ($narrowest < SetMeasure::LEAST) {
                $composed['width'] = 'wide';
            }
        }

        return $composed;
    }
}
