<?php

/**
 * Buttons (the rebuild's README 1.2, D-164): how every button on the site is drawn — filled,
 * outlined or soft, its corners (28 is a pill), its height, and whether its words are in
 * capitals. Read by block CSS through the button tokens, with fallbacks. Included by
 * appearance.php inside its section, with the closures of parts/controls.php in scope.
 *
 * @var Closure(string): string $control
 * @var Closure(string, string, string, bool=): string $group
 */
?>
                <?= $group('buttons', 'style', $control('button_style') . $control('button_radius') . $control('button_height') . $control('button_caps')) ?>
