<?php

namespace App\Modules\Forms;

use App\Core\Container;
use App\Core\Settings;
use App\Modules\Mailer\MailSettings;
use App\Support\Url;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * The two messages a form may send once a visitor's message is stored (PLAN.md D-046): the
 * owner told about it, and the sender answered with the words the owner wrote.
 *
 * THE MESSAGE IS ALREADY SAFE when this runs. Mail that fails — a wrong password, a host
 * that blocks the port — never costs the visitor's message or their thank-you page: the
 * failure is recorded (MailSettings::recordFailure) and shown to the owner in the admin,
 * where the message itself is waiting.
 *
 * The notification is in the admin's language, for the owner; the reply is the owner's own
 * words, in the form's language.
 */
final class FormMail
{
    /**
     * @param array{id: int, locale: string, name: string, fields: list<array<string, mixed>>, settings: array{submit: string, success: string, notify: bool, autoreply: bool, autoreply_subject: string, autoreply_body: string}} $form
     * @param list<array{key: string, label: string, type: string, value: string}> $answers
     * @param array<string, mixed>|null $page
     */
    public static function send(Container $container, array $form, array $answers, ?array $page): void
    {
        $db = $container->get('db');
        if (!MailSettings::configured($db)) {
            return;
        }
        $addresses = MailSettings::addresses($db);
        $site = Settings::text($db, 'site_name');
        $from = new Address($addresses['from'], $addresses['fromName']);
        $sender = '';
        foreach ($answers as $answer) {
            if ($answer['type'] === 'email' && $answer['value'] !== '' && $sender === '') {
                $sender = $answer['value'];
            }
        }
        $transport = $container->get('mail_transport');

        try {
            if ($form['settings']['notify'] && $addresses['notify'] !== '') {
                $lines = [];
                foreach ($answers as $answer) {
                    $value = $answer['type'] === 'checkbox' ? t($answer['value'] === '1' ? 'forms.mail.ticked' : 'forms.mail.not_ticked') : $answer['value'];
                    $lines[] = $answer['label'] . ":\n" . ($value !== '' ? $value : '—');
                }
                $lines[] = '';
                if ($page !== null) {
                    $lines[] = t('forms.mail.from_page', ['page' => (string) $page['title'], 'url' => Url::canonical((string) $page['locale'], (string) $page['slug'])]);
                }
                $lines[] = t('forms.mail.all_messages', ['url' => Url::withOrigin(Url::admin('forms', $form['id'], 'messages'))]);

                $notice = (new Email())
                    ->from($from)
                    ->to($addresses['notify'])
                    ->subject(t('forms.mail.subject', ['form' => $form['name'], 'site' => $site]))
                    ->text(implode("\n\n", $lines));
                if ($sender !== '') {
                    // Replying to the notification writes to the visitor, which is what an
                    // owner reading it means to do.
                    $notice->replyTo($sender);
                }
                $transport->send($notice);
            }

            if ($form['settings']['autoreply'] && $form['settings']['autoreply_body'] !== '' && $sender !== '') {
                $transport->send((new Email())
                    ->from($from)
                    ->to($sender)
                    ->subject($form['settings']['autoreply_subject'] !== '' ? $form['settings']['autoreply_subject'] : $site)
                    ->text($form['settings']['autoreply_body']));
            }
        } catch (TransportExceptionInterface | RfcComplianceException $e) {
            MailSettings::recordFailure($db, $e->getMessage());
        }
    }
}
