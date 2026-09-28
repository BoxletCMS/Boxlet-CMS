<?php

namespace App\Modules\Forms;

use App\Core\Db;
use App\Modules\Pages\Slug;

/**
 * A form: its fields and what it does when sent (PLAN.md D-046). The only writer of
 * forms.fields_json and forms.settings_json, and the one place their shapes are checked.
 *
 * A CLOSED SET OF FIELD TYPES, each one a thing a small site's form actually asks: a line
 * of text, an email address, a phone number, a message, a choice from a list, and a box to
 * tick (consent, a newsletter). No file uploads — a public upload is a door, and SPEC §6's
 * rules for uploads were written for one trusted admin.
 *
 * A field's KEY is fixed when it is made, from its first label, and never changes: a label
 * is edited freely, and the key is what ties an answer to its field while the form is sent.
 *
 * @phpstan-type Field array{key: string, type: string, label: string, required: bool, options: list<string>}
 * @phpstan-type FormSettings array{submit: string, success: string, notify: bool, autoreply: bool, autoreply_subject: string, autoreply_body: string}
 * @phpstan-type FormRow array{id: int, locale: string, name: string, fields: list<Field>, settings: FormSettings}
 */
final class Form
{
    public const TYPES = ['text', 'email', 'tel', 'textarea', 'select', 'checkbox'];

    /** Enough for a contact form, a booking request and a short survey; a form past this is a questionnaire. */
    public const MAX_FIELDS = 20;

