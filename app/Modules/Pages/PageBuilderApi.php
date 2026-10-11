<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Design\Composition;
use App\Modules\Media\MediaReference;

/**
 * WHAT THE BUILDER ASKS THE SERVER (PLAN.md D-163, D-175): the document lives in the browser,
 * and three things stay the server's — drawing a section as the page will, drawing the
 * inspector with the admin's controls, and cleaning a block's fields.
 *
 *   POST render  {section, blocks}               → {html}: one band, with the editor's marks
 *   POST inspect {kind, key, section, blocks}     → {html}: the block's or the band's inspector
 *   POST fields  a block's form fields            → {block}: content, options and layout,
 *                                                   cleaned by the form's own parser
 *
 * Every body goes through PageDocument::read(), so nothing the browser holds is drawn or kept
 * without the registry's shapes and the form's checks. None of them writes anything; the
 * autosave (PageDraftController) does.
 *
 * @phpstan-import-type Document from PageDocument
 * @phpstan-import-type DocBlock from PageDocument
 * @phpstan-import-type DocSection from PageDocument
 */
final class PageBuilderApi
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function render(Request $request, string $locale, array $params): Response
    {
        $page = Page::find($this->db(), (int) $params['id']);
        $band = $this->band($request);
        if ($page === null || $band === null) {
            return Response::json(['error' => t('pages.draft.unreadable')], $page === null ? 404 : 422);
        }
        $stale = TranslationStatus::of($this->db(), $this->registry(), (int) $page['id'])['stale'];
        $html = PageRender::draw($this->db(), $this->registry(), $band, (string) $page['locale'], Composition::active($this->db()), (string) $this->container->get('config')->get('app.key'), ['editor' => true, 'stale' => $stale])['html'];

        return Response::json(['html' => $html]);
    }

    /**
     * @param array<string, string> $params
     */
    public function inspect(Request $request, string $locale, array $params): Response
    {
        $page = Page::find($this->db(), (int) $params['id']);
        $band = $this->band($request);
        $kind = $request->body['kind'] ?? null;
        $key = $request->body['key'] ?? null;
        if ($page === null || $band === null || !in_array($kind, ['block', 'section'], true) || !is_string($key)) {
            return Response::json(['error' => t('pages.draft.unreadable')], $page === null ? 404 : 422);
        }
        $section = $band['sections'][0];
        $block = null;
        foreach ($band['blocks'] as $candidate) {
            if ($candidate['key'] === $key) {
                $block = $candidate;
            }
        }
        if ($kind === 'block' && ($block === null || $block['content'] === null)) {
            return Response::json(['error' => t('pages.draft.unreadable')], 422);
        }
        $character = Composition::active($this->db());
        $design = \App\Modules\Design\Design::resolved($this->db());
        $number = $request->body['number'] ?? 1;
        $html = (new View(__DIR__ . '/views'))->render('admin/inspector/' . $kind, $locale, [
            'number' => is_int($number) && $number > 0 ? $number : 1,
            'swatches' => $kind === 'section' ? $this->swatches() : [],
            'gap' => \App\Modules\Design\Tokens::readable($design)['section'],
            'design' => $design,
            'page' => $page,
            'section' => $section,
            'blocks' => $band['blocks'],
            'block' => $block,
            'character' => $character,
            'registry' => $this->registry(),
            'errors' => [],
            'pictures' => MediaReference::choices($this->db()),
            'files' => \App\Modules\Media\MediaFiles::choices($this->db()),
            'formChoices' => \App\Modules\Forms\Form::choices($this->db(), (string) $page['locale']),
            'linkPages' => PageLinks::choices($this->db(), (string) $page['locale']),
            'translation' => $kind === 'block' ? TranslationStatus::described($this->db(), $this->registry(), (int) $page['id']) : null,
        ], null);

        return Response::json(['html' => $html]);
    }

    /**
     * One block's fields, as the inspector's form sends them (`blocks[KEY][…]`), cleaned by the
     * form's own parser (BlockForm::parse): the same checks a form's save has always made, and
     * the errors with them, keyed by field.
     *
     * @param array<string, string> $params
     */
    public function fields(Request $request, string $locale, array $params): Response
    {
        $posted = is_array($request->body['blocks'] ?? null) ? $request->body['blocks'] : [];
        // Or the block as the document holds it (D-178), from typing on the page: put in the
        // form's shape, so the same parser cleans it and names the same errors.
        $sent = $request->body['block'] ?? null;
        if (is_array($sent) && is_string($sent['key'] ?? null) && is_string($sent['type'] ?? null) && $this->registry()->has($sent['type']) && is_array($sent['content'] ?? null)) {
            $raw = ['type' => $sent['type'], 'layout' => is_string($sent['layout'] ?? null) ? $sent['layout'] : '', 'options' => is_array($sent['options'] ?? null) ? $sent['options'] : []];
            foreach ($this->registry()->get($sent['type'])['fields'] as $name => $field) {
                $raw[$name] = BlockValues::asSent($field, $sent['content'][$name] ?? null);
            }
            $posted = [$sent['key'] => $raw];
        }
        $key = array_key_first($posted);
        if (!is_string($key) || count($posted) !== 1) {
            return Response::json(['error' => t('pages.draft.unreadable')], 422);
        }
        // Parsed as a block new to the page: who it is and where it stands are the browser's
        // document's; only what it says, its options and its layout come from here.
        $raw = $posted[$key];
        unset($raw['id']);
        $parsed = BlockForm::parse($this->registry(), [$key => $raw], []);
        $block = $parsed['blocks'][0] ?? null;
        if ($block === null) {
            return Response::json(['error' => t('pages.draft.unreadable')], 422);
        }
        $errors = [];
        foreach ($parsed['errors'] as $at => $message) {
            $errors[substr($at, strlen($key) + 1)] = $message;
        }

        return Response::json(['block' => ['content' => $block['content'], 'options' => $block['options'] ?? [], 'layout' => $block['layout']], 'errors' => $errors]);
    }

    /**
     * The colours each surface is drawn in under the site's design, for the Background tiles:
     * a picture of the surface, in attributes (the admin's CSP refuses a style), never a site
     * token in the admin's stylesheet.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function swatches(): array
    {
        $colors = \App\Modules\Design\Derived::from(\App\Modules\Design\Design::resolved($this->db()))['color'];
        $one = static fn (string $name): array => [(string) ($colors[$name] ?? '#888888'), (string) ($colors[$name] ?? '#888888')];

        return [
            'plain' => $one('background'),
            'tinted' => $one('surface'),
            'contrast' => $one('contrast'),
            'gradient' => [(string) ($colors['gradient-start'] ?? '#888888'), (string) ($colors['gradient-end'] ?? '#444444')],
            // A picture: what the image surface shows until one is chosen, darkening.
            'image' => [(string) ($colors['muted'] ?? '#777777'), (string) ($colors['contrast'] ?? '#333333')],
        ];
    }

    /**
     * One band of a document — its section and the blocks in it — read as any document is.
     *
     * @return array{blocks: list<DocBlock>, sections: list<DocSection>}|null
     */
    private function band(Request $request): ?array
    {
        $section = $request->body['section'] ?? null;
        $blocks = $request->body['blocks'] ?? null;
        if (!is_array($section) || !is_array($blocks)) {
            return null;
        }
        $document = PageDocument::read($this->registry(), ['sections' => [$section], 'blocks' => $blocks]);
        if ($document === null || count($document['sections']) < 1) {
            return null;
        }

        return ['blocks' => $document['blocks'], 'sections' => [$document['sections'][0]]];
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
