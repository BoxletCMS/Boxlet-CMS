<?php

namespace App\Modules\Forms;

/**
 * When a form was drawn, signed so a sender cannot claim another time (SPEC §6: a minimum
 * three-second delay before a form may be sent).
 *
 * A person reads a form before sending it; a spam script posts the moment it has the page,
 * or without the page at all. The token is the time the page was drawn and an HMAC of it and
 * the form's id, keyed by APP_KEY. No session: visitors have none, and a contact form must
 * not start one.
 *
 * There is no upper limit, on purpose. A page left open overnight is still a person, and the
 * page cache to come (Slice 8) will serve a drawn page for as long as it is cached — which
 * also means that on a cached page the delay counts from when it was cached, not from when
 * the visitor opened it. The honeypot and the rate limit are what stand beside it there.
 */
final class FormToken
{
    public const MIN_SECONDS = 3;

    public static function issue(int $formId, string $appKey, ?int $now = null): string
    {
        $time = (string) ($now ?? time());

        return $time . '.' . self::sign($formId, $time, $appKey);
    }

    /**
     * How many seconds ago the form was drawn, or null when the token is not one this site
     * issued for this form.
     */
    public static function age(string $token, int $formId, string $appKey, ?int $now = null): ?int
    {
        if (preg_match('~^(\d{9,11})\.([0-9a-f]{64})$~', $token, $parts) !== 1) {
            return null;
        }
        if (!hash_equals(self::sign($formId, $parts[1], $appKey), $parts[2])) {
            return null;
        }

        return ($now ?? time()) - (int) $parts[1];
    }

    private static function sign(int $formId, string $time, string $appKey): string
    {
        return hash_hmac('sha256', 'form:' . $formId . ':' . $time, $appKey);
    }
}
