<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Forms\FormBlocks;
use App\Modules\Media\MediaFiles;
use App\Modules\Media\MediaPicture;

/**
 * A page's sections, drawn from its DOCUMENT (PLAN.md D-168, D-173): what a visitor's page shows
 * between its header and its footer, what the Appearance screen previews, and what the
 * builder's canvas shows.
 *
 * THREE CALLERS, ONE DRAWING. The visitor's page and the Appearance preview were one drawing
 * since D-168 (PageBody); the canvas ran the same loop by hand, with the editor's marks added.
 * They draw a document now — the stored page, or the draft when one exists — so a draft is
 * drawn by the very code that will draw it once it is published.
 *
 * Every picture, link, form and file the page refers to is resolved up front, one query each,
 * so a template is handed what it needs and never queries.
 *
 * @phpstan-import-type Document from PageDocument
 */
final class PageRender
{
    /**
     * @param array{blocks: list<array{type: string, content: array<string, mixed>|null, layout: string, section: string, id?: int|null, column?: int, options?: array<string, string>}>, sections: list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>} $document
     *        the document's blocks and sections; nothing else of it is read
     * @param array{pageId?: int|null, sent?: int|null, layouts?: array<string, string>, editor?: bool, stale?: array<int, mixed>, design?: array<string, string>} $how
     *        pageId and sent: the form a visitor just sent (D-046); layouts: block type => the
     *        layout a block following the character is drawn in, for a preview of a
     *        character not yet applied; editor: the canvas's drawing (below); stale: the
     *        translation's blocks that have fallen behind their source, by id
     * @return array{html: string, firstSurface: string} the sections, and what the first one
     *         stands on — the ink on a header laid over it is that section's (D-112)
     */
    public static function draw(Db $db, Blocks $registry, array $document, string $locale, string $character, string $appKey, array $how = []): array
    {
        $editor = ($how['editor'] ?? false) === true;
        // How wide a column comes out, for a section of columns (SectionRender::composed): the
        // design being tried in a preview, else the site's.
        $design = $how['design'] ?? \App\Modules\Design\Design::resolved($db);
        $layouts = $how['layouts'] ?? [];
        $blocks = [];
        foreach ($document['blocks'] as $block) {
            // A block that follows the character ('') is drawn in the layout its composition
            // names — or, in a preview of a character not yet applied, that character's. The
            // owner's own layout is drawn as it is (D-191).
            if ((string) $block['layout'] === '' && $registry->has((string) $block['type'])) {
                $block['layout'] = $layouts[$block['type']] ?? \App\Modules\Design\Composition::layout($registry, $character, (string) $block['type']);
            }
            $blocks[] = $block;
        }
        // THE REPLACEMENT TAGS FIRST (SPEC §5.6, D-201): {{year}}, {{lang:switcher}} and
        // {{snippet:name}} drawn in every rich text field, before the links below are followed,
        // so a link inside a snippet's words is followed like the page's own. In the editor
        // each is a chip the editor keeps as its tag.
        $tags = \App\Modules\Snippets\Tags::context($db, $locale, isset($how['pageId']) ? (int) $how['pageId'] : null, $editor);
        foreach ($blocks as $i => $block) {
            if ($block['content'] !== null && $registry->has((string) $block['type'])) {
                $blocks[$i]['content'] = \App\Modules\Snippets\Tags::content($registry, (string) $block['type'], $block['content'], $tags);
            }
        }
        // Only what can be drawn is resolved: a block of a type this install no longer has
        // keeps its stored row, with no content to read.
        $drawable = array_values(array_filter($blocks, static fn (array $block): bool => $block['content'] !== null && $registry->has((string) $block['type'])));
        $media = MediaPicture::forBlocks($db, $registry, $locale, $drawable);
        // Links to pages, followed in one query in the page's language (PLAN.md D-034), so a
        // link to a draft is missing in the editor exactly as it will be on the site.
        $links = PageLinks::targets($db, $registry, $locale, $drawable);
        // Forms, with what the visitor just did to one (D-046); the canvas's CSP stops a send.
        $forms = FormBlocks::resolve($db, $drawable, $locale, $how['pageId'] ?? null, $appKey, $how['sent'] ?? null);
        // The files the page's Downloads blocks offer (D-127).
        $files = MediaFiles::forBlocks($db, $registry, $drawable);
        $stale = $how['stale'] ?? [];

        $html = '';
        $first = true;
        $firstSurface = '';
        foreach (Sections::group(self::byKey($document['sections']), $blocks, $editor) as $group) {
            $shown = [];
            $isStale = false;
            foreach ($group['blocks'] as $block) {
                if ($block['content'] === null || !$registry->has((string) $block['type'])) {
                    continue;
                }
                $block['content'] = PageLinks::content($registry, (string) $block['type'], $block['content'], $links);
                $shown[] = $block;
                $isStale = $isStale || (is_int($block['id'] ?? null) && isset($stale[$block['id']]));
            }
            // A band whose every block is of a type this install no longer has is not drawn —
            // the same thing prune() refuses to leave behind on save. In the editor an EMPTY
            // band is: it is the band just added and about to be filled (Sections::group).
            if ($shown === [] && ($group['blocks'] !== [] || !$editor)) {
                continue;
            }
            // In the editor ALWAYS AS COLUMNS (D-103): a column is what a block is dragged
            // into and what the + in it adds to, and a band that draws none has neither.
            // Only the first section drawn is eager: its picture is the one a visitor waits for.
            // The canvas's drawing carries the editor's marks on each field (D-178); no other does.
            $drawn = \App\Support\Editing::during($editor, static fn (): string => SectionRender::draw($registry, $character, $group['section'], $shown, $media, $first, ['forms' => $forms, 'files' => $files], $locale, $editor, $design));
            if ($editor) {
                $drawn = self::marked($drawn, (string) $group['id'], $isStale);
            } elseif ($first) {
                // The picture a visitor waits for (Lighthouse's LCP) asked for first (D-205):
                // the first one the first section draws, eager as every picture there is, and
                // only that one — high priority given to three cards is given to none.
                $drawn = (string) preg_replace('~<img\b(?![^>]*\sloading=)~', '<img fetchpriority="high"', $drawn, 1);
            }
            $html .= $drawn;
            if ($first) {
                // As it is drawn: a surface left to the character is the character's (D-165).
                $firstSurface = (string) (SectionRender::style($character, $group['section']['style'], $shown, SectionLayout::normalize($group['section']['layout']), $design)['surface'] ?? '');
            }
            $first = false;
        }

        return ['html' => $html, 'firstSurface' => $firstSurface];
    }

