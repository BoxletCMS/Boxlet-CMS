<?php

namespace App\Modules\Mailer;

use RuntimeException;

/**
 * A password or API key kept in the settings table, sealed with the site's APP_KEY
 * (PLAN.md D-045).
 *
 * The database is the part of a site most often copied — a backup, a move to another
 * host, a dump handed to someone for help — and .env is not in it. Sealing with the key
 * from .env means a copy of the database alone gives away no mail account. It is not a
 * defence against someone holding both, which nothing on a single server can be.
 *
 * libsodium's secretbox: authenticated, so a tampered value is refused rather than
 * decrypted into nonsense, and in PHP since 7.2 with no extension to install.
 */
final class Secret
{
    private const PREFIX = 'sealed:';

    public static function seal(string $plain, string $appKey): string
    {
        if ($plain === '') {
            return '';
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key($appKey)));
    }

    /** The plain value, or '' when there is none or it cannot be opened with this key. */
    public static function open(string $sealed, string $appKey): string
    {
        if (!str_starts_with($sealed, self::PREFIX)) {
            return '';
        }
        $raw = base64_decode(substr($sealed, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }
        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::key($appKey),
        );

        return $plain === false ? '' : $plain;
    }

    private static function key(string $appKey): string
    {
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is not set. The installer writes it to .env.');
        }

        return hash('sha256', 'boxlet-secret:' . $appKey, true);
    }
}
