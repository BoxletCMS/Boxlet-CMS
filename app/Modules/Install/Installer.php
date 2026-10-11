<?php

namespace App\Modules\Install;

use App\Core\Blocks;
use App\Core\Db;
use App\Core\Migrator;
use App\Core\Settings;
use App\Modules\Demo\DemoPictures;
use App\Modules\Demo\DemoSite;
use App\Modules\Design\Design;
use App\Support\PageCache;
use ErrorException;
use RuntimeException;
use Throwable;

/**
 * The installer's writing: migrate, seed, write .env, write install.lock. Earlier steps only
 * collect and check input; nothing touches disk or database before this.
 *
 * In two halves since D-214: start() makes the database (tables, the admin, the language,
 * the settings) and finish() puts the demo in, compiles the design and writes .env and the
 * lock. Without the demo they run one after the other in the site step. With it, the demo's
 * pictures are taken in between, a few to a request (InstallDemo): fifty pictures, every size
 * of each, in one request ran past what a host or Cloudflare waits for (a 524 after 100
 * seconds, the owner's first try).
 */
final class Installer
{
    public function __construct(
        private readonly string $root,
        private readonly string $storage,
        private readonly string $envPath,
        private readonly string $cacheDirectory,
        // The web root the picture sizes are written under (m/): the folder install.php is in.
        private readonly string $public,
    ) {
    }

    /**
     * Both halves in one request: the install without the demo, or with its pictures made here
     * (the tests and `php migrations/seed.php` have no request to keep short).
     *
     * @param array<mixed> $env   DB_* values for .env
     * @param array<mixed> $admin email and password_hash
     * @param array{name: string, locale: string, timezone: string} $site
     */
    public function run(Db $db, array $env, array $admin, array $site, bool $demo = false): void
    {
        $this->start($db, $admin, $site);
        $this->finish($db, $env, $site, $demo, $demo ? DemoPictures::importer($db, $this->storage, $this->public) : null);
    }

    /**
     * The database: its tables, the admin, the first language and the site's settings.
     *
     * @param array<mixed> $admin email and password_hash
     * @param array{name: string, locale: string, timezone: string} $site
     */
    public function start(Db $db, array $admin, array $site): void
    {
        (new Migrator($db, $this->root . '/migrations'))->migrate();

        $now = gmdate('Y-m-d H:i:s');
        $languages = self::languages();
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
    }

    /**
     * The rest: the demo, the design, .env and the lock. The demo's pictures and documents are
     * stored by $store, from $root (DemoPictures); without a $store its pages come without them.
     *
     * @param array<mixed> $env DB_* values for .env
     * @param array{name: string, locale: string, timezone: string} $site
     * @param \Closure(string, string): int|null $store what stores a demo file and gives its id
     */
    public function finish(Db $db, array $env, array $site, bool $demo, ?\Closure $store = null, string $root = ''): void
    {
        $now = gmdate('Y-m-d H:i:s');
        // The demo is written for its own character (DemoSite::CHARACTER) and sets it, so it
        // is seeded before the design is compiled.
        if ($demo) {
            DemoSite::seed($db, Blocks::discover($this->root . '/app/Blocks'), $site['locale'], $store, $root);
        }
        // A new site starts with nothing of the owner's: its character, the default one
        // without the demo, compiled so its first page is styled (D-164). The demo's own
        // header (DemoSite::LOOK, D-216) is kept.
        Design::save($db, $demo ? Design::load($db) : [], $this->cacheDirectory);
        // A site installed where another stood must not answer with that one's kept pages:
        // public/cache outlives a reinstall, and the browser copy served the old demo home
        // after one, measured.
        PageCache::use($this->cacheDirectory);
        PageCache::clear();

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
     * Every language a site may start in, as the site step offers them.
     *
     * @return array<string, string> ISO 639-1 code => native name
     */
    public static function languages(): array
    {
        return require dirname(__DIR__) . '/I18n/languages.php';
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
