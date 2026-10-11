<?php

/**
 * Checks design set files as an import would, and says more than an import does (PLAN.md D-183,
 * App\Modules\Design\SetCheck):
 *
 *   php bin/check-set.php my-set.json [another.json ...]
 *
 * Exits 1 when any file has an error, 2 when there is nothing to check. Needs no database and
 * no install.
 */

use App\Core\Blocks;
use App\Modules\Design\SetCheck;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$files = array_slice($argv, 1);
if ($files === []) {
    fwrite(STDERR, "Usage: php bin/check-set.php file.json [file.json ...]\n");
    exit(2);
}

$registry = Blocks::discover($root . '/app/Blocks');
$failed = false;
foreach ($files as $file) {
    echo $file, "\n";
    if (!is_file($file)) {
        echo "  ERROR    no such file\n\n";
        $failed = true;
        continue;
    }
    $report = SetCheck::check((string) file_get_contents($file), $registry);
    if ($report['id'] !== '') {
        echo '  ', $report['id'], ' — ', $report['name'], $report['character'] ? ' (a character)' : '', "\n";
    }
    foreach ($report['errors'] as $line) {
        echo '  ERROR    ', $line, "\n";
    }
    foreach ($report['warnings'] as $line) {
        echo '  WARNING  ', $line, "\n";
    }
    foreach ($report['notes'] as $line) {
        echo '  note     ', $line, "\n";
    }
    echo '  ', $report['errors'] === [] ? 'OK' : 'REFUSED', ': ', count($report['errors']), ' error(s), ', count($report['warnings']), " warning(s)\n\n";
    $failed = $failed || $report['errors'] !== [];
}

exit($failed ? 1 : 0);
