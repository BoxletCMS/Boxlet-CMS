<?php

use App\Support\Url;

/**
 * The messages sent through one form, newest first (PLAN.md D-046).
 *
 * @var array{id: int, name: string} $form
 * @var list<array{id: int, unread: bool, created: string, summary: string}> $messages
 * @var string $zone
 * @var array{at: string, reason: string}|null $failure the last message the site could not send
 * @var string $title
 * @var string $csrf
 */
?>
        <div class="page-header">
            <h1><?= e($title) ?></h1>
<?php if ($messages !== []): ?>
            <a class="button button-secondary" href="<?= e(Url::admin('forms', $form['id'], 'export')) ?>"><?= e(t('messages.export')) ?></a>
<?php endif; ?>
        </div>
        <p class="page-subtitle"><a href="<?= e(Url::admin('forms')) ?>"><?= e(t('forms.back')) ?></a> · <a href="<?= e(Url::admin('forms', $form['id'])) ?>"><?= e(t('messages.edit_form')) ?></a></p>

<?php if ($failure !== null): ?>
        <div class="notice notice-warning" role="status">
            <p><?= e(t('messages.mail_failed', ['when' => \App\Support\Dates::local($failure['at'], $zone), 'reason' => $failure['reason']])) ?> <a href="<?= e(Url::admin('settings') . '#mail') ?>"><?= e(t('forms.mail_settings')) ?></a></p>
        </div>
<?php endif; ?>

<?php if ($messages === []): ?>
        <div class="empty-state">
            <p><?= e(t('messages.none')) ?></p>
        </div>
<?php else: ?>
        <div class="table-wrap">
            <table class="table messages-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(t('messages.col.date')) ?></th>
                        <th scope="col"><?= e(t('messages.col.from')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= e(t('messages.col.actions')) ?></span></th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($messages as $message): ?>
                    <tr<?= $message['unread'] ? ' class="is-unread"' : '' ?>>
                        <td class="date"><?= e(\App\Support\Dates::local($message['created'], $zone)) ?></td>
                        <td class="row-title">
                            <a href="<?= e(Url::admin('forms', $form['id'], 'messages', $message['id'])) ?>"><?= e($message['summary'] !== '' ? $message['summary'] : t('messages.untitled')) ?></a>
<?php if ($message['unread']): ?>
                            <span class="badge badge-accent"><?= e(t('messages.new')) ?></span>
<?php endif; ?>
                        </td>
                        <td class="row-actions">
                            <form method="post" action="<?= e(Url::admin('forms', $form['id'], 'messages', $message['id'], 'delete')) ?>">
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <button type="submit" class="button button-ghost button-danger button-icon" title="<?= e(t('messages.delete')) ?>" data-confirm="<?= e(t('messages.delete_confirm')) ?>"><?= icon('trash-2') ?><span class="visually-hidden"><?= e(t('messages.delete')) ?></span></button>
                            </form>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php endif; ?>
