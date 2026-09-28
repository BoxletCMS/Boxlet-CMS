<?php

namespace App\Modules\Auth;

use App\Core\Db;

/**
 * Login rate limit: at most MAX_FAILURES failed attempts per WINDOW_SECONDS, counted
 * separately per IP and per account (SPEC §6). Times are passed in, so tests can move
 * the clock.
 */
final class LoginThrottle
{
    public const MAX_FAILURES = 5;
    public const WINDOW_SECONDS = 900;

    public function __construct(private readonly Db $db)
    {
    }

    public function isLocked(string $ipHash, string $emailHash, int $now): bool
    {
        $row = $this->db->one(
            'SELECT
                SUM(CASE WHEN ip_hash = ? THEN 1 ELSE 0 END) AS ip_failures,
                SUM(CASE WHEN email_hash = ? THEN 1 ELSE 0 END) AS email_failures
             FROM login_attempts
             WHERE successful = 0 AND attempted_at >= ? AND (ip_hash = ? OR email_hash = ?)',
            [$ipHash, $emailHash, self::timestamp($now - self::WINDOW_SECONDS), $ipHash, $emailHash],
        );

        return (int) ($row['ip_failures'] ?? 0) >= self::MAX_FAILURES
            || (int) ($row['email_failures'] ?? 0) >= self::MAX_FAILURES;
    }

    /**
     * Records an attempt and prunes rows older than the window, so the table stays
     * bounded. Attempts made while locked are not recorded, so a lock always expires.
     */
    public function record(string $ipHash, string $emailHash, bool $successful, int $now): void
    {
        $this->db->query(
            'DELETE FROM login_attempts WHERE attempted_at < ?',
            [self::timestamp($now - self::WINDOW_SECONDS)],
        );
        $this->db->query(
            'INSERT INTO login_attempts (ip_hash, email_hash, successful, attempted_at) VALUES (?, ?, ?, ?)',
            [$ipHash, $emailHash, $successful ? 1 : 0, self::timestamp($now)],
        );
    }

    private static function timestamp(int $unix): string
    {
        return gmdate('Y-m-d H:i:s', $unix);
    }
}
