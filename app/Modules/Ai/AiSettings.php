<?php

namespace App\Modules\Ai;

use App\Core\Db;
use App\Core\Settings;
use App\Support\Secret;

/**
 * AI TRANSLATION'S SETTINGS (PLAN.md D-043, D-248, the owner): which provider, which model, the
 * owner's own API key, the words never to translate and how to write. Off until a key is saved:
 * Boxlet never carries someone else's bill.
 *
 * One provider so far, Anthropic's Claude; the provider is a setting so a second one is a value,
 * not a migration. The key is sealed with the site's APP_KEY, as the mail passwords are (D-045).
 */
final class AiSettings
{
    public const PROVIDERS = ['anthropic'];

    /**
     * The models offered until Anthropic has been asked for its list (D-255), and when it cannot
     * be: newest and most capable first, id => what it is. "Another model" takes an id neither
     * list knows.
     */
    public const MODELS = [
        'claude-sonnet-5' => 'Claude Sonnet 5',
        'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
        'claude-opus-5-5' => 'Claude Opus 5.5',
    ];

    public const DEFAULT_MODEL = 'claude-sonnet-5';

    /** What a model id may be: letters, digits, dots and dashes. */
    public const MODEL_ID = '~^[a-z0-9][a-z0-9.\-]{2,79}$~';

    /**
     * The models the panel offers: Anthropic's list as last fetched with the owner's key, else
     * the built-in one.
     *
     * @return array<string, string>
     */
    public static function models(Db $db): array
    {
        $fetched = Settings::get($db, 'ai_models');
        $models = [];
        foreach (is_array($fetched) ? $fetched : [] as $id => $name) {
            if (is_string($id) && is_string($name) && preg_match(self::MODEL_ID, $id) === 1) {
                $models[$id] = $name;
            }
        }

        return $models !== [] ? $models : self::MODELS;
    }

    /**
     * The model the panel marks recommended: the newest Sonnet on the list (Anthropic lists the
     * newest first), so the mark moves when a new one comes out; else the built-in default.
     *
     * @param array<string, string> $models
     */
    public static function recommended(array $models): string
    {
        foreach (array_keys($models) as $id) {
            if (str_starts_with($id, 'claude-sonnet-')) {
                return $id;
            }
        }

        return self::DEFAULT_MODEL;
    }

    /**
     * Keeps the list Anthropic gave, for the panel. An empty one changes nothing.
     *
     * @param array<string, string> $models
     */
    public static function rememberModels(Db $db, array $models): void
    {
        if ($models !== []) {
            Settings::set($db, 'ai_models', $models);
        }
    }

    /**
     * What is stored, the key still sealed.
     *
     * @return array{provider: string, model: string, key: string, keep: string, voice: string}
     */
    public static function stored(Db $db): array
    {
        $values = Settings::many($db, ['ai_provider', 'ai_model', 'ai_key', 'ai_keep', 'ai_voice'], '');
        $text = static fn (string $name): string => is_string($values[$name] ?? null) ? $values[$name] : '';

        return [
            'provider' => in_array($text('ai_provider'), self::PROVIDERS, true) ? $text('ai_provider') : 'anthropic',
            'model' => $text('ai_model') !== '' ? $text('ai_model') : self::DEFAULT_MODEL,
            'key' => $text('ai_key'),
            'keep' => $text('ai_keep'),
            'voice' => $text('ai_voice'),
        ];
    }

    /** Whether AI translation can be offered: a key is saved and can be opened with this site's key. */
    public static function ready(Db $db, string $appKey): bool
    {
        return Secret::open(self::stored($db)['key'], $appKey) !== '';
    }

    /**
     * Saves the panel, or says what is wrong with it, field => message. An empty key field keeps
     * the key there is; "remove" takes it away.
     *
     * @param array{provider: string, model: string, model_other: string, key: string, keep: string, voice: string, remove_key: bool} $input
     * @return array<string, string>
     */
    public static function save(Db $db, array $input, string $appKey): array
    {
        $errors = [];
        if (!in_array($input['provider'], self::PROVIDERS, true)) {
            $errors['provider'] = t('ai.provider_invalid');
        }
        $model = $input['model'] === 'other' ? trim($input['model_other']) : $input['model'];
        if ($input['model'] !== 'other' && !isset(self::models($db)[$model]) && !isset(self::MODELS[$model])) {
            $errors['model'] = t('ai.model_invalid');
        }
        if ($input['model'] === 'other' && preg_match(self::MODEL_ID, $model) !== 1) {
            $errors['model_other'] = t('ai.model_other_invalid');
        }
        $key = trim($input['key']);
        if ($key !== '' && preg_match('~^[\x21-\x7e]{20,300}$~', $key) !== 1) {
            $errors['key'] = t('ai.key_invalid');
        }
        if (mb_strlen($input['keep']) > 2000 || mb_strlen($input['voice']) > 2000) {
            $errors['keep'] = t('ai.too_long');
        }
        if ($errors !== []) {
            return $errors;
        }

        $write = [
            'ai_provider' => $input['provider'],
            'ai_model' => $model,
            'ai_keep' => trim($input['keep']),
            'ai_voice' => trim($input['voice']),
        ];
        if ($input['remove_key']) {
            $write['ai_key'] = '';
        } elseif ($key !== '') {
            $write['ai_key'] = Secret::seal($key, $appKey);
        }
        foreach ($write as $name => $value) {
            Settings::set($db, $name, $value);
        }

        return [];
    }

    /**
     * What the open key, the model and the owner's words come to for a request; null with no key.
     *
     * @return array{key: string, model: string, keep: string, voice: string}|null
     */
    public static function forRequest(Db $db, string $appKey): ?array
    {
        $stored = self::stored($db);
        $key = Secret::open($stored['key'], $appKey);

        return $key === '' ? null : ['key' => $key, 'model' => $stored['model'], 'keep' => $stored['keep'], 'voice' => $stored['voice']];
    }

    /**
     * The tokens used so far, in and out, as the provider counted them: for the panel, so the
     * owner can set it beside their bill.
     *
     * @return array{in: int, out: int}
     */
    public static function usage(Db $db): array
    {
        $used = Settings::get($db, 'ai_usage');

        return ['in' => (int) (is_array($used) ? ($used['in'] ?? 0) : 0), 'out' => (int) (is_array($used) ? ($used['out'] ?? 0) : 0)];
    }

    public static function addUsage(Db $db, int $in, int $out): void
    {
        $used = self::usage($db);
        Settings::set($db, 'ai_usage', ['in' => $used['in'] + $in, 'out' => $used['out'] + $out]);
    }
}
