<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Reduces HTML typed into a richtext field to a fixed whitelist, applied when a page is
 * saved (SPEC §5.3). Elements outside the whitelist are unwrapped, keeping their text;
 * a few are removed together with their content. Only <a href> keeps an attribute, and
 * only with a URL that SafeUrl allows or a page reference (PLAN.md D-034).
 *
 * This decides what may be stored at all. What an allowed block then looks like — a div
 * that should be a paragraph, a heading level we do not store, a break an editor left at a
 * block's edge, a paragraph wrapping a list item's text — is BlockShape, which this calls
 * as it walks. The two were one file until it passed the size limit.
 *
 * This is the security boundary. It runs on save whatever the editor sends, and it keeps
 * one stored shape however the markup was produced.
 */
final class RichText
{
    /** Allowed elements, each with its allowed attributes. */
    public const ALLOWED = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => [],
        'a' => ['href'],
    ];

    /** Removed with everything inside them; any other element keeps its text. */
    private const REMOVE_WITH_CONTENT = [
        'script', 'style', 'template', 'iframe', 'object', 'embed', 'svg', 'math',
        'noscript', 'textarea', 'select', 'button', 'form', 'head', 'title',
        // An attachment's insides are the editor's markup, not the author's words.
        'figure',
    ];

    /**
     * Attachment markup carries JSON in these. No editor in this project may store its own
     * container format, so they are stripped whatever puts them there; the names are the
     * literal strings to defend against, not a reference to any one editor.
     */
    private const ATTACHMENT_ATTRIBUTES = [
        'data-trix-attachment', 'data-trix-attributes', 'data-trix-content-type',
    ];

    /**
     * What a FOOTER'S text may hold (PLAN.md D-113): a line or two with a link in it — an
     * address, a phone number, an email — and nothing that makes a document: no headings,
     * no lists, no quotations. The same sanitiser, a shorter list.
     */
    public const INLINE = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'a' => ['href'],
    ];

    /**
     * @param array<string, list<string>> $allowed tag => attributes it may keep; ALLOWED
     *        for a page's rich text, INLINE for the footer's (D-113)
     */
    public static function sanitize(string $html, array $allowed = self::ALLOWED): string
    {
        $html = mb_scrub($html, 'UTF-8');
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument();
        $internalErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML(
                '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
                . '</head><body>' . $html . '</body></html>',
                LIBXML_NONET,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        }

        $body = $document->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return '';
        }
        self::clean($body, $allowed);

        $output = '';
        foreach ($body->childNodes as $child) {
            $output .= (string) $document->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * @param array<string, list<string>> $allowed
     */
    private static function clean(DOMNode $parent, array $allowed): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMText) {
                continue;
            }
            if (!$node instanceof DOMElement) {
                $parent->removeChild($node); // comments, processing instructions
                continue;
            }

            $tag = strtolower($node->nodeName);
            if (in_array($tag, self::REMOVE_WITH_CONTENT, true) || self::isAttachment($node)) {
                $parent->removeChild($node);
                continue;
            }
            self::clean($node, $allowed);

            // After the children are cleaned, so a nested div has already become a p and
            // the block test inside sees the final shape.
            $node = BlockShape::rename($parent, $node, $tag);
            $tag = strtolower($node->nodeName);

            if (!isset($allowed[$tag])) {
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }

            $attributes = [];
            foreach ($node->attributes as $attribute) {
                $attributes[] = $attribute->nodeName;
            }
            foreach ($attributes as $attribute) {
                if (!in_array($attribute, $allowed[$tag], true)) {
                    $node->removeAttribute($attribute);
                }
            }
            if ($tag === 'a' && $node->hasAttribute('href')) {
                // A bare email or phone number becomes the link it was meant to be (D-039).
                $href = SafeUrl::normalize($node->getAttribute('href'));
                if (SafeUrl::isLink($href)) {
                    $node->setAttribute('href', $href);
                } else {
                    $node->removeAttribute('href');
                }
            }

            // Last, so the block's final name is known and its children are settled.
            BlockShape::tidy($node, $tag);
        }
    }

    /**
     * Attachments are not offered by the editor; this is the backstop, so a later version
     * of it cannot reintroduce them silently.
     */
    private static function isAttachment(DOMElement $node): bool
    {
        foreach (self::ATTACHMENT_ATTRIBUTES as $attribute) {
            if ($node->hasAttribute($attribute)) {
                return true;
            }
        }

        return false;
    }
}
