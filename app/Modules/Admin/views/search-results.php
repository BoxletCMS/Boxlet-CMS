<?php

/**
 * Search results, as the Search screen shows them and the ⌘K palette puts in its list.
 * Rendered on its own as the palette's fragment, so both show the same (PLAN.md D-052).
 *
 * @var list<array{kind: string, label: string, hint: string, href: string}> $results
 * @var string $query
 */
?>
<?php if ($query !== '' && $results === []): ?>
<p class="search-none"><?= e(t('search.none', ['query' => $query])) ?></p>
<?php elseif ($results !== []): ?>
<ul class="search-results" role="listbox" aria-label="<?= e(t('search.title')) ?>" data-search-results>
<?php foreach ($results as $index => $result): ?>
    <li role="presentation"><a class="search-result" role="option" href="<?= e($result['href']) ?>" id="search-result-<?= $index ?>"<?= $index === 0 ? ' aria-selected="true"' : '' ?>>
        <span class="search-kind"><?= e(t('search.kind.' . $result['kind'])) ?></span>
        <span class="search-label"><?= e($result['label']) ?></span>
<?php if ($result['hint'] !== ''): ?>
        <span class="search-hint"><?= e($result['hint']) ?></span>
<?php endif; ?>
    </a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
