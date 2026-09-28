/*
 * Reordering a menu's items by dragging.
 *
 * A sibling of pages.js, deliberately: same library, same shape, same rule that a drag
 * submits the form the buttons already submit rather than posting on its own. One
 * endpoint and one CSRF token, with nothing to keep in step between a script and a button.
 *
 * Not written as one shared file with pages.js. The two screens agree today, but merging
 * them would mean editing the page list — which works — to make room for this, and the
 * saving is about thirty lines.
 */
(function () {
  'use strict';

  // Editing an item opens its dialog in place (D-039). The Edit link still works without
  // this: it asks the server for the page with that dialog drawn open.
  document.addEventListener('click', function (event) {
    var opener = event.target.closest && event.target.closest('[data-dialog-open]');
    if (opener) {
      var dialog = document.getElementById(opener.getAttribute('data-dialog-open'));
      if (dialog && typeof dialog.showModal === 'function') {
        event.preventDefault();
        dialog.showModal();
      }
      return;
    }
    var closer = event.target.closest && event.target.closest('[data-dialog-close]');
    if (closer && closer.closest('dialog')) {
      event.preventDefault();
      closer.closest('dialog').close();
    }
  });

  var rows = document.querySelector('[data-menu-rows]');
  var form = document.querySelector('[data-menu-order]');
  if (!rows || !form || !window.Sortable) {
    return;
  }

  // An item is ordered among its siblings, never across them: dropping a child into
  // another parent's run of rows would look like re-parenting, and a menu's depth is
  // chosen in the item's own form (D-028 allows one level). A drag that crosses a group
  // is put back.
  function group(row) {
    return row ? row.getAttribute('data-menu-group') : null;
  }

  window.Sortable.create(rows, {
    handle: '[data-menu-handle]',
    draggable: 'tr[data-menu-item]',
    animation: 120,
    ghostClass: 'is-dragging',
    onMove: function (event) {
      return group(event.dragged) === group(event.related);
    },
    onEnd: function (event) {
      if (event.oldIndex === event.newIndex) {
        return;
      }
      var moved = group(event.item);
      var ids = [];
      Array.prototype.forEach.call(rows.querySelectorAll('tr[data-menu-item]'), function (row) {
        if (group(row) === moved) {
          ids.push(row.getAttribute('data-menu-item'));
        }
      });
      form.querySelector('[name="order"]').value = ids.join(',');
      form.submit();
    },
  });
})();
