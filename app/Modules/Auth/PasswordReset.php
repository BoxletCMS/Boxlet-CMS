<?php

namespace App\Modules\Auth;

use App\Core\Db;
use App\Modules\Admin\Activity;

/**
 * A way back in for an admin who forgot the password (PLAN.md D-132). Two of them:
 *
 *   by email   a one-use link, good for an hour, when the site can send mail
 *   by FTP     a file in storage/ holding the new password, for when it cannot
 *
 * NEITHER SWITCHES TWO-STEP LOGIN OFF. A new password is a new password; the code from
 * the phone is still asked for, and storage/disable-2fa is the way past that (D-050).
 */
final class PasswordReset
{
    public const LIFETIME_SECONDS = 3600;

    /** The file an owner with FTP access puts the new password in. */
    public const FILE = 'reset-password';

    /**
     * A new link's token for the admin with $email, or null when there is no such admin.
     * Only its hash is stored; asking again replaces the link asked for before.
     */
    public static function issue(Db $db, string $email, int $now): ?string
    {
        $admin = $db->one('SELECT id FROM admin WHERE email = ?', [$email]);
        if ($admin === null) {
            return null;
        }
        $token = bin2hex(random_bytes(32));
        $db->query('UPDATE admin SET reset_hash = ?, reset_expires_at = ? WHERE id = ?', [
            hash('sha256', $token),
            gmdate('Y-m-d H:i:s', $now + self::LIFETIME_SECONDS),
            (int) $admin['id'],
        ]);

        return $token;
    }

    /** The admin a link's token belongs to while it still works, or null. */
    public static function holder(Db $db, string $token, int $now): ?int
    {
        if (preg_match('~^[0-9a-f]{64}$~', $token) !== 1) {
            return null;
        }
        $admin = $db->one('SELECT id, reset_expires_at FROM admin WHERE reset_hash = ?', [hash('sha256', $token)]);
        if ($admin === null || (string) $admin['reset_expires_at'] < gmdate('Y-m-d H:i:s', $now)) {
            return null;
        }

        return (int) $admin['id'];
    }

    /** Sets the password and spends the link: it opens nothing a second time. */
    public static function complete(Db $db, int $adminId, string $password): void
    {
        $db->query('UPDATE admin SET password_hash = ?, reset_hash = NULL, reset_expires_at = NULL WHERE id = ?', [
            password_hash($password, PASSWORD_DEFAULT),
            $adminId,
        ]);
        Activity::record($db, 'account', 'password_reset', null, '');
    }

    /**
     * The FTP way: when storage/reset-password exists, its first line is the new password.
     *
     * THE FILE GOES FIRST. It is deleted before the password is set, and if it cannot be,
     * nothing is set at all: a password left lying on the server is worse than one not yet
     * changed. It sets the password rather than opening a form, so nobody who happens by
     * between the upload and the owner's visit can use the moment.
     *
     * Returns what to tell the owner on the login form, or null when there was no file.
     */
    public static function fromFile(Db $db, string $storagePath): ?string
    {
        $file = $storagePath . '/' . self::FILE;
        if (!is_file($file)) {
            return null;
        }
        $shown = 'storage/' . self::FILE;
        $password = rtrim((string) strtok((string) file_get_contents($file), "\n"), "\r");
        if (!@unlink($file)) {
            return t('account.file_left', ['file' => $shown]);
        }
        $problem = Password::problem($password);
        if ($problem !== null) {
            return t('account.file_refused', ['file' => $shown, 'problem' => $problem]);
        }
        $db->query('UPDATE admin SET password_hash = ?, reset_hash = NULL, reset_expires_at = NULL', [password_hash($password, PASSWORD_DEFAULT)]);
        Activity::record($db, 'account', 'password_file', null, '');

        return t('account.file_done', ['file' => $shown]);
    }
}
