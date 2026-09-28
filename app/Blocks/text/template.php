<?php
/**
 * Text block. body is richtext, reduced to the safe-HTML whitelist when the page is
 * saved, so it is output as HTML here. Everything else is escaped.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 */
?>
<div class="text">
<?php if ($content['heading'] !== ''): ?>
    <h2 class="text-heading"><?= e($content['heading']) ?></h2>
<?php endif; ?>
    <div class="richtext"><?= $content['body'] ?></div>
</div>
