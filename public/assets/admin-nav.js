/*
 * The rail (PLAN.md D-052): folded behind the strip's menu button on a phone and opened as a
 * drawer; elsewhere, folded to its icons or opened again with the toggle on its edge.
 *
 * Optional, like everything the admin's scripts do. Without it the rail is a plain list
 * above the content on a phone, open on a wide screen, icons alone under 1000px and in the
 * page editor, and every screen is still one link away.
 */
(function () {
  'use strict';

  var frame = document.querySelector('[data-admin-frame]');
  if (!frame) {
    return;
  }
  var rail = frame.querySelector('[data-admin-rail]');

  // ---- the phone's drawer ----------------------------------------------------------------
  var toggle = frame.querySelector('[data-admin-nav-toggle]');
  if (toggle) {
    frame.classList.add('nav-ready');
    toggle.hidden = false;

    var set = function (open) {
      toggle.setAttribute('aria-expanded', String(open));
      frame.classList.toggle('nav-open', open);
    };

    toggle.addEventListener('click', function (event) {
      event.stopPropagation();
      set(toggle.getAttribute('aria-expanded') !== 'true');
    });

    // Closed by a click outside it, or by Escape, as a drawer is expected to be.
    document.addEventListener('click', function (event) {
      if (frame.classList.contains('nav-open') && rail && !rail.contains(event.target)) {
        set(false);
      }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && frame.classList.contains('nav-open')) {
        set(false);
        toggle.focus();
      }
    });
  }

  // ---- folding the rail to its icons -----------------------------------------------------
  var fold = frame.querySelector('[data-rail-toggle]');
  if (!fold) {
    return;
  }
  var label = fold.querySelector('[data-rail-toggle-label]');
  var narrow = window.matchMedia('(max-width: 62.5rem)');
  // The page editor opens folded whatever was chosen elsewhere; a change there is for that
  // screen alone and is not remembered.
  var editor = frame.hasAttribute('data-rail-editor');

  // A screen that fills the window for itself (Appearance, the page editor) has no room for a
  // wide rail: opening it would lay a fourth column over the three already there, and because
  // nothing here is remembered in the cookie the next load would silently undo it. The rail
  // stays on its icons, and the button that could undo that does not exist.
  if (editor) {
    fold.hidden = true;
    return;
  }

  function folded() {
    return frame.classList.contains('rail-compact') || (narrow.matches && !frame.classList.contains('rail-wide'));
  }

  function show() {
    var isFolded = folded();
    var said = fold.getAttribute(isFolded ? 'data-open' : 'data-fold');
    fold.setAttribute('aria-expanded', String(!isFolded));
    fold.title = said;
    if (label) {
      label.textContent = said;
    }
  }

  fold.hidden = false;
  show();
  narrow.addEventListener('change', show);

  fold.addEventListener('click', function () {
    var foldNow = !folded();
    frame.classList.toggle('rail-compact', foldNow);
    frame.classList.toggle('rail-wide', !foldNow);
    if (!editor) {
      // Read by the server (AdminView), so the next page is drawn folded or open from the
      // start. Only the admin's own pages, only this browser.
      document.cookie = 'boxlet_rail=' + (foldNow ? 'compact' : 'wide') + '; path=/admin; max-age=31536000; samesite=strict';
    }
    show();
  });
})();
