<?php

namespace App\Modules\Install;

use App\Core\Blocks;
use App\Core\Db;
use App\Core\Migrator;
use App\Core\Settings;
use App\Modules\Demo\DemoSite;
use App\Modules\Design\Design;
use App\Modules\Design\Presets;
use ErrorException;
use RuntimeException;
use Throwable;

/**
 * The final installer step: migrate, seed, write .env, write install.lock. Earlier
 * steps only collect and check input; nothing touches disk or database before this.
 */
final class Installer
{
    public function __construct(
        private readonly string $root,
        private readonly string $storage,
        private readonly string $envPath,
        private readonly string $cacheDirectory,
    ) {
    }

    /**
     * @param array<mixed> $env   DB_* values for .env
     * @param array<mixed> $admin email and password_hash
     * @param array{name: string, locale: string, timezone: string} $site
     */
    public function run(Db $db, array $env, array $admin, array $site, bool $demo = false): void
    {
        (new Migrator($db, $this->root . '/migrations'))->migrate();

        $now = gmdate('Y-m-d H:i:s');
        $languages = require $this->root . '/app/Modules/I18n/languages.php';
        $pdo = $db->pdo();
        $pdo->beginTransaction();
        try {
            $db->query(
                'INSERT INTO admin (email, password_hash, created_at) VALUES (?, ?, ?)',
                [$admin['email'] ?? '', $admin['password_hash'] ?? '', $now],
            );
            // Only the primary locale is enabled; more arrive with Slice 6.
            $db->query(
                'INSERT INTO locales (code, label, is_primary, sort, enabled) VALUES (?, ?, 1, 0, 1)',
                [$site['locale'], $languages[$site['locale']]],
            );
            // Settings::set() deletes before inserting, which is a no-op on a table this
            // install has not written yet, and stays inside the transaction either way.
            // One difference in the bytes, none in behaviour: it does not escape slashes,
            // so a time zone is stored as "Europe/Zagreb" rather than "Europe\/Zagreb".
            foreach (['site_name' => $site['name'], 'timezone' => $site['timezone']] as $key => $value) {
                Settings::set($db, $key, $value);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // A new site starts with the default character, compiled so its first page is styled.
        Design::save($db, Presets::get(Presets::DEFAULT), $this->cacheDirectory);
        if ($demo) {
            DemoSite::seed($db, Blocks::discover($this->root . '/app/Blocks'), $site['locale']);
        }

        $values = ['APP_DEBUG' => 'false', 'APP_KEY' => bin2hex(random_bytes(32))];
        foreach ($env as $key => $value) {
            $values[(string) $key] = (string) $value;
        }
        self::writeFile($this->envPath, self::envFile($values));
        self::writeFile($this->storage . '/install.lock', "Installed {$now} UTC. Delete this file only to reinstall.\n");

        $token = $this->storage . '/install-token.txt';
        if (is_file($token)) {
            unlink($token);
        }
    }

    /**
     * .env content. Values are double-quoted with \, " and $ escaped, which phpdotenv
     * reads back verbatim (tests/install_test.php round-trips awkward passwords).
     *
     * @param array<string, string> $values
     */
    public static function envFile(array $values): string
    {
        $lines = ['# Written by the Boxlet installer.'];
        foreach ($values as $key => $value) {
            if (preg_match('~[\r\n]~', $value)) {
                throw new RuntimeException("{$key} cannot contain a line break");
            }
            $lines[] = $key . '="' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Tries to delete install.php. False means the owner has to delete it by hand.
     */
    public static function deleteScript(string $script): bool
    {
        if (!is_file($script)) {
            return true;
        }
        try {
            return unlink($script);
        } catch (ErrorException) {
            return false;
        }
    }

    private static function writeFile(string $path, string $content): void
    {
        $temporary = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temporary, $content) === false || !chmod($temporary, 0600) || !rename($temporary, $path)) {
            throw new RuntimeException("Cannot write {$path}");
        }
    }
}
