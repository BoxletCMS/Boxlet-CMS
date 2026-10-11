<?php

namespace App\Core;

use App\Modules\Design\SectionStyle;
use App\Modules\Media\MediaPicture;
use RuntimeException;
use Throwable;

/**
 * Block registry. Discovers app/Blocks/{type}/block.php, answers questions about the
 * definitions it found, and renders block templates inside their section wrapper.
 *
 * What a definition must CONTAIN is BlockDefinition: all of it static, all of it run once
 * at discovery. Rendering stayed here because it needs the definitions and the template
 * directory, so moving it would have meant inventing a collaborator to carry them.
 *
 * The resolved-picture shape is MediaPicture's to define, so it is imported rather than
 * restated: a looser copy here is what let a fully-shaped lookup reach tag() as a plain
 * array<string, mixed>.
 *
 * @phpstan-import-type Picture from MediaPicture
 */
final class Blocks
{
    /** The closed set of field types in SPEC §5.3. Kept here as the published name. */
    public const FIELD_TYPES = BlockDefinition::FIELD_TYPES;

    /** The subset implemented so far. The rest arrive when a block needs them. */
    public const SUPPORTED_FIELD_TYPES = BlockDefinition::SUPPORTED_FIELD_TYPES;

    /**
     * @param array<string, array<string, mixed>> $definitions validated, keyed by type
     */
    private function __construct(private readonly array $definitions, private readonly string $directory)
    {
    }

    public static function discover(string $directory): self
    {
        $definitions = [];
        foreach (glob($directory . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $type = basename($dir);
            foreach (['block.php', 'template.php'] as $file) {
                if (!is_file($dir . '/' . $file)) {
                    BlockDefinition::fail($type, "missing {$file}");
                }
            }
            $definitions[$type] = self::validate($type, require $dir . '/block.php');
        }
        ksort($definitions);

        return new self($definitions, $directory);
    }

    /**
     * Checks one definition against SPEC §5.3 and returns it with the optional field
     * flags filled in. Every problem is fatal and names the block and the key.
     *
     * The contract itself is BlockDefinition. This stays as the name callers already use —
     * a published entry point with real callers, not a wrapper invented to forward.
     *
     * @return array<string, mixed>
     */
    public static function validate(string $type, mixed $definition): array
    {
        return BlockDefinition::validate($type, $definition);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->definitions);
    }

