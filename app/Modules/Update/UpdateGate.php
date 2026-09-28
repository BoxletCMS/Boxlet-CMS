<?php

namespace App\Modules\Update;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Support\Url;

/**
 * What every request meets while the site is not open (PLAN.md D-019, D-021).
 *
 * One gate, two triggers, one page:
 *
 *   a pending migration   the site may not render at all until it is applied, so the 503
 *                         applies to EVERYONE, and the admin is sent to the update screen
 *   maintenance mode      the owner's own switch: visitors get the 503 page, and a
 *                         logged-in admin sees the real site with a bar saying so
 *
 * Nothing here runs a migration or changes the flag: only the buttons do.
 *
 * Called from two places on purpose. public/index.php calls it before the router is
 * built, because building the router reads the locales table and an update may be
 * exactly what that table is waiting for; Router::dispatch() calls it so the test suite
 * exercises the real gate rather than a copy of its reasoning.
 */
final class UpdateGate
{
    /** Paths the update gate lets through: logging in, and the update screen itself. */
    private const ALLOWED = ['/admin/login', '/admin/update'];

    public static function check(Container $container, Request $request): ?Response
    {
        if (!$container->get('installed')) {
            return null;
        }

        $path = rtrim($request->path, '/');
        if ($path === '') {
            $path = '/';
        }

        // A pending migration outranks everything, admin included.
        if ($container->get('update')->pending() !== []) {
            if (in_array($path, self::ALLOWED, true)) {
                return null;
            }

            return str_starts_with($path, '/admin')
                ? Response::redirect(Url::admin('update'))
                : self::closed($container, 'update');
        }

        if (!$container->get('maintenance')->isOn()) {
            return null;
        }

        // The admin keeps the whole admin, and sees the real site with a bar on it. The
        // session is resolved only here: an ordinary visit to a healthy site must not
        // start one, and so must not set a cookie, because this feature exists.
        if (self::isAdmin($container, $request)) {
            return null;
        }

        return str_starts_with($path, '/admin') ? null : self::closed($container, 'maintenance');
    }

    /**
     * The bar a logged-in admin sees on the real site while maintenance is on.
     *
     * Appended to the finished HTML rather than threaded through every template: the
     * front end renders through one place, the 404 page included, and a page under
     * inspection must not change above the bar.
     */
    public static function bar(Container $container, Request $request, Response $response): Response
    {
        if (!$container->get('installed')
            || !$container->get('maintenance')->isOn()
            || $container->get('update')->pending() !== []
            || !self::isAdmin($container, $request)
            || !str_contains((string) ($response->headers['Content-Type'] ?? ''), 'text/html')) {
            return $response;
        }

        $html = (new View(__DIR__ . '/views'))->render('public/bar', 'en', [
            'off' => Url::admin('maintenance'),
        ], null);

        $response->body = str_contains($response->body, '</body>')
            ? (string) preg_replace('~</body>~', $html . '</body>', $response->body, 1)
            : $response->body . $html;

        return $response;
    }

    /**
     * ONLY A REQUEST CARRYING THE SESSION COOKIE CAN BE THE ADMIN'S, and only then is the
     * session opened (D-128). Opening it for every visitor to a closed site gave each one a
     * `boxlet_session` cookie, and that cookie is how the statistics and the download count
     * tell the admin apart without a session (Tracker::wanted()): a visitor who met the
     * maintenance page went on being skipped as the admin after the site reopened.
     */
    private static function isAdmin(Container $container, Request $request): bool
    {
        if (preg_match('~(?:^|;)\s*boxlet_session=~', $request->header('cookie') ?? '') !== 1) {
            return false;
        }
        $id = $container->get('session')->get('admin_id');

        return is_int($id) && $container->get('db')->one('SELECT id FROM admin WHERE id = ?', [$id]) !== null;
    }

    /**
     * The page everyone else gets, with 503 so a crawler comes back rather than dropping
     * the page from its index.
     *
     * Nothing is rendered from the database: a page render reads tables that a pending
     * migration may be about to create or change, and maintenance is exactly when the
     * database may be unavailable.
     */
    private static function closed(Container $container, string $because): Response
    {
        $body = (new View(__DIR__ . '/views'))->render('public/updating', 'en', [
            'title' => $because === 'update' ? t('update.public.title') : t('maintenance.public.title'),
            'message' => $because === 'update' ? t('update.public.body') : t('maintenance.public.body'),
        ], null);

        return new Response($body, 503, [
            'Content-Type' => 'text/html; charset=utf-8',
            // Long enough that a crawler does not hammer the site, short enough that a
            // visitor who waits gets the real page.
            'Retry-After' => '120',
            'Cache-Control' => 'no-store',
        ]);
    }
}
