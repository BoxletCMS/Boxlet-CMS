<?php

namespace App\Modules\Admin;

use App\Core\Request;

/**
 * Which of the admin's two palettes this browser is drawn in (PLAN.md D-054).
 *
 * Three answers, not two: light, dark, or whichever the machine is set to. A two-state
 * toggle cannot say "follow the system", and a control whose label changes meaning as it is
 * pressed is the kind CLAUDE.md calls invisible at rest.
 *
 * Kept in a cookie rather than in the database, for the same reason the rail's folded state
 * is (AdminView::frame): it is how the admin looks on THIS screen, not something about the
 * site. A laptop in the dark and a desktop by a window are allowed to disagree, and nothing
 * about a personal view preference deserves a migration.
 *
 * The server reads it and writes the attribute into the page, so the first paint is already
 * right: no flash of the other theme, and none of the blocking inline script the admin's
 * CSP would refuse anyway.
 */
final class Theme
{
    /** What may be asked for. Anything else is the default. */
    public const NAMES = ['light', 'dark', 'system'];

    /** What an admin with no preference gets: the dark Workbench, as it shipped (D-052). */
    public const FALLBACK = 'dark';

    public const COOKIE = 'boxlet_theme';

    public static function of(Request $request): string
    {
        $cookies = (string) $request->header('cookie');
        if (preg_match('~(?:^|;)\s*' . self::COOKIE . '=([a-z]+)~', $cookies, $found) !== 1) {
            return self::FALLBACK;
        }

        return in_array($found[1], self::NAMES, true) ? $found[1] : self::FALLBACK;
    }

    /**
     * The Set-Cookie line that remembers it.
     *
     * Only /admin sends it: the public site has no use for it and a visitor should never
     * carry the owner's preference around. Lax rather than the rail's Strict — following a
     * link into the admin from somewhere else should not land on the other theme for one
     * page.
     */
    public static function cookie(string $theme): string
    {
        return self::COOKIE . '=' . $theme . '; Path=/admin; Max-Age=31536000; SameSite=Lax; HttpOnly';
    }
}
