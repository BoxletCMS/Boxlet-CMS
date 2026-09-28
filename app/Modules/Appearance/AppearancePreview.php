<?php

namespace App\Modules\Appearance;

use App\Core\Container;
use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Design\Composition;
use App\Modules\Design\Design;
use App\Modules\Design\Derived;
use App\Modules\Design\Palette;
use App\Modules\Design\Presets;
use App\Modules\Design\TokenCompiler;
use App\Modules\Design\Tokens;
use App\Modules\Design\Typography;
use App\Modules\Pages\Page;
use App\Modules\Pages\PageLayoutData;
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

        $blocks = [];
        if ($home !== null) {
            foreach (Page::blocks($this->db(), (int) $home['id']) as $block) {
                if ($registry->has($block['type'])) {
                    $blocks[] = [$block['type'], $block['content'], $block['style'], $block['layout']];
                }
            }
        } else {
            $blocks = self::specimen();
        }

        $html = '';
        foreach ($blocks as [$type, $content, $style, $layout]) {
            if ($character !== '') {
                $style = Composition::style($character, $type);
                $layout = Composition::layout($registry, $character, $type);
            }
            $html .= $registry->render($type, $content, $style, $layout);
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
        $trying['first_surface'] = '';
        foreach ($blocks as [$type, $content, $style, $layout]) {
            $trying['first_surface'] = (string) (($character !== '' ? Composition::style($character, $type) : $style)['surface'] ?? '');
            break;
        }
        $body = (new View(dirname(__DIR__) . '/Pages/views'))->render('page', $shown, [
            'blocksHtml' => $html,
        ] + PageLayoutData::forPreview($this->container, $shown, t('design.preview'), $trying));
        $response = Response::admin($body);
        // The one admin page that may be framed, and only by the admin itself.
        $response->headers['Content-Security-Policy'] = "default-src 'self'; img-src 'self' data:; form-action 'none'; frame-ancestors 'self'; base-uri 'none'";
        $response->headers['X-Frame-Options'] = 'SAMEORIGIN';

        return $response;
    }

    /**
     * @param array<string, string> $params
     */
    public function stylesheet(Request $request, string $locale, array $params): Response
    {
        $decisions = $this->decisions($request->query);
        $fonts = Typography::fontFaces($decisions['typography'], Url::asset('assets/fonts'));
        $css = (new TokenCompiler())->css(Derived::from($decisions), $fonts);

        return new Response($css, 200, ['Content-Type' => 'text/css; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    /**
     * The six typefaces, for the cards that choose between them (PLAN.md D-065).
     *
     * THE ONE PLACE THE ADMIN LOADS THE SITE'S FONTS, and it is not a leak of the site's
     * design into the tool: these faces are the thing being CHOSEN, and a list of six names
     * is not a choice anybody can make. Nothing else on the screen uses them — the sample is
     * two letters wide.
     *
     * Served rather than written into a stylesheet by hand, because Typography already knows
     * where the files are and what weights they come in; a second copy of that would drift
     * the first time a family changed.
     *
     * @param array<string, string> $params
     */
    public function typefaces(Request $request, string $locale, array $params): Response
    {
        $css = '';
        foreach (array_keys(Typography::PAIRINGS) as $pairing) {
            $css .= Typography::fontFaces($pairing, Url::asset('assets/fonts'));
        }
        /*
         * And what each card's sample is set in — and, since D-075, the SPECIMEN too. The
         * stack is Typography's, so neither can ever show a face the site would not use.
         *
         * The specimen is the one place on this screen where both halves of a pairing are
         * shown: a pairing is a heading face AND a body face, and two lines of each is the
         * only way to see whether they belong together.
         */
        foreach (Typography::PAIRINGS as $name => $pairing) {
            $heading = Typography::stack($pairing['heading']);
            $css .= '.typeface-sample[data-typeface="' . $name . '"] { font-family: ' . $heading . "; }\n";
            $css .= '.specimen[data-typeface="' . $name . '"] .specimen-heading { font-family: ' . $heading . "; }\n";
            $css .= '.specimen[data-typeface="' . $name . '"] .specimen-body { font-family: ' . Typography::stack($pairing['body']) . "; }\n";
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
        $result = Tokens::validate(AppearanceForm::decisions($request->query));
        $decisions = $result['decisions'];
        $byHand = Tokens::byHand($decisions);
        $colors = Palette::colors($decisions['seed'], $decisions['secondary'], $decisions['surface_contrast'], $byHand);
        $body = json_encode([
            'errors' => (object) $result['errors'],
            'colors' => $colors,
            'pairs' => Palette::pairs($colors, $decisions['secondary'] !== '', $byHand, Tokens::ownChrome($decisions)),
            // What every control comes to, so a readout follows the control it belongs to
            // instead of holding the number the page was rendered with (D-066).
            'readouts' => AppearanceForm::readouts($decisions),
        ], JSON_THROW_ON_ERROR);

        return new Response($body, 200, ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']);
    }

    /**
     * @param array<mixed> $query
     * @return array<string, string>
     */
    private function decisions(array $query): array
    {
        $preset = $query['preset'] ?? null;
        if (is_string($preset) && Presets::exists($preset)) {
            return Presets::get($preset);
        }
        if (!isset($query['seed'])) {
            return Design::load($this->db());
        }

        return Tokens::validate(AppearanceForm::decisions($query))['decisions'];
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
            ['hero', ['heading' => t('design.specimen.hero'), 'subheading' => t('design.specimen.hero_sub'), 'cta' => ['label' => t('design.specimen.button'), 'url' => '#']], ['surface' => 'gradient', 'rhythm' => 'airy', 'align' => 'center'], 'center'],
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
