<?php

namespace App\Core;

use PDOException;
use RuntimeException;
use Throwable;

/**
 * Applies migrations/NNNN_name.sql in filename order and records each file in the
 * migrations table. SQL must be portable between MySQL and SQLite (SPEC §5.0), with
 * exactly one exception: {{pk}}, the only substitution token that will ever exist.
 */
final class Migrator
{
    private const PRIMARY_KEY = [
        'mysql' => 'INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY',
        'sqlite' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
    ];

    public function __construct(private readonly Db $db, private readonly string $directory)
    {
    }

    /**
     * Every pending file is compiled before the first one runs, so a bad token aborts
     * the run with nothing applied.
     *
     * @return list<string> filenames applied by this run, empty when already up to date
     */
    public function migrate(): array
    {
        $applied = $this->appliedFilenames();
        $names = [];
        $compiled = [];
        foreach ($this->files() as $file) {
            $name = basename($file);
            if (!in_array($name, $applied, true)) {
                $names[] = $name;
                $compiled[] = self::compile((string) file_get_contents($file), $this->db->driver, $name);
            }
        }

        foreach ($names as $i => $name) {
            $this->apply($name, $compiled[$i]);
        }

        return $names;
    }

    /**
     * The migration files this database has not applied, in filename order.
     *
     * Reads and compiles nothing: the update gate asks this on requests that are about
     * to be refused, so it must be a directory listing and one query, and it must never
     * be the thing that runs a migration.
     *
     * @return list<string>
     */
    public function pending(): array
    {
        $applied = $this->appliedFilenames();
        $pending = [];
        foreach ($this->files() as $file) {
            $name = basename($file);
            if (!in_array($name, $applied, true)) {
                $pending[] = $name;
            }
        }

        return $pending;
    }

    /**
     * Turns one migration file into statements for $driver. Statements end with a
     * semicolon at the end of a line; whole-line "--" comments are ignored.
     *
     * @return list<string>
     */
    public static function compile(string $sql, string $driver, string $name): array
    {
        if (!isset(self::PRIMARY_KEY[$driver])) {
            throw new RuntimeException("{$name}: unsupported driver {$driver}");
        }
        $sql = (string) preg_replace('~^\s*--.*$~m', '', $sql);

        // INTEGER AUTO_INCREMENT PRIMARY KEY parses on SQLite but silently stores NULL ids.
        if (preg_match('~AUTO_?INCREMENT~i', $sql)) {
            throw new RuntimeException("{$name}: declare auto-increment ids as {{pk}}, never directly");
        }
        preg_match_all('~\{\{.*?\}\}~s', $sql, $tokens);
        foreach ($tokens[0] as $token) {
            if ($token !== '{{pk}}') {
                throw new RuntimeException("{$name}: unknown token {$token}; {{pk}} is the only token");
            }
        }
        $sql = str_replace('{{pk}}', self::PRIMARY_KEY[$driver], $sql);
        if (str_contains($sql, '{{') || str_contains($sql, '}}')) {
            throw new RuntimeException("{$name}: unbalanced {{ or }}; {{pk}} is the only token");
        }

        $statements = [];
        foreach (preg_split('~;\s*(?:\n|$)~', $sql) ?: [] as $statement) {
            if (trim($statement) !== '') {
                $statements[] = trim($statement);
            }
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $files = [];
        foreach (glob($this->directory . '/*.sql') ?: [] as $file) {
            if (!preg_match('~^\d{4}_[a-z0-9_]+\.sql$~', basename($file))) {
                throw new RuntimeException('Migration filenames look like 0001_name.sql: ' . basename($file));
            }
            $files[] = $file;
        }
        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function appliedFilenames(): array
    {
        // Connect first: a connection error must surface, not read as "nothing applied".
        $this->db->pdo();
        try {
            $rows = $this->db->all('SELECT filename FROM migrations');
        } catch (PDOException) {
            return []; // no migrations table yet; 0001 creates it
        }

        return array_values(array_map(static fn (array $row): string => (string) $row['filename'], $rows));
    }

    /**
     * @param list<string> $statements
     */
    private function apply(string $name, array $statements): void
    {
        $pdo = $this->db->pdo();
        // MySQL commits DDL implicitly, so only SQLite can roll a failed file back.
        $transactional = $this->db->driver === 'sqlite';
        if ($transactional) {
            $pdo->beginTransaction();
        }
        try {
            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }
            $this->db->query(
                'INSERT INTO migrations (filename, applied_at) VALUES (?, ?)',
                [$name, gmdate('Y-m-d H:i:s')],
            );
            if ($transactional) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($transactional && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException("Migration {$name} failed: {$e->getMessage()}", 0, $e);
        }
    }
}
