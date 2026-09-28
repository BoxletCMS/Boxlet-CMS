<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
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
     * @param bool  $eager whether this is the first section drawn on the page
     * @param array<string, mixed> $resolved what the renderer resolved for these templates
     */
    public static function draw(
        Blocks $registry,
        array $section,
        array $blocks,
        array $media,
        bool $eager,
        array $resolved = [],
        string $locale = '',
        bool $asColumns = false,
    ): string {
        $layout = SectionLayout::normalize($section['layout']);
        $style = SectionStyle::normalize($section['style']);

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

        return '<section class="' . e($classes) . "\">\n"
            . Blocks::sectionPicture($style, $media, $eager)
            . "<div class=\"container\">\n"
            . '<div class="' . e($cols) . "\">\n" . $inner . "</div>\n"
            . "</div>\n</section>\n";
    }

}
