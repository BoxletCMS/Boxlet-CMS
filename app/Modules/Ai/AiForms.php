<?php

namespace App\Modules\Ai;

use App\Core\Db;
use App\Modules\Forms\Form;
use Closure;

/**
 * A COPIED FORM IN ITS LANGUAGE'S WORDS (PLAN.md D-262, the owner): a form FormCopy made for a
 * page copied into another language, translated as the page is. Its fields' labels and choices,
 * its button, its thank-you and its automatic reply: everything a visitor reads. Not its name,
 * which only the owner sees, nor its keys, which tie answers to fields.
 *
 * Only a copy still word for word its original's: once the owner has changed anything in it,
 * it is theirs. Live at once, as a form's saves are.
 *
 * @phpstan-import-type FormRow from Form
 */
final class AiForms
{
    /** The settings a visitor reads, besides the fields. */
    private const SETTINGS = ['submit', 'success', 'autoreply_subject', 'autoreply_body'];

    /** Whether form $id is a copy nobody has changed: its fields and settings its original's exactly. */
    public static function untouched(Db $db, int $id): bool
    {
        $form = $db->one('SELECT f.fields_json, f.settings_json, s.fields_json AS source_fields, s.settings_json AS source_settings
                          FROM forms f JOIN forms s ON s.id = f.source_form_id WHERE f.id = ?', [$id]);

        return $form !== null && $form['fields_json'] === $form['source_fields'] && $form['settings_json'] === $form['source_settings'];
    }

    /**
     * Translates the copy's words and writes them. Returns how many kept no translation.
     *
     * @param array{keep: string, voice: string} $words
     * @param Closure(string, string): array{text: string, in: int, out: int} $ask
     */
    public static function run(Db $db, int $id, string $fromName, string $toName, array $words, Closure $ask): int
    {
        $form = Form::find($db, $id);
        if ($form === null || !self::untouched($db, $id)) {
            return 0;
        }
        $texts = [];
        foreach ($form['fields'] as $n => $field) {
            $texts['label:' . $n] = ['text' => $field['label'], 'html' => false];
            foreach ($field['options'] as $o => $option) {
                $texts['option:' . $n . ':' . $o] = ['text' => $option, 'html' => false];
            }
        }
        foreach (self::SETTINGS as $name) {
            $texts['setting:' . $name] = ['text' => (string) $form['settings'][$name], 'html' => false];
        }
        $result = AiTranslator::translate($texts, $fromName, $toName, $words, $ask);
        AiSettings::addUsage($db, $result['in'], $result['out']);

        $fields = $form['fields'];
        $settings = $form['settings'];
        foreach ($result['done'] as $key => $text) {
            $part = explode(':', $key);
            if ($part[0] === 'label' && isset($fields[(int) $part[1]])) {
                $fields[(int) $part[1]]['label'] = mb_substr($text, 0, 255);
            } elseif ($part[0] === 'option' && isset($fields[(int) $part[1]]['options'][(int) $part[2]])) {
                $fields[(int) $part[1]]['options'][(int) $part[2]] = mb_substr($text, 0, 255);
            } elseif ($part[0] === 'setting' && in_array($part[1], self::SETTINGS, true)) {
                $settings[$part[1]] = $text;
            }
        }
        Form::update($db, $id, $form['name'], $fields, $settings);

        return count($result['failed']);
    }
}
