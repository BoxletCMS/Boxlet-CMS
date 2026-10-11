/*
 * THE TICKED ROWS (PLAN.md D-218): the bar above Pages' and Media's lists (Admin/views/bulk.php)
 * says how many rows are ticked, keeps Delete off while none is, and asks with the count;
 * the tick in the head ticks every row of the list, or none.
 *
 * Without it the bar still works: the ticks are real checkboxes of the bar's form, and Delete
 * asks the general question admin.js asks of every data-confirm.
 */
(function () {
  'use strict';

  Array.prototype.forEach.call(document.querySelectorAll('form[data-bulk]'), function (form) {
    var id = form.id;
    var picks = function () {
      return Array.prototype.slice.call(document.querySelectorAll('input[data-bulk-pick][form="' + id + '"]'));
    };
    var all = document.querySelector('input[data-bulk-all][form="' + id + '"]');
    var count = form.querySelector('[data-bulk-count]');
    var button = form.querySelector('[data-bulk-delete]');

    function update() {
      var boxes = picks();
      var ticked = boxes.filter(function (box) { return box.checked; }).length;
      count.textContent = ticked === 0 ? count.getAttribute('data-none') : count.getAttribute('data-some').replace(':count', String(ticked));
      button.disabled = ticked === 0;
      button.setAttribute('data-confirm', button.getAttribute('data-confirm-some').replace(':count', String(ticked)));
      if (all) {
        all.checked = ticked > 0 && ticked === boxes.length;
        all.indeterminate = ticked > 0 && ticked < boxes.length;
      }
    }

    document.addEventListener('change', function (event) {
      var target = event.target;
      if (target === all) {
        picks().forEach(function (box) { box.checked = all.checked; });
      }
      if (target === all || (target.matches && target.matches('input[data-bulk-pick][form="' + id + '"]'))) {
        update();
      }
    });
    // Back to the list from the page it led to, the browser may keep what was ticked.
    window.addEventListener('pageshow', update);
    update();
  });
})();
