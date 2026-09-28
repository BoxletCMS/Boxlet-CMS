<?php

namespace App\Modules\Admin;

use App\Core\Container;
use App\Core\Response;
use App\Core\Settings;
use App\Core\View;
use App\Support\Dates;
use App\Support\Url;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Renders an admin page inside the admin shell (Admin/views/layout.php): the rail, the strip
 * above the content, the CSRF token for the logout form and a one-time flash message.
 */
final class AdminView
{
    /**
     * @param string               $directory the module's views directory
     * @param array<string, mixed> $data
     */
    public static function render(Container $container, string $directory, string $template, array $data, int $status = 200): Response
    {
        $session = $container->get('session');
        $siteName = Settings::text($container->get('db'), 'site_name');
        $flash = $session->get('flash');
        // A refusal dressed as a confirmation is worse than no message: the owner reads the
        // colour before the words. One slot, two kinds — 'success' unless a controller says
        // otherwise, so every existing caller keeps the message it already sets.
        $kind = $session->get('flash_kind');
        $session->remove('flash');
        $session->remove('flash_kind');

        $data += [
            'title' => t('admin.brand'),
            'nav' => '',
            // Stylesheets this screen needs on top of the shell's own, and whether it
            // wants the wide column (the Design screen does; a form does not).
            'styles' => [],
            // Scripts this screen needs beyond admin.js, in load order. Deferred, so they
            // run in the order they are listed.
            'scripts' => [],
            'wide' => false,
            // A screen that fills the window itself rather than sitting in the reading
            // column: the visual editor, whose canvas is the screen.
            'bare' => false,
            'siteName' => $siteName,
            'flash' => is_string($flash) ? $flash : null,
            // 'error' as well: five controllers set it for a refusal, and until D-051 found
            // it here it was drawn as 'success' — the very thing the comment above forbids.
            'flashKind' => in_array($kind, ['warning', 'error'], true) ? $kind : 'success',
            'csrf' => $session->csrfToken(),
            // Statistics has a place in the rail only while it counts (D-051).
            'statsOn' => \App\Modules\Stats\Tracker::settings($container->get('db'))['enabled'],
        ] + self::frame($container);
        $html = (new View($directory, __DIR__ . '/views'))->render($template, 'en', $data);

        return Response::admin($html, $status);
    }

    /**
     * What the rail and the strip above the content show on every screen (D-052): how many
     * of each thing the site has, beside its entry, the site's own host, its time zone and
     * the time there, and who is logged in.
     *
     * One query for the four counts, as scalar subqueries both databases accept: this runs
     * on every admin page, and four round trips for four numbers would be the rail's whole
     * cost.
     *
     * The rail's folded or open state comes from the boxlet_rail cookie admin-nav.js sets
     * when the owner uses the toggle, so the server draws it right the first time and the
     * rail never opens and then folds as a page loads. Only the admin ever gets it: it is
     * set by the admin's own script, on /admin.
     *
     * The palette comes from the boxlet_theme cookie the same way (D-054), and `here` is
     * the address of this very page, which the theme switch posts back so that pressing it
     * returns to the screen it was pressed on.
     *
     * @return array{counts: array{pages: int, media: int, menus: int, forms: int}, host: string, zone: string, time: string, adminEmail: string, railState: string, theme: string, here: string}
     */
    private static function frame(Container $container): array
    {
        $db = $container->get('db');
        $request = $container->get('request');
        $row = $db->one('SELECT (SELECT COUNT(*) FROM pages) AS pages, (SELECT COUNT(*) FROM media) AS media,
            (SELECT COUNT(*) FROM menus) AS menus, (SELECT COUNT(*) FROM forms) AS forms') ?? [];
        $zone = Dates::zone($db);
        $id = $container->get('session')->get('admin_id');
        $admin = is_int($id) ? $db->one('SELECT email FROM admin WHERE id = ?', [$id]) : null;

        return [
            'counts' => [
                'pages' => (int) ($row['pages'] ?? 0),
                'media' => (int) ($row['media'] ?? 0),
                'menus' => (int) ($row['menus'] ?? 0),
                'forms' => (int) ($row['forms'] ?? 0),
            ],
            'host' => (string) parse_url(Url::withOrigin('/'), PHP_URL_HOST),
            'zone' => $zone,
            'time' => (new DateTimeImmutable('now', new DateTimeZone($zone)))->format('H:i'),
            'adminEmail' => (string) ($admin['email'] ?? ''),
            'railState' => preg_match('~(?:^|;)\s*boxlet_rail=(compact|wide)~', (string) $request->header('cookie'), $rail) === 1 ? $rail[1] : '',
            'theme' => Theme::of($request),
            'here' => Url::withQuery($request->basePath . $request->path, array_map('strval', array_filter($request->query, 'is_scalar'))),
        ];
    }
}
