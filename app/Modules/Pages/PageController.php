<?php

namespace App\Modules\Pages;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Design\Composition;
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

        // Forms, with what the visitor just did to one (D-046): ?sent=id after a send.
        $sent = $request->query['sent'] ?? null;
        // The page as stored: what is published, never a draft (D-173).
        $registry = $this->container->get('blocks');
        $body = PageRender::draw(
            $db,
            $registry,
            PageDocument::stored($db, $registry, (int) $page['id']) ?? ['blocks' => [], 'sections' => []],
            $locale,
            Composition::active($db),
            (string) $this->container->get('config')->get('app.key'),
            ['pageId' => (int) $page['id'], 'sent' => is_string($sent) && ctype_digit($sent) ? (int) $sent : null],
        );
        $html = $body['html'];
        $firstSurface = $body['firstSurface'];

        // D-004. THE ONLY PLACE THE TITLE FALLS BACK. An empty <title> is worse than one
        // repeating the page's own, so the page title stands in; an empty description is
        // better than one repeating the title, so it stays empty and the tag is dropped.
        $seo = PageSeo::of($page);

        return $this->render('page', $locale, [
            'title' => $seo['title'] !== '' ? $seo['title'] : (string) $page['title'],
            'description' => $seo['description'],
            'noindex' => $seo['noindex'],
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
     * @param array{title: string, description?: string, canonical?: string|null, shareImage?: string|null, first_surface?: string, breadcrumbs?: string, noindex?: bool} $head
     * @param array<string, mixed> $view
     * @param array<string, mixed>|null $page the page being drawn; null on an error page
     */
    private function render(string $template, string $locale, array $head, array $view = [], int $status = 200, string $current = '', ?array $page = null): Response
    {
        $data = $view + PageLayoutData::forPage($this->container, $locale, $head, $page, $current);

        return Response::html((new View(__DIR__ . '/views'))->render($template, $locale, $data), $status);
    }
}
