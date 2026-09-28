<?php

namespace App\Modules\Auth;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\View;
use App\Modules\Admin\Theme;
use App\Modules\Mailer\MailSettings;
use App\Support\ClientIp;
use App\Support\Url;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * A forgotten password, reset by a link sent by email (PLAN.md D-132).
 *
 * THE ANSWER NEVER SAYS WHETHER THE ADDRESS WAS THE ADMIN'S: the same words follow any
 * address, as the login form's failure does, and a send that failed is recorded where the
 * owner sees mail failures rather than told to whoever asked. Where the site cannot send mail
 * at all it says so plainly — that is a fact about the site, not about any account — and
 * gives the FTP way (PasswordReset::fromFile).
 *
 * Every request counts against the login throttle, so this is no way to send a hundred mails.
 */
final class ForgotController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, string $locale, array $params): Response
    {
        return $this->screen('forgot', ['sent' => false]);
    }

    /**
     * @param array<string, string> $params
     */
    public function send(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        if (!MailSettings::configured($db)) {
            return $this->screen('forgot', ['sent' => false, 'noMail' => true]);
        }

        $email = mb_strtolower(trim($request->input('email')));
        $key = (string) $this->container->get('config')->get('app.key');
        $emailHash = hash_hmac('sha256', $email, $key);
        $ipHash = hash_hmac('sha256', ClientIp::of($request, Settings::text($db, 'trusted_proxies')), $key);
        $throttle = new LoginThrottle($db);
        $now = time();
        if ($throttle->isLocked($ipHash, $emailHash, $now)) {
            return $this->screen('forgot', ['sent' => false, 'error' => t('auth.throttled', ['minutes' => intdiv(LoginThrottle::WINDOW_SECONDS, 60)])], 429);
        }
        $throttle->record($ipHash, $emailHash, false, $now);

        $token = PasswordReset::issue($db, $email, $now);
        if ($token !== null) {
            $addresses = MailSettings::addresses($db);
            $site = Settings::text($db, 'site_name');
            try {
                $this->container->get('mail_transport')->send((new Email())
                    ->from(new Address($addresses['from'], $addresses['fromName']))
                    ->to($email)
                    ->subject(t('account.mail_subject', ['site' => $site]))
                    ->text(t('account.mail_body', [
                        'site' => $site,
                        'url' => Url::withOrigin(Url::admin('reset', $token)),
                        'minutes' => (string) intdiv(PasswordReset::LIFETIME_SECONDS, 60),
                    ])));
            } catch (TransportExceptionInterface | RfcComplianceException $e) {
                MailSettings::recordFailure($db, $e->getMessage());
            }
        }

        return $this->screen('forgot', ['sent' => true]);
    }

    /**
     * @param array<string, string> $params
     */
    public function showReset(Request $request, string $locale, array $params): Response
    {
        $working = PasswordReset::holder($this->db(), (string) $params['token'], time()) !== null;

        return $this->screen('reset', ['token' => (string) $params['token'], 'working' => $working], $working ? 200 : 410);
    }

    /**
     * @param array<string, string> $params
     */
    public function reset(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $token = (string) $params['token'];
        $adminId = PasswordReset::holder($db, $token, time());
        if ($adminId === null) {
            return $this->screen('reset', ['token' => $token, 'working' => false], 410);
        }
        $problem = Password::problem($request->input('new_password'), $request->input('new_password_confirm'));
        if ($problem !== null) {
            return $this->screen('reset', ['token' => $token, 'working' => true, 'error' => $problem], 422);
        }

        PasswordReset::complete($db, $adminId, $request->input('new_password'));
        $this->container->get('session')->set('login_notice', t('account.reset_done'));

        return Response::redirect(Url::admin('login'));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function screen(string $template, array $data, int $status = 200): Response
    {
        $html = (new View(__DIR__ . '/views'))->render($template, 'en', $data + [
            'title' => t('account.forgot_title'),
            'theme' => Theme::of($this->container->get('request')),
            'csrf' => $this->container->get('session')->csrfToken(),
            'error' => null,
            'noMail' => false,
            'file' => 'storage/' . PasswordReset::FILE,
        ]);

        return Response::admin($html, $status);
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
