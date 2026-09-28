/*
 * The characters as a panel, once the screen is too narrow to keep them as a column
 * (docs/ispravci.md §A2.4).
 *
 * THIS FILE IS WHAT MAKES THE MIDDLE WIDTH EXIST. Without it the stylesheet stacks the whole
 * screen at 74rem, because a button that opens nothing is worse than a column that has to be
 * scrolled to. It says so by putting `rail-panel-ready` on the screen: every rule that turns
 * the rail into a panel is behind that class, so the no-script layout is the honest one.
 *
 * NOTHING IS MOVED OR DUPLICATED. The rail stays where it is in the one form that wraps all
 * three columns — the form IS the layout (D-059) — and the stylesheet positions it
 * differently. A second copy of those cards would be a second set of submit buttons with the
 * same names.
 */
(function () {
  'use strict';

  var screen = document.querySelector('[data-appearance]');
  var rail = document.getElementById('appearance-rail');
  var button = document.querySelector('[data-rail-panel]');
  if (!screen || !rail || !button) {
    return;
  }
  screen.classList.add('rail-panel-ready');

  function set(open) {
    button.setAttribute('aria-expanded', String(open));
    if (open) {
      rail.setAttribute('data-open', '');
    } else {
      rail.removeAttribute('data-open');
    }
  }

  button.addEventListener('click', function (event) {
    event.stopPropagation();
    set(button.getAttribute('aria-expanded') !== 'true');
  });

  // Closed by a click outside it and by Escape, as a panel over a screen is expected to be.
  // Not by a click INSIDE it: every card there is a submit, and the page it posts is the
  // whole screen coming back anyway.
  document.addEventListener('click', function (event) {
    if (rail.hasAttribute('data-open') && !rail.contains(event.target)) {
      set(false);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && rail.hasAttribute('data-open')) {
      set(false);
      button.focus();
    }
  });
})();
