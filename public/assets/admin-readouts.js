/*
 * A READOUT THAT DOES NOT FIT BESIDE ITS NAME HAS A LINE OF ITS OWN (PLAN.md D-196, the owner).
 * "Picture behind, words low on the left" beside "Layout" ran onto a second line against the
 * row's end, beside the reset; it now stands on the line under the name, from the left.
 *
 * Measured, not guessed: the row is laid out once at its natural width, the readout on one
 * line, and when that is wider than the row the readout is marked .readout-below. Again when
 * the window changes size and when words change on the page (a readout rewritten, an
 * inspector drawn anew). Without a script the readout runs onto a second line in its place.
 */
(function () {
  'use strict';

  function place(readout) {
    var row = readout.parentElement;
    if (!row || row.getClientRects().length === 0) {
      return;
    }
    readout.classList.remove('readout-below');
    var room = row.getBoundingClientRect().width;
    readout.classList.add('readout-measure');
    row.style.setProperty('inline-size', 'max-content');
    var natural = row.getBoundingClientRect().width;
    row.style.removeProperty('inline-size');
    readout.classList.remove('readout-measure');
    if (natural > room + 0.5) {
      readout.classList.add('readout-below');
    }
  }

  var queued = false;
  function placeAll() {
    queued = false;
    Array.prototype.forEach.call(document.querySelectorAll('.control-head > .readout, .field-row > .readout'), place);
  }
  function soon() {
    if (!queued) {
      queued = true;
      window.requestAnimationFrame(placeAll);
    }
  }

  window.addEventListener('resize', soon);
  // Words, not classes: the marks this sets are attributes, so it never wakes itself.
  new MutationObserver(soon).observe(document.documentElement, { childList: true, subtree: true, characterData: true });
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(soon);
  }
  soon();
})();
