<?php

namespace App\Modules\Install;

use App\Core\Db;
use PDOException;
use RuntimeException;

/**
 * Builds and checks the database chosen in the installer. Every failure is a
 * RuntimeException whose message names the actual problem in words a site owner can
 * act on, never just "could not connect".
 */
final class DatabaseSetup
{
    /**
     * @param array{host: string, port: int, database: string, username: string, password: string} $mysql
     */
    public static function mysql(array $mysql): Db
    {
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException(t('install.db.no_pdo_mysql'));
        }
        // These go into a DSN, where ; would start a new parameter.
        if (!preg_match('~^[A-Za-z0-9._\-\[\]:]{1,255}$~', $mysql['host'])) {
            throw new RuntimeException(t('install.db.bad_host'));
        }
        if (!preg_match('~^[A-Za-z0-9_$\-]{1,64}$~', $mysql['database'])) {
            throw new RuntimeException(t('install.db.bad_name'));
        }

        $db = Db::fromConfig(['driver' => 'mysql'] + $mysql);
        try {
            $db->pdo();
        } catch (PDOException $e) {
            throw new RuntimeException(self::mysqlMessage($e, $mysql), 0, $e);
        }

        $row = $db->one('SELECT @@character_set_database AS charset');
        $charset = (string) ($row['charset'] ?? '');
        if ($charset !== 'utf8mb4') {
            throw new RuntimeException(t('install.db.charset', ['database' => $mysql['database'], 'charset' => $charset]));
        }
        self::assertNoBoxletTables($db);

        return $db;
    }

    public static function sqlite(string $root, string $path): Db
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException(t('install.db.no_pdo_sqlite'));
        }
        if (!str_ends_with($path, '.sqlite')) {
            throw new RuntimeException(t('install.db.sqlite_extension'));
        }
        $absolute = str_starts_with($path, '/') ? $path : $root . '/' . $path;
        $directory = dirname($absolute);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException(t('install.db.sqlite_dir', ['path' => $directory]));
        }
        // A database under the document root could be downloaded by anyone.
        $public = realpath($root . '/public');
        $real = realpath($directory);
        if ($public !== false && $real !== false && str_starts_with($real . '/', $public . '/')) {
            throw new RuntimeException(t('install.db.sqlite_public'));
        }

        $db = new Db('sqlite', 'sqlite:' . $absolute);
        try {
            $db->pdo();
        } catch (PDOException $e) {
            throw new RuntimeException(t('install.db.sqlite_open', ['path' => $absolute, 'error' => $e->getMessage()]), 0, $e);
        }
        self::assertNoBoxletTables($db);

        return $db;
    }

    /**
     * Reconnects from the DB_* values collected earlier, checking everything again.
     *
     * @param array<mixed> $env
     */
    public static function fromEnv(string $root, array $env): Db
    {
        if (($env['DB_DRIVER'] ?? '') === 'sqlite') {
            return self::sqlite($root, (string) ($env['DB_PATH'] ?? ''));
        }

        return self::mysql([
            'host' => (string) ($env['DB_HOST'] ?? ''),
            'port' => (int) ($env['DB_PORT'] ?? 3306),
            'database' => (string) ($env['DB_DATABASE'] ?? ''),
            'username' => (string) ($env['DB_USERNAME'] ?? ''),
            'password' => (string) ($env['DB_PASSWORD'] ?? ''),
        ]);
    }

    /**
     * Refuses to install over an existing Boxlet database.
     */
    private static function assertNoBoxletTables(Db $db): void
    {
        foreach (['migrations', 'admin'] as $table) {
            try {
                $db->query("SELECT 1 FROM {$table} LIMIT 1");
            } catch (PDOException) {
                continue; // no such table, as it should be
            }
            throw new RuntimeException(t('install.db.not_empty', ['table' => $table]));
        }
    }

    /**
     * @param array{host: string, port: int, database: string, username: string, password: string} $mysql
     */
    private static function mysqlMessage(PDOException $e, array $mysql): string
    {
        $code = preg_match('~\[(\d{4})\]~', $e->getMessage(), $match) ? (int) $match[1] : 0;
        $vars = [
            'host' => $mysql['host'],
            'port' => $mysql['port'],
            'database' => $mysql['database'],
            'user' => $mysql['username'],
        ];
        if ($code === 2002 && str_contains($e->getMessage(), 'getaddrinfo')) {
            return t('install.db.mysql_unknown_host', $vars);
        }

        return match ($code) {
            1045 => t('install.db.mysql_1045', $vars),
            1044 => t('install.db.mysql_1044', $vars),
            1049 => t('install.db.mysql_1049', $vars),
            2002 => t('install.db.mysql_2002', $vars),
            2005 => t('install.db.mysql_unknown_host', $vars),
            2006, 2013 => t('install.db.mysql_2006', $vars),
            default => t('install.db.mysql_other', $vars + ['error' => $e->getMessage()]),
        };
    }
}
