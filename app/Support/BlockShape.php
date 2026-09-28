<?php

namespace App\Support;

use DOMElement;
use DOMNode;
use DOMText;

/**
 * The shape of the blocks inside a richtext field: what a block is called, and what its
 * edges may hold (SPEC §5.3).
 *
 * Split out of RichText, which had grown past the file limit holding two jobs. RichText
 * decides what may be stored at all — the whitelist, the attributes, the URL rule, what is
 * removed with its content. This decides what an allowed block looks like once it is kept:
 * a div that should be a paragraph, a heading level we do not store, a break the editor
 * left at a block's edge, a paragraph that is only there to wrap a list item's text.
 *
 * Every rule here exists because an editor produced markup that was valid but not what we
 * store, and storage keeps one shape whatever produced it. None of them allows anything
 * new: they rename, trim and unwrap within what RichText has already permitted.
 */
final class BlockShape
{
    /**
     * Elements renamed to their nearest allowed equivalent instead of being unwrapped.
     *
     * An editor or a pasted document may wrap blocks in divs, or use heading levels we do
     * not store. Unwrapping those would throw away the structure the author made:
     * paragraphs would run together and every heading would become bare text. Renaming
     * keeps the meaning and lands it inside the whitelist.
     *
     * h1 becomes h2 because the page's own title is the h1; a heading inside body copy
     * sits below it. h5 and deeper collapse to h4, the deepest we store (PLAN.md D-016),
     * which matters mostly for pasted documents.
     */
    private const RENAME = [
        'div' => 'p',
        'h1' => 'h2',
        'h5' => 'h4',
        'h6' => 'h4',
    ];

    /** A p may not contain these, so a div holding one is unwrapped rather than renamed. */
    private const BLOCK = ['p', 'div', 'ul', 'ol', 'li', 'blockquote', 'h2', 'h3', 'h4', 'table'];

    /** Blocks whose leading and trailing <br> are dropped on save (PLAN.md D-014). */
    private const TRIM_BREAKS = ['p', 'h2', 'h3', 'h4', 'li', 'blockquote'];

    /** Blocks whose lone paragraph wrapper is the editor's packaging (PLAN.md D-017). */
    private const UNWRAP_LONE_PARAGRAPH = ['li', 'blockquote'];

    /**
     * What may follow that paragraph and still leave it a wrapper. Both may hold a list
     * after their text, which is structure the author made rather than packaging.
     */
    private const AFTER_LONE_PARAGRAPH = ['li' => ['ul', 'ol'], 'blockquote' => ['ul', 'ol']];

    /**
     * The element under the name we store it as, or unchanged when no rule applies.
     *
     * Called after the children are cleaned, so a nested div has already become a p and the
     * block test below sees the final shape. `div → p` is conditional: a div containing a
     * block element is left alone for the caller to unwrap, since a paragraph may not
     * contain a list and renaming regardless would generate invalid markup of our own
     * making.
     */
    public static function rename(DOMNode $parent, DOMElement $node, string $tag): DOMElement
    {
        if (!isset(self::RENAME[$tag]) || ($tag === 'div' && self::hasBlockChild($node))) {
            return $node;
        }

        $document = $node->ownerDocument;
        if ($document === null) {
            return $node;
        }
        $replacement = $document->createElement(self::RENAME[$tag]);
        while ($node->firstChild !== null) {
            $replacement->appendChild($node->firstChild);
        }
        $parent->replaceChild($replacement, $node);

        return $replacement;
    }

    /**
     * The edges of a block we keep: breaks the editor left there, and a paragraph that is
     * only wrapping the text.
     */
    public static function tidy(DOMElement $node, string $tag): void
    {
        if (in_array($tag, self::TRIM_BREAKS, true)) {
            self::trimBreaks($node);
        }
        if (in_array($tag, self::UNWRAP_LONE_PARAGRAPH, true)) {
            self::unwrapLoneParagraph($node);
        }
    }

    /**
     * True when this element holds something a paragraph may not contain, which is what
     * decides whether a div is renamed to p or left to be unwrapped.
     */
    private static function hasBlockChild(DOMElement $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), self::BLOCK, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drops <br> at the very start and end of a block.
     *
     * An editor that marks block boundaries with breaks hands back <div><br>alpha<br><br>
     * </div> for a paragraph it was given as <p>alpha</p>. Without this rule every
     * open-and-save of a page added a break at each end of every rich text field on it —
     * including fields nobody edited — and it compounded with each cycle, so text drifted
     * further from what was written every time the page was opened. Measured in a browser,
     * not inferred.
     *
     * The cost is a deliberate break at the very edge of a paragraph. A blank line is a
     * new paragraph in any editor we would use, so nothing a person can type is lost.
     */
    private static function trimBreaks(DOMElement $node): void
    {
        foreach ([true, false] as $fromStart) {
            while (true) {
                $child = $fromStart ? $node->firstChild : $node->lastChild;
                // Whitespace between the edge and the break is left where it is; only the
                // break itself goes.
                while ($child instanceof DOMText && trim($child->textContent) === '') {
                    $child = $fromStart ? $child->nextSibling : $child->previousSibling;
                }
                if (!$child instanceof DOMElement || strtolower($child->nodeName) !== 'br') {
                    break;
                }
                $node->removeChild($child);
            }
        }
    }

    /**
     * A paragraph wrapping the text of a list item or a quote is the editor's packaging,
     * not the author's structure, so it is unwrapped.
     *
     * An editor whose schema puts a paragraph inside every list item and quote turns
     * <li>one</li> into <li><p>one</p></li> the first time a field is edited. Measured on
     * the front end: that list grew from 51px to 67px, because a paragraph inside a list
     * item takes the normal paragraph margin and gains 16px above and below every item.
     *
     * What survives is what the author made. Two paragraphs in one item or quote are kept,
     * both of them. Either may hold a list after its text, so a paragraph followed only by
     * lists is still a wrapper and goes, while a paragraph after a list is not.
     */
    private static function unwrapLoneParagraph(DOMElement $parent): void
    {
        $blocks = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $blocks[] = $child;
            } elseif ($child instanceof DOMText && trim($child->textContent) !== '') {
                return; // text beside the paragraph: this is not just a wrapper
            }
        }
        if ($blocks === [] || strtolower($blocks[0]->nodeName) !== 'p') {
            return;
        }

        $allowed = self::AFTER_LONE_PARAGRAPH[strtolower($parent->nodeName)] ?? [];
        foreach (array_slice($blocks, 1) as $sibling) {
            if (!in_array(strtolower($sibling->nodeName), $allowed, true)) {
                return;
            }
        }

        $paragraph = $blocks[0];
        while ($paragraph->firstChild !== null) {
            $parent->insertBefore($paragraph->firstChild, $paragraph);
        }
        $parent->removeChild($paragraph);
    }
}
