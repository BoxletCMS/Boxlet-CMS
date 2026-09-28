<?php

use App\Modules\Design\Palette;
use App\Modules\Design\Presets;
use App\Modules\Settings\ChromeLook;
use App\Support\Url;

/**
 * The Appearance screen (PLAN.md D-059): a strip of characters, five tabs of controls on the
 * left, and a picture of the whole site on the right.
 *
 * FIVE TABS RATHER THAN ONE SCROLL. The controls were a column over three thousand pixels
 * tall, and the header and footer were on another screen entirely. Grouped, each tab is a
 * question a person actually asks: what colour, what type, what shape, how the page sits,
 * and what wraps around it.
 *
 * WITHOUT JAVASCRIPT THE TABS ARE LINKS and every panel is on the page, one under the other,
 * exactly as before. appearance.js turns that into a real tablist — roles, arrow keys, one
 * panel at a time. Nothing here is only reachable through a script.
 *
 * @var array<string, string> $decisions
 * @var array<string, string> $errors keyed by the decision or the field at fault
 * @var string|null $notice
 * @var string $character character loaded into the form, '' when none
 * @var string $activeCharacter character the site composes new blocks with
 * @var bool $hasBlocks whether applying a composition would overwrite anything
 * @var bool $confirm whether Publish is asking how to apply the loaded character
 * @var list<array{id: int, name: string, character: string, decisions: array<string, string>, look: array<string, string>}> $library the designs the owner keeps
 * @var array<string, string> $colors derived palette
 * @var list<array{pair: string, decision: string, ratio: float, required: float, passes: bool, foreground: string, background: string}> $pairs
 * @var array{text: array<string, int>, text_phone: int, space: int, section: int, radius: int, container: int, container_rem: float} $readable
 * @var array<string, string> $look the chrome's seven choices, '' for "follow the character"
 * @var string $menu the menu the header shows, by name
 * @var list<string> $menus every menu name on offer
 * @var array<string, array<string, string>> $words the owner's words, per locale
 * @var array<string, array<int, array{title: string, depth: int, published: bool, url: string}>> $linkPages
 * @var array<int, array<string, mixed>> $locales
 * @var string $shownLocale the language the preview draws
 * @var array<string, string> $characterLook what the character gives each look choice
 * @var array<string, string> $readouts what every control comes to, in words
 * @var string $host the site's own hostname, for the strip over the picture
 * @var string $pageName which page the picture is of
 * @var list<array{id: int, title: string, depth: int}> $previewPages the published pages the picture can be of (D-111)
 * @var string $previewUrl
 * @var string $title
 * @var string $csrf
 */
$error = static fn (string $key): string => isset($errors[$key])
    ? '<p class="field-error" data-error-for="' . e($key) . '" role="alert">' . e($errors[$key]) . '</p>'
    : '<p class="field-error" data-error-for="' . e($key) . '" hidden></p>';
/*
 * A CLOSED SET IS A ROW OF BUTTONS, NOT A DROPDOWN (PLAN.md D-065).
 *
 * Every option is visible at rest, the current one is visibly the current one, and choosing
 * is one press rather than open-read-choose-close. A select hides four of five answers
 * behind the one already given, which on a screen whose whole point is "change it and look"
 * is the wrong shape.
 *
 * RADIO INPUTS, not buttons with a hidden field: they submit without a script, the browser
 * gives arrow-key movement inside the group for free, and screen readers already know what
 * a radio group is.
 *
 * $readout is the number the choice comes to — "20px", "56rem · 896px" — which is what
 * replaced the compiler's clamp() on this screen (D-058).
 *
 * @param array<array-key, string> $labels value => what it is called. array-key, not string:
 *        the heading weights are their own labels and PHP turns '600' into 600.
 */