    public function has(string $type): bool
    {
        return isset($this->definitions[$type]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $type): array
    {
        if (!isset($this->definitions[$type])) {
            throw new RuntimeException("Unknown block type: {$type}");
        }

        return $this->definitions[$type];
    }

    /**
     * $layout if the block still declares it, otherwise the block's default. A definition
     * can change under an existing page; a layout it dropped must not break rendering.
     */
    public function layout(string $type, mixed $layout): string
    {
        $definition = $this->get($type);

        return is_string($layout) && in_array($layout, $definition['layouts'], true)
            ? $layout
            : (string) $definition['defaults']['layout'];
    }

    /**
     * The layout as it is STORED (PLAN.md D-191, the owner): '' follows the character, and is
     * drawn in the layout its composition names (PageRender); any other is the owner's own,
     * kept through Apply, and a name the block no longer declares is its default.
     */
    public function own(string $type, mixed $layout): string
    {
        return $layout === '' || $layout === null ? '' : $this->layout($type, $layout);
    }

    /**
     * Every field of the block present, with stored values of the wrong shape replaced by
     * the field's empty value, so templates never check whether a key exists.
     *
     * @param array<mixed> $content
     * @return array<string, mixed>
     */
    public function normalize(string $type, array $content): array
    {
        $normalized = [];
        foreach ($this->get($type)['fields'] as $name => $field) {
            $normalized[$name] = self::value($field, $content[$name] ?? null);
        }

        return $normalized;
    }

    /**
     * The content a block starts with when it is added to a page: every field empty, and
     * every repeater holding three empty items rather than none.
     *
     * Three, because a repeater with no items draws nothing at all — a new Columns block
     * was an empty band on the canvas and "no items yet" in the inspector, which reads as
     * a block that failed to load. Three is one row of the default layout, and the owner
     * removes what they do not need, as they would a field they leave empty. Never more
     * than the repeater's own maximum.
     *
     * @return array<string, mixed>
     */
    public function fresh(string $type): array
    {
        $content = $this->normalize($type, []);
        foreach ($this->get($type)['fields'] as $name => $field) {
            if ($field['type'] === 'repeater') {
                $content[$name] = array_fill(0, min(3, $field['max']), self::emptyItem($field));
            }
        }

        return $content;
    }

    /**
     * The content a block starts with when it is added in the builder (D-176): fresh(), with
     * every word field that declares a sample holding it — in the words $say gives for a
     * sample key, which is the page's language. Rich text gets it as one paragraph; a link
     * and a picture stay empty, since a link's words without an address are an error.
     *
     * @param \Closure(string): string $say
     * @return array<string, mixed>
     */
    public function sampled(string $type, \Closure $say): array
    {
        $fill = static function (array $fields, array $content) use ($say): array {
            foreach ($fields as $name => $field) {
                $sample = $field['sample'] ?? null;
                if (!is_string($sample) || !in_array($field['type'], ['text', 'textarea', 'richtext'], true)) {
                    continue;
                }
                $words = $say($sample);
                $content[$name] = $field['type'] === 'richtext' ? '<p>' . htmlspecialchars($words, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' : $words;
            }

            return $content;
        };
        $content = $fill($this->get($type)['fields'], $this->fresh($type));
        foreach ($this->get($type)['fields'] as $name => $field) {
            if ($field['type'] === 'repeater' && is_array($content[$name] ?? null)) {
                $content[$name] = array_map(static fn (array $item): array => $fill($field['fields'], $item), $content[$name]);
            }
        }

        return $content;
    }

    /**
     * One empty item of a repeater: every field present, at its empty value.
     *
     * Needed twice by the editor — to render the blank item its <template> holds, and to
     * append one when a browser without JavaScript presses Add — and both must agree with
     * what normalize() produces for a stored item, which is why it asks the same value().
     *
     * @param array<string, mixed> $field a validated repeater declaration
     * @return array<string, mixed>
     */
    public static function emptyItem(array $field): array
    {
        $item = [];
        foreach ($field['fields'] as $name => $itemField) {
            $item[$name] = self::value($itemField, null);
        }

        return $item;
    }

    /**
     * One field's stored value, made safe for a template (PLAN.md O-11).
     *
     * Split out of normalize() because a repeater's items are the same question asked
     * again, one level down: each item is a set of fields, each field cleaned by type. A
     * second copy of the match below, written for items, is how a type added later gets
     * handled in one place and forgotten in the other.
     *
     * @param array<string, mixed> $field a validated field declaration
     */
    private static function value(array $field, mixed $value): mixed
    {
        if ($field['type'] === 'repeater') {
            /*
             * A LIST, ALWAYS, and never longer than the definition allows. Anything that is
             * not a list of items reads as no items rather than as an error: the same rule
             * every other field follows here, so a template can draw the items it is given
             * without asking whether it was given any.
             *
             * Trimmed rather than refused, because this runs on RENDER as well as on save.
             * A definition whose max shrinks would otherwise make every page that used the
             * old maximum fail to draw, which is a worse answer than showing the first few.
             */
            $items = [];
            foreach (is_array($value) ? array_values($value) : [] as $item) {
                if (count($items) >= $field['max']) {
                    break;
                }
                $one = [];
                foreach ($field['fields'] as $itemName => $itemField) {
                    $one[$itemName] = self::value($itemField, is_array($item) ? ($item[$itemName] ?? null) : null);
                }
                $items[] = $one;
            }

            return $items;
        }

        return match ($field['type']) {
            // A reference, like media: an id or nothing (PLAN.md D-046).
            'media', 'file', 'form' => is_int($value) && $value > 0 ? $value : null,
            'link' => [
                'label' => is_array($value) && is_string($value['label'] ?? null) ? $value['label'] : '',
                'url' => is_array($value) && is_string($value['url'] ?? null) ? $value['url'] : '',
            ],
            'select' => is_string($value) && in_array($value, $field['options'], true) ? $value : $field['options'][0],
            default => is_string($value) ? $value : '',
        };
    }

    /**
     * Renders one block inside its section wrapper. Layers 2 and 3 are class names on
     * the wrapper; nothing is inlined as a style attribute (SPEC §5.4).
     *
     * Pictures are resolved BEFORE this is called and handed in as a lookup, so a template
     * never touches a database (MediaPicture). The parameter is optional because three
     * callers have no database to resolve from — the block library's previews, the design
     * specimen, and the tests — and a template that finds no entry draws its placeholder
     * exactly as it did before pictures existed. That is what keeps a missing, deleted or
     * still-encoding picture from ever becoming a broken URL.
     *
     * @param array<mixed> $content stored content_json
     * @param array<mixed> $style   stored style_json
     * @param string       $layout  stored layout; one the block no longer declares renders as its default
     * @param array<int, Picture> $media id => resolved picture
     * @param bool         $eager   the first section on the page, which is never lazy-loaded
     * @param string       $wrapper the element to wrap it in: site chrome is a header or a
     *                              footer (PLAN.md D-030), and a page block inside a section
     *                              that holds more than one is `none` — a plain div carrying
     *                              only its own layer 3, because the section around it has
     *                              already drawn the surface, the spacing and the container
     *                              (D-093 step 3). `section` remains for the case a section
     *                              holds exactly one block, where the two are the same
     *                              element and every existing page is drawn unchanged.
     * @param array<string, mixed> $resolved values the renderer resolved for this template;
     *                              today: the menu. A page block is rendered without it, and
     *                              a second kind of value belongs in an argument about this
     *                              line rather than quietly in the same bag.
     * @param string $locale  the locale being rendered — a fact about the request, not
     *                        something resolved for one template, which is why it is its own
     *                        argument and not another key in $resolved (D-030)
     * @param array<int, array<string, mixed>> $locales enabled locales, for the footer's
     *                        language switcher; empty for a page block, which has no use
     *                        for them yet
     * @param array<mixed> $options the block's options as they are to be drawn — the owner's
     *                        over the character's (D-166); each one missing is its default
     */
    public function render(string $type, array $content, array $style = [], string $layout = '', array $media = [], bool $eager = false, string $wrapper = 'section', array $resolved = [], string $locale = '', array $locales = [], array $options = []): string
    {
        // An allowlist, not the caller's word for it: this string is written straight into
        // the markup, and "whatever you pass" is how a tag name becomes an injection point.
        // Three elements are all the design has a meaning for, and one <header> and one
        // <footer> per page is the rule (D-028) — enforced by the callers, since a registry
        // cannot know how many times it will be asked.
        if (!in_array($wrapper, ['section', 'header', 'footer', 'none'], true)) {
            throw new RuntimeException("Unknown wrapper element: {$wrapper}");
        }

        $layout = $this->layout($type, $layout);
        $template = $this->directory . '/' . $type . '/template.php';
        $include = static function (string $__template, array $content, array $style, string $layout, array $media, bool $eager, array $resolved, string $locale, array $locales, array $options): void {
            require $__template;
        };
        // Every option answered (D-166): what the caller gives, each default where it gives
        // none. The caller composes the character's answer in; a template never asks.
        $specs = $this->get($type)['options'];
        $options = BlockOptions::effective($specs, BlockOptions::normalize($specs, $options), []);

        $style = SectionStyle::normalize($style);
        ob_start();
        try {
            // Whose field labels the canvas's placeholders read (D-178).
            \App\Support\Editing::block($type, fn () => $include($template, $this->normalize($type, $content), $style, $layout, $media, $eager, $resolved, $locale, $locales, $options));
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $inner = (string) ob_get_clean();

        /*
         * A BLOCK INSIDE A SECTION THAT HOLDS OTHERS carries its layer 3 and nothing else.
         *
         * Not `.block`: that class is section language — it sets the section padding and
         * --section-width, the block padding and position: relative, and it is what
         * `main > .block:first-child` and `.block:has(+ .divider-slant)` mean by a section.
         * Wearing it here would give every block in a column a second band of padding and
         * make the divider rules count blocks instead of sections.
         *
         * Nothing else has to change for this to be safe, and that is measured, not hoped:
         * every rule in blocks.css that reads `layout-*` is a DESCENDANT selector, and
         * `block-{type}` is used as a selector only for the header and the footer. So both
         * classes may sit one level lower than they used to and not one rule stops matching.
         */
        if ($wrapper === 'none') {
            return '<div class="' . e('block-' . $type . ' layout-' . $layout) . "\">\n" . $inner . "</div>\n";
        }

        $classes = implode(' ', array_merge(['block', 'block-' . $type, 'layout-' . $layout], SectionStyle::classes($style)));

        // The anchor and the animation only on a page's section: the chrome has neither.
        return '<' . $wrapper . ' class="' . e($classes) . '"' . ($wrapper === 'section' ? SectionStyle::attributes($style) : '') . ">\n"
            . self::sectionPicture($style, $media, $eager)
            . "<div class=\"container\">\n" . $inner . "</div>\n</" . $wrapper . ">\n";
    }

    /**
     * A section's background picture (D-024), laid under the content.
     *
     * Public because Sections::render() draws the same picture when a section holds more
     * than one block and this method is no longer on the path — the second caller that
     * makes it an entry point rather than a helper reached from outside.
     *
     * An element rather than a CSS background-image: the URL is a per-section value and
     * nothing is inlined as a style attribute (SPEC §5.4). As an element it also carries
     * srcset, true dimensions and lazy loading, which a background cannot.
     *
     * DECORATION, DELIBERATELY. The alt is emptied and the layer hidden from assistive
     * technology even when the owner wrote alt text for that picture: the words laid over
     * a backdrop carry the meaning, and announcing the backdrop would talk over them. The
     * same picture used as a block's own content keeps its alt, because there it IS the
     * content.
     *
     * Renders nothing when no picture is set, when the id names none, or when no variant
     * exists yet — and then surface: image falls back to the contrast colours, exactly as
     * it did before pictures existed.
     *
     * @param array<string, string|int|null> $style normalized
     * @param array<int, Picture>            $media
     */
    public static function sectionPicture(array $style, array $media, bool $eager): string
    {
        // ONLY under surface: image. D-024 is explicit — the sixth key "is used only when
        // surface is image" — and drawing it under any other surface is not a cosmetic
        // liberty: .surface-image > .container is what lifts the words above the picture,
        // so on any other surface the absolutely-positioned backdrop paints OVER them. The
        // demo's gradient hero rendered as a photograph with its heading invisible beneath.
        if (($style['surface'] ?? null) !== 'image') {
            return '';
        }

        $id = $style[SectionStyle::IMAGE] ?? null;
        if (!is_int($id) || !isset($media[$id])) {
            return '';
        }

        // The same picture with its alt emptied. Written out rather than made with `+`,
        // which erodes the shape to a plain array and hides what is being changed.
        $decorative = $media[$id];
        $decorative['alt'] = '';
        $tag = MediaPicture::tag($decorative, ['wide', 'hero', 'full'], '100vw', $eager);

        return $tag === '' ? '' : '<div class="section-picture" aria-hidden="true">' . "\n" . $tag . "</div>\n";
    }

}
