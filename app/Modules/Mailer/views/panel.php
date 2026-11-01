<?php

use App\Support\Url;

/**
 * The Mail panel on the Settings screen (PLAN.md D-045), with forms of its own.
 *
 * Every field is on the page; the ones a chosen way does not use are grouped under it and
 * hidden by mail-settings.js, so without a script the panel is simply longer. A password or
 * key is never drawn back: the field says whether one is set, and left empty it is kept.
 *
 * @var array<string, string> $mail
 * @var array<string, string> $mailErrors
 * @var array<string, string> $mailSecrets what each saved secret shows in its empty field, by
 *      MailSettings::SECRETS; '' when none is saved
 * @var string $csrf
 */
$mailError = static fn (string $key): string => isset($mailErrors[$key])
    ? '<p class="field-error" role="alert">' . e($mailErrors[$key]) . '</p>'
    : '';
$mailField = static function (string $key, string $type, string $label, string $hint = '', string $extra = '') use ($mail, $mailError): string {
    $id = 'mail_' . $key;

    return '<div class="field">'
        . '<label for="' . e($id) . '">' . e($label) . '</label>'
        . '<input type="' . e($type) . '" id="' . e($id) . '" name="' . e($id) . '" value="' . e($mail[$key] ?? '') . '"' . $extra
        . ($hint !== '' ? ' aria-describedby="' . e($id) . '-hint"' : '') . '>'
        . ($hint !== '' ? '<span class="hint" id="' . e($id) . '-hint">' . e($hint) . '</span>' : '')
        . $mailError($key)
        . '</div>';
};
/** A key's field: empty, its saved trace as the placeholder, empty on save keeps it. */
$mailKey = static function (string $key, string $label, string $hint) use ($mailSecrets, $mailField): string {
    $set = ($mailSecrets[$key] ?? '') !== '';

    return $mailField($key, 'password', $label, $set ? t('mail.secret_set') : $hint, ' autocomplete="new-password" spellcheck="false"' . ($set ? ' placeholder="' . e($mailSecrets[$key]) . '"' : ''));
};
?>
        <div class="panel stack" id="mail">
            <h2><?= e(t('mail.title')) ?></h2>
            <p class="hint"><?= e(t('mail.intro')) ?></p>

            <form method="post" action="<?= e(Url::admin('settings', 'mail')) ?>" class="stack" data-mail-settings>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field">
                    <label for="mail_transport"><?= e(t('mail.transport')) ?></label>
                    <select id="mail_transport" name="mail_transport" aria-describedby="mail_transport-hint" data-mail-transport>
<?php foreach (\App\Modules\Mailer\MailSettings::TRANSPORTS as $transport): ?>
                        <option value="<?= e($transport) ?>"<?= $transport === $mail['transport'] ? ' selected' : '' ?>><?= e(t('mail.transport.' . ($transport === '' ? 'none' : $transport))) ?></option>
<?php endforeach; ?>
                    </select>
                    <span class="hint" id="mail_transport-hint"><?= e(t('mail.transport_hint')) ?></span>
                    <?= $mailError('transport') ?>
                </div>

                <div class="field-pair">
                    <?= $mailField('from_address', 'email', t('mail.from_address'), t('mail.from_address_hint'), ' autocomplete="off"') ?>
                    <?= $mailField('from_name', 'text', t('mail.from_name'), t('mail.from_name_hint')) ?>
                </div>
                <?= $mailField('notify_address', 'email', t('mail.notify_address'), t('mail.notify_address_hint'), ' autocomplete="off"') ?>

                <fieldset class="fieldset mail-group" data-mail-group="smtp">
                    <legend><?= e(t('mail.smtp')) ?></legend>
                    <div class="field-pair">
                        <?= $mailField('smtp_host', 'text', t('mail.smtp_host'), t('mail.smtp_host_hint'), ' autocomplete="off" spellcheck="false" list="mail-smtp-hosts"') ?>
                        <?php /* The providers' own servers, offered as the host is typed (O-72):
                                 port 587 and STARTTLS, the defaults below, suit them all. */ ?>
                        <datalist id="mail-smtp-hosts">
<?php foreach (['smtp-relay.brevo.com' => 'Brevo', 'smtp.mailgun.org' => 'Mailgun (US)', 'smtp.eu.mailgun.org' => 'Mailgun (EU)', 'smtp.postmarkapp.com' => 'Postmark', 'smtp.sendgrid.net' => 'SendGrid', 'smtp.resend.com' => 'Resend'] as $host => $provider): ?>
                            <option value="<?= e($host) ?>"><?= e($provider) ?></option>
