<?php

namespace App\Modules\Forms;

use App\Core\Db;
use App\Support\Url;

/**
 * What a Form block needs to draw, resolved before any block draws — the rule pictures
 * and links follow: a template asks the database nothing (PLAN.md D-046).
 *
 * A form is drawn only on a page of its own language. A translation copies its blocks
 * verbatim, form id included, and a Croatian page showing the English form would be the
 * one place on it in the wrong language; until the owner chooses a Croatian form there, the
 * block draws its heading and nothing more.
 *
 * What the visitor just did is part of it: the thank-you after a send, or the fields
 * refused with what was typed in them (FormState, set by the submit route for this one
 * request).
 *
 * @phpstan-import-type FormRow from Form
 * @phpstan-type Drawn array{form: FormRow, action: string, token: string, page: int|null, sent: bool, errors: array<string, string>, old: array<string, string>, notice: string}
 */
final class FormBlocks
{
    /**
     * @param list<array{type: string, content: array<string, mixed>|null}> $blocks
     * @return array<int, Drawn> form id => what its block draws
     */
    public static function resolve(Db $db, array $blocks, string $locale, ?int $pageId, string $appKey, ?int $sent = null): array
    {
        $ids = [];
        foreach ($blocks as $block) {
            $id = $block['type'] === 'form' && is_array($block['content']) ? ($block['content']['form'] ?? null) : null;
            if (is_int($id) && $id > 0) {
                $ids[$id] = true;
            }
        }
        $drawn = [];
        foreach (array_keys($ids) as $id) {
            $form = Form::find($db, $id);
            if ($form === null || $form['locale'] !== $locale) {
                continue;
            }
            $state = FormState::for($id);
            $drawn[$id] = [
                'form' => $form,
                'action' => self::action($id),
                'token' => FormToken::issue($id, $appKey),
                'page' => $pageId,
                'sent' => $sent === $id,
                'errors' => $state['errors'],
                'old' => $state['old'],
                'notice' => $state['notice'],
            ];
        }

        return $drawn;
    }

    /** Where a form posts: one address for every page it is on, which the page id completes. */
    public static function action(int $formId): string
    {
        return Url::asset('form/' . $formId);
    }
}
