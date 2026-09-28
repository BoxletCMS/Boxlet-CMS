/*
 * A row's menu (<details data-menu>, D-052): the "…" at the end of a row in Pages, Media
 * and the other lists. Split from admin.js when this grew past closing on a click.
 *
 * IT FLOATS OVER THE PAGE, NOT INSIDE ITS TABLE (the owner, 2026-09-27). The table sits in
 * .table-wrap, which scrolls sideways on a phone, and a box that scrolls one way clips the
 * other: the menu of a row near the bottom opened inside the table, the table grew a
 * scrollbar, and the menu was cut off under it. Opened, the list is placed against the
 * window from where its button is — below it, or above when there is no room below — and
 * it closes when the page scrolls or the window changes, as a menu is expected to.
 *
 * Without a script the list opens below its button as the stylesheet places it, and closes
 * by its own summary: every action in it is a link or a form that works either way.
 */
(function () {
  'use strict';

  var GAP = 4;
  var EDGE = 8;

  function openMenus() {
    return document.querySelectorAll('details[data-menu][open]');
  }

  function close(menu) {
    menu.open = false;
  }

  function place(menu) {
    var list = menu.querySelector('.row-menu-list');
    var button = menu.querySelector('summary');
    if (!list || !button) {
      return;
    }
    list.classList.add('is-floating');
    var anchor = button.getBoundingClientRect();
    var width = list.offsetWidth;
    var height = list.offsetHeight;
    // The window's bottom, or the top of the maintenance bar where one lies over it
    // (UpdateGate::bar): a list placed under that bar is a list half hidden.
    var floor = window.innerHeight;
    var bar = document.querySelector('.boxlet-maintenance-bar');
    if (bar && bar.getBoundingClientRect().height > 0) {
      floor = Math.min(floor, bar.getBoundingClientRect().top);
    }
    var below = floor - anchor.bottom - GAP - EDGE;
    var top = below >= height || anchor.top < height + GAP + EDGE
      ? anchor.bottom + GAP
      : anchor.top - GAP - height;
    // Its right edge under the button's, as before, and never past either side.
    var left = Math.min(Math.max(EDGE, anchor.right - width), window.innerWidth - width - EDGE);
    list.style.top = Math.round(top) + 'px';
    list.style.left = Math.round(left) + 'px';
  }

  // 'toggle' does not bubble; caught on the way down instead.
  document.addEventListener('toggle', function (event) {
    var menu = event.target;
    if (!(menu instanceof HTMLDetailsElement) || !menu.hasAttribute('data-menu')) {
      return;
    }
    if (menu.open) {
      Array.prototype.forEach.call(openMenus(), function (other) {
        if (other !== menu) {
          close(other);
        }
      });
      place(menu);
    }
  }, true);

  // Closes on a click anywhere else and on Escape.
  document.addEventListener('click', function (event) {
    Array.prototype.forEach.call(openMenus(), function (menu) {
      if (!menu.contains(event.target)) {
        close(menu);
      }
    });
  });
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
      return;
    }
    Array.prototype.forEach.call(openMenus(), function (menu) {
      close(menu);
      menu.querySelector('summary').focus();
    });
  });

  // A floating list would be left behind by its row: the page scrolling, the table scrolling
  // sideways, or the window changing closes it instead.
  function closeAll() {
    Array.prototype.forEach.call(openMenus(), close);
  }
  window.addEventListener('scroll', closeAll, true);
  window.addEventListener('resize', closeAll);
})();
