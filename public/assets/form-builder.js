/*
 * A form's edit screen (PLAN.md D-046): shows a field's list of choices only when its kind
 * is a list. Without this script the choices box is always there, with a hint saying it is
 * used only by a list; every button works either way.
 */
(function () {
  'use strict';

  Array.prototype.forEach.call(document.querySelectorAll('[data-form-field]'), function (field) {
    var type = field.querySelector('[data-field-type]');
    var options = field.querySelector('[data-field-options]');
    if (!type || !options) {
      return;
    }
    function show() {
      options.hidden = type.value !== 'select';
    }
    type.addEventListener('change', show);
    show();
  });
})();
