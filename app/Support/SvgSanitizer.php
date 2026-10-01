<?php

namespace App\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

/**
 * An SVG logo, rebuilt from what is known to be safe (PLAN.md D-142).
 *
 * An SVG is a document, not a picture: it can carry a script, an event handler, a link to
 * javascript:, a stylesheet that loads from elsewhere, an entity that reads a file off the
 * server. Opened directly on the site's own domain, any of those runs as the site. So nothing
 * is kept because it looks harmless; everything is dropped unless it is on the lists below:
 *   - elements: shapes, paths, groups, text, gradients, patterns, clips and masks
 *   - attributes: geometry, paint, text and transforms, by name, never an on…
 *   - links: only to an id inside the file (#logo-mark), never to anything outside it
 *   - styles, in a style attribute or a <style> element: only what paints; no @ rule, no
 *     url() but url(#id), no backslash escape that could spell anything else
 *
 * A file with a DOCTYPE is refused whole rather than parsed, since that is where entities
 * live. What is saved is the rebuilt document, never the bytes that were uploaded.
 */
final class SvgSanitizer
{
    public const MAX_BYTES = 262144;

    private const SVG = 'http://www.w3.org/2000/svg';
    private const XLINK = 'http://www.w3.org/1999/xlink';

    private const ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textPath',
        'linearGradient', 'radialGradient', 'stop', 'pattern', 'clipPath', 'mask', 'style',
    ];

    private const ATTRIBUTES = [
        'id', 'class', 'style', 'transform', 'viewBox', 'preserveAspectRatio', 'width', 'height', 'x', 'y',
        'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'd', 'points', 'pathLength',
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap',
        'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'opacity',
        'clip-path', 'clip-rule', 'mask', 'display', 'visibility', 'color',
        'offset', 'stop-color', 'stop-opacity', 'gradientUnits', 'gradientTransform', 'spreadMethod',
        'patternUnits', 'patternContentUnits', 'patternTransform', 'clipPathUnits', 'maskUnits', 'maskContentUnits',
        'font-family', 'font-size', 'font-weight', 'font-style', 'letter-spacing', 'word-spacing',
        'text-anchor', 'dominant-baseline', 'dx', 'dy', 'rotate', 'textLength', 'lengthAdjust', 'startOffset',
        'href', 'version', 'role', 'aria-label', 'aria-hidden', 'focusable',
    ];

    /** The CSS properties a logo paints with, in a style attribute or a <style> element. */
    private const PROPERTIES = [
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap',
        'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'opacity', 'color',
        'stop-color', 'stop-opacity', 'clip-rule', 'display', 'visibility', 'isolation', 'mix-blend-mode',
        'font-family', 'font-size', 'font-weight', 'font-style', 'letter-spacing', 'word-spacing', 'text-anchor',
        'dominant-baseline', 'paint-order', 'vector-effect', 'shape-rendering', 'transform', 'transform-origin',
    ];

    /**
     * The safe rebuild of $svg, and its size as its viewBox gives it.
     *
     * @return array{svg: string, width: float, height: float}
     */
    public static function clean(string $svg): array
    {
        if (strlen($svg) > self::MAX_BYTES) {
            throw new RuntimeException(t('svg.too_large', ['size' => (string) intdiv(self::MAX_BYTES, 1024)]));
        }
        if (preg_match('~<!DOCTYPE|<!ENTITY~i', $svg) === 1) {
            throw new RuntimeException(t('svg.doctype'));
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        // Checked again after parsing: a file in UTF-16 carries its DOCTYPE in bytes the
        // pattern above does not read.
        if ($document->doctype !== null) {
            throw new RuntimeException(t('svg.doctype'));
        }
        $root = $document->documentElement;
        if (!$loaded || !$root instanceof DOMElement || $root->localName !== 'svg' || $root->namespaceURI !== self::SVG) {
            throw new RuntimeException(t('svg.not_svg'));
        }

        $clean = new DOMDocument('1.0', 'UTF-8');
        $rebuilt = self::copy($root, $clean);
        if (!$rebuilt instanceof DOMElement) {
            throw new RuntimeException(t('svg.not_svg'));
        }
        $clean->appendChild($rebuilt);
        [$width, $height] = self::size($rebuilt);
        if ($width <= 0 || $height <= 0) {
            throw new RuntimeException(t('svg.no_size'));
        }
        return ['svg' => (string) $clean->saveXML($rebuilt), 'width' => $width, 'height' => $height];
    }

    /**
     * $node as it may stand in the clean document, with its children, or null when it may
     * not stand at all. Text is kept; comments, processing instructions and CDATA that is
     * not a <style>'s are not.
     */
    private static function copy(DOMNode $node, DOMDocument $into): ?DOMNode
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return $into->createTextNode((string) $node->nodeValue);
        }
        if (!$node instanceof DOMElement || $node->namespaceURI !== self::SVG || !in_array($node->localName, self::ELEMENTS, true)) {
            return null;
        }
        $element = $into->createElementNS(self::SVG, $node->localName);

        if ($node->localName === 'style') {
            $css = self::css((string) $node->textContent);
            if ($css === '') {
                return null;
            }
            $element->appendChild($into->createTextNode($css));

            return $element;
        }

        foreach ($node->attributes ?? [] as $attribute) {
            /** @var DOMAttr $attribute */
            $value = self::attribute($attribute);
            if ($value !== null) {
                $element->setAttribute((string) $attribute->localName, $value);
            }
        }
        foreach ($node->childNodes as $child) {
            $copied = self::copy($child, $into);
            if ($copied !== null) {
                $element->appendChild($copied);
            }
        }

        return $element;
    }

    /** The attribute's value as it may stay, or null when it goes. */
    private static function attribute(DOMAttr $attribute): ?string
    {
        $name = $attribute->localName;
        $namespace = $attribute->namespaceURI;
        $value = trim((string) $attribute->value);
        // Only unprefixed attributes, and xlink:href, which older exports use for links.
        if (($namespace !== null && !($namespace === self::XLINK && $name === 'href')) || !in_array($name, self::ATTRIBUTES, true)) {
            return null;
        }
        if ($name === 'href') {
            return preg_match('~^#[A-Za-z_][\w.-]*$~', $value) === 1 ? $value : null;
        }
        if ($name === 'style') {
            $value = self::declarations($value);

            return $value === '' ? null : $value;
        }

        return self::safeValue($value) ? $value : null;
    }

    /** A <style> element's rules, each kept only with its safe declarations. */
    private static function css(string $css): string
    {
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
        if (str_contains($css, '@') || str_contains($css, '\\') || str_contains($css, '<')) {
            return ''; // an @import, @font-face or an escape: the whole sheet goes
        }
        $kept = [];
        preg_match_all('~([^{}]+)\{([^{}]*)\}~', $css, $rules, PREG_SET_ORDER);
        foreach ($rules as [, $selector, $body]) {
            $selector = trim($selector);
            $body = self::declarations($body);
            if ($body !== '' && preg_match('~^[\w\s.#,:>*\[\]="\'-]+$~', $selector) === 1) {
                $kept[] = $selector . '{' . $body . '}';
            }
        }

        return implode('', $kept);
    }

    /** Declarations "a: b; c: d", each kept only when its property and value are safe. */
    private static function declarations(string $body): string
    {
        $kept = [];
        foreach (explode(';', $body) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if (in_array($property, self::PROPERTIES, true) && $value !== '' && self::safeValue($value)) {
                $kept[] = $property . ':' . $value;
            }
        }

        return implode(';', $kept);
    }

    /**
     * No url() but one naming an id in this file, no escape, no script by any spelling, and
     * nothing that could close the attribute or the element it stands in.
     */
    private static function safeValue(string $value): bool
    {
        $lower = strtolower($value);
        foreach (['javascript', 'expression', 'behavior', 'binding', 'data:', '\\', '<', '>', '@import'] as $never) {
            if (str_contains($lower, $never)) {
                return false;
            }
        }
        if (preg_match_all('~url\s*\(([^)]*)\)~i', $value, $urls) > 0) {
            foreach ($urls[1] as $url) {
                if (preg_match('~^\s*[\'"]?#[A-Za-z_][\w.-]*[\'"]?\s*$~', $url) !== 1) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Width and height as the viewBox says, or the width and height attributes when it has
     * none, in the units a browser lays an <img> out by.
     *
     * @return array{float, float}
     */
    private static function size(DOMElement $svg): array
    {
        $box = preg_split('~[\s,]+~', trim($svg->getAttribute('viewBox'))) ?: [];
        if (count($box) === 4 && is_numeric($box[2]) && is_numeric($box[3])) {
            return [(float) $box[2], (float) $box[3]];
        }
        $number = static fn (string $v): float => (float) preg_replace('~[^\d.]~', '', $v);

        return [$number($svg->getAttribute('width')), $number($svg->getAttribute('height'))];
    }
}
