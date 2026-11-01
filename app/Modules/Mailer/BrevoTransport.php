<?php

namespace App\Modules\Mailer;

use Symfony\Component\Mime\Address;

/**
 * Mail through Brevo's HTTP API (PLAN.md O-72; the owner chose it, with Mailgun): one POST of
 * JSON to /v3/smtp/email, the key in an api-key header. Brevo takes one reply-to address; a
 * contact form has one, the visitor's.
 */
final class BrevoTransport extends ApiTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __toString(): string
    {
        return 'brevo://api';
    }

    protected function name(): string
    {
        return 'Brevo';
    }

    protected function keyRequired(): string
    {
        return t('mail.brevo_key_required');
    }

    protected function request(array $mail): array
    {
        $person = static fn (Address $a): array => array_filter(['email' => $a->getAddress(), 'name' => $a->getName()], static fn (string $v): bool => $v !== '');
        $payload = array_filter([
            'sender' => $person($mail['from']),
            'to' => array_map($person, $mail['to']),
            'replyTo' => $mail['replyTo'] !== [] ? $person($mail['replyTo'][0]) : null,
            'subject' => $mail['subject'],
            'textContent' => $mail['text'],
            'htmlContent' => $mail['html'],
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return [self::ENDPOINT, ['api-key: ' . $this->apiKey, 'Content-Type: application/json', 'Accept: application/json'], self::json($payload)];
    }

    protected function refusal(mixed $answer, int $status): ?string
    {
        return is_array($answer) && is_string($answer['message'] ?? null) ? $answer['message'] : null;
    }

    protected function messageId(mixed $answer): ?string
    {
        return is_array($answer) && is_string($answer['messageId'] ?? null) ? $answer['messageId'] : null;
    }
}