<?php endforeach; ?>
                        </datalist>
                        <?= $mailField('smtp_port', 'text', t('mail.smtp_port'), t('mail.smtp_port_hint'), ' inputmode="numeric" autocomplete="off"') ?>
                    </div>
                    <div class="field">
                        <label for="mail_smtp_encryption"><?= e(t('mail.smtp_encryption')) ?></label>
                        <select id="mail_smtp_encryption" name="mail_smtp_encryption">
<?php foreach (\App\Modules\Mailer\MailSettings::ENCRYPTIONS as $encryption): ?>
                            <option value="<?= e($encryption) ?>"<?= $encryption === $mail['smtp_encryption'] ? ' selected' : '' ?>><?= e(t('mail.smtp_encryption.' . $encryption)) ?></option>
<?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-pair">
                        <?= $mailField('smtp_username', 'text', t('mail.smtp_username'), '', ' autocomplete="off" spellcheck="false"') ?>
                        <?= $mailField('smtp_password', 'password', t('mail.smtp_password'), t($mailSecrets['smtp_password'] !== '' ? 'mail.secret_set' : 'mail.secret_unset'), ' autocomplete="new-password"' . ($mailSecrets['smtp_password'] !== '' ? ' placeholder="' . e($mailSecrets['smtp_password']) . '"' : '')) ?>
                    </div>
                </fieldset>

                <fieldset class="fieldset mail-group" data-mail-group="resend">
                    <legend><?= e(t('mail.resend')) ?></legend>
                    <?php /* A saved key shows a trace as the field's placeholder — its first
                             three and last four characters — so the owner sees one is set and
                             which; the field itself stays empty, and empty keeps the key. */ ?>
                    <?= $mailField('resend_key', 'password', t('mail.resend_key'), t($mailSecrets['resend_key'] !== '' ? 'mail.secret_set' : 'mail.resend_key_hint'), ' autocomplete="new-password" spellcheck="false"' . ($mailSecrets['resend_key'] !== '' ? ' placeholder="' . e($mailSecrets['resend_key']) . '"' : '')) ?>
                </fieldset>

                <fieldset class="fieldset mail-group" data-mail-group="brevo">
                    <legend><?= e(t('mail.brevo')) ?></legend>
                    <?= $mailKey('brevo_key', t('mail.api_key'), t('mail.brevo_key_hint')) ?>
                </fieldset>

                <fieldset class="fieldset mail-group" data-mail-group="mailgun">
                    <legend><?= e(t('mail.mailgun')) ?></legend>
                    <?= $mailKey('mailgun_key', t('mail.api_key'), t('mail.mailgun_key_hint')) ?>
                    <div class="field-pair">
                        <?= $mailField('mailgun_domain', 'text', t('mail.mailgun_domain'), t('mail.mailgun_domain_hint'), ' autocomplete="off" spellcheck="false"') ?>
                        <div class="field">
                            <label for="mail_mailgun_region"><?= e(t('mail.mailgun_region')) ?></label>
                            <select id="mail_mailgun_region" name="mail_mailgun_region" aria-describedby="mail_mailgun_region-hint">
<?php foreach (array_keys(\App\Modules\Mailer\MailgunTransport::REGIONS) as $region): ?>
                                <option value="<?= e($region) ?>"<?= $region === ($mail['mailgun_region'] ?? 'us') ? ' selected' : '' ?>><?= e(t('mail.mailgun_region.' . $region)) ?></option>
<?php endforeach; ?>
                            </select>
                            <span class="hint" id="mail_mailgun_region-hint"><?= e(t('mail.mailgun_region_hint')) ?></span>
                        </div>
                    </div>
                </fieldset>

                <p class="hint mail-group" data-mail-group="sendmail"><?= e(t('mail.sendmail_hint')) ?></p>

                <div class="form-actions">
                    <button type="submit" class="button"><?= e(t('mail.save')) ?></button>
                </div>
            </form>

            <form method="post" action="<?= e(Url::admin('settings', 'mail', 'test')) ?>" class="mail-test">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="button button-secondary"><?= e(t('mail.test')) ?></button>
                <span class="hint"><?= e(t('mail.test_hint')) ?></span>
            </form>
        </div>
