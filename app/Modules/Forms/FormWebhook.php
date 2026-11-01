<?php

namespace App\Modules\Forms;

use App\Core\Container;
use App\Support\PublicAddress;
use App\Support\Secret;
use App\Support\Url;
use Closure;
use RuntimeException;
use Throwable;

/**
 * A FORM'S MESSAGES SENT ON TO A WEB ADDRESS (PLAN.md O-77; the owner, 2026-10-22: "admini
 * često imaju potrebu prikupljene podatke negdje poslati"). Each message, as JSON, in one POST,
 * signed so the receiver can tell it came from this site: Zapier, Make, n8n and one's own code
 * all take it.
 *
 * WHAT IT MAY CALL. Only https, and only an address that resolves to the public internet: a
 * form a visitor sends must not make the server knock on its own neighbours (127.0.0.1, the
 * host's private network, a cloud's metadata address). The address is resolved once and the
 * request pinned to what was checked, so a name cannot answer differently the second time; a
 * redirect is not followed. A few seconds at most, so the visitor is never kept waiting.
 *
 * WHEN IT FAILS, the message is kept until it is sent or deleted (the owner: "možemo li dodati
 * gumb za ručno slanje hooka ako nije prošao"), whatever the form's keep setting, and at most
 * thirty days: there is no cron to try again, and a form that keeps nothing would otherwise
 * have nothing to send again.
 */
final class FormWebhook
{
    public const EVENT = 'form.submitted';
    public const TEST_EVENT = 'form.test';
    private const CONNECT_SECONDS = 3;
    private const SECONDS = 5;
    /** How long a message kept only because its sending failed is kept. */
    public const KEEP_DAYS = 30;

    /**
     * Whether an address can be saved as a form's: '' when it can, else what is wrong with it.
     * Where it resolves is checked again at every send.
     */
    public static function invalid(string $url): string
    {
        return $url === '' ? '' : PublicAddress::invalid($url);
    }

    /**
     * What is sent for one message: the form, the page it came from, each answer with its
     * label, the answers again by key (what Zapier and Make map most easily), the time.
     *
     * @param array{id: int, locale: string, name: string} $form
     * @param list<array{key: string, label: string, type: string, value: string}> $answers
     * @param array<string, mixed>|null $page
     * @return array<string, mixed>
     */
    public static function payload(array $form, array $answers, ?array $page, string $at, string $event = self::EVENT): array
    {
        return [
            'event' => $event,
            'form' => ['id' => $form['id'], 'name' => $form['name'], 'language' => $form['locale']],
            'page' => $page === null ? null : [
                'id' => (int) $page['id'],
                'title' => (string) $page['title'],
                'url' => Url::canonical((string) $page['locale'], (string) $page['slug']),
            ],
            'fields' => $answers,
            'values' => array_column($answers, 'value', 'key'),
            'submitted_at' => str_replace(' ', 'T', $at) . 'Z',
        ];
    }

    /**
     * Sends $payload to the form's address. '' when it was taken (any 2xx), else why not, in
     * words for the owner.
     *
     * @param array{settings: array{webhook: string, webhook_secret: string}} $form
     * @param array<string, mixed> $payload
     */
    public static function send(Container $container, array $form, array $payload): string
    {
        $url = $form['settings']['webhook'];
        $secret = Secret::open($form['settings']['webhook_secret'], (string) $container->get('config')->get('app.key'));
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'User-Agent: Boxlet',
            'X-Boxlet-Event: ' . (string) ($payload['event'] ?? self::EVENT),
            'X-Boxlet-Signature: sha256=' . hash_hmac('sha256', $body, $secret),
        ];
        try {
            /** @var Closure(string, list<string>, string): array{int, string} $post */
            $post = $container->get('form_webhook');
            [$status] = $post($url, $headers, $body);
        } catch (Throwable $e) {
            return mb_substr($e->getMessage(), 0, 500);
        }

        return $status >= 200 && $status < 300 ? '' : t('forms.webhook_status', ['status' => (string) $status]);
    }

    /**
     * The POST itself, through curl, to the address checked and pinned (see the class).
     *
     * @param list<string> $headers
     * @param (Closure(string): list<string>)|null $resolve a host's addresses; DNS by default
     * @return array{int, string}
     */
    public static function post(string $url, array $headers, string $body, ?Closure $resolve = null): array
    {
        $address = PublicAddress::check($url, $resolve);

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('curl could not start.');
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => [$address['pin']],
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_SECONDS,
            CURLOPT_TIMEOUT => self::SECONDS,
        ]);
        $answer = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($answer)) {
            throw new RuntimeException(t('address.unreachable', ['host' => $address['host'], 'error' => $error]));
        }

        return [$status, $answer];
    }

    /** A secret for a new address: what the receiver checks the signature with. */
    public static function newSecret(): string
    {
        return 'whsec_' . bin2hex(random_bytes(24));
    }
}
