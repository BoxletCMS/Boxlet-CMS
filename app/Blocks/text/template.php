<?php
/**
 * Text block. body is richtext, reduced to the safe-HTML whitelist when the page is
 * saved, so it is output as HTML here. Everything else is escaped.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 * @var array<string, string> $options measure comfortable/wide/full; in two columns, each column's (D-187, D-191)
 */
?>
<div class="text measure-<?= e($options['measure']) ?>">
<?php if (edit_show($content['heading'] !== '')): ?>
    <h2 class="text-heading"<?= edit_attr('heading') ?>><?= e($content['heading']) ?></h2>
<?php endif; ?>
    <div class="richtext"<?= edit_attr('body') ?>><?= $content['body'] ?></div>
</div>
