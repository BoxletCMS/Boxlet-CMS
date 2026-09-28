<?php

namespace App\Modules\Backup;

use App\Core\Db;

/**
 * What a backup reads out of the database and a restore writes back (PLAN.md D-139): which
 * tables, which columns, and how to walk a table in pieces that a later request can carry
 * on from.
 *
 * Asked of the database itself rather than listed here, so a table a later migration adds
 * is in the next backup without anyone remembering to add it.
 */
final class BackupTables
{
    /**
     * Left out, each for a reason:
     *   - login_attempts: the lockout's own record, which would lock the owner out again, or
     *     let someone in, when brought back at another time
     *   - stats_seen: the day's visitor keys, deleted each day so a visit cannot be linked to
     *     another day's (D-051); a backup keeping them for months would undo that
     */
    public const LEFT_OUT = ['login_attempts', 'stats_seen'];

    public const ROWS_PER_READ = 500;

    /**
     * Every table a backup holds, in a fixed order.
     *
     * @return list<string>
     */
    public static function all(Db $db): array
    {
        $rows = $db->driver === 'sqlite'
            ? $db->all("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
            : $db->all('SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name');
        $tables = [];
        foreach ($rows as $row) {
            $name = (string) $row['name'];
            if (!in_array($name, self::LEFT_OUT, true)) {
                $tables[] = $name;
            }
        }

        return $tables;
    }

    /**
     * The table's primary key when it is one column, which lets a table be walked by key;
     * null for a table with none, which is walked by position instead.
     */
    public static function key(Db $db, string $table): ?string
    {
        if ($db->driver === 'sqlite') {
            $key = [];
            foreach ($db->all('PRAGMA table_info(' . self::quote($db, $table) . ')') as $column) {
                if ((int) $column['pk'] > 0) {
                    $key[] = (string) $column['name'];
                }
            }
        } else {
            $key = array_map(
                static fn (array $row): string => (string) $row['name'],
                $db->all(
                    "SELECT column_name AS name FROM information_schema.columns
                     WHERE table_schema = DATABASE() AND table_name = ? AND column_key = 'PRI'",
                    [$table],
                ),
            );
        }

        return count($key) === 1 ? $key[0] : null;
    }

    /**
     * The next piece of $table after $after (a key, for a table walked by key) or $offset
     * rows in (for one walked by position).
     *
     * A table with no key is ordered by every column, so the same rows come back in the same
     * order each time. Rows written into it while the backup runs (a visit counted) may be
     * missed or read twice; the only tables like that are the statistics.
     *
     * @return list<array<string, mixed>>
     */
    public static function read(Db $db, string $table, ?string $key, mixed $after, int $offset): array
    {
        $name = self::quote($db, $table);
        if ($key !== null) {
            $column = self::quote($db, $key);

            return array_values($after === null
                ? $db->all("SELECT * FROM {$name} ORDER BY {$column} LIMIT " . self::ROWS_PER_READ)
                : $db->all("SELECT * FROM {$name} WHERE {$column} > ? ORDER BY {$column} LIMIT " . self::ROWS_PER_READ, [$after]));
        }
        $columns = self::columns($db, $table);
        $order = implode(', ', array_map(static fn (string $c): string => self::quote($db, $c), $columns));

        return array_values($db->all("SELECT * FROM {$name} ORDER BY {$order} LIMIT " . self::ROWS_PER_READ . ' OFFSET ' . $offset));
    }

    /**
     * @return list<string>
     */
    public static function columns(Db $db, string $table): array
    {
        if ($db->driver === 'sqlite') {
            return array_values(array_map(static fn (array $c): string => (string) $c['name'], $db->all('PRAGMA table_info(' . self::quote($db, $table) . ')')));
        }

        return array_values(array_map(
            static fn (array $row): string => (string) $row['name'],
            $db->all(
                'SELECT column_name AS name FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position',
                [$table],
            ),
        ));
    }

    /** A table or column name, quoted for the driver. Names come from the database itself. */
    public static function quote(Db $db, string $name): string
    {
        return $db->driver === 'mysql'
            ? '`' . str_replace('`', '``', $name) . '`'
            : '"' . str_replace('"', '""', $name) . '"';
    }
}
