<?php

namespace App\Modules\Auth;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Modules\Admin\Activity;
use App\Modules\Admin\AdminView;
use App\Support\Url;

/**
 * Switching two-step login on and off, and new recovery codes (PLAN.md D-050), from the
 * Settings screen.
 *
 * Switching on is two steps and cannot be done by half: the setup screen shows a QR code
 * for a secret kept in the session, and only a code the app then makes switches it on —
 * so nobody is left with two-step login on and no app that knows the secret. The recovery
 * codes are shown once, on the page that answers that step, and are never stored in the
 * clear or put in the session.
 */
final class TwoFactorController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function setup(Request $request, string $locale, array $params): Response
    {
        $twoFactor = $this->twoFactor();
        if ($twoFactor->enabled($this->adminId())) {
            // To Your login, which holds two-step login since D-132; #two-step is its heading.
        return Response::redirect(Url::admin('settings') . '#two-step');
        }
        $session = $this->container->get('session');
        $secret = $session->get('totp_setup');
        if (!is_string($secret) || $secret === '') {
            $secret = $twoFactor->newSecret();
            $session->set('totp_setup', $secret);
        }

        return $this->setupScreen($secret, null, 200);
    }

    /**
     * @param array<string, string> $params
     */
    public function confirm(Request $request, string $locale, array $params): Response
    {
        $twoFactor = $this->twoFactor();
        $session = $this->container->get('session');
        $secret = $session->get('totp_setup');
        if (!is_string($secret) || $secret === '' || $twoFactor->enabled($this->adminId())) {
            // To Your login, which holds two-step login since D-132; #two-step is its heading.
        return Response::redirect(Url::admin('settings') . '#two-step');
        }
        if (!$twoFactor->valid($secret, $request->input('code'))) {
            return $this->setupScreen($secret, t('twofactor.code_wrong'), 422);
        }
        $session->remove('totp_setup');
        $codes = $twoFactor->enable($this->adminId(), $secret);
        Activity::record($this->container->get('db'), 'settings', 'two_step_on', null, '');

        return $this->codesScreen($codes, t('twofactor.enabled'));
    }

    /**
     * New recovery codes, for the owner whose old ones are used up or lost. Asks for a code
     * from the app first, so a session left open is not enough to take the way back in.
     *
     * @param array<string, string> $params
     */
    public function renew(Request $request, string $locale, array $params): Response
    {
        $twoFactor = $this->twoFactor();
        if (!$twoFactor->check($this->adminId(), $request->input('code'))) {
            return $this->back(t('twofactor.code_wrong'), true);
        }

        return $this->codesScreen($twoFactor->renewCodes($this->adminId()), t('twofactor.renewed'));
    }

    /**
     * Switching it off asks for the password, for the same reason.
     *
     * @param array<string, string> $params
     */
    public function off(Request $request, string $locale, array $params): Response
    {
        $db = $this->container->get('db');
        $hash = (string) ($db->one('SELECT password_hash FROM admin WHERE id = ?', [$this->adminId()])['password_hash'] ?? '');
        if (!password_verify($request->input('password'), $hash)) {
            return $this->back(t('twofactor.password_wrong'), true);
        }
        $this->twoFactor()->disable($this->adminId());
        Activity::record($db, 'settings', 'two_step_off', null, '');

        return $this->back(t('twofactor.disabled'), false);
    }

    private function setupScreen(string $secret, ?string $error, int $status): Response
    {
        $db = $this->container->get('db');
        $email = (string) ($db->one('SELECT email FROM admin WHERE id = ?', [$this->adminId()])['email'] ?? '');
        $uri = $this->twoFactor()->uri($secret, $email, Settings::text($db, 'site_name'));

        return AdminView::render($this->container, __DIR__ . '/views', 'two-step-setup', [
            'title' => t('twofactor.setup_title'),
            'nav' => 'settings',
            'styles' => ['admin-two-step.css'],
            'qr' => TwoFactor::qr($uri),
            'secret' => $secret,
            'error' => $error,
        ], $status);
    }

    /**
     * @param list<string> $codes
     */
    private function codesScreen(array $codes, string $said): Response
    {
        return AdminView::render($this->container, __DIR__ . '/views', 'two-step-codes', [
            'title' => t('twofactor.codes_title'),
            'nav' => 'settings',
            'styles' => ['admin-two-step.css'],
            'codes' => $codes,
            'said' => $said,
        ]);
    }

    private function back(string $message, bool $error): Response
    {
        $session = $this->container->get('session');
        $session->set('flash', $message);
        if ($error) {
            $session->set('flash_kind', 'error');
        }

        // To Your login, which holds two-step login since D-132; #two-step is its heading.
        return Response::redirect(Url::admin('settings') . '#two-step');
    }

    private function twoFactor(): TwoFactor
    {
        return new TwoFactor($this->container->get('db'), (string) $this->container->get('config')->get('app.key'));
    }

    private function adminId(): int
    {
        return (int) $this->container->get('session')->get('admin_id');
    }
}