$segmented = static function (string $key, array $labels, string $readout = '', string $follows = '') use ($decisions, $error, $readouts): string {
    $readout = $readouts[$key] ?? $readout;
    $current = $decisions[$key] ?? '';
    $id = 'design-' . $key;
    $html = '<div class="field"><div class="field-row"><span class="field-label" id="' . e($id) . '-label">' . e(t('design.' . $key)) . '</span>';
    // The right of the label row says either what the choice comes to, or — for a chrome
    // choice nobody has touched — that it is still following the character (D-065, §3.5).
    if ($follows !== '' && $current === '') {
        $html .= '<span class="readout readout-following" data-readout="' . e($key) . '" title="' . e($follows) . '">' . e($follows) . '</span>';
    } elseif ($readout !== '') {
        // The whole phrase in the title: a readout gives way at the end when the row is
        // short, and a phrase that is cut has to be readable somewhere (D-110).
        $html .= '<span class="readout" data-readout="' . e($key) . '" title="' . e($readout) . '">' . e($readout) . '</span>';
    }
    // The group itself is segmented_group()'s (D-107): the Section panel asks the same
    // question about a band, and one definition of a control is one definition of its
    // behaviour. What stays here is this screen's own framing — the readout, the "still
    // following the character" state, and the error.
    $html .= '</div>' . segmented_group($key, $labels, $current, $id . '-label', $id . '-');

    return $html . field_hint('hint.design.' . $key) . $error($key) . '</div>';
};
$labels = static function (string $key, array $values): array {
    $result = [];
    foreach ($values as $value) {
        $result[$value] = t('design.' . $key . '.' . $value);
    }

    return $result;
};
$swatch = static fn (string $hex, string $name): string => '<svg viewBox="0 0 10 10" aria-hidden="true">'
    . '<rect width="10" height="10" fill="' . e($hex) . '" data-swatch="' . e($name) . '"/></svg>';

/**
 * A decision that is a NUMBER: a slider, with what it comes to beside its name (D-062,
 * D-066). The readout is the number a person can picture, not the one the CSS is in.
 */
$slider = static function (string $key, float $min, float $max, float $step) use ($decisions, $error, $readouts): string {
    $id = 'design-' . $key;

    return '<div class="field"><div class="field-row">'
        . '<label for="' . e($id) . '">' . e(t('design.' . $key)) . '</label>'
        . '<output class="readout" data-readout="' . e($key) . '" id="' . e($id) . '-value" for="' . e($id) . '">' . e($readouts[$key] ?? '') . '</output>'
        . '</div>'
        . '<input type="range" id="' . e($id) . '" name="' . e($key) . '"'
        . ' min="' . e(rtrim(rtrim(number_format($min, 3, '.', ''), '0'), '.')) . '"'
        . ' max="' . e(rtrim(rtrim(number_format($max, 3, '.', ''), '0'), '.')) . '"'
        . ' step="' . e(rtrim(rtrim(number_format($step, 3, '.', ''), '0'), '.')) . '"'
        . ' value="' . e($decisions[$key]) . '" data-slider-for="' . e($id) . '-value">'
        . field_hint('hint.design.' . $key)
        . $error($key)
        . '</div>';
};

/**
 * OR A COLOUR OF YOUR OWN (PLAN.md D-076, D-111).
 *
 * Sits directly under the segmented group it belongs to, never in a section of its own: it
 * is one more answer to the question above it — "which colour is this" — and a panel called
 * "custom colours" somewhere else would be the same question asked twice.
 *
 * THE SAME ROW AS A ROLE OF THE PALETTE (D-074), because it is the same act: the swatch IS
 * the picker, the hex beside it follows the hand, and one button gives the colour back. It
 * was a lone square under a three-line label, with no hex — the one colour control on the
 * screen that did not look like the others.
 *
 * A colour input always carries SOME colour, so "is this mine" cannot be read off its value;
 * the switch is what says so, choosing flips it (appearance.js), and the button gives it
 * back. Nothing here depends on a script: without one, the switch is a checkbox and the
 * button is an ordinary submit.
 *
 * WHILE IT IS NOT THE OWNER'S, the input holds the shade that place has as the page was
 * rendered — a starting point for the picker — and the row says "palette" where the hex
 * would be, because that hex does not follow the palette live and a number that can go
 * stale is worse than none. Taken, the hex is the owner's and is shown.
 */
