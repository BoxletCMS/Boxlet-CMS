<?php
/**
 * Room between two blocks, and optionally a rule across it.
 *
 * ARIA-HIDDEN AND EMPTY. It says nothing, so it says nothing to a screen reader either: a
 * rule that is decoration is noise when announced, and the separation it draws is already
 * carried by the headings around it. <hr> was refused for the same reason — it means a
 * change of topic, and this is a change of spacing.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 */
?>
<div class="divider height-<?= e($content['height']) ?>" aria-hidden="true"></div>
