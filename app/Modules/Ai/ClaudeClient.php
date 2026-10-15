<?php

namespace App\Modules\Ai;

/**
 * ONE QUESTION TO CLAUDE (PLAN.md D-248), or the list of its models (D-255): Anthropic's API
 * over plain HTTPS, with curl as ResendTransport talks to Resend, so no SDK joins the closed
 * list of what Boxlet runs on.
 *
 * Returns the answer's text and what it cost in tokens, or throws AiFailure with a sentence the
 * owner can act on: the key refused, the model unknown, the account out of credit, the service
 * busy, no answer at all. The provider's own words are kept beside it for the log.
 */
final class ClaudeClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const MODELS = 'https://api.anthropic.com/v1/models';
    private const VERSION = '2023-06-01';

    /**
     * @return array{text: string, in: int, out: int}
     */
    public static function ask(string $key, string $model, string $system, string $user, int $maxTokens = 4096): array
    {
        $answer = self::call($key, self::ENDPOINT, [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $user]],
        ], $model);
        $text = '';
        foreach (is_array($answer['content'] ?? null) ? $answer['content'] : [] as $part) {
            if (is_array($part) && ($part['type'] ?? '') === 'text' && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }
        if ($text === '') {
            throw new AiFailure(t('ai.error.empty'));
        }

        return [
            'text' => $text,
            'in' => (int) ($answer['usage']['input_tokens'] ?? 0),
            'out' => (int) ($answer['usage']['output_tokens'] ?? 0),
        ];
    }

    /**
     * The models this key may use, as Anthropic lists them, newest first: id => name (D-255).
     * Asking costs nothing; the panel offers what comes back, so a model that came out after this
     * release is in the list the next time the owner saves or tests.
     *
     * @return array<string, string>
     */
    public static function models(string $key): array
    {
        $answer = self::call($key, self::MODELS . '?limit=100', null, '');
        $models = [];
        foreach (is_array($answer['data'] ?? null) ? $answer['data'] : [] as $model) {
            $id = is_array($model) && is_string($model['id'] ?? null) ? $model['id'] : '';
            if (preg_match(AiSettings::MODEL_ID, $id) === 1) {
                $name = is_string($model['display_name'] ?? null) && $model['display_name'] !== '' ? $model['display_name'] : $id;
                $models[$id] = mb_substr($name, 0, 80);
            }
        }

        return $models;
    }

    /**
     * One request: a POST with $payload, a GET without. The decoded answer, or AiFailure.
     *
     * @param array<string, mixed>|null $payload
     * @return array<mixed>
     */
    private static function call(string $key, string $url, ?array $payload, string $model): array
    {
        if (!function_exists('curl_init')) {
            throw new AiFailure(t('ai.error.no_curl'));
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new AiFailure(t('ai.error.no_answer', ['reason' => 'curl']));
        }
        $options = [
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'x-api-key: ' . $key,
                'anthropic-version: ' . self::VERSION,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $payload === null ? 20 : 90,
        ];
        if ($payload !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($body) || $status === 0) {
            throw new AiFailure(t('ai.error.no_answer', ['reason' => $error !== '' ? $error : 'no response']));
        }
        $answer = json_decode($body, true);
        if ($status !== 200) {
            $said = is_array($answer) && is_string($answer['error']['message'] ?? null) ? $answer['error']['message'] : 'HTTP ' . $status;
            throw new AiFailure(match (true) {
                $status === 401, $status === 403 => t('ai.error.key'),
                $status === 404 => t('ai.error.model', ['model' => $model]),
                $status === 429 => t('ai.error.busy'),
                $status === 529, $status >= 500 => t('ai.error.overloaded'),
                str_contains(strtolower($said), 'credit') => t('ai.error.credit'),
                default => t('ai.error.refused', ['reason' => $said]),
            }, $said);
        }

        return is_array($answer) ? $answer : [];
    }
}