$ownColour = static function (string $key) use ($decisions, $error, $colors, $look, $characterLook): string {
    $id = 'design-' . $key;
    $taken = ($decisions[$key] ?? '') !== '';
    $label = t('design.' . $key);
    // Which palette shade the place shows right now — what the owner chose, else what the
    // character gives — so the picker opens on the colour that is there rather than black.
    if ($key === 'page_background_colour') {
        $showing = $colors[$decisions['page_background']] ?? $colors['surface'];
    } else {
        $part = str_replace('_colour', '_surface', $key);
        $surface = ($look[$part] ?? '') !== '' ? $look[$part] : ($characterLook[$part] ?? 'plain');
        $showing = $colors[['plain' => 'background', 'tinted' => 'surface', 'contrast' => 'contrast', 'gradient' => 'gradient-start'][$surface] ?? 'background'];
    }
    $free = t('design.by_hand.free', ['role' => $label]);

    return '<div class="field own-colour' . ($taken ? ' own-colour-taken' : '') . '">'
        . '<ul class="roles" role="list"><li class="role">'
        . '<input type="color" class="role-swatch" id="' . e($id) . '" name="' . e($key) . '"'
        . ' value="' . e($taken ? $decisions[$key] : $showing) . '" data-by-hand="' . e($key) . '" aria-label="' . e($label) . '">'
        . '<span class="role-name" aria-hidden="true" title="' . e($label) . '">' . e($label) . '</span>'
        . '<code class="role-value" data-colour-for="' . e($id) . '">' . e($taken ? $decisions[$key] : $showing) . '</code>'
        . '<span class="role-derived">' . e(t('design.by_hand.palette')) . '</span>'
        . '<input type="checkbox" name="' . e($key) . '_on" value="1"' . ($taken ? ' checked' : '')
        . ' data-by-hand-switch="' . e($key) . '" tabindex="-1" aria-hidden="true">'
        . '<button type="submit" form="design-form" name="action" value="colour:free:' . e($key) . '"'
        . ' class="icon-button role-free own-colour-free" title="' . e($free) . '">'
        . icon('history') . '<span class="visually-hidden">' . e($free) . '</span></button>'
        . '</li></ul>'
        . field_hint('hint.design.' . $key)
        . $error($key)
        . '</div>';
};

/**
 * ONE CHROME CHOICE AS A ROW OF BUTTONS (D-032, D-036, D-065). Every choice can be left to
 * the character, and that is a STATE you can see: the group says "following" while nothing
 * is chosen, and the first segment — named for what the character actually gives — puts it
 * back. Shared by the Header and Footer tabs (D-111), which is why it lives here.
 */
$lookGroup = static function (string $choice) use ($look, $characterLook): string {
    $field = ChromeLook::field($choice);
    $chosen = $look[$choice] ?? '';
    $fromCharacter = t('chrome.look.' . $choice . '.' . ($characterLook[$choice] ?? ''));
    $said = $chosen === '' ? t('chrome.look.following') : t('chrome.look.' . $choice . '.' . $chosen);
    $html = '<div class="field"><div class="field-row">'
        . '<span class="field-label" id="' . e($field) . '-label">' . e(t('chrome.look.' . $choice)) . '</span>'
        . '<span class="readout' . ($chosen === '' ? ' readout-following' : '') . '" title="' . e($said) . '">' . e($said) . '</span>'
        . '</div>'
        . '<div class="segmented-choice" role="radiogroup" aria-labelledby="' . e($field) . '-label">'
        . '<label class="segment segment-follow"><input type="radio" id="' . e($field) . '" name="' . e($field) . '" value=""' . ($chosen === '' ? ' checked' : '') . '>'
        . '<span>' . e(t('chrome.look.follow_short', ['value' => $fromCharacter])) . '</span></label>';
    foreach (ChromeLook::OPTIONS[$choice] as $option) {
        $html .= '<label class="segment"><input type="radio" name="' . e($field) . '" value="' . e($option) . '"' . ($chosen === $option ? ' checked' : '') . '>'
            . '<span>' . e(t('chrome.look.' . $choice . '.' . $option)) . '</span></label>';
    }

    return $html . '</div>' . field_hint('hint.look.' . $choice) . '</div>';
};

/** A stored word of the owner's, for one language; '' when there is none. */
$word = static fn (string $code, string $field): string => is_string($words[$code][$field] ?? null)
    ? $words[$code][$field]
    : '';

