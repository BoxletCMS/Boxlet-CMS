<?php

/**
 * Applies pending migrations from the command line (PLAN.md D-033):
 *
 *   php migrations/migrate.php           apply every pending migration
 *   php migrations/migrate.php --check   list them and exit 1 if there are any
 *
 * Uses the database configured in .env. It goes through the same Update service as the
 * admin's "Database update needed" button (D-019), so the lock, the SQLite backup and the
 * marker that lifts the update gate all behave exactly as they do there. The button stays
 * the product's way; this is for development, where pressing it after every pull is a
 * chore that interrupts whoever is working.
 */

use App\Core\Config;
use App\Core\Db;
use App\Modules\Update\Update;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->safeLoad();

$config = new Config($root . '/config');
$storage = (string) $config->get('app.storage_path');
$database = $config->get('database', []);

if (!is_file($storage . '/install.lock')) {
    fwrite(STDERR, "Boxlet is not installed yet: there is no storage/install.lock. Run the installer first.\n");
    exit(1);
}

$db = Db::fromConfig($database);
$update = new Update(
    $db,
    $root . '/migrations',
    $storage,
    $db->driver === 'sqlite' ? (string) ($database['path'] ?? '') : null,
);

$pending = $update->pending();

if (in_array('--check', $argv, true)) {
    echo $pending === [] ? "Up to date.\n" : 'Pending: ' . implode(', ', $pending) . "\n";
    exit($pending === [] ? 0 : 1);
}

if ($pending === []) {
    echo "Up to date.\n";
    exit(0);
}

try {
    $applied = $update->run();
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo 'Applied: ' . implode(', ', $applied) . "\n";
