<?php

namespace App\Modules\Forms;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Admin\AdminView;
use App\Modules\Mailer\MailSettings;
use App\Support\Dates;
use App\Support\Url;

/**
 * What visitors sent through a form (PLAN.md D-046): the list, one message, deleting one,
 * and all of them as a spreadsheet.
 *
 * Opening a message marks it read; the list shows which are new. Every message is kept as
 * it was sent — each answer with the label it was asked under — so a form edited since
 * still shows its old messages as their senders saw the form.
 */
final class MessagesController
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $form = Form::find($db, (int) $params['id']);
        if ($form === null) {
            return Response::admin(e(t('forms.not_found')), 404);
        }
        $messages = [];
        foreach ($db->all('SELECT id, data_json, read_at, created_at FROM form_submissions WHERE form_id = ? ORDER BY created_at DESC, id DESC', [$form['id']]) as $row) {
            $answers = self::answers((string) $row['data_json']);
            $messages[] = [
                'id' => (int) $row['id'],
                'unread' => $row['read_at'] === null,
                'created' => (string) $row['created_at'],
                'summary' => self::summary($answers),
            ];
        }

        return AdminView::render($this->container, __DIR__ . '/views', 'messages', [
            'title' => t('messages.title', ['form' => $form['name']]),
            'nav' => 'forms',
            'wide' => true,
            'form' => $form,
            'messages' => $messages,
            'zone' => Dates::zone($db),
            'failure' => MailSettings::lastFailure($db),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $form = Form::find($db, (int) $params['id']);
        $row = $form === null ? null : $db->one(
            'SELECT s.id, s.data_json, s.created_at, p.title AS page_title, p.id AS page_id
             FROM form_submissions s LEFT JOIN pages p ON p.id = s.page_id
             WHERE s.id = ? AND s.form_id = ?',
            [(int) $params['message'], $form['id']],
        );
        if ($form === null || $row === null) {
            return Response::admin(e(t('messages.not_found')), 404);
        }
        $db->query('UPDATE form_submissions SET read_at = ? WHERE id = ? AND read_at IS NULL', [gmdate('Y-m-d H:i:s'), (int) $row['id']]);

        return AdminView::render($this->container, __DIR__ . '/views', 'message', [
            'title' => t('messages.one'),
            'nav' => 'forms',
            'form' => $form,
            'message' => [
                'id' => (int) $row['id'],
                'created' => (string) $row['created_at'],
                'answers' => self::answers((string) $row['data_json']),
                'page' => $row['page_id'] === null ? null : ['id' => (int) $row['page_id'], 'title' => (string) $row['page_title']],
            ],
            'zone' => Dates::zone($db),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function delete(Request $request, string $locale, array $params): Response
    {
        $id = (int) $params['id'];
        $this->db()->query('DELETE FROM form_submissions WHERE id = ? AND form_id = ?', [(int) $params['message'], $id]);
        $this->container->get('session')->set('flash', t('messages.deleted'));

        return Response::redirect(Url::admin('forms', $id, 'messages'));
    }

    /**
     * Every message as CSV, one row each, a column per field the form has now and per field
     * an older message answered that it no longer has. Opened by any spreadsheet.
     *
     * @param array<string, string> $params
     */
    public function export(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $form = Form::find($db, (int) $params['id']);
        if ($form === null) {
            return Response::admin(e(t('forms.not_found')), 404);
        }
        $rows = $db->all('SELECT data_json, created_at FROM form_submissions WHERE form_id = ? ORDER BY created_at, id', [$form['id']]);

        $columns = array_column($form['fields'], 'label', 'key');
        $messages = [];
        foreach ($rows as $row) {
            $answers = self::answers((string) $row['data_json']);
            foreach ($answers as $answer) {
                $columns[$answer['key']] ??= $answer['label'];
            }
            $messages[] = ['created' => (string) $row['created_at'], 'values' => array_column($answers, 'value', 'key')];
        }

        $zone = Dates::zone($db);
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return Response::admin(e(t('messages.export_failed')), 500);
        }
        // A byte-order mark, so a spreadsheet opens "Ivić" as Ivić rather than guessing.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_merge([t('messages.col.date')], array_values($columns)), ',', '"', '');
        foreach ($messages as $message) {
            $line = [Dates::local($message['created'], $zone)];
            foreach (array_keys($columns) as $key) {
                $line[] = self::cell((string) ($message['values'][$key] ?? ''));
            }
            fputcsv($out, $line, ',', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        $name = preg_replace('~[^a-z0-9]+~', '-', strtolower($form['name'])) ?: 'form';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . trim($name, '-') . '-messages.csv"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return list<array{key: string, label: string, type: string, value: string}>
     */
    public static function answers(string $json): array
    {
        $stored = json_decode($json, true);
        $answers = [];
        foreach (is_array($stored) ? $stored : [] as $answer) {
            if (is_array($answer) && is_string($answer['key'] ?? null)) {
                $answers[] = [
                    'key' => $answer['key'],
                    'label' => is_string($answer['label'] ?? null) ? $answer['label'] : $answer['key'],
                    'type' => is_string($answer['type'] ?? null) ? $answer['type'] : 'text',
                    'value' => is_string($answer['value'] ?? null) ? $answer['value'] : '',
                ];
            }
        }

        return $answers;
    }

    /**
     * One line to tell messages apart in the list: the first two answers that are not a
     * long message or a tick, such as a name and an address.
     *
     * @param list<array{key: string, label: string, type: string, value: string}> $answers
     */
    private static function summary(array $answers): string
    {
        $short = array_values(array_filter($answers, static fn (array $a): bool => $a['value'] !== '' && !in_array($a['type'], ['textarea', 'checkbox'], true)));

        return implode(' · ', array_map(static fn (array $a): string => mb_substr($a['value'], 0, 80), array_slice($short, 0, 2)));
    }

    /**
     * A value made safe to open in a spreadsheet: one starting like a formula is kept as
     * text, so a visitor cannot write "=HYPERLINK(...)" into the owner's spreadsheet.
     */
    private static function cell(string $value): string
    {
        return preg_match('~^[=+\-@\t\r]~', $value) === 1 ? "'" . $value : $value;
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