/**
 * ONE LANGUAGE'S WORDS, FOLDED (D-111). With one language the words are a group like any
 * other. With more, each language is a <details>, and the one the picture is drawn in
 * stands open: the tab grew by four fields per language, and a person editing the Croatian
 * footer is not helped by the German one standing between them and it. A <details> works
 * without a script, and a language folded away is still on the form and still saved.
 */
$wordsPanel = static function (string $code, string $legend, string $inside) use ($locales, $shownLocale): string {
    $label = '';
    foreach ($locales as $locale) {
        if ((string) $locale['code'] === $code) {
            $label = (string) $locale['label'];
        }
    }
    if (count($locales) < 2) {
        return '<fieldset class="fieldset"><legend>' . e($legend) . '</legend>' . $inside . '</fieldset>';
    }
    $previewed = $code === $shownLocale;

    return '<details class="fieldset words"' . ($previewed ? ' open' : '') . '>'
        . '<summary>' . e($legend . ': ' . $label) . ($previewed ? ' <span class="words-previewed">' . e(t('appearance.words_previewed')) . '</span>' : '') . '</summary>'
        . '<div class="words-inside">' . $inside . '</div>'
        . '</details>';
};

/** The six tabs, in the order the questions arrive (D-059, D-111). */
$tabs = ['colour', 'type', 'shape', 'page', 'header', 'footer'];

/**
 * A character or a saved design in one line, BUILT FROM ITS OWN DECISIONS: "modern · 56rem ·
 * normal · full bleed". Not a sentence somebody wrote about it — a sentence that cannot go
 * out of date.
 *
 * @param array<string, string> $decisions
 */
$summary = static fn (array $decisions, string $header): string => implode(' · ', array_filter([
    t('design.typography.' . $decisions['typography']),
    $decisions['container'] . 'rem',
    t('design.spacing.' . $decisions['spacing']),
    t($decisions['boxed'] === 'yes' ? 'appearance.boxed' : 'appearance.full_bleed'),
    // And what header it gives (D-111): a card that said nothing about the chrome was a
    // card about half the design.
    $header === '' ? '' : t('chrome.look.header_arrangement.' . $header),
]));

/**
 * One card in the left rail: three swatches, a name, what it is, and what it does.
 *
 * @param array<string, string> $decisions
 * @param string $header the header arrangement the card's design gives, '' when unknown
 */
$card = static function (array $decisions, string $header, string $name, string $badge, string $key, string $inside) use ($swatch, $summary): string {
    $palette = Palette::colors($decisions['seed'], $decisions['secondary'], $decisions['surface_contrast']);

    return '<div class="rail-card' . ($badge !== '' ? ' rail-card-current' : '') . '">'
        . '<div class="rail-card-top">'
        . '<span class="rail-chips" aria-hidden="true">'
        . $swatch($palette['accent'], $key . '-accent')
        . $swatch($palette['contrast'], $key . '-contrast')
        . $swatch($palette['surface'], $key . '-surface')
        . '</span>'
        . '<span class="rail-card-name">' . e($name) . '</span>'
        // The badge is the WORD, not a glyph: "in use" is a fact about the site, and a dot
        // that means it is a dot somebody has to be taught.
        . ($badge !== '' ? '<span class="rail-live">' . e($badge) . '</span>' : '')
        . $inside
        . '</div>'
        . '<p class="rail-card-shape">' . e($summary($decisions, $header)) . '</p>'
        . '</div>';
};
?>
        <div class="appearance" data-appearance>
            <?php /* THE BAR. Everything that acts on the whole screen: what it is on the
                     left, how to look at it and what to do with it on the right. */ ?>
            <div class="appearance-bar">
                <span class="appearance-title"><?= icon('palette') ?><strong><?= e($title) ?></strong>
                    <span class="appearance-subhead"><?= e(t('appearance.subhead')) ?></span></span>

                <?php /* The characters, once there is no room for them as a column: the same
                         rail, opened over the screen from here (appearance-rail.js). Drawn
                         only when that script is running and the screen is narrow enough —
                         everywhere else it is a column, and this is display:none. */ ?>
                <button type="button" class="button button-quiet appearance-rail-open" data-rail-panel
                        aria-expanded="false" aria-controls="appearance-rail"><?= e(t('appearance.characters')) ?></button>

                <div class="preview-tools" data-preview-tools hidden>
                    <div class="viewports" role="group" aria-label="<?= e(t('appearance.width')) ?>">
