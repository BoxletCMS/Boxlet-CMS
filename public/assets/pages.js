/*
 * Dragging a page in the page list: up and down picks its place, left and right its level
 * (PLAN.md D-133, reversing D-011's "never by dragging" on purpose).
 *
 * The arrows beside it already do all of this without a script, and they are what a
 * keyboard uses. The drag only works out where the page was dropped — under which page,
 * and where among the pages there — and submits the same request → and ← and Undo make.
 * The server holds every rule (PagePlacing) and answers a refusal in words.
 *
 * WHILE A PAGE IS DRAGGED its subpages are folded away, because they go with it; the row
 * shows the level it will land at, and the page it will go under.
 *
 * SortableJS rather than native drag and drop, for the same reason the builder's tree uses it: it
 * handles touch, and a tablet is a real case for this screen. forceFallback, so the pointer
 * is reported the same way on every browser, which the level is read from.
 */
(function () {
  'use strict';

  var rows = document.querySelector('[data-page-rows]');
  var form = document.querySelector('[data-page-place]');
  if (!rows || !form || !window.Sortable) {
    return;
  }

  var MAX_LEVELS = parseInt(rows.getAttribute('data-max-levels'), 10) || 3;
  var STEP = 28; // pixels of sideways movement per level, about the indent of one level
  var startX = 0;
  var pointerX = 0;
  var landing = null; // {depth, parent, title} for the row being dragged, kept up to date

  function all() {
    return Array.prototype.slice.call(rows.querySelectorAll('tr[data-page-id]'));
  }
  function depthOf(row) {
    return parseInt(row.getAttribute('data-depth'), 10) || 0;
  }
  function idOf(row) {
    return row.getAttribute('data-page-id');
  }

  // Everything below a row that is deeper than it: its subpages, which move with it.
  function descendants(row) {
    var list = all();
    var at = list.indexOf(row);
    var found = [];
    for (var i = at + 1; i < list.length && depthOf(list[i]) > depthOf(row); i++) {
      found.push(list[i]);
    }
    return found;
  }

  // The visible rows around the dragged one, in its own language.
  function neighbours(dragged) {
    var list = all().filter(function (row) {
      return row === dragged || !row.hidden;
    });
    var at = list.indexOf(dragged);
    var locale = dragged.getAttribute('data-locale');
    var above = at > 0 && list[at - 1].getAttribute('data-locale') === locale ? list[at - 1] : null;
    var below = at < list.length - 1 && list[at + 1].getAttribute('data-locale') === locale ? list[at + 1] : null;
    return { list: list, at: at, above: above, below: below };
  }

  // Where the page lands: its level from how far it was moved sideways, held between what
  // the rows around it allow; then the page it goes under, found by walking up from the row
  // above to the first one a level higher.
  function work(dragged) {
    var around = neighbours(dragged);
    var wanted = parseInt(dragged.getAttribute('data-start-depth'), 10) + Math.round((pointerX - startX) / STEP);
    var deepest = around.above ? depthOf(around.above) + 1 : 0;
    // A home page takes no subpages (D-133, B).
    if (around.above && around.above.hasAttribute('data-home') && deepest > depthOf(around.above)) {
      deepest = depthOf(around.above);
    }
    deepest = Math.min(deepest, MAX_LEVELS - 1);
    var shallowest = around.below ? Math.min(depthOf(around.below), deepest) : 0;
    var depth = Math.max(shallowest, Math.min(deepest, wanted));

    var parent = null;
    var position = 0;
    for (var i = around.at - 1; i >= 0; i--) {
      var row = around.list[i];
      if (row.getAttribute('data-locale') !== dragged.getAttribute('data-locale')) {
        break;
      }
      var d = depthOf(row);
      if (d === depth) {
        position++;
      }
      if (d < depth) {
        parent = row;
        break;
      }
    }
    return {
      depth: depth,
      parent: parent ? idOf(parent) : '',
      title: parent ? parent.getAttribute('data-title') : '',
      position: position,
    };
  }

  function show(dragged) {
    landing = work(dragged);
    var cell = dragged.querySelector('.page-name');
    cell.className = cell.className.replace(/\bdepth-\d+\b/, '').trim() + ' depth-' + landing.depth;
    var hint = dragged.querySelector('[data-landing]');
    if (hint) {
      hint.textContent = landing.parent
        ? hint.getAttribute('data-under').replace(':parent', landing.title)
        : hint.getAttribute('data-top');
    }
  }

  function track(event) {
    var point = event.touches ? event.touches[0] : event;
    if (point) {
      pointerX = point.clientX;
    }
  }

  window.Sortable.create(rows, {
    handle: '[data-page-handle]',
    draggable: 'tr[data-page-id]',
    animation: 120,
    forceFallback: true,
    // The copy that follows the pointer is a lone <tr>, whose cells fold up outside their
    // table; it is hidden, and the row in its place shows the place and the level.
    fallbackClass: 'page-drag-follower',
    ghostClass: 'is-dragging',
    // Never into another language's rows: a page goes under a page of its own language.
    onMove: function (event) {
      return !event.related || event.related.getAttribute('data-locale') === event.dragged.getAttribute('data-locale');
    },
    onStart: function (event) {
      var row = event.item;
      var point = event.originalEvent && (event.originalEvent.touches ? event.originalEvent.touches[0] : event.originalEvent);
      startX = pointerX = point ? point.clientX : 0;
      row.setAttribute('data-start-depth', String(depthOf(row)));
      descendants(row).forEach(function (child) {
        child.hidden = true;
        child.setAttribute('data-folded', '');
      });
      row.classList.add('is-placing');
      document.addEventListener('mousemove', track);
      document.addEventListener('touchmove', track, { passive: true });
      document.addEventListener('mousemove', follow);
      document.addEventListener('touchmove', follow, { passive: true });
      show(row);
    },
    onChange: function (event) {
      show(event.item);
    },
    onEnd: function (event) {
      var row = event.item;
      dragging = null;
      document.removeEventListener('mousemove', track);
      document.removeEventListener('touchmove', track);
      document.removeEventListener('mousemove', follow);
      document.removeEventListener('touchmove', follow);
      show(row);
      var moved = landing.parent !== (row.getAttribute('data-parent') || '') || event.oldIndex !== event.newIndex;
      if (!moved) {
        row.classList.remove('is-placing');
        Array.prototype.forEach.call(rows.querySelectorAll('[data-folded]'), function (child) {
          child.hidden = false;
          child.removeAttribute('data-folded');
        });
        return;
      }
      form.action = row.getAttribute('data-place');
      form.querySelector('[name="parent"]').value = landing.parent;
      form.querySelector('[name="position"]').value = String(landing.position);
      form.submit();
    },
  });

  var dragging = null;
  function follow() {
    dragging = dragging || rows.querySelector('tr.is-placing');
    if (dragging) {
      show(dragging);
    }
  }
})();
