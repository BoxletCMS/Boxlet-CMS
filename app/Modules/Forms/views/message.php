<?php

use App\Support\Url;

/**
 * One message, as it was sent: each answer under the label it was asked with (D-046).
 *
 * @var array{id: int, name: string} $form
 * @var array{id: int, created: string, answers: list<array{key: string, label: string, type: string, value: string}>, page: array{id: int, title: string}|null} $message
 * @var string $zone
 * @var string $csrf
 */
$replyTo = '';
foreach ($message['answers'] as $answer) {
    if ($answer['type'] === 'email' && $answer['value'] !== '' && $replyTo === '') {
        $replyTo = $answer['value'];
    }
}
?>
        <div class="page-header">
            <h1><?= e(t('messages.one')) ?></h1>
<?php if ($replyTo !== ''): ?>
            <?php /* The address was checked as an email address when it was sent. */ ?>
            <a class="button" href="mailto:<?= e($replyTo) ?>"><?= e(t('messages.reply')) ?></a>
<?php endif; ?>
        </div>
        <p class="page-subtitle"><a href="<?= e(Url::admin('forms', $form['id'], 'messages')) ?>"><?= e(t('messages.back', ['form' => $form['name']])) ?></a></p>

        <div class="panel stack">
            <p class="hint"><?= e(\App\Support\Dates::local($message['created'], $zone)) ?><?php if ($message['page'] !== null): ?> · <?= e(t('messages.from_page')) ?> <a href="<?= e(Url::admin('pages', $message['page']['id'])) ?>"><?= e($message['page']['title']) ?></a><?php endif; ?></p>
            <dl class="message-answers">
<?php foreach ($message['answers'] as $answer): ?>
                <dt><?= e($answer['label']) ?></dt>
                <dd><?= $answer['value'] === '' ? '<span class="hint">—</span>' : ($answer['type'] === 'checkbox' ? e(t('forms.mail.ticked')) : nl2br(e($answer['value']))) ?></dd>
<?php endforeach; ?>
            </dl>
            <form method="post" action="<?= e(Url::admin('forms', $form['id'], 'messages', $message['id'], 'delete')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="button button-ghost button-danger" data-confirm="<?= e(t('messages.delete_confirm')) ?>"><?= icon('trash-2') ?> <?= e(t('messages.delete')) ?></button>
            </form>
        </div>