<?php foreach (['desktop' => 1280, 'tablet' => 834, 'phone' => 390] as $name => $width): ?>
                        <button type="button" class="viewport" data-viewport="<?= e((string) $width) ?>" aria-pressed="<?= $name === 'desktop' ? 'true' : 'false' ?>" title="<?= e(t('appearance.width.' . $name)) ?>"><?= e(t('appearance.width.' . $name)) ?></button>
<?php endforeach; ?>
                    </div>
                    <label class="zoom">
                        <span class="visually-hidden"><?= e(t('appearance.zoom')) ?></span>
                        <select data-zoom>
                            <option value="fit"><?= e(t('appearance.zoom.fit')) ?></option>
                            <option value="1">100%</option>
                            <option value="0.75">75%</option>
                            <option value="0.5">50%</option>
                        </select>
                    </label>
                    <?php /* Held, not toggled: a comparison you have to keep holding is one
                             you cannot walk away from and mistake for the site. */ ?>
                    <button type="button" class="viewport viewport-compare" data-compare aria-pressed="false" title="<?= e(t('appearance.compare_hint')) ?>"><?= e(t('appearance.compare')) ?></button>
                </div>

                <span class="preview-state" data-state role="status"
                      data-published="<?= e(t('appearance.state.published')) ?>"
                      data-unpublished="<?= e(t('appearance.state.unpublished')) ?>"
                      data-problem="<?= e(t('appearance.state.problem')) ?>"><?= e(t('appearance.state.published')) ?></span>

                <?php /* ONE BUTTON. Applying a character to a site that has blocks can rewrite
                         every section, so that needs two explicit answers — but the question
                         belongs at the moment of publishing, not permanently in the bar
                         (D-068). */ ?>
                <div class="preview-actions">
                    <button type="submit" form="design-preview-form" class="button button-quiet" data-preview-button><?= e(t('design.update_preview')) ?></button>
                    <a class="button button-quiet" href="<?= e(Url::admin('appearance')) ?>" data-revert hidden><?= e(t('appearance.revert')) ?></a>
                    <button type="submit" form="design-form" name="action" value="save" class="button"><?= e(t('appearance.publish')) ?></button>
                </div>
            </div>

<?php if ($notice !== null): ?>
            <p class="notice appearance-notice<?= $errors !== [] ? ' notice-error' : '' ?>" role="<?= $errors !== [] ? 'alert' : 'status' ?>"><?= e($notice) ?></p>
<?php endif; ?>
<?php if ($confirm): ?>
            <?php /* The one destructive choice in the design layer, asked once, with what
                     each answer does written beside it rather than behind it. */ ?>
            <div class="appearance-confirm" role="alert">
                <p class="confirm-question"><?= e(t('design.apply.title', ['character' => t('design.preset.' . $character)])) ?></p>
                <div class="confirm-options">
                    <span>
                        <button type="submit" form="design-form" name="action" value="save_design" class="button button-secondary"><?= e(t('design.apply.design_only')) ?></button>
                        <span class="hint"><?= e(t('design.apply.design_only_hint')) ?></span>
                    </span>
                    <span>
                        <button type="submit" form="design-form" name="action" value="save_composition" class="button"><?= e(t('design.apply.with_composition')) ?></button>
                        <span class="hint"><?= e(t('design.apply.with_composition_hint')) ?></span>
                    </span>
                </div>
            </div>
<?php endif; ?>

            <?php /* ONE FORM AROUND ALL THREE COLUMNS. The character cards, the library and
                     every control post the same screen — that is what stopped a character
                     load from clearing the header (D-059) — so the form IS the layout. */ ?>
            <form id="design-form" method="post" action="<?= e(Url::admin('appearance')) ?>" class="appearance-body design-form" data-design-form
                  data-check-url="<?= e(Url::admin('appearance', 'check')) ?>" data-preview-url="<?= e(Url::admin('appearance', 'preview')) ?>"
                  data-stylesheet-url="<?= e(Url::admin('appearance', 'stylesheet')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="character" value="<?= e($character) ?>">

                <div class="appearance-rail" id="appearance-rail">
                    <h2 class="rail-heading"><?= e(t('appearance.characters')) ?> <span><?= e(t('appearance.characters_hint')) ?></span></h2>
