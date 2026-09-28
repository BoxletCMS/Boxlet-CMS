<?php

namespace App\Core;

use Closure;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Thin PDO wrapper for MySQL and SQLite. Connects on first use. SQL passed in must be
 * portable between both engines (SPEC §5.0).
 */
final class Db
{
    private ?PDO $pdo = null;

    public function __construct(
        public readonly string $driver,
        private readonly string $dsn,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
    ) {
        if ($driver !== 'mysql' && $driver !== 'sqlite') {
            throw new InvalidArgumentException("Unsupported database driver: {$driver}");
        }
    }

    /**
     * @param array<mixed> $config shaped like config/database.php
     */
    public static function fromConfig(array $config): self
    {
        if (($config['driver'] ?? '') === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                (string) ($config['host'] ?? ''),
                (int) ($config['port'] ?? 3306),
                (string) ($config['database'] ?? ''),
            );

            return new self('mysql', $dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''));
        }

        return new self('sqlite', 'sqlite:' . (string) ($config['path'] ?? ''));
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = new PDO($this->dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            if ($this->driver === 'sqlite') {
                $this->pdo->exec('PRAGMA foreign_keys = ON');
            }
        }

        return $this->pdo;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * Rows in result order, keyed by column name. It is a list at runtime, but PDO's
     * fetchAll() is typed as plain array, so the annotation says int keys rather than
     * claim a list the analyser cannot verify.
     *
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public function lastInsertId(): string
    {
        return (string) $this->pdo()->lastInsertId();
    }

    /**
     * Runs $work inside a transaction: commit on success, roll back on any throw, and
     * the exception re-thrown. Silence is never the default — a caller that wants to
     * swallow one has to say so.
     *
     * A transaction belongs to the database rather than to whatever is being saved, which
     * is why it lives here rather than in the model that happened to need it first.
     * Design, Installer and Migrator still open theirs by hand and could use this.
     *
     * @template T
     * @param Closure(): T $work
     * @return T
     */
    public function transaction(Closure $work): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $result = $work();
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
