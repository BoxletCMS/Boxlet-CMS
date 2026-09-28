<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Design\Composition;
use App\Modules\Design\SectionStyle;
use App\Modules\Forms\FormBlocks;
use App\Modules\Media\MediaPicture;
use App\Modules\Media\MediaReference;

/**
 * PART OF A PAGE, drawn for the visual editor's canvas from what is currently typed.
 *
 * Split out of PageBuilderController, which had reached the 300-line limit. The seam is a
 * real one rather than a convenience: PageBuilderController renders the EDITOR — its shell,
 * its canvas document, and the re-render after a refused save — while this answers the
 * endpoints that draw a FRAGMENT of the page. It writes nothing, ever.
 *
 * Two of them now. `insert()` draws one block, brand new or as it is being edited. `band()`
 * draws a whole section, because some choices are not a block's to show: changing One column
 * to Two rearranges the markup around every block in the band, and no amount of redrawing
 * one of them can put a column there (D-099).
 */
final class PageBlockController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * ONE BAND, drawn as it stands: its own arrangement and style, and the blocks in it.
     *
     * For the choices a block cannot show. The five style keys are class names on the
     * band's own element and the editor swaps those itself, instantly and without asking
     * anybody; the number of columns is the markup AROUND the blocks, and that only the
     * thing which knows both shapes can draw — SectionRender, here, rather than a second
     * copy of those two shapes written out in JavaScript.
     *
     * Writes nothing, like its neighbour. The band exists only in the page being edited
     * until Save.
     *
     * @param array<string, string> $params
     */
    public function band(Request $request, string $locale, array $params): Response
    {
        $page = Page::find($this->db(), (int) $params['id']);
        if ($page === null) {
            return PagesController::missing();
        }

        $registry = $this->registry();
        $sent = $request->body['section'] ?? null;
        $section = [
            'layout' => SectionLayout::normalize(is_array($sent) ? ($sent['layout'] ?? null) : null),
            'stack' => SectionLayout::normalizeStack(is_array($sent) ? ($sent['stack'] ?? null) : null),
            'style' => SectionStyle::normalize(is_array($sent) ? ($sent['style'] ?? null) : null),
        ];

        /* THE SAME PARSER THE SAVE RUNS, so the canvas shows what would actually be stored
           — including rich text reduced to the whitelist. An empty $stored, because none of
           these blocks is being looked up by id: they are drawn from what was sent. */
        $parsed = BlockForm::parse($registry, $request->body['blocks'] ?? [], []);
        $blocks = [];
        foreach ($parsed['blocks'] as $block) {
            if ($block['content'] === null) {
                continue;
            }
            $blocks[] = [
                // The key goes back on the drawn block (PLAN.md D-117), so the editor pairs
                // the redrawn band with its fields by name rather than by counting.
                'key' => $block['key'],
                'type' => $block['type'],
                'content' => $block['content'],
                'layout' => $block['layout'],
                'column' => $block['column'] ?? 0,
            ];
        }
        // AN EMPTY BAND IS A REAL ANSWER (D-101): it is what "+ Section" asks for, and the
        // editor draws it as the thing about to be filled. A page never shows one.
        $locale = (string) $page['locale'];
        $links = PageLinks::targets($this->db(), $registry, $locale, $blocks);
        foreach ($blocks as $at => $block) {
            $blocks[$at]['content'] = PageLinks::content($registry, $block['type'], $block['content'], $links);
        }

        $body = (new View(__DIR__ . '/views'))->render('admin/band', $locale, [
            // The band's own fields, for a band the editor is adding rather than redrawing.
            // The key is a placeholder the browser renames as it places the group, the way
            // it does for a block (D-098).
            'sectionOf' => [
                'key' => SectionForm::key(null, 0),
                'id' => null,
                'layout' => $section['layout'],
                'stack' => $section['stack'],
                'style' => $section['style'],
            ],
            'composed' => Composition::section(
                Composition::active($this->db()),
                array_values(array_unique(array_map(static fn (array $b): string => (string) $b['type'], $blocks))),
            ),
            'pictures' => MediaReference::choices($this->db()),
            // The files a Downloads block may offer (D-127).
            'files' => \App\Modules\Media\MediaFiles::choices($this->db()),
            'registry' => $registry,
            'bandHtml' => SectionRender::draw(
                $registry,
                $section,
                $blocks,
                MediaPicture::forBlocks($this->db(), $registry, $locale, $blocks),
                false,
                ['forms' => FormBlocks::resolve($this->db(), $blocks, $locale, null, (string) $this->container->get('config')->get('app.key')),
                    // The files a Downloads block names (D-127), as the page gets them.
                    'files' => \App\Modules\Media\MediaFiles::forBlocks($this->db(), $registry, $blocks)],
                $locale,
                // The editor's shape, like the canvas it is going into (D-103).
                true,
            ),
        ], null);

        return Response::admin($body);
    }

    /**
     * One new block, as two fragments: the section for the canvas and the field group for
     * the form. Nothing is written — the block exists only in the page being edited until
     * Save, exactly like a block added in the fallback editor.
     *
     * The response is HTML, not JSON. The server owns what a block is; sending a schema
     * for the browser to render would put a second copy of the block definition in
     * JavaScript, which is the thing this design exists to avoid.
     *
     * @param array<string, string> $params
     */
    public function insert(Request $request, string $locale, array $params): Response
    {
        $page = Page::find($this->db(), (int) $params['id']);
        if ($page === null) {
            return PagesController::missing();
        }

        $registry = $this->registry();
        $type = $request->input('type');
        if (!$registry->has($type)) {
            return new Response(t('pages.insert_unknown'), 422, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $character = Composition::active($this->db());
        $block = [
            // The browser names the group the moment it places it (D-094), because only it
            // knows which keys the page is already using. This is the placeholder until then.
            'key' => BlockForm::key(null, 0),
            'id' => null,
            'type' => $type,
            'content' => $registry->fresh($type),
            'style' => Composition::style($character, $type),
            'layout' => Composition::layout($registry, $character, $type),
        ];

        // With values posted, this is a block being re-drawn as it is edited rather than
        // a new one. The same parser the save runs cleans them, so the canvas shows what
        // would actually be stored — including rich text reduced to the whitelist.
        $submitted = $request->body['block'] ?? null;
        if (is_array($submitted)) {
            $parsed = BlockForm::parse($registry, [['type' => $type] + $submitted], []);
            $first = $parsed['blocks'][0] ?? null;
            // A parsed block keeps a null content when its type is not installed, which
            // this method has already ruled out; building the block explicitly says so
            // rather than carrying the null through.
            if ($first !== null && is_array($first['content'])) {
                $block = [
                    'key' => $first['key'],
                    'id' => null,
                    'type' => $first['type'],
                    'content' => $first['content'],
                    'style' => $first['style'],
                    'layout' => $first['layout'],
                ];
            }
        }

        /* NO BAND STYLE IS READ HERE ANY MORE (D-103). It was, for one day: a redraw drew
         * the block with a composed style and one keystroke took a tinted band to plain on
         * the canvas. Since the editor's canvas always draws columns, what comes back is
         * the block ALONE — the band's classes are on the band, which the redraw never
         * touches — so there is nothing here for a style to change, and carrying one would
         * be an argument that does nothing.
         */
        $body = (new View(__DIR__ . '/views'))->render('admin/insert', $locale, [
            // The browser renumbers every group after inserting, so this index only has
            // to be unique in the returned markup.
            'index' => max(0, (int) $request->input('index')),
            'block' => $block,
            'errors' => [],
            'character' => $character,
            'registry' => $registry,
            'pictures' => MediaReference::choices($this->db()),
            // The files a Downloads block may offer (D-127).
            'files' => \App\Modules\Media\MediaFiles::choices($this->db()),
            // What a form field offers: the forms of the page's own language (D-046).
            'formChoices' => \App\Modules\Forms\Form::choices($this->db(), (string) $page['locale']),
            'linkPages' => PageLinks::choices($this->db(), (string) $page['locale']),
            // The picture this block refers to, so a block re-drawn as it is edited shows
            // the photograph rather than the placeholder it had a moment ago.
            'canvasHtml' => $registry->render(
                $type,
                PageLinks::content($registry, $type, $block['content'], PageLinks::targets($this->db(), $registry, (string) $page['locale'], [$block])),
                $block['style'],
                $block['layout'],
                MediaPicture::forBlocks($this->db(), $registry, $locale, [$block]),
                false,
                /* A BLOCK IS NEVER A BAND HERE (PLAN.md D-099, D-103). The band around it
                   draws the surface, the rhythm and the container, so what comes back is
                   the block's own wrapper and nothing else — exactly what
                   SectionRender::draw() renders inside a column, which is the only shape
                   the editor's canvas has since D-103. A block always lands in a column:
                   the + that adds one is in a column, and the library will not act until
                   one has been pressed. */
                'none',
                // The form this block shows, drawn as a visitor sees it (D-046).
                ['forms' => FormBlocks::resolve($this->db(), [$block], (string) $page['locale'], null, (string) $this->container->get('config')->get('app.key')),
                    // The files a Downloads block names (D-127), as the page gets them.
                    'files' => \App\Modules\Media\MediaFiles::forBlocks($this->db(), $registry, [$block])],
                (string) $page['locale'],
            ),
        ], null);

        return Response::admin($body);
    }

    private function registry(): Blocks
    {
        return $this->container->get('blocks');
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
