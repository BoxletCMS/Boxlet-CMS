<?php

namespace App\Modules\Appearance;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Design\Characters;
use App\Modules\Design\Composition;
use App\Modules\Design\SectionStyle;
use App\Modules\Design\Design;
use App\Modules\Design\Derived;
use App\Modules\Design\Fonts;
use App\Modules\Design\Palette;
use App\Modules\Design\PalettePairs;
use App\Modules\Design\Presets;
use App\Modules\Design\TokenCompiler;
use App\Modules\Design\Tokens;
use App\Modules\Design\Typography;
use App\Modules\Pages\PageDocument;
use App\Modules\Pages\PageLayoutData;
use App\Modules\Pages\PageRender;
use App\Modules\Settings\ChromeLook;
use App\Support\Url;

/**
 * The three endpoints behind the Appearance screen's picture (PLAN.md D-059): the page
 * itself, the stylesheet it links, and the contrast check the gauge reads.
 *
 * Their own class because they answer a MACHINE — an iframe and a fetch — while
 * AppearanceController answers a person, and because the screen and its publish were
 * already at the size where a file stops being readable.
 *
 * Every one of them reads the whole submitted screen and writes nothing.
 */
final class AppearancePreview
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * The site as the screen would leave it: the primary home page if there is one, else a
     * specimen of every surface. With a character named, blocks are composed as that
     * character composes them, so the preview shows the shape the page would take and not
     * only its colours.
     *
     * @param array<string, string> $params
     */
    public function page(Request $request, string $locale, array $params): Response
    {
        $decisions = $this->decisions($request->query);
        Url::useStylesheet(Url::withQuery(Url::admin('appearance', 'stylesheet'), $request->query));

        $character = is_string($request->query['character'] ?? null) ? $request->query['character'] : '';
        $character = Presets::exists($character) ? $character : '';
        $registry = $this->container->get('blocks');
        // The site's own language, not 'en': the chrome's words and its menu are per locale,
        // so a site whose main language is Croatian would judge its design under an empty
        // English footer.
        $shown = Url::primaryLocale() !== '' ? Url::primaryLocale() : 'en';
        // WHICH PAGE (D-111): one the screen asked for, if it is a published page of the
        // language being drawn; else the home page; else the specimen. A page of another
        // language or a draft falls back rather than failing — the picture is of the site
        // as it would be, and a draft is not on the site.
        $asked = is_string($request->query['page'] ?? null) && preg_match('~^[1-9][0-9]{0,9}$~', $request->query['page']) === 1
            ? $this->db()->one('SELECT id FROM pages WHERE id = ? AND locale = ? AND status = ?', [(int) $request->query['page'], $shown, 'published'])
            : null;
        $home = ($request->query['specimen'] ?? '') === '1' ? null : ($asked ?? $this->db()->one(
            'SELECT p.id FROM pages p JOIN locales l ON l.code = p.locale WHERE p.slug = ? AND l.is_primary = 1',
            [''],
        ));

        // Each section as it would be drawn under the character being tried: the owner's own
        // values over what that character composes (D-165), which is what publishing the
        // design gives — drawn by the page's own drawing, so the picture is the page (D-168).
        // A block that follows the character is drawn in the layout the one being tried names
        // (PageRender); a layout the owner chose is drawn as it is (D-191).
        $basis = $character !== '' ? $character : Composition::active($this->db());
        if ($home !== null) {
            $drawn = PageRender::draw(
                $this->db(),
                $registry,
                PageDocument::stored($this->db(), $registry, (int) $home['id']) ?? ['blocks' => [], 'sections' => []],
                $shown,
                $basis,
                (string) $this->container->get('config')->get('app.key'),
                ['pageId' => (int) $home['id'], 'design' => Tokens::resolve($decisions, $basis)],
            );
            $html = $drawn['html'];
            $firstSurface = $drawn['firstSurface'];
        } else {
            $html = '';
            $firstSurface = null;
            foreach (self::specimen() as [$type, $content, $style, $layout]) {
                $style = SectionStyle::effective(SectionStyle::normalize($style), Composition::style($basis, $type));
                $firstSurface ??= (string) $style['surface'];
                if ($character !== '') {
                    $layout = Composition::layout($registry, $character, $type);
                }
                $html .= $registry->render($type, $content, $style, $layout, [], false, 'section', [], '', [], Composition::options($basis, $type));
            }
        }

        // Every variable the layout reads comes from ONE place (D-057), and everything the
        // owner is trying comes from the query, validated and never written.
        $trying = AppearanceForm::trying($request->query, $shown, $character);
        // Which of the header and the footer has a colour of its own, from the same
        // decisions the stylesheet is compiled from — so the class the template emits and
        // the tokens the stylesheet carries can never disagree (D-110).
        $trying['own'] = Tokens::ownChrome($decisions);
        // And the design itself with what the first section stands on, for the choice
        // between the two logos (D-112): the same facts a visitor's page hands the layout.
        $trying['decisions'] = $decisions;
        $trying['first_surface'] = $firstSurface ?? '';
        $layout = PageLayoutData::forPreview($this->container, $shown, t('design.preview'), $trying);
        $layout['headerHtml'] = self::region($layout['headerHtml'], 'header', 'header', 1);
        $layout['footerHtml'] = self::region($layout['footerHtml'], 'footer', 'footer', 1);
        $body = (new View(dirname(__DIR__) . '/Pages/views'))->render('page', $shown, [
            'blocksHtml' => self::region($html, 'section class="block', 'section', -1),
        ] + $layout);
        $response = Response::admin($body);
        // The one admin page that may be framed, and only by the admin itself.
        $response->headers['Content-Security-Policy'] = "default-src 'self'; img-src 'self' data:; form-action 'none'; frame-ancestors 'self'; base-uri 'none'";
        $response->headers['X-Frame-Options'] = 'SAMEORIGIN';

        return $response;
    }

    /**
     * PRESSING A PART OF THE PICTURE OPENS ITS SETTINGS (D-181, README 5.7): the wrappers say
     * what they are — the header, each band (`<section class="block`), the footer — here, in
     * the preview alone, at the end of their opening tag (appearance-regions.js reads them).
     */
    private static function region(string $html, string $opening, string $name, int $limit): string
    {
        return preg_replace('~(<' . preg_quote($opening, '~') . '\b[^>]*)>~', '$1 data-bx-region="' . $name . '">', $html, $limit) ?? $html;
    }

    /**
     * @param array<string, string> $params
     */
    public function stylesheet(Request $request, string $locale, array $params): Response
    {
        $decisions = $this->decisions($request->query);
        $fonts = Typography::fontFaces([$decisions['heading_font'], $decisions['body_font']], Url::asset('assets/fonts'));
        $css = (new TokenCompiler())->css(Derived::from($decisions, Composition::section($this->character($request->query), [])['width']), $fonts);

        return new Response($css, 200, ['Content-Type' => 'text/css; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    /**
     * The library's faces, for the pickers that choose between them (PLAN.md D-065, D-185).
     *
     * THE ONE PLACE THE ADMIN LOADS THE SITE'S FONTS, and it is not a leak of the site's design
     * into the tool: these faces are the thing being CHOSEN, and a list of names is not a choice
     * anybody can make. Nothing else on the screen uses them. A browser fetches a face only when
     * text is drawn in it, so a closed picker costs nothing.
     *
     * Served rather than written into a stylesheet by hand, because Fonts already knows where
     * the files are and what weights they come in; a second copy of that would drift.
     *
     * @param array<string, string> $params
     */
    public function typefaces(Request $request, string $locale, array $params): Response
    {
        $css = Typography::fontFaces(array_keys(Fonts::ALL), Url::asset('assets/fonts'));
        // What each sample is set in, and the specimen's two halves: the stack is Fonts', so
        // none can show a face the site would not use.
        foreach (Fonts::ALL as $font => $definition) {
            $css .= '.font-sample[data-font="' . $font . '"] { font-family: ' . $definition['stack'] . "; }\n";
            $css .= '.specimen[data-heading-font="' . $font . '"] .specimen-heading { font-family: ' . $definition['stack'] . "; }\n";
            $css .= '.specimen[data-body-font="' . $font . '"] .specimen-body { font-family: ' . $definition['stack'] . "; }\n";
        }

        return new Response($css, 200, ['Content-Type' => 'text/css; charset=utf-8', 'Cache-Control' => 'max-age=3600']);
    }

    /**
     * What the gauge and the inline messages read while values change: every contrast pair
     * with its ratio, the derived palette, and the errors Save would refuse on. The same
     * validation Save runs — this endpoint is not a second opinion.
     *
     * @param array<string, string> $params
     */
    public function check(Request $request, string $locale, array $params): Response
    {
        $character = $this->character($request->query);
        $result = Tokens::validate(self::fields($request->query), $character);
        $decisions = Tokens::resolve($result['decisions'], $character, AppearanceForm::dark($request->query));
        $colors = Palette::forDecisions($decisions);
        $pairs = PalettePairs::pairs($colors, $decisions['secondary'] !== '', Tokens::byHand($decisions), Tokens::ownChrome($decisions));
        $body = json_encode([
            'errors' => (object) $result['errors'],
            'colors' => $colors,
            'pairs' => $pairs,
            // What every control comes to, so a readout follows the control it belongs to
            // instead of holding the number the page was rendered with (D-066) — and the
            // line under each section's name on the home, by the same rule (D-157).
            'readouts' => AppearanceForm::readouts($decisions) + SectionSummaries::of($decisions, $pairs),
            // The layout diagram's rectangles, worked out where the screen draws them.
            'diagram' => LayoutDiagram::geometry($decisions),
        ], JSON_THROW_ON_ERROR);

        return new Response($body, 200, ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']);
    }

    /**
     * The design a request is drawing, every key answered: a character by name, the site's
     * own when the request says nothing, or what the screen is trying (D-164).
     *
     * @param array<mixed> $query
     * @return array<string, string>
     */
    private function decisions(array $query): array
    {
        $preset = $query['preset'] ?? null;
        if (is_string($preset) && Presets::exists($preset)) {
            return Characters::decisions($preset);
        }
        if (!isset($query['seed'])) {
            return Design::resolved($this->db());
        }
        $character = $this->character($query);

        return Tokens::resolve(Tokens::validate(self::fields($query), $character)['decisions'], $character, AppearanceForm::dark($query));
    }

    /**
     * The screen's fields as decisions: the switches read, and the header and footer's
     * `look_` fields under their own keys.
     *
     * @param array<mixed> $query
     * @return array<mixed>
     */
    private static function fields(array $query): array
    {
        $fields = AppearanceForm::decisions($query);
        foreach (ChromeLook::keys() as $key) {
            if (array_key_exists(ChromeLook::field($key), $query)) {
                $fields[$key] = $query[ChromeLook::field($key)];
            }
        }

        return $fields;
    }

    /**
     * The character a request draws over: the one it names, else the site's.
     *
     * @param array<mixed> $query
     */
    private function character(array $query): string
    {
        $named = $query['character'] ?? null;

        return is_string($named) && Presets::exists($named) ? $named : Composition::active($this->db());
    }

    /**
     * A page of nothing but surfaces, for a site with no home page yet: every band the
     * design can draw, in one scroll.
     *
     * @return list<array{string, array<string, mixed>, array<string, string>, string}>
     */
    private static function specimen(): array
    {
        $paragraph = static fn (string $key): string => '<p>' . e(t($key)) . ' <a href="#">' . e(t('design.specimen.link')) . '</a>.</p>';

        return [
            ['hero', ['heading' => t('design.specimen.hero'), 'subheading' => t('design.specimen.hero_sub'), 'cta' => ['label' => t('design.specimen.button'), 'url' => '#']], ['surface' => 'gradient', 'pad_top' => '120', 'pad_bottom' => '120', 'align' => 'center'], 'center'],
            ['text', ['heading' => t('design.specimen.text_heading'), 'body' => $paragraph('design.specimen.text_body')], [], 'single'],
            ['image_text', ['heading' => t('design.specimen.tinted_heading'), 'body' => $paragraph('design.specimen.tinted_body'), 'image' => 1, 'link' => ['label' => t('design.specimen.button'), 'url' => '#']], ['surface' => 'tinted', 'divider' => 'line'], 'image-left'],
            ['hero', ['heading' => t('design.specimen.contrast_heading'), 'subheading' => t('design.specimen.contrast_body'), 'cta' => ['label' => t('design.specimen.button'), 'url' => '#']], ['surface' => 'contrast', 'divider' => 'slant'], 'left'],
        ];
    }

    private function db(): Db
    {
        return $this->container->get('db');
    }
}
