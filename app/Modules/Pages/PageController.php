<?php

namespace App\Modules\Pages;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Forms\FormBlocks;
use App\Modules\Media\MediaPicture;
use App\Modules\Redirects\Redirects;
use App\Modules\Settings\SiteChrome;
use App\Support\Url;

/**
 * Front end: renders a published page's blocks in order, or the 404 page.
 */
final class PageController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params slug, absent for the home page
     */
    public function show(Request $request, string $locale, array $params): Response
    {
        $db = $this->container->get('db');
        // The address may be nested, /usluge/web-dizajn (D-129). A slug is unique in its
        // language, so the last segment finds the page and the rest is only checked.
        $address = $params['slug'] ?? '';
        $segments = explode('/', $address);
        $slug = (string) end($segments);
        // The home page with a query may be an old site's address, `/?p=12` (D-129).
        if ($slug === '' && ($target = Redirects::queryTarget($db, $request)) !== null) {
            return Response::redirect($target, 301);
        }
        $page = Page::published($db, $locale, $slug);
        if ($page === null) {
            return $this->notFound($request, $locale, $params);
        }
        // The path above it is wrong when a parent was renamed or the page moved since the
        // link was made: sent on to where it is now, its query kept. Only when every
        // segment in front was a page's slug, now or before — any other prefix is an
        // address nobody made, and /de/hello with German not enabled must stay a 404
        // (SPEC §5.1), never be answered in another language.
        if ($address !== Url::pathOf($locale, $slug)) {
            if (!PagePaths::known($db, $locale, array_slice($segments, 0, -1))) {
                return $this->notFound($request, $locale, $params);
            }

            return Response::redirect(Url::withQuery(Url::page($locale, $slug), self::query($request)), 301);
        }

        $registry = $this->container->get('blocks');
        $blocks = Page::blocks($db, (int) $page['id']);
        // Every picture this page refers to, in one query rather than one per block, and
        // before anything renders: a template is handed what it needs and never queries.
        $media = MediaPicture::forBlocks($db, $registry, $locale, $blocks);
        // Links to pages, followed in one query in the visitor's language (PLAN.md D-034).
        $links = PageLinks::targets($db, $registry, $locale, $blocks);
        // Forms, with what the visitor just did to one (D-046): ?sent=id after a send.
        $sent = $request->query['sent'] ?? null;
        $forms = FormBlocks::resolve(
            $db,
            $blocks,
            $locale,
            (int) $page['id'],
            (string) $this->container->get('config')->get('app.key'),
            is_string($sent) && ctype_digit($sent) ? (int) $sent : null,
        );
        // The files the page's Downloads blocks offer (D-127), in one query.
        $files = \App\Modules\Media\MediaFiles::forBlocks($db, $registry, $blocks);

        $html = '';
        $first = true;
        // What the first section stands on, for a header laid over it: the ink on such a
        // header is that section's, and so is the choice between the two logos (D-112).
        $firstSurface = '';
        foreach (Sections::group(Sections::forPage($db, (int) $page['id']), $blocks) as $group) {
            $drawable = [];
            foreach ($group['blocks'] as $block) {
                // A block whose type was removed from app/Blocks cannot render; skip it.
                if (!$registry->has($block['type'])) {
                    continue;
                }
                $block['content'] = PageLinks::content($registry, $block['type'], $block['content'], $links);
                $drawable[] = $block;
            }
            // A section whose every block is of a type this install no longer has would be
            // an empty band of surface and rhythm — the same thing prune() refuses to leave
            // behind on save, refused here on the way out for the rows it cannot see.
            if ($drawable === []) {
                continue;
            }
            // Only the first section that actually draws is eager. Everything below the
            // fold is lazy, which is the whole point of loading="lazy" — and the first
            // picture is usually the one a visitor is waiting to see.
            $html .= SectionRender::draw($registry, $group['section'], $drawable, $media, $first, ['forms' => $forms, 'files' => $files], $locale);
            if ($first) {
                $firstSurface = (string) ($group['section']['style']['surface'] ?? '');
            }
            $first = false;
        }

        // D-004. THE ONLY PLACE THE TITLE FALLS BACK. An empty <title> is worse than one
        // repeating the page's own, so the page title stands in; an empty description is
        // better than one repeating the title, so it stays empty and the tag is dropped.
        $seo = Page::seo($page);

        return $this->render('page', $locale, [
            'title' => $seo['title'] !== '' ? $seo['title'] : (string) $page['title'],
            'description' => $seo['description'],
            // An error page carries no link preview; a real page is where the site's
            // default sharing picture belongs (D-028).
            'shareImage' => SiteChrome::shareImage($db),
            // The one address this page is indexed under, whatever variant reached it.
            'canonical' => Url::canonical($locale, $slug),
            // What a header laid over the page stands on (D-112).
            'first_surface' => $firstSurface,
            // Where the page sits under its parents, for search engines (D-129).
            'breadcrumbs' => PagePaths::jsonLd(PagePaths::trail($db, $page)),
        ], ['blocksHtml' => $html], 200, Url::page($locale, $slug), $page);
    }

    /**
     * The sitemap as a route, for a host where public/sitemap.xml cannot be written
     * (D-049). The file is what search engines are pointed at wherever it exists.
     *
     * @param array<string, string> $params
     */
    public function sitemap(Request $request, string $locale, array $params): Response
    {
        return new Response(Sitemap::xml($this->container->get('db')), 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /**
     * @param array<string, string> $params
     */
    public function notFound(Request $request, string $locale, array $params): Response
    {
        // An address that used to lead somewhere still does (D-129): a page's old slug, or
        // a rule the owner made for the site this one replaced. Only here, after nothing
        // else answered, so a live page always wins.
        if ($request->method === 'GET' || $request->method === 'HEAD') {
            $target = Redirects::target($this->container->get('db'), $request, $locale);
            if ($target !== null) {
                return Response::redirect($target, 301);
            }
        }

        return $this->render('404', $locale, [
            'title' => site_t('site.not_found.title', $locale),
        ], ['intro' => site_t('site.not_found.intro', $locale)], 404);
    }

    /**
     * The request's query as withQuery() takes it: strings only, since an array in a query
     * (`?a[]=1`) is nothing a page reads and nothing worth carrying through a redirect.
     *
     * @return array<string, string>
     */
    private static function query(Request $request): array
    {
        return array_filter($request->query, 'is_string');
    }

    /**
     * $head is what only the caller knows about this page; $view is what its own template
     * reads. Everything the LAYOUT reads comes from PageLayoutData, which is the one place
     * that knows the whole list (D-057).
     *
     * @param array{title: string, description?: string, canonical?: string|null, shareImage?: string|null, first_surface?: string, breadcrumbs?: string} $head
     * @param array<string, mixed> $view
     * @param array<string, mixed>|null $page the page being drawn; null on an error page
     */
    private function render(string $template, string $locale, array $head, array $view = [], int $status = 200, string $current = '', ?array $page = null): Response
    {
        $data = $view + PageLayoutData::forPage($this->container, $locale, $head, $page, $current);

        return Response::html((new View(__DIR__ . '/views'))->render($template, $locale, $data), $status);
    }
}
