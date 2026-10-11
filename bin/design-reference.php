<?php

/**
 * Prints the design set reference, Markdown, from the code that validates a set (PLAN.md D-183):
 *
 *   php bin/design-reference.php > design-set-reference.md
 */

use App\Core\Blocks;
use App\Modules\Design\DesignReference;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

echo DesignReference::markdown(Blocks::discover($root . '/app/Blocks'));
