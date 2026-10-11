<?php

namespace App\Modules\Pages;

use App\Core\Db;
use App\Core\Request;

/**
 * Reading what an editor's form sent (PLAN.md D-173): whether it arrived whole, the page's
 * settings, and the arrangement its blocks stand in. Out of PageEditorController, which is the
 * flow — render, act, save, publish — and grew past the size where a file is read rather than
 * searched when drafts arrived.
 *
 * @phpstan-import-type Document from PageDocument
 */
final class PageSubmission
{
    /**
     * True when the form did not arrive whole: its last field, _end, is missing, or the
     * number of fields reached max_input_vars. PHP drops input past the limit without any
     * error, and saving what arrived would silently delete content.
     */
    public static function truncated(Request $request): bool
    {
        if (($request->body['_end'] ?? null) !== '1') {
            return true;
        }
        $limit = (int) ini_get('max_input_vars');

        return $limit > 0 && self::countFields($request->body) >= $limit;
    }

    /**
     * The page's settings as submitted, falling back to the document's for any the form did
     * not send. The plain editor sends none of them; only the visual editor's page panel does.
     *
     * parent_id comes back as false when a parent was named that this page may not have.
     * The list of allowed parents already excludes the page and its descendants, so this
     * is what stops a crafted request creating a cycle the interface would not offer.
     *
     * @param array<string, mixed> $page the pages row: its id and language
     * @param Document $document what the form was made from
     * @return array{parent_id: int|null|false, seo_json: string}
     */
    public static function settings(Request $request, Db $db, array $page, array $document): array
    {
        $settings = ['parent_id' => $document['parent_id'], 'seo_json' => self::seo($request, $document['seo_json'])];
        if (!array_key_exists('parent_id', $request->body)) {
            return $settings;
        }
        $submitted = $request->input('parent_id');
        if ($submitted === '') {
            $settings['parent_id'] = null;

            return $settings;
        }
        $allowed = array_column(PageTree::parents($db, (string) $page['locale'], (int) $page['id']), 'id');
        $settings['parent_id'] = in_array((int) $submitted, $allowed, true) ? (int) $submitted : false;

        return $settings;
    }

    /**
     * The page's meta title and description as submitted, already encoded for storage
     * (D-004).
     *
     * Presence decides, the same rule parent_id follows: a field the form did not send
     * keeps what is stored, a field sent empty was cleared on purpose. Without that
     * distinction any save from a form lacking these two — which is every save made
     * before this existed, and any made by a form added later — would quietly erase
     * what the owner wrote.
     */
    public static function seo(Request $request, string $seoJson): string
    {
        $seo = PageSeo::of(['seo_json' => $seoJson]);
        foreach (['title', 'description'] as $field) {
            if (array_key_exists('seo_' . $field, $request->body)) {
                $seo[$field] = trim($request->input('seo_' . $field));
            }
        }
        // A checkbox sends nothing when it is off, so it is read only from a form that drew
        // the SEO fields at all — the same rule as above, by the field beside it (D-170).
        if (array_key_exists('seo_title', $request->body)) {
            $seo['noindex'] = $request->input('seo_noindex') === '1';
        }

        return PageSeo::json($seo);
    }

    /**
     * The sections the form's blocks stand in, every one answered.
     *
     * A form that sent no sections is saying nothing about the arrangement (an older form, a
     * hand-made request): the document's bands are kept, and each block that already stood in
     * one stays there. A form that sent them is believed — except where it left a band's
     * layout or style unsaid, which keeps what the document has, the rule Page::update()
     * follows for a stored band (D-098).
     *
     * @param list<array<string, mixed>> $blocks as BlockForm::parse() gives them
     * @param list<array{key: string, id: int|null, layout: string|null, stack: string|null, style: array<string, string|int|null>|null}>|null $sections as SectionForm::parse() gives them
     * @param Document $document what the form was made from
     * @return array{blocks: list<array<string, mixed>>, sections: list<array<string, mixed>>}
     */
    public static function arranged(array $blocks, ?array $sections, array $document): array
    {
        $before = [];
        foreach ($document['sections'] as $section) {
            $before[$section['key']] = $section;
        }
        if ($sections === null) {
            // One block, one band, in the order the blocks came (SectionForm::oneEach): each in
            // the band it stood in, which keeps its arrangement; the style is the block's, as a
            // form without sections says it.
            $bandOf = [];
            foreach ($document['blocks'] as $block) {
                if ($block['id'] !== null && isset($before[$block['section']])) {
                    $bandOf[$block['id']] = $before[$block['section']];
                }
            }
            $bands = [];
            foreach ($blocks as $at => $block) {
                $was = is_int($block['id'] ?? null) ? ($bandOf[$block['id']] ?? null) : null;
                $key = $was['key'] ?? SectionForm::key(null, 3000 + $at);
                $bands[$key] ??= [
                    'key' => $key,
                    'id' => $was['id'] ?? null,
                    'layout' => $was['layout'] ?? null,
                    'stack' => $was['stack'] ?? null,
                    'style' => ($block['content'] ?? null) === null || !is_array($block['style'] ?? null) ? ($was['style'] ?? null) : $block['style'],
                ];
                $blocks[$at]['section'] = $key;
                $blocks[$at]['column'] = $was === null ? 0 : (int) ($block['column'] ?? 0);
            }

            return ['blocks' => $blocks, 'sections' => array_values($bands)];
        }
        $answered = [];
        foreach ($sections as $section) {
            $was = $before[$section['key']] ?? null;
            $answered[] = [
                'key' => $section['key'],
                'id' => $section['id'] ?? ($was['id'] ?? null),
                'layout' => $section['layout'] ?? ($was['layout'] ?? null),
                'stack' => $section['stack'] ?? ($was['stack'] ?? null),
                'style' => $section['style'] ?? ($was['style'] ?? null),
            ];
        }

        return ['blocks' => $blocks, 'sections' => $answered];
    }

    /**
     * The section ids a document holds, for SectionForm::parse to tell a key of this page's
     * from one that arrived from somewhere else — the rule BlockForm::parse follows for a
     * block id.
     *
     * @param Document $document
     * @return array<int, int>
     */
    public static function sectionIds(array $document): array
    {
        $ids = [];
        foreach ($document['sections'] as $section) {
            if ($section['id'] !== null) {
                $ids[$section['id']] = $section['id'];
            }
        }

        return $ids;
    }

    /**
     * @param array<mixed> $values
     */
    private static function countFields(array $values): int
    {
        $count = 0;
        foreach ($values as $value) {
            $count += is_array($value) ? self::countFields($value) : 1;
        }

        return $count;
    }
}
