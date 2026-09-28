<?php

namespace App\Modules\Forms;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Modules\Admin\Activity;
use App\Modules\Pages\Page;
use App\Modules\Pages\PageController;
use App\Support\ClientIp;
use App\Support\Url;

/**
 * A visitor sending a form (PLAN.md D-046, SPEC §6).
 *
 * In order: the form must exist; the honeypot must be empty and the form must have been
 * on screen for three seconds (FormToken) — a send failing either is answered exactly as a
 * real one is, so a script learns nothing; one sender may send at most five messages in
 * ten minutes; every field is checked. A refused send draws the page again with the
 * answers kept and each problem beside its field. A send that passes is stored, the owner
 * is told and the sender answered where the form says so (FormMail), and the visitor is
 * sent back to the page with the thank-you — a redirect, so reloading does not send twice.
 */
final class FormSubmitController
{
    private const WINDOW_MINUTES = 10;
    private const PER_WINDOW = 5;

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function submit(Request $request, string $locale, array $params): Response
    {
        $db = $this->db();
        $form = Form::find($db, (int) $params['id']);
        if ($form === null) {
            return Response::html(e(site_t('site.not_found.title', $locale)), 404);
        }
        $page = $this->page((int) $request->input('page'), $form['locale']);
        $back = $page === null ? Url::page($form['locale']) : Url::page((string) $page['locale'], (string) $page['slug']);
        $thanks = Response::redirect($back . '?sent=' . $form['id'] . '#form-' . $form['id'], 303);

        $appKey = (string) $this->container->get('config')->get('app.key');
        $age = FormToken::age($request->input('started'), $form['id'], $appKey);
        if ($request->input('website') !== '' || $age === null || $age < FormToken::MIN_SECONDS) {
            return $thanks;
        }

        // Behind a proxy, every sender would otherwise share one address and one limit (O-20).
        $ipHash = hash_hmac('sha256', ClientIp::of($request, Settings::text($db, 'trusted_proxies')), $appKey);
        $since = gmdate('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60);
        $recent = (int) ($db->one('SELECT COUNT(*) AS n FROM form_submissions WHERE ip_hash = ? AND created_at >= ?', [$ipHash, $since])['n'] ?? 0);

        $posted = is_array($request->body['f'] ?? null) ? $request->body['f'] : [];
        [$answers, $old, $errors] = self::check($form['fields'], $posted, $form['locale']);
        if ($recent >= self::PER_WINDOW) {
            return $this->again($page, $form['id'], [], $old, site_t('site.form.too_many', $form['locale']), 429);
        }
        if ($errors !== []) {
            return $this->again($page, $form['id'], $errors, $old, '', 422);
        }

        $db->query(
            'INSERT INTO form_submissions (form_id, page_id, data_json, ip_hash, created_at) VALUES (?, ?, ?, ?, ?)',
            [$form['id'], $page === null ? null : (int) $page['id'], json_encode($answers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $ipHash, gmdate('Y-m-d H:i:s')],
        );
        // A visitor's message, logged against its form: the owner's Overview says one came.
        Activity::record($db, 'message', 'received', (int) $form['id'], (string) $form['name']);
        FormMail::send($this->container, $form, $answers, $page);

        return $thanks;
    }

    /**
     * Every field checked against what it is: required, an email address, one of a list's
     * choices, and no longer than a person writes.
     *
     * @param list<array{key: string, type: string, label: string, required: bool, options: list<string>}> $fields
     * @param array<mixed> $posted
     * @return array{list<array{key: string, label: string, type: string, value: string}>, array<string, string>, array<string, string>}
     *         the answers as stored, what to draw back into the fields, and the errors by key
     */
    public static function check(array $fields, array $posted, string $locale): array
    {
        $answers = [];
        $old = [];
        $errors = [];
        foreach ($fields as $field) {
            $raw = $posted[$field['key']] ?? '';
            $value = is_string($raw) ? trim(str_replace("\0", '', $raw)) : '';
            $value = mb_substr($value, 0, $field['type'] === 'textarea' ? 5000 : 500);
            if ($field['type'] === 'checkbox') {
                $value = $value === '1' ? '1' : '';
            }
            $old[$field['key']] = $value;

            if ($value === '') {
                if ($field['required']) {
                    $errors[$field['key']] = site_t('site.form.required', $locale);
                }
            } elseif ($field['type'] === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $errors[$field['key']] = site_t('site.form.email', $locale);
            } elseif ($field['type'] === 'select' && !in_array($value, $field['options'], true)) {
                $errors[$field['key']] = site_t('site.form.required', $locale);
            }
            $answers[] = ['key' => $field['key'], 'label' => $field['label'], 'type' => $field['type'], 'value' => $value];
        }

        return [$answers, $old, $errors];
    }

    /**
     * The page the form was sent from, drawn again with what was sent — or, when it was
     * sent from nowhere this site can draw, the form's language's home.
     *
     * @param array<string, mixed>|null $page
     * @param array<string, string> $errors
     * @param array<string, string> $old
     */
    private function again(?array $page, int $formId, array $errors, array $old, string $notice, int $status): Response
    {
        FormState::set($formId, $errors, $old, $notice);
        if ($page === null) {
            return Response::html(e($notice !== '' ? $notice : site_t('site.form.error', 'en')), $status);
        }
        $response = (new PageController($this->container))->show(
            $this->container->get('request'),
            (string) $page['locale'],
            (string) $page['slug'] === '' ? [] : ['slug' => (string) $page['slug']],
        );
        $response->status = $status;

        return $response;
    }

    /**
     * The published page a form was sent from, in the form's language; null for anything
     * else, so a posted page id can only lead back to a page a visitor could have been on.
     *
     * @return array<string, mixed>|null
     */
    private function page(int $pageId, string $locale): ?array
    {
        $page = $pageId > 0 ? Page::find($this->db(), $pageId) : null;

        return $page !== null && $page['status'] === 'published' && $page['locale'] === $locale ? $page : null;
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
