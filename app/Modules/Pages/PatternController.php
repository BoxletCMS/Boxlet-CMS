<?php

namespace App\Modules\Pages;

use App\Core\Blocks;
use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Design\Composition;

/**
 * PATTERNS FOR THE BUILDER (PLAN.md D-173, D-175): the one to insert, in the page's language,
 * and a band of the document kept as one. The plain editor does the same through its form
 * (PageEditorController); the builder's document is in the browser, so it asks here.
 *
 *   GET  /admin/patterns?ref=user:3&page=7  → {pattern: {section, blocks}}
 *   POST /admin/patterns {name, section, blocks} → {id, name}
 */
final class PatternController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, string $locale, array $params): Response
    {
        $page = Page::find($this->db(), (int) ($request->query['page'] ?? 0));
        $reference = is_string($request->query['ref'] ?? null) ? $request->query['ref'] : '';
        $pattern = $page === null ? null : PagePattern::find($this->db(), $this->registry(), $reference, Composition::active($this->db()), (string) $page['locale']);
        if ($pattern === null) {
            return Response::json(['error' => t('patterns.gone')], 404);
        }

        return Response::json(['pattern' => $pattern]);
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, string $locale, array $params): Response
    {
        $name = is_string($request->body['name'] ?? null) ? trim($request->body['name']) : '';
        $section = $request->body['section'] ?? null;
        $document = is_array($section) ? PageDocument::read($this->registry(), ['sections' => [$section], 'blocks' => $request->body['blocks'] ?? []]) : null;
        $pattern = $document === null || $document['sections'] === [] ? null : PagePattern::fromSection($document, $document['sections'][0]['key']);
        if ($name === '' || $pattern === null) {
            return Response::json(['error' => t('patterns.save_needs')], 422);
        }
        $id = PagePattern::save($this->db(), $name, $pattern);

        return Response::json(['id' => $id, 'ref' => 'user:' . $id, 'name' => mb_substr($name, 0, PagePattern::NAME)]);
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
