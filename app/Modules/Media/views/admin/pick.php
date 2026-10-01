<?php

/**
 * One page of the media browser (PLAN.md D-145): the library's own cards, and a way to
 * the next page when there is one. The script appends the next page's cards to this grid,
 * so the button is the only thing here that is not a card.
 *
 * @var list<array<string, mixed>> $pictures
 * @var string $search
 * @var bool $picking
 * @var int|null $next
 * @var string $csrf
 */
require __DIR__ . '/cards.php';
?>
<?php if ($next !== null): ?>
        <button type="button" class="button button-secondary media-picker-more" data-pick-more="<?= e((string) $next) ?>"><?= e(t('picker.more')) ?></button>
<?php endif; ?>
