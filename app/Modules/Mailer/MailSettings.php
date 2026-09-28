<?php

namespace App\Modules\Mailer;

use App\Core\Db;
use App\Core\Settings;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * How this site sends mail (PLAN.md D-045): which way, from whom, to whom notifications go,
 * and the account details the way needs. The only place a `mail_` setting is spelled.
 *
 * Passwords and keys are stored sealed (Secret) and never sent back to the screen: the form
 * shows whether one is set, and an empty field on save keeps it. An owner re-typing a
 * password every time they fix the sender's name is how passwords end up in notes.
 */
final class MailSettings
{
    /** The ways a site can send. '' is none: forms still store what is sent, and say so. */
    public const TRANSPORTS = ['', 'smtp', 'resend', 'sendmail'];

    /**
     * STARTTLS or SSL. No "none": Symfony Mailer 6.4 upgrades to TLS whenever the server
     * offers it and has no switch to stop that, and a choice that does nothing is worse
     * than no choice.
     */
    public const ENCRYPTIONS = ['starttls', 'ssl'];

    private const KEYS = [
        'mail_transport', 'mail_from_address', 'mail_from_name', 'mail_notify_address',
        'mail_smtp_host', 'mail_smtp_port', 'mail_smtp_encryption', 'mail_smtp_username',
        'mail_smtp_password', 'mail_resend_key',
    ];

    /**
     * What is stored, secrets still sealed.
     *
     * @return array<string, string>
     */
    public static function stored(Db $db): array
    {
        $values = Settings::many($db, self::KEYS, '');
        $stored = [];
        foreach (self::KEYS as $key) {
            $value = $values[$key] ?? '';
            $stored[substr($key, 5)] = is_scalar($value) ? (string) $value : '';
        }
        if (!in_array($stored['transport'], self::TRANSPORTS, true)) {
            $stored['transport'] = '';
        }
        if (!in_array($stored['smtp_encryption'], self::ENCRYPTIONS, true)) {
            $stored['smtp_encryption'] = 'starttls';
        }

        return $stored;
    }

    /**
     * Checks and writes what the form sent. An empty password or key keeps the stored one.
     * Returns the errors by field, empty when saved.
     *
     * @param array<string, string> $input field => value, without the mail_ prefix
     * @return array<string, string>
     */
    public static function save(Db $db, array $input, string $appKey): array
    {
        $value = static fn (string $key): string => trim($input[$key] ?? '');
        $transport = $value('transport');
        $errors = [];
        if (!in_array($transport, self::TRANSPORTS, true)) {
            $errors['transport'] = t('mail.transport_invalid');
        }
        foreach (['from_address', 'notify_address'] as $key) {
            if ($value($key) !== '' && filter_var($value($key), FILTER_VALIDATE_EMAIL) === false) {
                $errors[$key] = t('mail.address_invalid');
            }
        }
        if ($transport !== '' && $value('from_address') === '') {
            $errors['from_address'] = t('mail.from_required');
        }
        $port = $value('smtp_port');
        if ($transport === 'smtp') {
            if ($value('smtp_host') === '') {
                $errors['smtp_host'] = t('mail.smtp_host_required');
            }
            if ($port !== '' && (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535)) {
                $errors['smtp_port'] = t('mail.smtp_port_invalid');
            }
        }
        $stored = self::stored($db);
        if ($transport === 'resend' && $value('resend_key') === '' && $stored['resend_key'] === '') {
            $errors['resend_key'] = t('mail.resend_key_required');
        }
        if ($errors !== []) {
            return $errors;
        }

        $write = [
            'transport' => $transport,
            'from_address' => $value('from_address'),
            'from_name' => $value('from_name'),
            'notify_address' => $value('notify_address'),
            'smtp_host' => $value('smtp_host'),
            'smtp_port' => $port,
            'smtp_encryption' => in_array($value('smtp_encryption'), self::ENCRYPTIONS, true) ? $value('smtp_encryption') : 'starttls',
            'smtp_username' => $value('smtp_username'),
        ];
        foreach (['smtp_password', 'resend_key'] as $secret) {
            if ($value($secret) !== '') {
                $write[$secret] = Secret::seal($value($secret), $appKey);
            }
        }
        foreach ($write as $key => $each) {
            Settings::set($db, 'mail_' . $key, $each);
        }

        return [];
    }

