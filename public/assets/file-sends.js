/*
 * A file is sent the moment it is chosen, as a picture is in the media library (D-038): the
 * browser's own file control is hidden behind a label in the admin's words, and a second
 * press on a send button would be a step with nothing to decide. Without this script the
 * button beside it (no-js-only) does the same.
 *
 * For any input marked data-file-sends, which sends the form it belongs to — by its form
 * attribute, where it stands inside another one. Two use it: the SVG logo (D-142) and a
 * design file on the Appearance screen (D-152); it was logo-svg.js until the second.
 */
(function () {
  'use strict';

  Array.prototype.forEach.call(document.querySelectorAll('[data-file-sends]'), function (input) {
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
