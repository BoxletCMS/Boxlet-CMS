<?php
/**
 * A heading, a line of persuasion, and up to two things to press.
 *
 * The second link is drawn as `cta-quiet` and never as a second solid button. Two buttons of
 * equal weight is a question, and this block exists to ask for one thing.
 *
 * @var array<string, mixed> $content
 * @var array<string, mixed> $style
 * @var string $layout
 */
$links = [];
foreach (['action' => 'button', 'second' => 'cta-quiet'] as $field => $class) {
    if ($content[$field]['url'] !== '' && $content[$field]['label'] !== '') {
        $links[] = ['class' => $class, 'link' => $content[$field]];
    }
}
?>
<div class="cta">
    <div class="cta-words">
        <h2 class="cta-heading"><?= e($content['heading']) ?></h2>
<?php if ($content['body'] !== ''): ?>
        <p class="cta-body"><?= nl2br(e($content['body'])) ?></p>
<?php endif; ?>
    </div>
<?php if ($links !== []): ?>
    <p class="cta-links">
<?php foreach ($links as $each): ?>
        <a class="<?= $each['class'] ?>" href="<?= e($each['link']['url']) ?>"><?= e($each['link']['label']) ?></a>
<?php endforeach; ?>
    </p>
<?php endif; ?>
</div>
