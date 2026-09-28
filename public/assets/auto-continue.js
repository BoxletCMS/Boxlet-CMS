/*
 * A job that arrives in pieces, pressing its own Continue (PLAN.md D-048, D-055): while a
 * piece is still owed, press the button, so the whole pass runs without the owner clicking
 * through it. Each press is one ordinary request that does what fits in its time; leaving
 * the page simply stops, and coming back carries on.
 *
 * Two screens do this now — making every picture's sizes again, and fetching the city
 * database eight megabytes at a time — so the mark is what the form carries rather than
 * what it is for. Without this script both are a button the owner presses.
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-auto-continue]');
  if (!form) {
    return;
  }
  var button = form.querySelector('button');
  window.setTimeout(function () {
    if (button) {
      button.disabled = true;
    }
    form.submit();
  }, 600);
})();
