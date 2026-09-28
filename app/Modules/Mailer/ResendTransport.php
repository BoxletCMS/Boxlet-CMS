<?php

namespace App\Modules\Mailer;

use Closure;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * Mail through Resend's HTTP API (PLAN.md D-045): one POST to /emails, over HTTPS, which a
 * shared host lets out where it blocks the SMTP ports.
 *
 * Boxlet's own rather than symfony/resend-mailer, which would add that package and
 * symfony/http-client to the closed dependency list for one request. PHP's curl makes it,
 * which every host this is written for has.
 *
 * What it sends is what a contact form needs — from, to, reply-to, subject, text and HTML —
 * and nothing more: no attachments, no templates, no scheduling. A message carrying
 * something it cannot send is refused rather than sent without it.
 */
final class ResendTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.resend.com/emails';

    /** @var Closure(string, string, string): array{int, string} */
    private Closure $post;

    /**
     * @param (Closure(string, string, string): array{int, string})|null $post the HTTP POST:
     *        URL, API key and JSON body in, status and response body out. Tests replace it;
     *        everything else uses curl.
     */
    public function __construct(private readonly string $apiKey, ?Closure $post = null)
    {
        parent::__construct();
        $this->post = $post ?? self::curl(...);
    }

    public function __toString(): string
    {
        return 'resend://api';
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->apiKey === '') {
            throw new TransportException(t('mail.resend_key_required'));
        }
        $original = $message->getOriginalMessage();
        if (!$original instanceof Message) {
            throw new TransportException('Resend: only a composed message can be sent.');
        }
        $email = MessageConverter::toEmail($original);
        if ($email->getAttachments() !== []) {
            throw new TransportException('Attachments are not sent through Resend by Boxlet.');
        }
        $envelope = $message->getEnvelope();
        $payload = array_filter([
            'from' => $envelope->getSender()->toString(),
            'to' => array_map(static fn (Address $a): string => $a->toString(), $envelope->getRecipients()),
            'reply_to' => array_map(static fn (Address $a): string => $a->toString(), $email->getReplyTo()),
            'subject' => (string) $email->getSubject(),
            'text' => is_string($email->getTextBody()) ? $email->getTextBody() : null,
            'html' => is_string($email->getHtmlBody()) ? $email->getHtmlBody() : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        [$status, $body] = ($this->post)(self::ENDPOINT, $this->apiKey, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $answer = json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            // Resend's own words where it gave them: "The gmail.com domain is not verified"
            // tells an owner what to do; "HTTP 403" does not.
            $said = is_array($answer) && is_string($answer['message'] ?? null) ? $answer['message'] : "HTTP {$status}";
            throw new TransportException('Resend: ' . $said);
        }
        if (is_array($answer) && is_string($answer['id'] ?? null)) {
            $message->setMessageId($answer['id']);
        }
    }

    /**
     * @return array{int, string}
     */
    private static function curl(string $url, string $key, string $json): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new TransportException('Resend: curl could not start.');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($body)) {
            throw new TransportException('Resend could not be reached: ' . $error);
        }

        return [$status, $body];
    }
}