<?php foreach (Presets::names() as $preset): ?>
                    <?= $card(
                        Presets::get($preset),
                        ChromeLook::CHARACTER[$preset]['header_arrangement'] ?? '',
                        t('design.preset.' . $preset),
                        $preset === $activeCharacter ? t('design.preset.current') : '',
                        'preset-' . $preset,
                        '<button type="submit" form="design-form" name="action" value="preset:' . e($preset) . '" class="rail-use">'
                            . '<span class="visually-hidden">' . e(t('design.load_preset')) . ': ' . e(t('design.preset.' . $preset)) . '</span></button>',
                    ) ?>
<?php endforeach; ?>

                    <h2 class="rail-heading"><?= e(t('appearance.library')) ?>
                        <span><?= e($library === [] ? t('appearance.library.empty_rail') : t('appearance.library_count', ['count' => count($library)])) ?></span></h2>
<?php foreach ($library as $saved): ?>
                    <?= $card(
                        $saved['decisions'],
                        // Its own choice, else its character's, else nothing to say.
                        ($saved['look']['header_arrangement'] ?? '') !== '' ? $saved['look']['header_arrangement'] : (ChromeLook::CHARACTER[$saved['character']]['header_arrangement'] ?? ''),
                        $saved['name'],
                        '',
                        'saved-' . $saved['id'],
                        '<span class="rail-card-tools">'
                            . '<button type="submit" form="design-form" name="action" value="library:save:' . $saved['id'] . '" class="icon-button" title="' . e(t('appearance.library.overwrite', ['name' => $saved['name']])) . '">'
                            . icon('replace') . '<span class="visually-hidden">' . e(t('appearance.library.overwrite', ['name' => $saved['name']])) . '</span></button>'
                            . '<button type="submit" form="design-form" name="action" value="library:delete:' . $saved['id'] . '" class="icon-button" title="' . e(t('appearance.library.delete_one', ['name' => $saved['name']])) . '">'
                            . icon('trash-2') . '<span class="visually-hidden">' . e(t('appearance.library.delete_one', ['name' => $saved['name']])) . '</span></button>'
                            . '</span>'
                            . '<button type="submit" form="design-form" name="action" value="library:use:' . $saved['id'] . '" class="rail-use">'
                            . '<span class="visually-hidden">' . e(t('appearance.library.use')) . ': ' . e($saved['name']) . '</span></button>',
                    ) ?>
<?php endforeach; ?>

                    <div class="rail-keep">
                        <?php /* A .field, so it is the admin's own input rather than the
                                 browser's (D-078). It carried only a width, so what was
                                 drawn was a 2px inset border on rgb(59, 59, 59) with a
                                 content-box width of 100% — which is how it came to touch
                                 the edge of the column. */ ?>
                        <div class="field">
                            <label class="visually-hidden" for="library_name"><?= e(t('appearance.library.name')) ?></label>
                            <input type="text" id="library_name" name="library_name" maxlength="80" value="" autocomplete="off"
                                   placeholder="<?= e(t('appearance.library.name')) ?>">
                            <?= $error('library_name') ?>
                        </div>
                        <button type="submit" form="design-form" name="action" value="library:save" class="button button-secondary"><?= e(t('appearance.library.save')) ?></button>
                    </div>
                </div>

                <div class="appearance-stage-column">
                    <?php /* The strip says WHAT is in the frame and at what size: a preview
                             with no address is a picture of something. */ ?>
                    <div class="stage-strip">
                        <span class="stage-where"><?= e($host) ?> <span>·</span>
                            <?php /* WHICH PAGE THE PICTURE IS OF (D-111). The home page by
                                     default; any published page of the previewed language
                                     on request, because a header laid over the first
                                     section looks different over a page without a hero,
                                     and a sticky header cannot be judged on a short one.
                                     Inside the form, so the script's query carries it like
                                     any control; hidden until the script runs, because
                                     without one the picture is the home page and a select
                                     that changed nothing would teach people not to trust
                                     selects (D-060). */ ?>
                            <span class="stage-page" data-page-name><?= e($pageName) ?></span>
                            <label class="stage-pick" data-preview-page hidden>
                                <span class="visually-hidden"><?= e(t('appearance.page_to_preview')) ?></span>
                                <select name="page">