    /**
     * The transport the stored settings describe. NullTransport when none is chosen, so a
     * caller never has to ask whether mail is set up before handing a message over.
     */
    public static function transport(Db $db, string $appKey): TransportInterface
    {
        $stored = self::stored($db);

        return match ($stored['transport']) {
            'smtp' => self::smtp($stored, $appKey),
            'resend' => new ResendTransport(Secret::open($stored['resend_key'], $appKey)),
            'sendmail' => new SendmailTransport(),
            default => new NullTransport(),
        };
    }

    /**
     * Records that a message could not be sent, and why, for the admin to show (D-046): a
     * form's notification fails after the visitor has been thanked, so nobody else would
     * ever know. Only the last failure is kept; a test message that succeeds clears it.
     */
    public static function recordFailure(Db $db, string $reason): void
    {
        Settings::set($db, 'mail_last_failure', ['at' => gmdate('Y-m-d H:i:s'), 'reason' => mb_substr($reason, 0, 500)]);
    }

    public static function clearFailure(Db $db): void
    {
        Settings::set($db, 'mail_last_failure', null);
    }

    /**
     * The last message that could not be sent, or null.
     *
     * @return array{at: string, reason: string}|null
     */
    public static function lastFailure(Db $db): ?array
    {
        $failure = Settings::get($db, 'mail_last_failure');

        return is_array($failure) && is_string($failure['at'] ?? null) && is_string($failure['reason'] ?? null)
            ? ['at' => $failure['at'], 'reason' => $failure['reason']]
            : null;
    }

    /**
     * What a saved secret shows in its empty field, so a panel with a key set does not look
     * like one without (the owner's report, 2026-09-19): an API key by its first three and
     * last four characters — enough to tell which key it is, as Resend's own dashboard
     * shows them — and a password as dots only. '' when nothing is saved.
     */
    public static function trace(string $sealed, string $appKey, bool $showEnds): string
    {
        $plain = Secret::open($sealed, $appKey);
        if ($plain === '') {
            return $sealed === '' ? '' : '••••••••';
        }
        if (!$showEnds || mb_strlen($plain) < 12) {
            return '••••••••';
        }

        return mb_substr($plain, 0, 3) . '…' . mb_substr($plain, -4);
    }

    /** Whether a way of sending is chosen at all. */
    public static function configured(Db $db): bool
    {
        return self::stored($db)['transport'] !== '';
    }

    /**
     * Who the site's mail comes from, and where notifications go: the notification
     * address the owner gave, else the admin's own login address.
     *
     * @return array{from: string, fromName: string, notify: string}
     */
    public static function addresses(Db $db): array
    {
        $stored = self::stored($db);
        $admin = (string) ($db->one('SELECT email FROM admin ORDER BY id LIMIT 1')['email'] ?? '');

        return [
            'from' => $stored['from_address'],
            'fromName' => $stored['from_name'] !== '' ? $stored['from_name'] : Settings::text($db, 'site_name'),
            'notify' => $stored['notify_address'] !== '' ? $stored['notify_address'] : $admin,
        ];
    }

    /**
     * @param array<string, string> $stored
     */
    private static function smtp(array $stored, string $appKey): EsmtpTransport
    {
        $encryption = $stored['smtp_encryption'];
        $port = (int) ($stored['smtp_port'] !== '' ? $stored['smtp_port'] : ($encryption === 'ssl' ? 465 : 587));
        // ssl: TLS from the first byte (465). starttls: plain, upgraded when the server
        // offers it (587), which Symfony does by itself.
        $transport = new EsmtpTransport($stored['smtp_host'], $port, $encryption === 'ssl');
        if ($stored['smtp_username'] !== '') {
            $transport->setUsername($stored['smtp_username']);
            $transport->setPassword(Secret::open($stored['smtp_password'], $appKey));
        }

        return $transport;
    }
}
