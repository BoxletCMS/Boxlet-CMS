<?php

namespace App\Modules\Mailer;

use Symfony\Component\Mime\Address;

/**
 * Mail through Resend's HTTP API (PLAN.md D-045): one POST of JSON to /emails, the key as a
 * bearer token. What is common to the providers is ApiTransport's.
 */
final class ResendTransport extends ApiTransport
{
    private const ENDPOINT = 'https://api.resend.com/emails';

    public function __toString(): string
    {
        return 'resend://api';
    }

    protected function name(): string
    {
        return 'Resend';
    }

    protected function keyRequired(): string
    {
        return t('mail.resend_key_required');
    }

    protected function request(array $mail): array
    {
        $payload = array_filter([
            'from' => $mail['from']->toString(),
            'to' => array_map(static fn (Address $a): string => $a->toString(), $mail['to']),
            'reply_to' => array_map(static fn (Address $a): string => $a->toString(), $mail['replyTo']),
            'subject' => $mail['subject'],
            'text' => $mail['text'],
            'html' => $mail['html'],
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        return [self::ENDPOINT, ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json'], self::json($payload)];
    }

    protected function refusal(mixed $answer, int $status): ?string
    {
        return is_array($answer) && is_string($answer['message'] ?? null) ? $answer['message'] : null;
    }

    protected function messageId(mixed $answer): ?string
    {
        return is_array($answer) && is_string($answer['id'] ?? null) ? $answer['id'] : null;
    }
}
