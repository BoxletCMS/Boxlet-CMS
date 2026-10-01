/*
 * An SVG logo is sent the moment it is chosen (PLAN.md D-142), as a picture is in the media
 * library (D-038): the browser's own file control is hidden behind a label in the admin's
 * words, and a second press on an Upload button would be a step with nothing to decide.
 * Without this script the Upload button beside it does the same.
 */
(function () {
  'use strict';

  Array.prototype.forEach.call(document.querySelectorAll('[data-logo-svg]'), function (input) {
    input.addEventListener('change', function () {
      if (input.files && input.files.length > 0 && input.form) {
        var label = document.querySelector('label[for="' + input.id + '"]');
        if (label) {
          label.setAttribute('aria-busy', 'true');
        }
        input.form.submit();
      }
    });
  });
})();