    /**
     * Every form, with how many messages it has and how many are unread.
     *
     * @return list<array{id: int, locale: string, name: string, fields: int, messages: int, unread: int}>
     */
    public static function all(Db $db): array
    {
        $rows = $db->all(
            'SELECT f.id, f.locale, f.name, f.fields_json,
                    (SELECT COUNT(*) FROM form_submissions s WHERE s.form_id = f.id) AS messages,
                    (SELECT COUNT(*) FROM form_submissions s WHERE s.form_id = f.id AND s.read_at IS NULL) AS unread
             FROM forms f ORDER BY f.locale, f.name, f.id'
        );

        return array_values(array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'locale' => (string) $row['locale'],
            'name' => (string) $row['name'],
            'fields' => count(self::fields((string) $row['fields_json'])),
            'messages' => (int) $row['messages'],
            'unread' => (int) $row['unread'],
        ], $rows));
    }

    /**
     * @return FormRow|null
     */
    public static function find(Db $db, int $id): ?array
    {
        $row = $db->one('SELECT id, locale, name, fields_json, settings_json FROM forms WHERE id = ?', [$id]);
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'locale' => (string) $row['locale'],
            'name' => (string) $row['name'],
            'fields' => self::fields((string) $row['fields_json']),
            'settings' => self::settings((string) $row['settings_json']),
        ];
    }

    /**
     * The forms a Form block may show on a page in $locale, by id.
     *
     * @return array<int, string>
     */
    public static function choices(Db $db, string $locale): array
    {
        $choices = [];
        foreach ($db->all('SELECT id, name FROM forms WHERE locale = ? ORDER BY name, id', [$locale]) as $row) {
            $choices[(int) $row['id']] = (string) $row['name'];
        }

        return $choices;
    }

    /**
     * A new form, starting as a contact form: name, email and message, the three fields
     * nearly every site asks first. Deleting two is quicker than adding three. Labelled in
     * the form's own language, because visitors read them (site_t, D-044).
     */
    public static function create(Db $db, string $locale, string $name): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $fields = [
            ['key' => 'name', 'type' => 'text', 'label' => site_t('site.form.field_name', $locale), 'required' => true, 'options' => []],
            ['key' => 'email', 'type' => 'email', 'label' => site_t('site.form.field_email', $locale), 'required' => true, 'options' => []],
            ['key' => 'message', 'type' => 'textarea', 'label' => site_t('site.form.field_message', $locale), 'required' => true, 'options' => []],
        ];
        $db->query(
            'INSERT INTO forms (locale, name, fields_json, settings_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$locale, $name, self::json($fields), self::json(self::settings('')), $now, $now],
        );

        return (int) $db->lastInsertId();
    }

    /**
     * Writes a form's name, fields and settings, already checked by parse().
     *
     * @param list<Field> $fields
     * @param FormSettings $settings
     */
    public static function update(Db $db, int $id, string $name, array $fields, array $settings): void
    {
        $db->query(
            'UPDATE forms SET name = ?, fields_json = ?, settings_json = ?, updated_at = ? WHERE id = ?',
            [$name, self::json($fields), self::json($settings), gmdate('Y-m-d H:i:s'), $id],
        );
    }

    public static function delete(Db $db, int $id): void
    {
        $db->query('DELETE FROM forms WHERE id = ?', [$id]);
    }

    /**
     * What the edit screen posted, cleaned: fields in order, with the ones marked removed
     * left out and a new one added where asked, and the settings. Errors by field name.
     *
     * @param array<mixed> $posted the fields as posted: fields[n][key|type|label|required|options|remove]
     * @param array<mixed> $settings the settings as posted
     * @return array{fields: list<Field>, settings: FormSettings, errors: array<string, string>}
     */
    public static function parse(array $posted, array $settings, bool $add): array
    {
        $fields = [];
        $errors = [];
        $taken = [];
        foreach (array_values($posted) as $row) {
            if (!is_array($row) || ($row['remove'] ?? '') === '1') {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $type = in_array($row['type'] ?? '', self::TYPES, true) ? (string) $row['type'] : 'text';
            $options = $type === 'select'
                ? array_values(array_filter(array_map('trim', preg_split('~\R~', (string) ($row['options'] ?? '')) ?: []), static fn (string $o): bool => $o !== ''))
                : [];
            $key = (string) ($row['key'] ?? '');
            if (preg_match('~^[a-z][a-z0-9_]{0,39}$~', $key) !== 1 || isset($taken[$key])) {
                $key = self::key($label, $taken);
            }
            $taken[$key] = true;
            // Numbered as the screen will draw it — the fields kept — not as it was posted,
            // so a message still sits beside its field when another was removed above it.
            $at = count($fields);
            if ($label === '') {
                $errors["fields.{$at}"] = t('forms.field.label_required');
            } elseif ($type === 'select' && $options === []) {
                $errors["fields.{$at}"] = t('forms.field.options_required');
            }
            $fields[] = ['key' => $key, 'type' => $type, 'label' => mb_substr($label, 0, 200), 'required' => ($row['required'] ?? '') === '1', 'options' => $options];
        }
        if ($add) {
            $label = t('forms.field.new');
            $key = self::key($label, $taken);
            $fields[] = ['key' => $key, 'type' => 'text', 'label' => $label, 'required' => false, 'options' => []];
        }
        if ($fields === []) {
            $errors['fields'] = t('forms.fields_required');
        } elseif (count($fields) > self::MAX_FIELDS) {
            $errors['fields'] = t('forms.fields_max', ['max' => (string) self::MAX_FIELDS]);
        }

        $text = static fn (string $key, int $max): string => mb_substr(trim((string) ($settings[$key] ?? '')), 0, $max);

        return [
            'fields' => $fields,
            'settings' => [
                'submit' => $text('submit', 60),
                'success' => $text('success', 500),
                'notify' => ($settings['notify'] ?? '') === '1',
                'autoreply' => ($settings['autoreply'] ?? '') === '1',
                'autoreply_subject' => $text('autoreply_subject', 200),
                'autoreply_body' => $text('autoreply_body', 5000),
            ],
            'errors' => $errors,
        ];
    }

    /**
     * @return list<Field>
     */
    private static function fields(string $json): array
    {
        $stored = json_decode($json, true);
        $fields = [];
        foreach (is_array($stored) ? $stored : [] as $field) {
            if (!is_array($field) || !is_string($field['key'] ?? null) || !in_array($field['type'] ?? null, self::TYPES, true)) {
                continue;
            }
            $fields[] = [
                'key' => $field['key'],
                'type' => (string) $field['type'],
                'label' => (string) ($field['label'] ?? ''),
                'required' => ($field['required'] ?? false) === true,
                'options' => array_values(array_filter(is_array($field['options'] ?? null) ? $field['options'] : [], 'is_string')),
            ];
        }

        return $fields;
    }

    /**
     * Stored settings, every key present. A new form notifies its owner and sends no
     * reply: a reply to a visitor is words the owner should have written.
     *
     * @return FormSettings
     */
    private static function settings(string $json): array
    {
        $stored = json_decode($json, true);
        $stored = is_array($stored) ? $stored : [];
        $string = static fn (string $key): string => is_string($stored[$key] ?? null) ? $stored[$key] : '';

        return [
            'submit' => $string('submit'),
            'success' => $string('success'),
            'notify' => ($stored['notify'] ?? true) === true,
            'autoreply' => ($stored['autoreply'] ?? false) === true,
            'autoreply_subject' => $string('autoreply_subject'),
            'autoreply_body' => $string('autoreply_body'),
        ];
    }

    /**
     * A key for a new field from its label: "E-mail address" gives e_mail_address, and a
     * taken one gets _2, _3.
     *
     * @param array<string, true> $taken
     */
    private static function key(string $label, array $taken): string
    {
        $base = str_replace('-', '_', Slug::fromTitle($label));
        $base = preg_match('~^[a-z]~', $base) === 1 ? substr($base, 0, 36) : 'field';
        $key = $base;
        for ($n = 2; isset($taken[$key]); $n++) {
            $key = $base . '_' . $n;
        }

        return $key;
    }

    /**
     * @param array<mixed> $value
     */
    private static function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
