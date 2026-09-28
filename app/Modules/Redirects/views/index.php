<?php

use App\Support\Url;

/**
 * The addresses that lead elsewhere (PLAN.md D-129): the owner's rules, a form for another,
 * and the old addresses Boxlet kept when pages were renamed. Provided by AdminView::render().
 *
 * @var list<array{id: int, from: string, page: string|null, pageUrl: string|null, pageId: int|null, url: string|null, hits: int, lastHit: string|null}> $rules
 * @var list<array{id: int, from: string, page: string, pageUrl: string|null, pageId: int, hits: int, lastHit: string|null}> $kept
 * @var list<array{id: int, locale: string, title: string, url: string, published: bool}> $pages
 * @var array<string, string> $errors
 * @var array{from: string, page: string, url: string} $typed
 * @var string $title
 * @var string $csrf
 */
$error = static fn (string $key): string => isset($errors[$key])
    ? '<p class="field-error" role="alert">' . e($errors[$key]) . '</p>'
    : '';
$used = static fn (int $hits, ?string $last): string => $hits === 0
    ? t('redirects.never_used')
    : t($hits === 1 ? 'redirects.used_one' : 'redirects.used_many', ['count' => (string) $hits, 'date' => substr((string) $last, 0, 10)]);
$remove = static function (int $id, string $from) use ($csrf): string {
    return '<form method="post" action="' . e(Url::admin('redirects', $id, 'delete')) . '">'
        . '<input type="hidden" name="_csrf" value="' . e($csrf) . '">'
        . '<button type="submit" class="button button-ghost button-danger" data-confirm="'
        . e(t('redirects.delete_confirm', ['from' => $from])) . '">' . e(t('redirects.delete')) . '</button></form>';
};
$locales = array_values(array_unique(array_column($pages, 'locale')));
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
        <p class="page-subtitle"><?= e(t('redirects.intro')) ?></p>

        <h2 class="redirects-heading"><?= e(t('redirects.rules')) ?></h2>
<?php if ($rules === []): ?>
        <div class="empty-state">
            <p><?= e(t('redirects.rules_none')) ?></p>
        </div>
<?php else: ?>
        <div class="table-wrap">
            <table class="table redirects-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(t('redirects.col.from')) ?></th>
                        <th scope="col"><?= e(t('redirects.col.to')) ?></th>
                        <th scope="col"><?= e(t('redirects.col.used')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= e(t('redirects.col.actions')) ?></span></th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($rules as $rule): ?>
                    <tr>
                        <td class="redirects-address"><?= e($rule['from']) ?></td>
                        <td>
<?php if ($rule['url'] !== null): ?>
                            <span class="redirects-address"><?= e($rule['url']) ?></span>
<?php elseif ($rule['pageId'] !== null): ?>
                            <a href="<?= e(Url::admin('pages', $rule['pageId'])) ?>"><?= e((string) $rule['page']) ?></a>
                            <span class="redirects-address redirects-muted"><?= e((string) $rule['pageUrl']) ?></span>
<?php else: ?>
                            <span class="badge badge-warning"><?= e(t('redirects.nowhere')) ?></span>
<?php endif; ?>
                        </td>
                        <td class="redirects-muted"><?= e($used($rule['hits'], $rule['lastHit'])) ?></td>
                        <td class="row-actions"><?= $remove($rule['id'], $rule['from']) ?></td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php endif; ?>

        <div class="panel stack redirects-new">
            <h2><?= e(t('redirects.new')) ?></h2>
            <form method="post" action="<?= e(Url::admin('redirects')) ?>" class="stack">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="redirect-from"><?= e(t('redirects.from')) ?></label>
                    <input type="text" id="redirect-from" name="from" maxlength="500" required spellcheck="false"
                           value="<?= e($typed['from']) ?>" placeholder="/usluge.html" aria-describedby="redirect-from-hint">
                    <span class="hint" id="redirect-from-hint"><?= e(t('redirects.from_hint')) ?></span>
                    <?= $error('from') ?>
                </div>
                <div class="redirects-to">
                    <div class="field">
                        <label for="redirect-page"><?= e(t('redirects.to_page')) ?></label>
                        <select id="redirect-page" name="page">
                            <option value=""><?= e(t('redirects.to_page_none')) ?></option>
<?php foreach ($locales as $code): ?>
                            <optgroup label="<?= e($code) ?>">
<?php foreach ($pages as $choice): ?>
<?php if ($choice['locale'] === $code): ?>
                                <option value="<?= e((string) $choice['id']) ?>"<?= $typed['page'] === (string) $choice['id'] ? ' selected' : '' ?>><?= e($choice['title'] . ' — ' . $choice['url'] . ($choice['published'] ? '' : ' · ' . t('redirects.draft'))) ?></option>
<?php endif; ?>
<?php endforeach; ?>
                            </optgroup>
<?php endforeach; ?>
                        </select>
                        <?= $error('page') ?>
                    </div>
                    <p class="redirects-or"><?= e(t('redirects.or')) ?></p>
                    <div class="field">
                        <label for="redirect-url"><?= e(t('redirects.to_url')) ?></label>
                        <input type="text" id="redirect-url" name="url" maxlength="1000" spellcheck="false"
                               value="<?= e($typed['url']) ?>" placeholder="https://" aria-describedby="redirect-url-hint">
                        <span class="hint" id="redirect-url-hint"><?= e(t('redirects.to_url_hint')) ?></span>
                        <?= $error('url') ?>
                    </div>
                </div>
                <div><button type="submit" class="button"><?= e(t('redirects.create')) ?></button></div>
            </form>
        </div>

        <h2 class="redirects-heading"><?= e(t('redirects.kept')) ?></h2>
        <p class="redirects-intro"><?= e(t('redirects.kept_intro')) ?></p>
<?php if ($kept === []): ?>
        <div class="empty-state">
            <p><?= e(t('redirects.kept_none')) ?></p>
        </div>
<?php else: ?>
        <div class="table-wrap">
            <table class="table redirects-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(t('redirects.col.old')) ?></th>
                        <th scope="col"><?= e(t('redirects.col.page')) ?></th>
                        <th scope="col"><?= e(t('redirects.col.used')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= e(t('redirects.col.actions')) ?></span></th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($kept as $row): ?>
                    <tr>
                        <td class="redirects-address"><?= e($row['from']) ?></td>
                        <td>
                            <a href="<?= e(Url::admin('pages', $row['pageId'])) ?>"><?= e($row['page']) ?></a>
<?php if ($row['pageUrl'] !== null): ?>
                            <span class="redirects-address redirects-muted"><?= e($row['pageUrl']) ?></span>
<?php else: ?>
                            <span class="badge badge-warning"><?= e(t('redirects.unpublished')) ?></span>
<?php endif; ?>
                        </td>
                        <td class="redirects-muted"><?= e($used($row['hits'], $row['lastHit'])) ?></td>
                        <td class="row-actions"><?= $remove($row['id'], $row['from']) ?></td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php endif; ?>
