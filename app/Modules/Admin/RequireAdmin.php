<?php

namespace App\Modules\Admin;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Auth\AdminLogin;
use App\Support\Url;

/**
 * Route middleware: lets the request through only for a session that belongs to an
 * admin account that still exists, and a login still within its limits (AdminLogin, O-67):
 * used in the last IDLE seconds, begun within ABSOLUTE, of the account's present generation.
 * Everyone else is sent to the login page; a login that ended is told why there.
 */
final class RequireAdmin
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(Request $request): ?Response
    {
        $session = $this->container->get('session');
        $id = $session->get('admin_id');
        // Every column, not session_gen by name: between an update's swap and its migration
        // (0043) the column is not there yet, and this guard is what the step adding it meets.
        $admin = is_int($id) ? $this->container->get('db')->one('SELECT * FROM admin WHERE id = ?', [$id]) : null;
        if ($admin === null) {
            return Response::redirect(Url::admin('login'));
        }
        // A session begun before these limits existed has none of the three: it counts from
        // now, under the first generation, so an update does not log the owner out mid-way.
        $now = time();
        $since = $session->get('admin_since');
        $seen = $session->get('admin_seen');
        $gen = $session->get('admin_gen');
        if ((is_int($seen) && $now - $seen > AdminLogin::IDLE)
            || (is_int($since) && $now - $since > AdminLogin::ABSOLUTE)
            || (is_int($gen) ? $gen : 0) !== (int) ($admin['session_gen'] ?? 0)) {
            foreach (['admin_id', 'admin_since', 'admin_seen', 'admin_gen'] as $key) {
                $session->remove($key);
            }
            $session->regenerate();
            $session->set('login_notice', t('auth.session_ended'));

            return Response::redirect(Url::admin('login'));
        }
        if (!is_int($since)) {
            $session->set('admin_since', $now);
        }
        $session->set('admin_seen', $now);

        return null;
    }
}
