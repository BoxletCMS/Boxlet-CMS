<?php

namespace App\Modules\Design\Vocabulary;

/**
 * WHAT EACH KEY OF A DESIGN SET DOES, IN ONE SENTENCE (PLAN.md D-183), for the people and models
 * who write sets: the schema's `description` and the reference's last column. English, as the
 * schema is; not the admin's words, which are t()'s.
 *
 * Every key of Decisions::ALL and of a section's style has one, and tests/design_set_test.php
 * fails when a key is added without: after the freeze (v2) a key only ever arrives with its
 * sentence.
 */
final class Descriptions
{
    /** Decisions and look, by key. */
    public const KEYS = [
        // ---- colour ---------------------------------------------------------------------
        'seed' => 'The main colour: buttons, links and the start of a gradient; the rest of the palette is worked out from it.',
        'secondary' => 'A second colour for contrast bands; without one, a deep shade of the main colour is used.',
        'mode' => 'Whether the page is light with dark words or dark with light words; every derived colour follows.',
        'surface_contrast' => 'How far the tinted surface and cards stand from the page, from none to strong.',
        'color_background' => 'The page colour set by hand instead of worked out; the contrast check measures what is read on it.',
        'color_card' => 'A card\'s colour set by hand instead of half a step from the page.',
        'color_surface' => 'The tinted surface set by hand instead of a step from the page.',
        'color_border' => 'The colour of rules and outlines set by hand.',
        'color_text' => 'The text colour set by hand; it must read at 4.5:1 on the page, a card and the tinted surface.',
        'color_muted' => 'The quieter text (leads, captions, a card\'s lines) set by hand; it must read at 4.5:1 on the page, a card and the tinted surface.',
        'color_link' => 'The colour of links in running text, set by hand instead of the main colour.',
        'page_background_colour' => 'Any colour around a boxed page, instead of the one Around the page names.',
        'header_colour' => 'Any colour for the header bar, instead of its surface; its words are worked out from it.',
        'footer_colour' => 'Any colour for the footer, instead of its surface; its words are worked out from it.',

        // ---- type -----------------------------------------------------------------------
        'heading_font' => 'The family headings are set in, from the font library; its own weight, letter spacing, line height and capitals apply unless set.',
        'body_font' => 'The family running text is set in, from the font library; its own line height applies unless set.',
        'text_size' => 'The size of running text, in pixels; headings are measured from it by the scale.',
        'scale' => 'How much larger each heading step is than the one below it.',
        'line_height' => 'The space between lines of running text, as a multiple of its size; \'\' takes the text family\'s.',
        'heading_weight' => 'How heavy headings are, the nearest weight the heading family has; \'\' takes the family\'s.',
        'tracking' => 'The space between a heading\'s letters, in em; \'\' takes the heading family\'s.',
        'caps' => 'Whether headings are set in capitals; \'\' takes the heading family\'s.',
        'nudge_h1' => 'Pixels added to the largest heading after the scale has set it.',
        'nudge_h2' => 'Pixels added to a section\'s heading after the scale has set it.',
        'nudge_sm' => 'Pixels added to small print after the scale has set it.',

        // ---- space & shape --------------------------------------------------------------
        'spacing' => 'The unit every space inside a section is a multiple of, in rem.',
        'section_gap' => 'The room above and below each section, in pixels, unless a section sets its own.',
        'radius' => 'The corners of cards, pictures and fields, in pixels.',
        'border_width' => 'The width of every rule and outline, in pixels.',
        'shadow' => 'How cards are lifted from the page: not at all, softly, with a hard offset edge, or in layers.',
        'shadow_strength' => 'How dark the shadow is, in percent.',

        // ---- layout ---------------------------------------------------------------------
        'container' => 'How wide the content runs on a large screen, in rem; narrow and wide sections are measured from it.',
        'boxed' => 'Whether the page is a sheet standing in a background of its own.',
        'sheet_width' => 'How wide the boxed sheet is, in rem.',
        'frame' => 'The room between a boxed sheet and the window\'s edge when the window is narrower than the sheet, in rem.',
        'sheet_gap' => 'The room above and below a boxed sheet, in steps of the spacing unit.',
        'sheet_radius' => 'The corners of a boxed sheet, in pixels.',
        'sheet_shadow' => 'How a boxed sheet is lifted from its background: not at all, a shadow, or a hairline.',
        'page_background' => 'What stands around a boxed sheet: the tinted surface, the border colour, or the contrast colour.',
        'header_bleed' => 'Whether the header bar stays on a boxed sheet or runs the window\'s whole width.',
        'header_width' => 'What the header\'s contents line up with: the text, the sheet, or the window.',
        'footer_bleed' => 'Whether the footer bar stays on a boxed sheet or runs the window\'s whole width.',
        'footer_width' => 'What the footer\'s contents line up with: the text, the sheet, or the window.',

        // ---- buttons --------------------------------------------------------------------
        'button_style' => 'How a button is drawn: filled with its colour, outlined in it, or a soft tint of it.',
        'button_radius' => 'A button\'s corners, in pixels; the largest values make a pill.',
        'button_height' => 'A button\'s height, in pixels.',
        'button_caps' => 'Whether a button\'s words are set in capitals.',

        // ---- the header -----------------------------------------------------------------
        'header_arrangement' => 'Where the logo, the menu and the button stand in the header.',
        'header_behaviour' => 'Whether the header scrolls away, stays at the top, or lies over the first section.',
        'header_opacity' => 'How solid the header\'s background is, in percent, when it is sticky or lies over the first section.',
        'header_blur' => 'How much the page under a see-through header is blurred, in pixels.',
        'header_surface' => 'The header\'s background: plain, tinted, contrast or gradient.',
        'header_edge' => 'What divides the header from the page: nothing, a line, or a shadow.',
        'header_height' => 'The header\'s height on a large screen, in pixels.',
        'logo_size' => 'The logo\'s height, in pixels.',
        'brand' => 'Whether the header shows the logo, the site\'s name, or both.',
        'nav_style' => 'How the menu\'s items are drawn: plain, in capitals, as pills, on a bar, or as chips.',
        'nav_ink' => 'Whether the menu\'s words take the main colour or the text colour.',
        'header_button' => 'How the header\'s button is drawn, or none.',

        // ---- the footer -----------------------------------------------------------------
        'footer_layout' => 'How the footer is arranged: one column, centred, two columns, the menu first, or three columns.',
        'footer_columns' => 'How a long menu in a footer column is listed when the footer is in columns: 2 one list, 3 two lists side by side, 4 three.',
        'footer_links' => 'Whether a column\'s menu follows the arrangement or stands one item under another.',
        'small_print_row' => 'How the last row is laid out: stacked, split to both sides, or centred.',
        'footer_surface' => 'The footer\'s background: plain, tinted, contrast or gradient.',
        'footer_edge' => 'What divides the footer from the page above it: nothing, a line, a slant or a curve.',
    ];

    /** A section's style, in a composition's `section` and a pattern's `section.style`. */
    public const SECTION = [
        'surface' => 'The band\'s background: the page, the tinted surface, the contrast colour, a picture under a veil of it, or the gradient.',
        'width' => 'How wide the band\'s content runs: narrow (two thirds of the content width), normal, wide (seven sixths), or the window\'s.',
        'align' => 'Whether the band\'s content stands at the start or in the middle.',
        'divider' => 'What divides the band from the one above: nothing, a line, a slant or a curve.',
        'v_align' => 'Where the content stands in a band taller than it: top, middle or bottom.',
        'animation' => 'How the band\'s content arrives as it scrolls into view; never for a visitor who asked for less motion.',
        'pad_top' => 'The room above the content, in pixels; \'\' is the design\'s section gap.',
        'pad_bottom' => 'The room below the content, in pixels; \'\' is the design\'s section gap.',
        'min_height' => 'The band\'s least height, in percent of the window; 0 is as tall as its content.',
    ];
}
