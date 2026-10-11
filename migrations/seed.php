<?php

/**
 * Adds the demo site to an installed Boxlet that has no pages yet:
 *
 *   php migrations/seed.php
 *
 * Uses the database configured in .env. The installer offers the same demo site.
 */

use App\Core\Blocks;
use App\Core\Config;
use App\Core\Db;
use App\Modules\Demo\DemoPictures;
use App\Modules\Demo\DemoSite;
use App\Modules\Design\Design;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->safeLoad();

$config = new Config($root . '/config');
$db = Db::fromConfig($config->get('database', []));

$primary = $db->one('SELECT code FROM locales WHERE is_primary = 1');
if ($primary === null) {
    fwrite(STDERR, "Boxlet is not installed yet: there is no primary locale. Run the installer first.\n");
    exit(1);
}

try {
    $storage = (string) ($config->get('app', [])['storage_path'] ?? $root . '/storage');
    $count = DemoSite::seed($db, Blocks::discover($root . '/app/Blocks'), (string) $primary['code'], DemoPictures::importer($db, $storage, $root . '/public'));
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

// The demo sets the character it is shown with, and its header; the stylesheet follows them
// (DemoSite::seed).
Design::publish($db, (string) $config->get('app.cache_path'));

echo "Added {$count} demo pages in locale {$primary['code']}, drawn with " . DemoSite::SHOWN_WITH . ".\n";
