<?php

namespace App\Modules\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Support\Url;

/**
 * The ADMIN's own light, dark, or whichever the machine is set to (PLAN.md D-054).
 *
 * Named for the theme rather than for "appearance", and posting to /admin/theme: Appearance
 * is now the screen where the SITE's look is decided (D-059), and one word meaning both the
 * tool's palette and the site's design is the kind of collision that ends with somebody
 * changing the wrong one.
 *
 * A form post rather than a script: the switch works with JavaScript off, which is what
 * CLAUDE.md asks of every control, and the page it returns to is already painted in the new
 * palette. One full reload, roughly once per person per install.
 *
 * No constructor: the router builds every controller with the container, and PHP ignores an
 * argument a class has no constructor for. This one needs nothing beyond the request it is
 * handed, and a container held and never read is a field the next reader has to check.
 */
final class ThemeController
{
    /**
     * @param array<string, string> $params
     */
    public function save(Request $request, string $locale, array $params): Response
    {
        $wanted = $request->body['theme'] ?? '';
        $theme = is_string($wanted) && in_array($wanted, Theme::NAMES, true) ? $wanted : Theme::FALLBACK;

        return new Response('', 302, [
            'Location' => $this->back($request),
            'Set-Cookie' => Theme::cookie($theme),
        ]);
    }

    /**
     * Where the switch was pressed, as the form says — but only if that is an admin page of
     * this install. An open redirect from a field the browser sends is the one thing this
     * controller could get wrong, so the value is checked against the admin's own prefix
     * rather than trusted, and anything else lands on the dashboard.
     */
    private function back(Request $request): string
    {
        $back = $request->body['back'] ?? '';
        $admin = Url::admin();
        if (!is_string($back) || !str_starts_with($back, $admin . '/') && $back !== $admin) {
            return $admin;
        }
        // A second slash after the prefix would be //host — a different site, which the
        // browser follows happily. Control characters would split the header.
        if (str_contains($back, '//') || preg_match('~[[:cntrl:]]~', $back) === 1) {
            return $admin;
        }

        return $back;
    }
}
