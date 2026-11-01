<?php

namespace App\Modules\Mcp;

use App\Core\Db;

/**
 * THE KEYS AN AI CLIENT USES (PLAN.md O-78): made by the owner, shown once, kept as a keyed hash,
 * revoked with a press. A key reads; reads and writes drafts; or also publishes (D-291). Nothing
 * more is in it.
 *
 * And the per-key limit the protocol asks a server to keep ("rate limit tool invocations"):
 * so many calls a minute, counted in mcp_calls, the old rows deleted as calls come in.
 *
 * @phpstan-type Key array{id: int, name: string, can_write: bool, can_publish: bool, created_at: string, last_used_at: string|null, revoked_at: string|null}
 */
final class McpKeys
{
    /** What every key starts with, so one pasted in the wrong place is recognised for what it is. */
    public const PREFIX = 'bxk_';
    public const PER_MINUTE = 120;

    /**
     * A new key: its row, and the key itself, which is never shown again. One that publishes
     * writes too.
     *
     * @return array{id: int, key: string}
     */
    public static function create(Db $db, string $name, bool $write, string $appKey, bool $publish = false): array
    {
        $key = self::PREFIX . bin2hex(random_bytes(24));
        $db->query(
            'INSERT INTO mcp_keys (name, key_hash, can_write, can_publish, created_at) VALUES (?, ?, ?, ?, ?)',
            [mb_substr(trim($name), 0, 190), self::hash($key, $appKey), $write || $publish ? 1 : 0, $publish ? 1 : 0, gmdate('Y-m-d H:i:s')],
        );

        return ['id' => (int) $db->lastInsertId(), 'key' => $key];
    }

    /**
     * The key a request carries, when it is one and not revoked; null otherwise.
     *
     * @return Key|null
     */
    public static function find(Db $db, string $key, string $appKey): ?array
    {
        if (!str_starts_with($key, self::PREFIX) || strlen($key) > 100) {
            return null;
        }
        $row = $db->one('SELECT * FROM mcp_keys WHERE key_hash = ? AND revoked_at IS NULL', [self::hash($key, $appKey)]);

        return $row === null ? null : self::row($row);
    }

    /**
     * Every key, newest first, revoked ones too.
     *
     * @return list<Key>
     */
    public static function all(Db $db): array
    {
        return array_map(self::row(...), array_values($db->all('SELECT * FROM mcp_keys ORDER BY id DESC')));
    }

    public static function revoke(Db $db, int $id): void
    {
        $db->query('UPDATE mcp_keys SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL', [gmdate('Y-m-d H:i:s'), $id]);
    }

    /** Takes a revoked key off the list; one still in use is revoked first, never removed. */
    public static function remove(Db $db, int $id): void
    {
        $db->query('DELETE FROM mcp_calls WHERE key_id = ? AND EXISTS (SELECT 1 FROM mcp_keys WHERE id = ? AND revoked_at IS NOT NULL)', [$id, $id]);
        $db->query('DELETE FROM mcp_keys WHERE id = ? AND revoked_at IS NOT NULL', [$id]);
    }

    /**
     * Counts a call against its key; false when the key has made its minute's worth already.
     */
    public static function allow(Db $db, int $keyId): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $since = gmdate('Y-m-d H:i:s', time() - 60);
        $db->query('DELETE FROM mcp_calls WHERE created_at < ?', [$since]);
        $made = (int) ($db->one('SELECT COUNT(*) AS n FROM mcp_calls WHERE key_id = ? AND created_at >= ?', [$keyId, $since])['n'] ?? 0);
        if ($made >= self::PER_MINUTE) {
            return false;
        }
        $db->query('INSERT INTO mcp_calls (key_id, created_at) VALUES (?, ?)', [$keyId, $now]);
        $db->query('UPDATE mcp_keys SET last_used_at = ? WHERE id = ?', [$now, $keyId]);

        return true;
    }

    private static function hash(string $key, string $appKey): string
    {
        return hash_hmac('sha256', $key, $appKey);
    }

    /**
     * @param array<string, mixed> $row
     * @return Key
     */
    private static function row(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'can_write' => (int) $row['can_write'] === 1,
            'can_publish' => (int) $row['can_write'] === 1 && (int) ($row['can_publish'] ?? 0) === 1,
            'created_at' => (string) $row['created_at'],
            'last_used_at' => $row['last_used_at'] === null ? null : (string) $row['last_used_at'],
            'revoked_at' => $row['revoked_at'] === null ? null : (string) $row['revoked_at'],
        ];
    }
}
