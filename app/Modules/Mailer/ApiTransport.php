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
 * MAIL THROUGH A PROVIDER'S HTTP API (PLAN.md D-045, O-72): one POST over HTTPS, which a shared
 * host lets out where it blocks the SMTP ports. Resend, Brevo and Mailgun each say how their
 * request is written and how their answer reads; what is common is here — the message taken
 * apart, the key required, curl, and the provider's own words when it refuses.
 *
 * Boxlet's own rather than Symfony's bridges (symfony/resend-mailer, brevo-mailer,
 * mailgun-mailer), each of which would add its package and symfony/http-client to the closed
 * dependency list for one request. PHP's curl makes it, which every host this is written for has.
 *
 * What it sends is what a contact form needs — from, to, reply-to, subject, text and HTML —
 * and nothing more: no attachments, no templates, no scheduling. A message carrying something
 * it cannot send is refused rather than sent without it.
 */
abstract class ApiTransport extends AbstractTransport
{
    /** @var Closure(string, list<string>, string): array{int, string} */
    private Closure $post;

    /**
     * @param (Closure(string, list<string>, string): array{int, string})|null $post the HTTP
     *        POST: URL, headers and body in, status and response body out. Tests replace it;
     *        everything else uses curl.
     */
    public function __construct(protected readonly string $apiKey, ?Closure $post = null)
    {
        parent::__construct();
        $this->post = $post ?? self::curl(...);
    }

    /** The provider's name, as the owner chose it and as a refusal is prefixed. */
    abstract protected function name(): string;

    /** What is said when no key is saved. */
    abstract protected function keyRequired(): string;

    /**
     * The request for one message.
     *
     * @param array{from: Address, to: list<Address>, replyTo: list<Address>, subject: string, text: ?string, html: ?string} $mail
     * @return array{string, list<string>, string} the URL, the headers and the body
     */
    abstract protected function request(array $mail): array;

    /** The provider's own words in a refusal's answer, or null where it gave none. */
    abstract protected function refusal(mixed $answer, int $status): ?string;

    /** The provider's id for a message it took, or null. */
    abstract protected function messageId(mixed $answer): ?string;

    protected function doSend(SentMessage $message): void
    {
        if ($this->apiKey === '') {
            throw new TransportException($this->keyRequired());
        }
        $original = $message->getOriginalMessage();
        if (!$original instanceof Message) {
            throw new TransportException($this->name() . ': only a composed message can be sent.');
        }
        $email = MessageConverter::toEmail($original);
        if ($email->getAttachments() !== []) {
            throw new TransportException('Attachments are not sent through ' . $this->name() . ' by Boxlet.');
        }
        $envelope = $message->getEnvelope();
        [$url, $headers, $body] = $this->request([
            'from' => $envelope->getSender(),
            'to' => array_values($envelope->getRecipients()),
            'replyTo' => array_values($email->getReplyTo()),
            'subject' => (string) $email->getSubject(),
            'text' => is_string($email->getTextBody()) ? $email->getTextBody() : null,
            'html' => is_string($email->getHtmlBody()) ? $email->getHtmlBody() : null,
        ]);

        [$status, $raw] = ($this->post)($url, $headers, $body);
        $answer = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            // The provider's own words where it gave them: "The gmail.com domain is not
            // verified" tells an owner what to do; "HTTP 403" does not.
            throw new TransportException($this->name() . ': ' . ($this->refusal($answer, $status) ?? "HTTP {$status}"));
        }
        $id = $this->messageId($answer);
        if ($id !== null) {
            $message->setMessageId($id);
        }
    }

    /** The JSON a request is written in, as each of these providers takes it. */
    protected static function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param list<string> $headers
     * @return array{int, string}
     */
    private static function curl(string $url, array $headers, string $body): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new TransportException('Mail: curl could not start.');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        $answer = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($answer)) {
            throw new TransportException((string) parse_url($url, PHP_URL_HOST) . ' could not be reached: ' . $error);
        }

        return [$status, $answer];
    }
}
