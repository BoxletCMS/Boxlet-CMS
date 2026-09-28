<?php

use App\Support\Url;

/**
 * The Search screen (PLAN.md D-052): what the rail's Search opens without a script. With
 * one, the ⌘K palette shows the same results over whatever screen is open.
 *
 * @var string $title
 * @var list<array{kind: string, label: string, hint: string, href: string}> $results
 * @var string $query
 */
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
        <p class="page-subtitle"><?= e(t('search.intro')) ?></p>
        <form class="list-search search-screen-form" method="get" action="<?= e(Url::admin('search')) ?>" role="search">
            <label for="search-q" class="visually-hidden"><?= e(t('search.placeholder')) ?></label>
            <input type="search" id="search-q" name="q" value="<?= e($query) ?>" placeholder="<?= e(t('search.placeholder')) ?>" autofocus>
            <button type="submit" class="button button-secondary"><?= e(t('search.submit')) ?></button>
        </form>
        <div class="search-screen-results">
<?php require __DIR__ . '/search-results.php'; ?>
        </div>
