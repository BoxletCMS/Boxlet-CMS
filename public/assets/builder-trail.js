/*
 * Where you are, in words, under the canvas — and the Section tab named for the band it
 * opens (PLAN.md D-102, D-108). Split from builder.js at the hard size limit (D-117).
 */
(function () {
  'use strict';

  var api = window.boxletBuilder;
  if (!api) {
    return;
  }
  var form = api.form;

  /**
   * WHERE YOU ARE, in words (PLAN.md D-102): Section 2 › Column 1 › Text.
   *
   * A page used to be a list of blocks, and the block under the cursor said everything there
   * was to say about where it stood. On a page of bands and columns it does not: the same
   * block can be the whole of one band or one of four things in another, and nothing on the
   * screen said which. The column is named only where there is more than one, because
   * "Column 1" under a band of one is a level of nothing — the same rule the outline follows.
   */
  /** The Section tab's word: the band it would open, or its plain name when there is none. */
  var sectionTab = form.querySelector('[data-panel-tab="section"]');
  var sectionTabWord = sectionTab ? sectionTab.textContent : '';

  function nameSectionTab(band) {
    if (sectionTab) {
      sectionTab.textContent = band === null ? sectionTabWord : band;
    }
  }

  api.trail = function (index, bandKey) {
    var strip = form.querySelector('[data-trail]');
    if (!strip) {
      return;
    }
    var group = index >= 0 ? api.groups.querySelector('[data-block-group="' + index + '"]') : null;
    var key = bandKey || (group ? group.getAttribute('data-section-key') : null);
    if (key === null) {
      strip.hidden = true;
      strip.textContent = '';
      nameSectionTab(null);

      return;
    }
    var at = 0;
    var bands = api.bands();
    bands.forEach(function (band, n) {
      if (band.getAttribute('data-bx-section') === key) {
        at = n;
      }
    });
    var parts = [(form.getAttribute('data-text-band') || 'Section') + ' ' + (at + 1)];
    /* AND THE TAB SAYS WHOSE SETTINGS IT OPENS (PLAN.md D-108). Selecting a BLOCK shows the
       fields of the BAND it stands in — which is the point of one group per band, and what
       the design artifact does too — but with the tab reading plain "Section" nothing said
       that changing Surface there would repaint every other block in that band. The trail
       under the canvas said `Section 2 › Text`, at the opposite end of the screen from the
       controls being pressed. The tab carries the same name the trail does, from the same
       line, so the two cannot drift apart. */
    nameSectionTab(parts[0]);
    if (group) {
      var band = bands[at];
      var columns = band ? band.querySelectorAll('.section-column').length : 0;
      if (columns > 1) {
        var column = Number((group.querySelector('[data-block-column]') || {}).value || 0);
        parts.push((form.getAttribute('data-text-column') || 'Column') + ' ' + (column + 1));
      }
      var labelled = group.querySelector('[data-block-label]');
      if (labelled) {
        parts.push(labelled.getAttribute('data-block-label'));
      }
    }
    strip.hidden = false;
    strip.textContent = '';
    parts.forEach(function (part, n) {
      if (n > 0) {
        strip.appendChild(document.createTextNode(' \u203A '));
      }
      // The last part is what is selected; the ones before it are where it stands.
      var piece = n === parts.length - 1 ? document.createElement('strong') : document.createElement('span');
      piece.textContent = part;
      strip.appendChild(piece);
    });
  };
})();
