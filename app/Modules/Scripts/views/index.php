<?php

use App\Support\Url;

/**
 * The owner's scripts (PLAN.md O-73): what is asked of a visitor, the scripts in the order they
 * are placed, and a form for another. Provided by AdminView::render().
 *
 * @var list<array{id: int, name: string, code: string, place: string, kind: string, cookies: list<array{name: string, provider: string, purpose: string, duration: string}>, enabled: bool, position: int}> $rows
 * @var list<string> $asked the kinds a visitor is asked about; none, and no banner is shown
 * @var array<string, string> $errors
 * @var array{name: string, code: string} $old
 * @var string $title
 * @var string $csrf
 */
$error = static fn (string $key): string => isset($errors[$key])
    ? '<p class="field-error" role="alert">' . e($errors[$key]) . '</p>'
    : '';
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
        </div>
        <p class="page-subtitle"><?= e(t('scripts.intro')) ?></p>

        <?php /* WHETHER VISITORS ARE ASKED, said first: the banner is the visible consequence of
                 everything below, and a site with none is Boxlet's promise kept. */ ?>
<?php if ($asked === []): ?>
        <p class="notice" role="status"><?= e(t('scripts.no_banner')) ?></p>
<?php else: ?>
        <p class="notice notice-warning" role="status"><?= e(t('scripts.banner', ['kinds' => implode(', ', array_map(static fn (string $kind): string => t('scripts.kind.' . $kind), $asked))])) ?></p>
<?php endif; ?>

<?php if ($rows === []): ?>
        <div class="empty-state">
            <p><?= e(t('scripts.none')) ?></p>
        </div>
<?php else: ?>
        <div class="table-wrap">
            <table class="table scripts-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(t('scripts.col.name')) ?></th>
                        <th scope="col"><?= e(t('scripts.col.kind')) ?></th>
                        <th scope="col"><?= e(t('scripts.col.place')) ?></th>
                        <th scope="col"><?= e(t('scripts.col.cookies')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= e(t('scripts.col.actions')) ?></span></th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="row-title">
                            <a href="<?= e(Url::admin('scripts', $row['id'])) ?>"><?= e($row['name']) ?></a>
<?php if (!$row['enabled']): ?>
                            <span class="badge"><?= e(t('scripts.off')) ?></span>
<?php endif; ?>
                        </td>
                        <td><?= e(t('scripts.kind.' . $row['kind'])) ?></td>
                        <td><?= e(t('scripts.place.' . $row['place'])) ?></td>
                        <td class="numeric"><?= e((string) count($row['cookies'])) ?></td>
                        <td class="row-actions">
                            <form method="post" action="<?= e(Url::admin('scripts', $row['id'], 'delete')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <button type="submit" class="button button-ghost button-danger" data-confirm="<?= e(t('scripts.delete_confirm', ['name' => $row['name']])) ?>"><?= e(t('scripts.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php endif; ?>

        <div class="panel stack">
            <h2><?= e(t('scripts.new')) ?></h2>
            <form method="post" action="<?= e(Url::admin('scripts')) ?>" class="stack">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="script-name"><?= e(t('scripts.name')) ?></label>
                    <input type="text" id="script-name" name="name" value="<?= e($old['name']) ?>" maxlength="190" required aria-describedby="script-name-hint">
                    <span class="hint" id="script-name-hint"><?= e(t('scripts.name_hint')) ?></span>
                    <?= $error('name') ?>
                </div>
                <div class="field">
                    <label for="script-code"><?= e(t('scripts.code')) ?></label>
                    <textarea id="script-code" name="code" rows="8" class="code-input" spellcheck="false" required aria-describedby="script-code-hint"><?= e($old['code']) ?></textarea>
                    <span class="hint" id="script-code-hint"><?= e(t('scripts.code_hint')) ?></span>
                    <?= $error('code') ?>
                </div>
                <div>
                    <button type="submit" class="button"><?= e(t('scripts.add')) ?></button>
                </div>
            </form>
        </div>
