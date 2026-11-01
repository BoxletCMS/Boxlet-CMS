<?php

namespace App\Modules\Mailer;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Modules\Admin\Activity;
use App\Support\Url;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * The Mail panel on the Settings screen (PLAN.md D-045): save how the site sends, and send
 * a test message to prove it does. Both answer with a redirect to the panel; a refused save
 * carries its errors and what was typed through the session, secrets left out, so the
 * screen shows them once.
 */
final class MailController
{
    /** The panel's fields, as posted: mail_{field}. */
    public const FIELDS = [
        'transport', 'from_address', 'from_name', 'notify_address', 'smtp_host', 'smtp_port',
        'smtp_encryption', 'smtp_username', 'smtp_password', 'resend_key', 'brevo_key',
        'mailgun_key', 'mailgun_domain', 'mailgun_region',
    ];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function save(Request $request, string $locale, array $params): Response
    {
        $input = [];
        foreach (self::FIELDS as $field) {
            $input[$field] = $request->input('mail_' . $field);
        }
        $errors = MailSettings::save($this->db(), $input, $this->appKey());
        $session = $this->container->get('session');
        if ($errors !== []) {
            foreach (MailSettings::SECRETS as $secret) {
                unset($input[$secret]);
            }
            $session->set('mail_form', ['errors' => $errors, 'old' => $input]);
            $session->set('flash', t('mail.not_saved'));
            $session->set('flash_kind', 'error');
        } else {
            Activity::record($this->db(), 'settings', 'mail_saved', null, '');
            $session->set('flash', t('mail.saved'));
        }

        return Response::redirect(Url::admin('settings', 'mail'));
    }

    /**
     * Sends one message to the notification address, the way every form will, and says
     * what happened — the server's own words when it refused.
     *
     * @param array<string, string> $params
     */
    public function test(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $session = $this->container->get('session');
        $addresses = MailSettings::addresses($db);
        if (!MailSettings::configured($db)) {
            $session->set('flash', t('mail.test_not_configured'));
            $session->set('flash_kind', 'error');

            return Response::redirect(Url::admin('settings', 'mail'));
        }

        $site = Settings::text($db, 'site_name');
        try {
            $email = (new Email())
                ->from(new Address($addresses['from'], $addresses['fromName']))
                ->to($addresses['notify'])
                ->subject(t('mail.test_subject', ['site' => $site]))
                ->text(t('mail.test_body', ['site' => $site]));
            $this->container->get('mail_transport')->send($email);
            MailSettings::clearFailure($db);
            $session->set('flash', t('mail.test_sent', ['address' => $addresses['notify']]));
        } catch (TransportExceptionInterface | RfcComplianceException $e) {
            $session->set('flash', t('mail.test_failed', ['reason' => $e->getMessage()]));
            $session->set('flash_kind', 'error');
        }

        return Response::redirect(Url::admin('settings', 'mail'));
    }

    private function appKey(): string
    {
        return (string) $this->container->get('config')->get('app.key');
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }

    /**
     * The Email tab's values (D-045, D-294): what is stored, or what was typed into a refused
     * save, with its errors — shown once. Secrets never reach the screen, only whether
     * one is set.
     *
     * @return array{mail: array<string, string>, mailErrors: array<string, string>, mailSecrets: array<string, string>}
     */
    public static function panel(Container $container): array
    {
        $stored = MailSettings::stored($container->get('db'));
        $appKey = (string) $container->get('config')->get('app.key');
        $session = $container->get('session');
        $refused = $session->get('mail_form');
        $session->remove('mail_form');
        $old = is_array($refused) && is_array($refused['old'] ?? null) ? $refused['old'] : [];
        $errors = is_array($refused) && is_array($refused['errors'] ?? null) ? $refused['errors'] : [];

        $values = [];
        foreach (self::FIELDS as $field) {
            $values[$field] = in_array($field, MailSettings::SECRETS, true)
                ? ''
                : (is_string($old[$field] ?? null) ? $old[$field] : $stored[$field]);
        }

        return [
            'mail' => $values,
            'mailErrors' => array_filter($errors, 'is_string'),
            // What each saved secret shows in its empty field: a trace, never the secret.
            // A key by its ends, a password by dots only.
            'mailSecrets' => array_combine(MailSettings::SECRETS, array_map(
                static fn (string $secret): string => MailSettings::trace($stored[$secret], $appKey, $secret !== 'smtp_password'),
                MailSettings::SECRETS,
            )),
        ];
    }
}
