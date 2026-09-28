/*
 * The Mail panel (PLAN.md D-045): shows the fields of the way of sending that is chosen and
 * hides the others. Without this script every field is on the page, grouped under its way,
 * and the panel works the same.
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-mail-settings]');
  if (!form) {
    return;
  }
  var select = form.querySelector('[data-mail-transport]');
  var groups = form.querySelectorAll('[data-mail-group]');

  function show() {
    Array.prototype.forEach.call(groups, function (group) {
      group.hidden = group.getAttribute('data-mail-group') !== select.value;
    });
  }

  select.addEventListener('change', show);
  show();
})();
