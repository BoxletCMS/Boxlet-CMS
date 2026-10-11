<?php

namespace App\Modules\Admin;

use App\Modules\Pages\PageLinks;

/**
 * RICH TEXT, WITH A SHORT TOOLBAR (D-113): bold, italic, a link, the alignment (D-217), and
 * undo. No headings, lists or quotations — RichText::INLINE would throw them away, and the
 * toolbar offers only what can be stored, as the page editor's does (D-017). The textarea is the real field; richtext.js
 * puts the editor above it and the plain toggle shows it again. Without a script it is a
 * textarea of HTML, which still saves.
 *
 * Two callers: the footer's words (Navigation) and a snippet's (D-201). The footer's offers
 * Insert, for the replacement tags; a snippet's does not, because a snippet's words are not
 * read for tags (SPEC §5.6).
 *
 * The link panel's address box is TEXT, not type="url": an email or a phone number is a link
 * as typed (D-039), and a url input holding one made the whole form refuse to submit.
 */
final class RichInline
{
    /**
     * A paragraph's alignment (D-217): as the block, centred, right. Every rich text toolbar has
     * it, the page's and this one's: a paragraph is in every field. Drawn from the icon sprite.
     */
    public static function alignment(): string
    {
        $html = '<div class="rt-group" role="group" aria-label="' . e(t('richtext.align')) . '">';
        foreach (['alignLeft' => ['align_left', 'text-align-start'], 'alignCenter' => ['align_center', 'text-align-center'], 'alignRight' => ['align_right', 'text-align-end']] as $command => [$words, $icon]) {
            $html .= '<button type="button" class="rt-button" data-rt="' . $command . '" aria-pressed="false" title="' . e(t('richtext.' . $words)) . '">'
                . icon($icon) . '<span class="visually-hidden">' . e(t('richtext.' . $words)) . '</span></button>';
        }

        return $html . '</div>';
    }

    /**
     * @param array<int, array{title: string, depth: int, published: bool, url: string}> $pages what a link may lead to, in $locale
     * @param string|null $hintId the hint that describes the field, where it has one
     */
    public static function field(string $id, string $name, string $value, string $locale, array $pages, ?string $hintId, bool $insert): string
    {
        $html = '<div class="richtext" data-richtext data-tag-locale="' . e($locale) . '">'
            . '<div class="richtext-toolbar" data-richtext-toolbar role="toolbar" aria-label="' . e(t('richtext.toolbar')) . '">'
            . '<div class="rt-group">'
            . '<button type="button" class="rt-button" data-rt="bold" aria-pressed="false" title="' . e(t('richtext.bold')) . '"><span aria-hidden="true">B</span><span class="visually-hidden">' . e(t('richtext.bold')) . '</span></button>'
            . '<button type="button" class="rt-button rt-italic" data-rt="italic" aria-pressed="false" title="' . e(t('richtext.italic')) . '"><span aria-hidden="true">I</span><span class="visually-hidden">' . e(t('richtext.italic')) . '</span></button>'
            . '<button type="button" class="rt-button" data-rt="link" aria-pressed="false" title="' . e(t('richtext.link')) . '"><svg class="rt-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10.5 13.5a4.5 4.5 0 0 0 6.36 0l2.83-2.83a4.5 4.5 0 0 0-6.36-6.36l-1.06 1.06"/><path d="M13.5 10.5a4.5 4.5 0 0 0-6.36 0l-2.83 2.83a4.5 4.5 0 0 0 6.36 6.36l1.06-1.06"/></svg><span class="visually-hidden">' . e(t('richtext.link')) . '</span></button>'
            . '</div>'
            . self::alignment()
            . ($insert
                ? '<div class="rt-group rt-insert"><button type="button" class="rt-button rt-button-words" data-rt="tag" aria-haspopup="menu" aria-expanded="false" title="' . e(t('tags.insert_title')) . '">' . e(t('tags.insert')) . '</button></div>'
                : '')
            . '<div class="rt-group rt-history">'
            . '<button type="button" class="rt-button" data-rt="undo" title="' . e(t('richtext.undo')) . '"><span aria-hidden="true">&#8630;</span><span class="visually-hidden">' . e(t('richtext.undo')) . '</span></button>'
            . '<button type="button" class="rt-button" data-rt="redo" title="' . e(t('richtext.redo')) . '"><span aria-hidden="true">&#8631;</span><span class="visually-hidden">' . e(t('richtext.redo')) . '</span></button>'
            . '</div></div>'
            . '<div class="richtext-link" data-richtext-link hidden>'
            . '<label class="rt-link-field"><span class="rt-link-label">' . e(t('richtext.link_text')) . '</span><input type="text" class="rt-link-text"></label>'
            . '<label class="rt-link-field"><span class="rt-link-label">' . e(t('richtext.page')) . '</span>'
            . '<select class="rt-link-page"><option value="">' . e(t('pages.field.link_address')) . '</option>';
        foreach ($pages as $group => $choice) {
            $html .= '<option value="' . e(PageLinks::to($group)) . '" data-title="' . e($choice['title']) . '">' . e(str_repeat('— ', $choice['depth']) . $choice['title'] . ($choice['published'] ? '' : ' ' . t('pages.field.link_page_draft'))) . '</option>';
        }

        return $html . '</select></label>'
            . '<label class="rt-link-field rt-link-address"><span class="rt-link-label">' . e(t('richtext.url')) . '</span>'
            . '<input type="text" inputmode="url" class="rt-link-input" placeholder="' . e(t('richtext.url_placeholder')) . '"></label>'
            . '<button type="button" class="button button-secondary" data-rt-link="apply">' . e(t('richtext.link')) . '</button>'
            . '<button type="button" class="button button-ghost" data-rt-link="remove">' . e(t('richtext.unlink')) . '</button>'
            . '</div>'
            . '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="3" data-richtext-source' . ($hintId !== null ? ' aria-describedby="' . e($hintId) . '"' : '') . '>' . e($value) . '</textarea>'
            . '<div class="richtext-actions">'
            . '<button type="button" class="button button-ghost js-only" data-richtext-toggle data-label-plain="' . e(t('richtext.plain')) . '" data-label-rich="' . e(t('richtext.rich')) . '">' . e(t('richtext.plain')) . '</button>'
            . '</div></div>';
    }
}
