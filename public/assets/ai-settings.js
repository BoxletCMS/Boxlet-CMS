/*
 * The AI translation panel (PLAN.md D-255): the field for another model's ID is shown only
 * while "Another model…" is chosen. Without this script the field is always there, and its
 * hint says when it counts.
 */
(function () {
  'use strict';

  var select = document.querySelector('[data-ai-model]');
  var other = document.querySelector('[data-ai-other]');
  if (!select || !other) {
    return;
  }

  function show() {
    other.hidden = select.value !== 'other';
  }

  select.addEventListener('change', show);
  show();
})();
