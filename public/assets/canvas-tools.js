/*
 * Inside the canvas iframe: the selected block's or band's tool bar — move, duplicate,
 * remove. Split from canvas.js at the hard size limit (PLAN.md D-117); canvas.js calls it
 * through window.bxCanvas.
 */
(function () {
  'use strict';

  var bx = window.bxCanvas;
  if (!bx) {
    return;
  }
  var main = bx.main;
  var overlay = bx.overlay;

  /**
   * Which of the bar's buttons has the keyboard, if any.
   *
   * Read BEFORE anything is removed: drawInserts() empties the whole overlay, so by the
   * time drawTools() looked for the old bar it was already gone and focus had already
   * fallen to the body. That is why the first attempt at keeping focus did not work, and
   * why this is a function rather than two lines inside drawTools().
   */
  function focusedAction() {
    var bar = overlay.querySelector('.bx-tools');
    var active = document.activeElement;

    return bar && active && bar.contains(active) ? active.getAttribute('data-block-action') : null;
  }

  /*
   * THE SELECTED BLOCK'S OWN CONTROLS (D-040): move up, move down, duplicate and remove,
   * as icons on the block's top right corner rather than a row of buttons in the panel, so
   * the block's fields start higher. Drawn in the overlay like the insertion controls, for
   * the same reason: nothing may sit between the sections. The action itself is the
   * builder's — this only says which one was pressed.
   */
  var ACTIONS = [['up', 'arrow-up'], ['down', 'arrow-down'], ['duplicate', 'copy'], ['remove', 'trash-2']];

  /**
   * KEEP THE KEYBOARD WHERE IT WAS. Every action rebuilds this bar, and rebuilding it threw
   * focus back to the document body — measured: press Move down once and the next press
   * needs the mouse. Which is also why there is no separate shortcut for reordering: the
   * button is the shortcut, once pressing it twice is possible.
   *
   * @param focused the action whose button had the keyboard, when the caller had to read it
   *                before clearing the overlay; omitted, it is read here.
   */
  function drawTools(focused) {
    var old = overlay.querySelector('.bx-tools');
    if (focused === undefined) {
      focused = focusedAction();
    }
    if (old) {
      old.remove();
    }
    /* A SELECTED BAND HAS ITS OWN FOUR (PLAN.md D-102), and they are the same four: move it,
       copy it, take it away. What differs is what they act on — a band and everything
       standing in it — so they are named apart and the builder can tell them from a block's.
       A band and a block are never both selected, so one bar is drawn either way. */
    var band = document.querySelector('.bx-band-selected');
    var list = bx.blocks();
    var index = list.findIndex(function (section) { return section.classList.contains('bx-selected'); });
    if (index < 0 && band === null) {
      return;
    }
    var bands = Array.prototype.filter.call(main.children, function (node) {
      return node.tagName === 'SECTION';
    });
    var section = index < 0 ? band : list[index];
    var prefix = index < 0 ? 'band-' : '';
    /* A BLOCK MOVES WITHIN ITS COLUMN (PLAN.md D-103), so the ends it can reach are the
       column's and not the page's. Moving it out of its column is dragging, and moving the
       whole band is the band's own pair of arrows — which is what a page of one-block bands
       now uses, every one of its blocks being alone where it stands. */
    var column = index < 0 ? null : section.parentNode;
    var of = index < 0 ? bands : Array.prototype.slice.call(column.children);
    var at = index < 0 ? bands.indexOf(band) : of.indexOf(section);
    var labels = (document.body.getAttribute('data-block-labels') || 'Move up|Move down|Duplicate|Remove').split('|');
    var sprite = document.body.getAttribute('data-icons') || '';
    var tools = document.createElement('div');
    tools.className = 'bx-tools';
    tools.setAttribute('role', 'toolbar');
    ACTIONS.forEach(function (pair, i) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'bx-tool' + (pair[0] === 'remove' ? ' bx-tool-danger' : '');
      button.setAttribute('data-block-action', prefix + pair[0]);
      button.setAttribute('aria-label', labels[i]);
      button.title = labels[i];
      // At the ends there is nowhere to move to: shown, and shown as unavailable.
      if ((pair[0] === 'up' && at === 0) || (pair[0] === 'down' && at === of.length - 1)) {
        button.disabled = true;
      }
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('aria-hidden', 'true');
      svg.setAttribute('focusable', 'false');
      var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
      use.setAttribute('href', sprite + '#i-' + pair[1]);
      svg.appendChild(use);
      button.appendChild(svg);
      tools.appendChild(button);
    });
    /*
     * ON THE BLOCK'S TOP EDGE, NOT INSIDE IT (PLAN.md D-085).
     *
     * It used to sit 12px down from the top, over the block's own first line. Measured on
     * the demo page, against the real line boxes of the text rather than the boxes of the
     * elements holding it: it covered the words of 2 of the 7 blocks — the ones whose
     * heading runs the full width. Straddling the edge puts it in the gap between blocks,
     * where the only thing under it is the boundary it belongs to.
     *
     * The first block has no gap above it, so there the bar is pushed down until it is
     * fully on the canvas rather than clipped by it. The insertion controls are centred and
     * this is at the right, so the two do not meet — measured, not assumed.
     */
    overlay.appendChild(tools);
    var height = tools.offsetHeight;
    /* MEASURED AGAINST THE OVERLAY, not read off offsetTop (D-099). A block standing in a
       column has its band as its offset parent, so its offsetTop is its distance from the
       band's top edge and not from the page's — the bar for the second column landed at the
       top of the band, over the first column's words. A band that IS its block has the same
       offset parent as the overlay, which is why this was right for as long as that was the
       only shape there was. */
    var box = overlay.getBoundingClientRect();
    var rect = section.getBoundingClientRect();
    /*
     * THE BAR GOES IN THE GAP ABOVE WHAT IT BELONGS TO — and which gap that is depends on
     * what is selected (PLAN.md D-085, D-103).
     *
     * A BAND, or a block that is FIRST in its column, straddles the BAND's top edge: that
     * is the gap between bands, which is where D-085 measured this belongs, and it is
     * exactly the geometry every page had while a block WAS a band. Reading the block's own
     * edge instead put half the bar on the first line of the first block — measured on the
     * development site, where the first band's padding is smaller than the bar.
     *
     * A BLOCK STANDING UNDER ANOTHER has no band edge near it, so it sits wholly above its
     * own, in the gap the column keeps between blocks.
     */
    var mine = index < 0 ? section : section.closest('[data-bx-section]');
    var first = index < 0 || section.previousElementSibling === null;
    var edge = first ? mine.getBoundingClientRect().top : rect.top;
    var above = first ? height / 2 : height + 2;
    tools.style.top = Math.round(Math.max(0, edge - box.top - above)) + 'px';
    tools.style.left = Math.round(rect.left - box.left + rect.width - 12) + 'px';
    if (focused !== null) {
      var again = tools.querySelector('[data-block-action="' + focused + '"]:not([disabled])');
      if (again) {
        again.focus();
      }
    }
  }

  bx.focusedAction = focusedAction;
  bx.drawTools = drawTools;
})();
