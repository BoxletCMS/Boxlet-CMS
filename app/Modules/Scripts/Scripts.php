<?php

namespace App\Modules\Scripts;

use App\Core\Db;

/**
 * The owner's own scripts (PLAN.md O-73): the one place their rows are read and written, and
 * their shapes checked.
 *
 * A script is the owner's code as written — a statistics snippet, a chat widget — put in the
 * head or at the end of the body of every page. Its KIND says when it runs: a necessary one
 * always, a statistics or marketing one only once the visitor has agreed to that kind
 * (Consent). Its COOKIES are what the banner tells the visitor it sets.
 *
 * @phpstan-type Cookie array{name: string, provider: string, purpose: string, duration: string}
 * @phpstan-type Script array{id: int, name: string, code: string, place: string, kind: string, cookies: list<Cookie>, enabled: bool, position: int}
 */
final class Scripts
{
    public const PLACES = ['head', 'body'];

    /** The kinds, in the order the banner lists them. Only necessary runs without consent. */
    public const KINDS = ['necessary', 'statistics', 'marketing'];

    /** The kinds that wait for a visitor's yes. */
    public const CONSENTED = ['statistics', 'marketing'];

    private const MAX_CODE = 50000;

    /**
     * Every script, in the order they are placed.
     *
     * @return list<Script>
     */
    public static function all(Db $db): array
    {
        $scripts = [];
        foreach ($db->all('SELECT * FROM scripts ORDER BY position, id') as $row) {
            $scripts[] = self::row($row);
        }

        return $scripts;
    }

    /**
     * The scripts a visitor's page carries.
     *
     * @return list<Script>
     */
    public static function enabled(Db $db): array
    {
        return array_values(array_filter(self::all($db), static fn (array $s): bool => $s['enabled']));
    }

    /**
     * Read afresh each time: a save or a delete in between changes the answer.
     *
     * @phpstan-impure
     * @return Script|null
     */
    public static function find(Db $db, int $id): ?array
    {
        $row = $db->one('SELECT * FROM scripts WHERE id = ?', [$id]);

        return $row === null ? null : self::row($row);
    }

    /**
     * What the edit screen posted, checked. Errors by field.
     *
     * @param array<mixed> $posted name, code, place, kind, enabled, cookies[n][name|provider|purpose|duration]
     * @return array{script: array{name: string, code: string, place: string, kind: string, cookies: list<Cookie>, enabled: bool}, errors: array<string, string>}
     */
    public static function parse(array $posted): array
    {
        $text = static fn (string $key, int $max): string => mb_substr(trim(is_string($posted[$key] ?? null) ? $posted[$key] : ''), 0, $max);
        $code = trim(str_replace("\0", '', is_string($posted['code'] ?? null) ? $posted['code'] : ''));
        $errors = [];
        $script = [
            'name' => $text('name', 190),
            'code' => $code,
            'place' => in_array($posted['place'] ?? '', self::PLACES, true) ? (string) $posted['place'] : 'head',
            'kind' => in_array($posted['kind'] ?? '', self::KINDS, true) ? (string) $posted['kind'] : 'statistics',
            'cookies' => self::cookies(is_array($posted['cookies'] ?? null) ? $posted['cookies'] : []),
            'enabled' => ($posted['enabled'] ?? '') === '1',
        ];
        if ($script['name'] === '') {
            $errors['name'] = t('scripts.name_required');
        }
        if ($code === '') {
            $errors['code'] = t('scripts.code_required');
        } elseif (mb_strlen($code) > self::MAX_CODE) {
            $errors['code'] = t('scripts.code_long');
        } elseif (stripos($code, '</template') !== false) {
            // A script waiting for consent is drawn inside a <template> (Consent); a closing
            // tag of its own would end it early and run the rest at once.
            $errors['code'] = t('scripts.code_template');
        }

        return ['script' => $script, 'errors' => $errors];
    }

    /**
     * Writes a parsed script: a new one at the end of its place when $id is null.
     *
     * @param array{name: string, code: string, place: string, kind: string, cookies: list<Cookie>, enabled: bool} $script
     */
    public static function save(Db $db, ?int $id, array $script): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $json = json_encode($script['cookies'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if ($id === null) {
            $last = (int) ($db->one('SELECT MAX(position) AS n FROM scripts')['n'] ?? 0);
            $db->query(
                'INSERT INTO scripts (name, code, place, kind, cookies_json, enabled, position, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$script['name'], $script['code'], $script['place'], $script['kind'], $json, $script['enabled'] ? 1 : 0, $last + 1, $now, $now],
            );

            return (int) $db->lastInsertId();
        }
        $db->query(
            'UPDATE scripts SET name = ?, code = ?, place = ?, kind = ?, cookies_json = ?, enabled = ?, updated_at = ? WHERE id = ?',
            [$script['name'], $script['code'], $script['place'], $script['kind'], $json, $script['enabled'] ? 1 : 0, $now, $id],
        );

        return $id;
    }

    public static function delete(Db $db, int $id): void
    {
        $db->query('DELETE FROM scripts WHERE id = ?', [$id]);
    }

    /**
     * The cookie rows posted, the empty ones left out.
     *
     * @param array<mixed> $rows
     * @return list<Cookie>
     */
    public static function cookies(array $rows): array
    {
        $cookies = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $field = static fn (string $key, int $max): string => mb_substr(trim(is_string($row[$key] ?? null) ? $row[$key] : ''), 0, $max);
            $cookie = ['name' => $field('name', 120), 'provider' => $field('provider', 120), 'purpose' => $field('purpose', 300), 'duration' => $field('duration', 60)];
            if ($cookie['name'] !== '') {
                $cookies[] = $cookie;
            }
        }

        return $cookies;
    }

    /**
     * @param array<string, mixed> $row
     * @return Script
     */
    private static function row(array $row): array
    {
        $stored = json_decode((string) $row['cookies_json'], true);

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'code' => (string) $row['code'],
            'place' => in_array($row['place'], self::PLACES, true) ? (string) $row['place'] : 'head',
            'kind' => in_array($row['kind'], self::KINDS, true) ? (string) $row['kind'] : 'statistics',
            'cookies' => self::cookies(is_array($stored) ? $stored : []),
            'enabled' => (int) $row['enabled'] === 1,
            'position' => (int) $row['position'],
        ];
    }
}
