<?php

namespace App\Modules\Admin;

use App\Core\Db;
use App\Core\Settings;
use Closure;

/**
 * WHETHER THE SITE'S PRIVATE FILES CAN BE DOWNLOADED, ASKED AGAIN AFTER INSTALL (PLAN.md O-38).
 * The installer refuses a folder whose files beside public/ are served (D-138), but a host can
 * move the site to a server that ignores .htaccess, or the root .htaccess can be deleted, and
 * then .env — the database password — is served with nobody told.
 *
 * The installer's own check (PrivateCheck) asked from Overview: at most once a day while the
 * answer is good, since it is an HTTP request to the site itself, and on every visit while it
 * is bad, so that the warning goes the moment the host is fixed. The answer is kept in the
 * settings table as {at, exposed}.
 */
final class Exposure
{
    private const SETTING = 'exposure_check';

    private const EVERY = 86400;

    /**
     * @param Closure(): bool $hidden whether nothing beside public/ comes back over HTTP
     */
    public static function exposed(Db $db, Closure $hidden, ?int $now = null): bool
    {
        $now ??= time();
        $last = Settings::get($db, self::SETTING);
        if (is_array($last) && ($last['exposed'] ?? null) === false && is_int($last['at'] ?? null) && $now - $last['at'] < self::EVERY) {
            return false;
        }
        $exposed = !$hidden();
        Settings::set($db, self::SETTING, ['at' => $now, 'exposed' => $exposed]);

        return $exposed;
    }
}
