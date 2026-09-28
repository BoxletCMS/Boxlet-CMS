<?php

namespace App\Core;

use App\Modules\Update\UpdateGate;
use App\Support\Bytes;
use App\Support\Url;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

use function FastRoute\simpleDispatcher;

/**
 * Locale-aware router over FastRoute. Routes are registered without the locale; every
 * request is resolved to a locale before a route is matched.
 *
 * A first segment is a locale only if it is an enabled locale. With primary "en" and
 * "hr" enabled:
 *
 *   /hello            dispatch "/hello" with locale "en"
 *   /hr/hello         dispatch "/hello" with locale "hr"
 *   /en/hello         301 to /hello        (primary never carries a prefix)
 *   /en/  and  /en    301 to /
 *   /hr               301 to /hr/
 *   /de/hello         dispatch "/de/hello" with locale "en", which 404s
 *   /admin            dispatch "/admin" with the primary locale; admin has no prefix
 *
 * Every non-GET request must carry a valid CSRF token; that is checked here so no
 * route can forget it. The one exception is declared by name, visitorPost(), for a form a
 * visitor sends: visitors have no session to hold a token, so such a route guards itself. /, /hr/ and other home pages 404 until Slice 3.
 */
final class Router
{
    /** @var list<array{string, string, array{class-string, string}, list<array{class-string, string}>, bool}> */
    private array $routes = [];

    /** @var array{class-string, string}|null */
    private ?array $notFound = null;

    /**
     * @param list<string> $locales enabled locale codes
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $locales,
        private readonly string $primaryLocale,
    ) {
    }

    /**
     * @param array{class-string, string}       $handler    controller class and method
     * @param list<array{class-string, string}> $middleware class and method returning ?Response
     */
    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->routes[] = ['GET', $path, $handler, $middleware, true];
    }

    /**
     * @param array{class-string, string}       $handler
     * @param list<array{class-string, string}> $middleware
     */
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->routes[] = ['POST', $path, $handler, $middleware, true];
    }

    /**
     * A POST a VISITOR sends, such as a contact form (PLAN.md D-046), exempt from the
     * session CSRF check every other POST meets. Visitors have no session — a public page
     * must not start one — so there is no token to check; the handler must stand guard
     * itself, as the form route does with a signed time token, a honeypot and a rate limit.
     * Named, not a flag on post(), so the exemption is visible where it is used.
     *
     * @param array{class-string, string} $handler
     */
    public function visitorPost(string $path, array $handler): void
    {
        $this->routes[] = ['POST', $path, $handler, [], false];
    }

    /**
     * @param array{class-string, string} $handler
     */
    public function setNotFound(array $handler): void
    {
        $this->notFound = $handler;
    }

    public function dispatch(Request $request): Response
    {
        // Before anything else, including the locale redirect: while migrations are
        // pending the answer is the update screen or 503, not a 301 to a page that
        // cannot be rendered (PLAN.md D-019). public/index.php checks the same gate
        // earlier, before this router is even built; this is the one the tests reach.
        $gate = UpdateGate::check($this->container, $request);
        if ($gate !== null) {
            return $gate;
        }

        $path = $request->path;
        $locale = $this->primaryLocale;
        $segments = explode('/', ltrim($path, '/'), 2);

        if (in_array($segments[0], $this->locales, true)) {
            $locale = $segments[0];
            $slug = $segments[1] ?? null;

            // Permanent: the primary locale is immutable, so these forms never change.
            if ($locale === $this->primaryLocale || $slug === null) {
                return Response::redirect(Url::page($locale, $slug ?? ''), 301);
            }
            $path = substr($path, strlen($locale) + 1);
        }

        $result = $this->dispatcher()->dispatch($request->method, $path);

        $response = match ($result[0]) {
            Dispatcher::FOUND => $this->run($result[1], $request, $locale, $result[2]),
            Dispatcher::METHOD_NOT_ALLOWED => $this->methodNotAllowed($request, $locale, $result[1]),
            default => $this->notFound($request, $locale),
        };

        // While maintenance is on, a logged-in admin sees the real site with a bar saying
        // so. Appended to the finished HTML rather than threaded through every template,
        // so the page above it is exactly the page it would otherwise be.
        return UpdateGate::bar($this->container, $request, $response);
    }

    private function dispatcher(): Dispatcher
    {
        return simpleDispatcher(function (RouteCollector $collector): void {
            foreach ($this->routes as [$method, $path, $handler, $middleware, $session]) {
                $collector->addRoute($method, $path, [$handler, $middleware, $session]);
            }
        });
    }

    /**
     * @param array{array{class-string, string}, list<array{class-string, string}>, bool} $route
     * @param array<string, string> $params
     */
    private function run(array $route, Request $request, string $locale, array $params): Response
    {
        [$handler, $middleware, $session] = $route;

        if ($session && $request->method !== 'GET' && $request->method !== 'HEAD'
            && !$this->container->get('session')->validCsrf($request->body['_csrf'] ?? null)) {
            // A post over post_max_size arrives with $_POST and $_FILES both empty, so the
            // token is missing for a reason that has nothing to do with the token. Saying
            // "this form has expired" would send someone to reload the page and send the
            // same oversized file again.
            //
            // Checked here rather than in a controller because the token check is what the
            // request meets first: such a request never gets past this line.
            if (Bytes::postWasDiscarded((int) ($request->header('content-length') ?? '0'), $request->files !== [], $request->body !== [])) {
                return new Response(
                    t('post.too_large', ['limit' => Bytes::limits()['requestLabel']]),
                    413,
                    ['Content-Type' => 'text/plain; charset=utf-8'],
                );
            }

            return new Response(t('csrf.invalid'), 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        foreach ($middleware as [$class, $method]) {
            $response = (new $class($this->container))->$method($request);
            if ($response instanceof Response) {
                return $response;
            }
        }

        return $this->call($handler, $request, $locale, $params);
    }

    /**
     * @param array{class-string, string} $handler
     * @param array<string, string>       $params
     */
    private function call(array $handler, Request $request, string $locale, array $params): Response
    {
        [$class, $method] = $handler;

        return (new $class($this->container))->$method($request, $locale, $params);
    }

    private function notFound(Request $request, string $locale): Response
    {
        if ($this->notFound === null) {
            return Response::html('Not Found', 404);
        }
        $response = $this->call($this->notFound, $request, $locale, []);
        // An address that used to lead somewhere is answered from here with a 301 (PLAN.md
        // D-129), and stays one; anything else the handler says is a 404.
        if ($response->status !== 301) {
            $response->status = 404;
        }

        return $response;
    }

    /**
     * @param list<string> $allowed
     */
    private function methodNotAllowed(Request $request, string $locale, array $allowed): Response
    {
        $response = $this->notFound($request, $locale);
        $response->status = 405;
        $response->headers['Allow'] = implode(', ', $allowed);

        return $response;
    }
}