<?php foreach ($previewPages as $option): ?>
                                    <option value="<?= e((string) $option['id']) ?>"><?= e(str_repeat('— ', $option['depth']) . $option['title']) ?></option>
<?php endforeach; ?>
                                </select>
                            </label>
                        </span>
                        <?php /* Said, not silently acted on: when the column cannot carry the
                                 chosen width the screen says so here and leaves the width
                                 alone. */ ?>
                        <span class="stage-tight" data-stage-tight role="status" hidden><?= e(t('appearance.stage.tight')) ?></span>
                        <span class="stage-size" data-stage-size></span>
                    </div>
                    <?php /* THE ZOOM SCALES THE STAGE, NEVER THE FRAME'S WIDTH. A page judged
                             at 1280 has to lay itself out at 1280; shrinking the frame instead
                             would hand it a narrower window and it would answer with the phone
                             layout, which is a different question entirely. */ ?>
                    <div class="preview-stage" data-stage>
                        <iframe name="design-preview" src="<?= e($previewUrl) ?>" title="<?= e(t('design.preview')) ?>" data-design-preview></iframe>
                    </div>
                </div>

                <div class="appearance-inspector" data-inspector data-hints-root="appearance">
                    <div class="tabs" data-tabs>
                        <div class="tab-strip" data-tab-strip>
<?php foreach ($tabs as $index => $name): ?>
                            <?php /* The whole name in the title: five tabs share 280px, and a
                                     name that gives way at the end has to be readable
                                     somewhere (D-075). */ ?>
                            <a class="tab<?= $index === 0 ? ' tab-current' : '' ?>" href="#panel-<?= e($name) ?>" id="tab-<?= e($name) ?>" data-tab="<?= e($name) ?>"
                               title="<?= e(t('appearance.tab.' . $name)) ?>"><?= e(t('appearance.tab.' . $name)) ?></a>
<?php endforeach; ?>
                        </div>

                        <?php /* HINTS ON DEMAND (PLAN.md D-078). A line of explanation under
                                 every control is what teaches this screen and also what
                                 fills a 312px column; the owner asked for the space back.
                                 They are off until asked for, and the answer is remembered
                                 in this browser and nowhere else — it is how one person
                                 likes to work, not a decision about the site.
                                 WITHOUT A SCRIPT THE HINTS SHOW and this button is not
                                 there: the state that explains itself is the safe one, and
                                 a toggle that cannot toggle is worse than no toggle. */ ?>
                        <?php /* Both words live in the markup, because they are translated
                                 and the script is not. */ ?>
                        <button type="button" class="hints-toggle" data-hints-toggle hidden
                                aria-pressed="false"
                                data-show="<?= e(t('hints.show')) ?>"
                                data-hide="<?= e(t('hints.hide')) ?>"><?= e(t('hints.show')) ?></button>
<?php foreach ($tabs as $name): ?>
                        <div class="tab-panel" id="panel-<?= e($name) ?>" data-panel="<?= e($name) ?>" aria-labelledby="tab-<?= e($name) ?>">
                            <?php include __DIR__ . '/tabs/' . $name . '.php'; ?>
                        </div>
<?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>

        <?php /* The faces the Type tab chooses between, loaded by this screen alone. */ ?>
        <link rel="stylesheet" href="<?= e(Url::admin('appearance', 'typefaces')) ?>">

        <?php /* Without JavaScript this form sends the current values to the preview frame. */ ?>
        <form id="design-preview-form" method="get" action="<?= e(Url::admin('appearance', 'preview')) ?>" target="design-preview" class="visually-hidden"></form>
        <script src="<?= e(Url::versioned('assets/appearance.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/appearance-readouts.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/appearance-tabs.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/hints.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/appearance-rail.js')) ?>" defer></script>
        <script src="<?= e(Url::versioned('assets/appearance-stage.js')) ?>" defer></script>
