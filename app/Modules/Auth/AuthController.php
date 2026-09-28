<?php

namespace App\Modules\Auth;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\View;
use App\Modules\Admin\Theme;
use App\Support\ClientIp;
use App\Support\Url;
use RuntimeException;

final class AuthController
{
    // A real bcrypt hash of a random string. Verifying against it when the email is
    // unknown takes as long as a real check, so timing does not reveal accounts.
    private const DUMMY_HASH = '$2y$10$Y3W.HoWn5OHOFjxrPf82I.OHOkckff7XWzw8kEMStpMZ2DHXxyRW2';

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function showLogin(Request $request, string $locale, array $params): Response
    {
        if ($this->session()->get('admin_id') !== null) {
            return Response::redirect(Url::admin());
        }
        // A new password from a file put there over FTP (D-132), read on the way in; else
        // what a reset from an emailed link left to say, once.
        $notice = PasswordReset::fromFile($this->container->get('db'), $this->storage());
        if ($notice === null) {
            $said = $this->session()->get('login_notice');
            $this->session()->remove('login_notice');
            $notice = is_string($said) ? $said : null;
        }

        return $this->form('', null, 200, $notice);
    }

    /**
     * The failure message is identical for a wrong password, an unknown email and a
     * locked account, so the form never reveals whether an account exists.
     *
     * @param array<string, string> $params
     */
    public function login(Request $request, string $locale, array $params): Response
    {
        $email = mb_strtolower(trim($request->input('email')));
        $key = (string) $this->container->get('config')->get('app.key');
        if ($key === '') {
            throw new RuntimeException('APP_KEY is not set. The installer writes it to .env.');
        }
        $emailHash = hash_hmac('sha256', $email, $key);

        $db = $this->container->get('db');
        // Behind a proxy every attempt would share one address, and one stranger guessing
        // would lock the owner out with them (O-20).
        $ipHash = hash_hmac('sha256', ClientIp::of($request, Settings::text($db, 'trusted_proxies')), $key);
        // The FTP way back in (SPEC §6): storage/disable-2fa switches two-step login off
        // before anything else, so the owner who put it there can log in with the password.
        $reset = (new TwoFactor($db, $key))->resetFromFile($this->storage());
        // And storage/reset-password, before the password is checked, so the owner who put a
        // new one there can log in with it straight away (D-132).
        $fromFile = PasswordReset::fromFile($db, $this->storage());
        $throttle = new LoginThrottle($db);
        $now = time();
        if ($throttle->isLocked($ipHash, $emailHash, $now)) {
            $minutes = intdiv(LoginThrottle::WINDOW_SECONDS, 60);

            return $this->form($email, t('auth.throttled', ['minutes' => $minutes]), 429);
        }

        $admin = $db->one('SELECT id, password_hash FROM admin WHERE email = ?', [$email]);
        $hash = $admin === null ? self::DUMMY_HASH : (string) $admin['password_hash'];
        $password = $request->input('password');
        // Always verify, even for an unknown email, so both cases take the same time.
        $passwordMatches = password_verify($password, $hash);
        $valid = $admin !== null && $passwordMatches;
        $throttle->record($ipHash, $emailHash, $valid, $now);

        if ($admin === null || !$passwordMatches) {
            return $this->form($email, t('auth.failed'), 422, $fromFile ?? $this->resetNotice($reset));
        }
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $db->query('UPDATE admin SET password_hash = ? WHERE id = ?', [
                password_hash($password, PASSWORD_DEFAULT),
                $admin['id'],
            ]);
        }

        $session = $this->session();
        $session->regenerate();
        // Two-step login (D-050): the password is right, and the code still to come. Nothing
        // here counts as logged in; the code step sets admin_id.
        if ((new TwoFactor($db, $key))->enabled((int) $admin['id'])) {
            $session->set('pending_admin', ['id' => (int) $admin['id'], 'email' => $email, 'at' => $now]);

            return Response::redirect(Url::admin('login', 'code'));
        }
        $session->set('admin_id', (int) $admin['id']);
        if ($reset['done'] || $fromFile !== null) {
            $session->set('flash', trim(($this->resetNotice($reset) ?? '') . ' ' . ($fromFile ?? '')));
        }

