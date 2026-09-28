<?php

namespace App\Modules\Auth;

/**
 * What a new admin password must be (PLAN.md D-132): at least twelve characters, typed the
 * same twice. One rule for every place a password is set — the installer, Your login, and
 * a forgotten password's reset — so they cannot drift apart.
 */
final class Password
{
    public const MIN = 12;

    /**
     * Why $password would be refused, in words, or null. $confirm null skips the second
     * typing, for a password that arrives once (a file put there over FTP).
     */
    public static function problem(string $password, ?string $confirm = null): ?string
    {
        if (mb_strlen($password) < self::MIN) {
            return t('install.admin.short_password', ['min' => self::MIN]);
        }
        if ($confirm !== null && !hash_equals($password, $confirm)) {
            return t('install.admin.mismatch');
        }

        return null;
    }
}
