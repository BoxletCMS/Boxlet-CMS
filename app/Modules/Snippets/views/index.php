<?php

use App\Modules\Admin\RichInline;
use App\Support\Url;

/**
 * The snippets (PLAN.md D-201): each with its tag and its words in every language, and a form
 * for another. Provided by AdminView::render().
 *
 * A snippet's words have no Insert: they are not read for tags (SPEC §5.6).
 *
 * @var array<string, array<string, string>> $snippets name => locale => inline rich text
 * @var array<int, array<string, mixed>> $locales the site's languages, the first language marked
 * @var array<string, array<int, array{title: string, depth: int, published: bool, url: string}>> $linkPages
 * @var array<string, string> $errors
 * @var string $name the new snippet's name, as typed
 * @var array<string, string> $typed the new snippet's words, as typed
 * @var string $title
 * @var string $csrf
 */
$primaryLabel = '';
foreach ($locales as $row) {
    if ((int) $row['is_primary'] === 1) {
        $primaryLabel = (string) $row['label'];
    }
}
$many = count($locales) > 1;

/* One language's words: labelled with the language where there is more than one, and told,
   outside the first, what shows when it is left empty. */
$words = static function (string $prefix, array $values) use ($locales, $linkPages, $many, $primaryLabel): string {
    $html = '';
    foreach ($locales as $row) {
        $code = (string) $row['code'];
        $id = $prefix . '-' . $code;
        $hint = (int) $row['is_primary'] === 1 || !$many ? '' : t('snippets.fallback_hint', ['language' => $primaryLabel]);
        $html .= '<div class="field">'
            . '<label for="' . e($id) . '">' . e($many ? (string) $row['label'] : t('snippets.words')) . '</label>'
            . RichInline::field($id, 'words_' . $code, $values[$code] ?? '', $code, $linkPages[$code] ?? [], $hint !== '' ? $id . '-hint' : null, false)
            . ($hint !== '' ? '<span class="hint" id="' . e($id) . '-hint">' . e($hint) . '</span>' : '')
            . '</div>';
    }

    return $html;
};
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
        <p class="page-subtitle"><?= e(t('snippets.intro')) ?></p>

<?php if ($snippets === []): ?>
        <div class="empty-state">
            <p><?= e(t('snippets.none')) ?></p>
        </div>
<?php endif; ?>
<?php foreach ($snippets as $snippet => $values): ?>
<?php $snippet = (string) $snippet; ?>
        <section class="panel stack snippet" id="snippet-<?= e($snippet) ?>" aria-labelledby="snippet-<?= e($snippet) ?>-name">
            <div class="snippet-head">
                <h2 id="snippet-<?= e($snippet) ?>-name"><?= e($snippet) ?></h2>
                <form method="post" action="<?= e(Url::admin('snippets', $snippet, 'delete')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <button type="submit" class="button button-ghost button-danger" data-confirm="<?= e(t('snippets.delete_confirm', ['name' => $snippet])) ?>"><?= e(t('snippets.delete')) ?></button>
                </form>
            </div>
            <div class="field">
                <label for="snippet-<?= e($snippet) ?>-tag"><?= e(t('snippets.tag')) ?></label>
                <input type="text" id="snippet-<?= e($snippet) ?>-tag" class="snippet-tag" readonly value="<?= e('{{snippet:' . $snippet . '}}') ?>" aria-describedby="snippet-<?= e($snippet) ?>-tag-hint">
                <span class="hint" id="snippet-<?= e($snippet) ?>-tag-hint"><?= e(t('snippets.tag_hint')) ?></span>
            </div>
            <form method="post" action="<?= e(Url::admin('snippets', $snippet)) ?>" class="stack">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <?= $words('snippet-' . $snippet, $values) ?>
                <div>
                    <button type="submit" class="button"><?= e(t('snippets.save')) ?></button>
                </div>
            </form>
        </section>
<?php endforeach; ?>

        <section class="panel stack snippet-new" aria-labelledby="snippet-new-title">
            <h2 id="snippet-new-title"><?= e(t('snippets.new')) ?></h2>
            <form method="post" action="<?= e(Url::admin('snippets')) ?>" class="stack">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="snippet-name"><?= e(t('snippets.name')) ?></label>
                    <input type="text" id="snippet-name" name="name" maxlength="64" value="<?= e($name) ?>" required
                           pattern="[a-z0-9](?:[a-z0-9\-]{0,62}[a-z0-9])?" aria-describedby="snippet-name-hint<?= isset($errors['name']) ? ' snippet-name-error' : '' ?>"<?= isset($errors['name']) ? ' aria-invalid="true"' : '' ?>>
                    <span class="hint" id="snippet-name-hint"><?= e(t('snippets.name_hint')) ?></span>
<?php if (isset($errors['name'])): ?>
                    <p class="field-error" id="snippet-name-error" role="alert"><?= e($errors['name']) ?></p>
<?php endif; ?>
                </div>
                <?= $words('snippet-new', $typed) ?>
                <div>
                    <button type="submit" class="button"><?= e(t('snippets.create')) ?></button>
                </div>
            </form>
        </section>
