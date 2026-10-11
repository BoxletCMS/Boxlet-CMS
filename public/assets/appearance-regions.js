/*
 * PRESSING THE PICTURE OPENS ITS SETTINGS (PLAN.md D-181, README 5.7). The preview marks its
 * wrappers only — the header, each band of the page, the footer (AppearancePreview::region(),
 * in Appearance's preview alone) — and pressing one opens the section of settings it answers to:
 *
 *   - the header: Header; the footer: Footer;
 *   - in a band, a button: Buttons; a heading: Typography; anything else: Space & shape, where
 *     the room between the bands and their corners are;
 *   - the page around them (a boxed page's margin): Layout & widths.
 *
 * Nothing in the picture is followed while it is pressed: a link opens settings, not a page.
 * The part pressed keeps a solid outline while its section is open; the one under the pointer
 * has a dashed one (canvas-regions.css).
 */
(function () {
  'use strict';

  var preview = document.querySelector('[data-design-preview]');
  var views = window.boxletAppearanceViews;
  if (!preview || !views) {
    return;
  }
  var hint = document.querySelector('[data-regions-hint]');
  if (hint) { hint.hidden = false; }
  var pressed = null;

  function sectionFor(target) {
    var region = target.closest('[data-bx-region]');
    if (!region) {
      return { name: 'layout', region: null };
    }
    var kind = region.getAttribute('data-bx-region');
    if (kind === 'header' || kind === 'footer') {
      return { name: kind, region: region };
    }
    if (target.closest('.button, .btn, button')) {
      return { name: 'buttons', region: region };
    }
    if (target.closest('h1, h2, h3, h4')) {
      return { name: 'typography', region: region };
    }
    return { name: 'space', region: region };
  }

  function mark(name) {
    var doc = preview.contentDocument;
    if (!doc) { return; }
    [].forEach.call(doc.querySelectorAll('[data-bx-open]'), function (n) { n.removeAttribute('data-bx-open'); });
    if (pressed && pressed.name === name && pressed.region && pressed.region.isConnected) {
      pressed.region.setAttribute('data-bx-open', '');
    }
  }

  function press(event) {
    var found = sectionFor(event.target);
    event.preventDefault();
    event.stopPropagation();
    // A search in progress gives way to the section asked for.
    var search = document.querySelector('[data-search] input');
    if (search && search.value !== '') {
      search.value = '';
      search.dispatchEvent(new Event('input'));
    }
    pressed = found;
    views.open(found.name);
    mark(found.name);
  }

  function listen() {
    var doc = preview.contentDocument;
    if (!doc || doc.__boxletRegions) { return; }
    doc.__boxletRegions = true;
    doc.addEventListener('click', press, true);
    // A region found again after the picture is drawn anew keeps its outline.
    if (pressed && pressed.region && !pressed.region.isConnected) {
      pressed = null;
    }
  }
  preview.addEventListener('load', listen);
  listen();
  document.addEventListener('appearance:section', function (event) {
    mark(event.detail && event.detail.section);
  });
})();