        return Response::redirect(Url::admin());
    }

    /**
     * The second step: the code from the owner's app, or one of their recovery codes. It
     * counts against the same limit as a wrong password, so guessing codes is as slow as
     * guessing passwords.
     *
     * @param array<string, string> $params
     */
    public function showCode(Request $request, string $locale, array $params): Response
    {
        return $this->pending() === null ? Response::redirect(Url::admin('login')) : $this->codeForm(null, 200);
    }

    /**
     * @param array<string, string> $params
     */
    public function code(Request $request, string $locale, array $params): Response
    {
        $pending = $this->pending();
        if ($pending === null) {
            return Response::redirect(Url::admin('login'));
        }
        $key = (string) $this->container->get('config')->get('app.key');
        $db = $this->container->get('db');
        $ipHash = hash_hmac('sha256', ClientIp::of($request, Settings::text($db, 'trusted_proxies')), $key);
        $emailHash = hash_hmac('sha256', $pending['email'], $key);
        $throttle = new LoginThrottle($db);
        $now = time();
        if ($throttle->isLocked($ipHash, $emailHash, $now)) {
            return $this->codeForm(t('auth.throttled', ['minutes' => intdiv(LoginThrottle::WINDOW_SECONDS, 60)]), 429);
        }

        $twoFactor = new TwoFactor($db, $key);
        $code = $request->input('code');
        $recovery = false;
        $valid = $twoFactor->check($pending['id'], $code);
        if (!$valid && $twoFactor->useRecovery($pending['id'], $code)) {
            $valid = true;
            $recovery = true;
        }
        $throttle->record($ipHash, $emailHash, $valid, $now);
        if (!$valid) {
            return $this->codeForm(t('twofactor.code_wrong'), 422);
        }

        $session = $this->session();
        $session->remove('pending_admin');
        $session->regenerate();
        $session->set('admin_id', $pending['id']);
        if ($recovery) {
            $session->set('flash', t('twofactor.recovery_used', ['left' => (string) $twoFactor->codesLeft($pending['id'])]));
        }

        return Response::redirect(Url::admin());
    }

    /**
     * The half-finished login, while it is fresh: ten minutes to type a code.
     *
     * @return array{id: int, email: string}|null
     */
    private function pending(): ?array
    {
        $pending = $this->session()->get('pending_admin');
        if (!is_array($pending) || !is_int($pending['id'] ?? null) || !is_string($pending['email'] ?? null) || (int) ($pending['at'] ?? 0) < time() - 600) {
            return null;
        }

        return ['id' => $pending['id'], 'email' => $pending['email']];
    }

    private function codeForm(?string $error, int $status): Response
    {
        return Response::admin((new View(__DIR__ . '/views'))->render('login-code', 'en', [
            'title' => t('twofactor.login_title'),
            'theme' => Theme::of($this->container->get('request')),
            'error' => $error,
            'csrf' => $this->session()->csrfToken(),
        ]), $status);
    }

    /**
     * @param array{done: bool, removed: bool} $reset
     */
    private function resetNotice(array $reset): ?string
    {
        if (!$reset['done']) {
            return null;
        }

        return t($reset['removed'] ? 'twofactor.reset_done' : 'twofactor.reset_done_file_left', ['file' => 'storage/' . TwoFactor::RESET_FILE]);
    }

    private function storage(): string
    {
        return (string) $this->container->get('config')->get('app.storage_path');
    }

    /**
     * @param array<string, string> $params
     */
    public function logout(Request $request, string $locale, array $params): Response
    {
        $this->session()->destroy();

        return Response::redirect(Url::admin('login'));
    }

    private function form(string $email, ?string $error, int $status, ?string $notice = null): Response
    {
        $html = (new View(__DIR__ . '/views'))->render('login', 'en', [
            'title' => t('auth.title'),
            // The palette this browser chose in the admin (D-054): logging out, or being
            // logged out, is no reason for the colours to change.
            'theme' => Theme::of($this->container->get('request')),
            'email' => $email,
            'error' => $error,
            'notice' => $notice,
            'csrf' => $this->session()->csrfToken(),
        ]);

        return Response::admin($html, $status);
    }

    private function session(): Session
    {
        return $this->container->get('session');
    }
}
