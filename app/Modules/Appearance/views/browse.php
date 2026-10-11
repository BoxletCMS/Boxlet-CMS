<?php

use App\Modules\Design\Fonts;
use App\Modules\Design\Palette;
use App\Modules\Design\Vocabulary\Decisions;
use App\Support\Url;

/**
 * BROWSE LIBRARY (PLAN.md D-195): the sets in designs/library/ as cards — three colours of the
 * set's own palette, its name, what it is for, its two typefaces — each added as a character
 * by one press. Provided by AdminView::render().
 *
 * @var string $title
 * @var string $csrf
 * @var list<array{id: string, set: array<string, mixed>, added: bool}> $sets
 */

/** One colour of the palette, drawn as Appearance draws its chips (cards.php): an SVG, no style. */
$swatch = static fn (string $hex): string => '<svg viewBox="0 0 10 10" aria-hidden="true"><rect width="10" height="10" fill="' . e($hex) . '"/></svg>';
$words = static fn (array $field): string => (string) ($field['en'] ?? reset($field) ?: '');
$font = static fn (string $id): string => Fonts::ALL[Fonts::known($id)]['name'];
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
            <a class="button button-secondary" href="<?= e(Url::admin('appearance')) ?>"><?= icon('chevron-left') ?> <?= e(t('browse.back')) ?></a>
        </div>
        <p class="page-subtitle"><?= e(t('browse.intro')) ?></p>
<?php if ($sets === []): ?>
        <p class="hint hint-always"><?= e(t('browse.empty')) ?></p>
<?php endif; ?>
        <ul class="library-cards" role="list">
<?php foreach ($sets as $entry): ?>
<?php
    $set = $entry['set'];
    $name = $words($set['name']);
    $decisions = $set['decisions'] + Decisions::neutral();
    $palette = Palette::forDecisions($decisions);
?>
            <li class="library-card">
                <span class="library-chips" aria-hidden="true"><?= $swatch($palette['accent']) . $swatch($palette['contrast']) . $swatch($palette['surface']) ?></span>
                <h2 class="library-name"><?= e($name) ?></h2>
                <p class="library-text"><?= e($words($set['description'])) ?></p>
                <p class="library-shape"><?= e(t('browse.fonts', ['heading' => $font((string) $decisions['heading_font']), 'body' => $font((string) $decisions['body_font'])])) ?><?= $decisions['mode'] === 'dark' ? ' · ' . e(t('browse.dark_first')) : '' ?></p>
<?php if ($entry['added']): ?>
                <p class="library-added"><span class="library-tag"><?= e(t('browse.added')) ?></span> <?= e(t('browse.added_hint')) ?></p>
<?php else: ?>
                <form method="post" action="<?= e(Url::admin('appearance', 'browse', $entry['id'])) ?>" class="library-add">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button type="submit" class="button" title="<?= e(t('browse.add_one', ['name' => $name])) ?>"><?= e(t('browse.add')) ?><span class="visually-hidden">: <?= e($name) ?></span></button>
                </form>
<?php endif; ?>
            </li>
<?php endforeach; ?>
        </ul>