    /**
     * The sections a drawing joins its blocks to, by KEY (D-098, Sections::group): a draft's
     * section added in this session has no id yet, and the stored page's are keyed `s{id}`.
     *
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}> $sections
     * @return array<string, array{layout: string, stack: string, style: array<string, string|int|null>}>
     */
    private static function byKey(array $sections): array
    {
        $byKey = [];
        foreach ($sections as $section) {
            $byKey[$section['key']] = [
                'layout' => SectionLayout::normalize($section['layout']),
                'stack' => SectionLayout::normalizeStack($section['stack']),
                'style' => \App\Modules\Design\SectionStyle::normalize($section['style']),
            ];
        }

        return $byKey;
    }

    /**
     * The editor's marks on a drawn band, and nothing else added to the visitor's markup.
     *
     * THE BAND SAYS WHICH BAND IT IS (D-099): without a name on it, the + in an empty column
     * has no way to say which column of which section it aims at, and the editor would be
     * back to counting positions — what D-094 and D-098 took out of every other part of it.
     * A TRANSLATION'S BAND THAT HAS FALLEN BEHIND its source is marked on the band, not on a
     * block inside it (D-043 step 3): with several blocks, picking one of several
     * identical-looking wrappers out of rendered markup by position is a guess.
     */
    private static function marked(string $drawn, string $key, bool $stale): string
    {
        $drawn = (string) preg_replace('~^(\s*<section)\b~', '$1 data-bx-section="' . e($key) . '"', $drawn, 1);

        return $stale ? (string) preg_replace('~^(\s*<section)\b~', '$1 data-bx-stale', $drawn, 1) : $drawn;
    }
}
