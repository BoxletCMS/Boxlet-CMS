<?php

namespace App\Modules\Auth;

use App\Core\Request;

/**
 * A BROWSER THAT HAS LOGGED IN HERE BEFORE (PLAN.md O-65). Five failures for an email locked
 * it from every address for fifteen minutes, and "forgot password" counted as one, so anyone
 * who knew the owner's email could keep them out with a request every three minutes. A
 * browser that has logged in as that email carries this cookie, and the lock for the email
 * does not stop it; the lock for its address still does.
 *
 * The cookie is a random value and a signature over it and the email's hash, made with the
 * site's key: it cannot be made up, and it names no one. Sent to /admin only, out of scripts'
 * reach, and kept for a year.
 */
final class KnownDevice
{
    public const NAME = 'boxlet_device';

    private const LIFETIME = 31536000;

    /** The Set-Cookie value that marks this browser as having logged in as $emailHash. */
    public static function cookie(string $appKey, string $emailHash, bool $secure): string
    {
        $nonce = bin2hex(random_bytes(16));
        $value = $nonce . '.' . self::sign($appKey, $nonce, $emailHash);

        return self::NAME . '=' . $value . '; Max-Age=' . self::LIFETIME . '; Path=/admin; HttpOnly; SameSite=Strict' . ($secure ? '; Secure' : '');
    }

    /** Whether the request carries a cookie this site made for $emailHash. */
    public static function known(Request $request, string $appKey, string $emailHash): bool
    {
        foreach (explode(';', (string) $request->header('cookie')) as $pair) {
            [$name, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            if ($name !== self::NAME || preg_match('~^([0-9a-f]{32})\.([0-9a-f]{64})$~', $value, $parts) !== 1) {
                continue;
            }
            if (hash_equals(self::sign($appKey, $parts[1], $emailHash), $parts[2])) {
                return true;
            }
        }

        return false;
    }

    private static function sign(string $appKey, string $nonce, string $emailHash): string
    {
        return hash_hmac('sha256', 'known-device:' . $nonce . ':' . $emailHash, $appKey);
    }
}
