<?php

namespace App\Modules\Auth;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Modules\Admin\Activity;
use App\Support\ClientIp;
use App\Support\Url;

/**
 * Your login (PLAN.md D-132): the admin's own email and password, changed from Settings.
 *
 * BOTH ASK FOR THE CURRENT PASSWORD. A session left open on someone else's computer must not
 * be enough to take the account, and a wrong password here counts against the same throttle
 * as one at the login form, so this is no faster a place to guess it.
 */
final class AccountController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function email(Request $request, string $locale, array $params): Response
    {
        $refused = $this->currentPasswordRefused($request);
        if ($refused !== null) {
            return $this->back($refused, true);
        }
        $email = mb_strtolower(trim($request->input('email')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->back(t('account.email_invalid'), true);
        }

        $db = $this->db();
        $db->query('UPDATE admin SET email = ? WHERE id = ?', [$email, $this->adminId()]);
        Activity::record($db, 'account', 'email', null, $email);

        return $this->back(t('account.email_changed', ['email' => $email]), false);
    }

    /**
     * @param array<string, string> $params
     */
    public function password(Request $request, string $locale, array $params): Response
    {
        $refused = $this->currentPasswordRefused($request);
        if ($refused !== null) {
            return $this->back($refused, true);
        }
        $problem = Password::problem($request->input('new_password'), $request->input('new_password_confirm'));
        if ($problem !== null) {
            return $this->back($problem, true);
        }

        $db = $this->db();
        $db->query('UPDATE admin SET password_hash = ? WHERE id = ?', [password_hash($request->input('new_password'), PASSWORD_DEFAULT), $this->adminId()]);
        // A new session id, as at every change of who holds the account (SPEC §6).
        $this->container->get('session')->regenerate();
        Activity::record($db, 'account', 'password', null, '');

        return $this->back(t('account.password_changed'), false);
    }

    /**
     * Why the current password was not accepted, in words, or null when it was. Counted
     * against the login throttle by the account's email and the visitor's address, exactly
     * as a login is.
     */
    private function currentPasswordRefused(Request $request): ?string
    {
        $db = $this->db();
        $admin = $db->one('SELECT email, password_hash FROM admin WHERE id = ?', [$this->adminId()]);
        if ($admin === null) {
            return t('account.current_wrong');
        }
        $key = (string) $this->container->get('config')->get('app.key');
        $emailHash = hash_hmac('sha256', (string) $admin['email'], $key);
        $ipHash = hash_hmac('sha256', ClientIp::of($request, Settings::text($db, 'trusted_proxies')), $key);
        $throttle = new LoginThrottle($db);
        $now = time();
        if ($throttle->isLocked($ipHash, $emailHash, $now)) {
            return t('auth.throttled', ['minutes' => intdiv(LoginThrottle::WINDOW_SECONDS, 60)]);
        }
        $right = password_verify($request->input('current_password'), (string) $admin['password_hash']);
        $throttle->record($ipHash, $emailHash, $right, $now);

        return $right ? null : t('account.current_wrong');
    }

    private function back(string $message, bool $error): Response
    {
        $session = $this->container->get('session');
        $session->set('flash', $message);
        $session->set('flash_kind', $error ? 'error' : 'success');

        return Response::redirect(Url::admin('settings') . '#account');
    }

    private function adminId(): int
    {
        $id = $this->container->get('session')->get('admin_id');

        return is_int($id) ? $id : 0;
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
