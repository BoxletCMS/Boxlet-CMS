<?php

namespace App\Modules\Forms;

use App\Core\Blocks;
use App\Core\Db;
use App\Modules\Pages\PageDraft;

/**
 * A COPIED PAGE'S FORM IN ITS LANGUAGE (PLAN.md D-262, the owner): a form belongs to one
 * language (D-046), and a Form block draws only a form of its page's (FormBlocks), so a page
 * copied into Croatian kept pointing at the English form and showed none. Each Form block of
 * the copy now points at the form's version in its language: the one there is, else a copy
 * made now, which remembers its original (source_form_id, migration 0045) so the next page
 * with that form finds it rather than making a second.
 *
 * The copy has the original's words until AI translation says them in the language (AiForms)
 * or the owner does, under Forms. Its messages are its own, as any form's are.
 */
final class FormCopy
{
    /** Points the page's Form blocks, stored and in its draft, at forms of its language. */
    public static function pageCopied(Db $db, Blocks $registry, int $pageId): void
    {
        $locale = (string) ($db->one('SELECT locale FROM pages WHERE id = ?', [$pageId])['locale'] ?? '');
        if ($locale === '') {
            return;
        }
        foreach ($db->all("SELECT id, content_json FROM page_blocks WHERE page_id = ? AND block_type = 'form'", [$pageId]) as $block) {
            $content = json_decode((string) $block['content_json'], true);
            $form = is_array($content) && is_int($content['form'] ?? null) ? $content['form'] : null;
            $there = $form === null ? null : self::into($db, $form, $locale);
            if ($there !== null && $there !== $form) {
                $content['form'] = $there;
                $db->query('UPDATE page_blocks SET content_json = ? WHERE id = ?', [json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $block['id']]);
            }
        }
        $draft = PageDraft::find($db, $registry, $pageId);
        if ($draft === null) {
            return;
        }
        $document = $draft['document'];
        $changed = false;
        foreach ($document['blocks'] as $at => $block) {
            $form = $block['type'] === 'form' && is_array($block['content']) && is_int($block['content']['form'] ?? null) ? $block['content']['form'] : null;
            $there = $form === null ? null : self::into($db, $form, $locale);
            if ($there !== null && $there !== $form) {
                $document['blocks'][$at]['content']['form'] = $there;
                $changed = true;
            }
        }
        if ($changed) {
            PageDraft::save($db, $pageId, $document, $draft['version']);
        }
    }

    /**
     * Form $id's version in $locale: itself when it is in that language, its copy there, or a
     * copy made now. Null when $id names no form.
     */
    public static function into(Db $db, int $id, string $locale): ?int
    {
        $form = $db->one('SELECT * FROM forms WHERE id = ?', [$id]);
        if ($form === null) {
            return null;
        }
        if ($form['locale'] === $locale) {
            return $id;
        }
        // From the original, whichever version the page held.
        $origin = $form['source_form_id'] !== null && $db->one('SELECT id FROM forms WHERE id = ?', [(int) $form['source_form_id']]) !== null
            ? (int) $form['source_form_id']
            : $id;
        $there = $db->one('SELECT id FROM forms WHERE locale = ? AND (id = ? OR source_form_id = ?) ORDER BY id LIMIT 1', [$locale, $origin, $origin]);
        if ($there !== null) {
            return (int) $there['id'];
        }
        $original = $db->one('SELECT name, fields_json, settings_json FROM forms WHERE id = ?', [$origin]) ?? $form;
        $now = gmdate('Y-m-d H:i:s');
        $db->query(
            'INSERT INTO forms (locale, name, fields_json, settings_json, source_form_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$locale, (string) $original['name'], (string) $original['fields_json'], (string) $original['settings_json'], $origin, $now, $now],
        );

        return (int) $db->lastInsertId();
    }
}
