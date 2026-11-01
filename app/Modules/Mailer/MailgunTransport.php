<?php

namespace App\Modules\Mailer;

use Symfony\Component\Mime\Address;

/**
 * Mail through Mailgun's HTTP API (PLAN.md O-72; the owner chose it, with Brevo): one POST of
 * a form to /v3/{domain}/messages, the key as the password of the user "api". A Mailgun
 * account lives in one region, and the EU's has an address of its own: a key sent to the
 * other region is refused as if it were wrong.
 */
final class MailgunTransport extends ApiTransport
{
    /** The regions, by what is stored, and the API each answers at. */
    public const REGIONS = ['us' => 'https://api.mailgun.net', 'eu' => 'https://api.eu.mailgun.net'];

    /**
     * @param (\Closure(string, list<string>, string): array{int, string})|null $post
     */
    public function __construct(string $apiKey, private readonly string $domain, private readonly string $region = 'us', ?\Closure $post = null)
    {
        parent::__construct($apiKey, $post);
    }

    public function __toString(): string
    {
        return 'mailgun://' . $this->domain;
    }

    protected function name(): string
    {
        return 'Mailgun';
    }

    protected function keyRequired(): string
    {
        return t('mail.mailgun_key_required');
    }

    protected function request(array $mail): array
    {
        $fields = array_filter([
            'from' => $mail['from']->toString(),
            'to' => implode(', ', array_map(static fn (Address $a): string => $a->toString(), $mail['to'])),
            'h:Reply-To' => implode(', ', array_map(static fn (Address $a): string => $a->toString(), $mail['replyTo'])),
            'subject' => $mail['subject'],
            'text' => $mail['text'],
            'html' => $mail['html'],
        ], static fn (?string $value): bool => $value !== null && $value !== '');
        $base = self::REGIONS[$this->region] ?? self::REGIONS['us'];

        return [
            $base . '/v3/' . rawurlencode($this->domain) . '/messages',
            ['Authorization: Basic ' . base64_encode('api:' . $this->apiKey), 'Content-Type: application/x-www-form-urlencoded'],
            http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
        ];
    }

    /**
     * Mailgun answers a wrong key, or a key sent to the other region, with a bare "Forbidden":
     * said here as what to check.
     */
    protected function refusal(mixed $answer, int $status): ?string
    {
        if ($status === 401) {
            return t('mail.mailgun_forbidden');
        }

        return is_array($answer) && is_string($answer['message'] ?? null) ? $answer['message'] : null;
    }

    protected function messageId(mixed $answer): ?string
    {
        return is_array($answer) && is_string($answer['id'] ?? null) ? $answer['id'] : null;
    }
}
