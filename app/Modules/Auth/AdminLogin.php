<?php

namespace App\Modules\Auth;

use App\Core\Db;
use App\Core\Session;

/**
 * HOW LONG A LOGIN LASTS, AND WHAT ENDS IT (PLAN.md O-67). A login lasted as long as the
 * browser stayed open and the host's garbage collection left the session file, and a password
 * changed or reset left every other login standing: a stolen session outlived the change
 * meant to end it.
 *
 * Now a login ends after IDLE seconds without a request, and ABSOLUTE seconds after it began
 * whatever. Each carries the generation of the admin's logins it began under; a password
 * changed or reset moves the generation on, and every login of an older one ends.
 * RequireAdmin asks on every admin request.
 */
final class AdminLogin
{
    /** Four hours without a request. */
    public const IDLE = 14400;

    /** A week from logging in, in use or not. */
    public const ABSOLUTE = 604800;

    /** The session as a login of $adminId begun at $now. */
    public static function begin(Session $session, Db $db, int $adminId, int $now): void
    {
        $session->set('admin_id', $adminId);
        $session->set('admin_since', $now);
        $session->set('admin_seen', $now);
        $session->set('admin_gen', self::generation($db, $adminId));
    }

    /**
     * Every login of $adminId ends but $current's, which carries on under the new generation:
     * a password changed in this browser keeps this browser logged in. Null for a reset,
     * which no login survives.
     */
    public static function endOthers(Db $db, int $adminId, ?Session $current): void
    {
        if (!self::counted($db)) {
            return;
        }
        $db->query('UPDATE admin SET session_gen = session_gen + 1 WHERE id = ?', [$adminId]);
        $current?->set('admin_gen', self::generation($db, $adminId));
    }

    /** Every login of every admin ends: the FTP reset names no admin. */
    public static function endAll(Db $db): void
    {
        if (self::counted($db)) {
            $db->query('UPDATE admin SET session_gen = session_gen + 1');
        }
    }

    public static function generation(Db $db, int $adminId): int
    {
        return (int) ($db->one('SELECT * FROM admin WHERE id = ?', [$adminId])['session_gen'] ?? 0);
    }

    /**
     * Whether the generations are counted yet. Between an update's swap and its migration
     * (0043) the column is not there, and a login or an FTP reset in that moment must not
     * fail on it: every column is asked for, never session_gen by name.
     */
    private static function counted(Db $db): bool
    {
        $row = $db->one('SELECT * FROM admin');

        return $row !== null && array_key_exists('session_gen', $row);
    }
}
