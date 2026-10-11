/*
 * The Appearance inspector's views (PLAN.md D-157): the home, and one section at a time.
 *
 * The markup ships as ONE COLUMN — the home, then every section under it — and the home's
 * rows are links down the page to their sections. Without this file that is the screen, and
 * every control is reachable. With it, the home and each section are views shown one at a
 * time, and the address says which: #view-header for a section, nothing for the home.
 *
 * NOT THE SECTIONS' OWN IDS. An address naming an element is one the browser scrolls to when
 * the page finishes loading, and it did: the inspector came up scrolled past the question at
 * its top (measured, 186px, after a character load). #view-* names no element, so the
 * address records the view and the browser leaves the scroll alone.
 *
 * BACK GOES HOME, NOT OFF THE SCREEN. Opening a section from the home pushes one entry, so
 * the browser's Back returns to the home; the section's own "All settings" link goes back
 * through that entry rather than adding another. A section restored on load is pushed over a
 * home entry for the same reason.
 *
 * WHICH SECTION A LOAD RESTORES. Every button on this screen posts it, and the answer is the
 * whole screen again; the owner who pressed a reset in Header expects to be in Header after
 * it. So the view is noted as the form is sent, and taken back once by the page that answers.
 * Arriving any other way — from the admin's menu, a link, a bookmark — opens the home.
 *
 * THE SECTION WITH THE PROBLEM OPENS BY ITSELF. A refused publish puts its messages beside
 * the controls at fault, and a message inside a view that is not shown is a message nobody
 * reads.
 */
(function () {
  'use strict';

  var inspector = document.querySelector('[data-inspector]');
  if (!inspector) {
    return;
  }
  var views = {};
  [].slice.call(inspector.querySelectorAll('[data-view]')).forEach(function (view) {
    views[view.getAttribute('data-view')] = view;
  });
  if (!views.home) {
    return;
  }
  var RETURN = 'boxlet.appearance.return';
  var form = document.querySelector('form[data-design-form]');
  // Whether the entry before this one is the home this script pushed from.
  var pushedFromHome = false;
  var current = null;

  /** The view the last post was sent from, once: read and forgotten. */
  function returning() {
    try {
      var name = window.sessionStorage.getItem(RETURN);
      window.sessionStorage.removeItem(RETURN);
      return name;
    } catch (e) {
      // Private windows and blocked storage: the views still work, the screen opens home.
      return null;
    }
  }

  /**
   * The view an address names; null for one that names none, such as an old #panel-*. The
   * sections' own anchors (#section-header) are read too: they are what the links are
   * without a script, and an address somebody kept.
   */
  function fromHash() {
    var hash = window.location.hash.replace(/^#/, '');
    if (hash === 'appearance-home') {
      return 'home';
    }
    var match = /^(?:view|section)-([a-z]+)$/.exec(hash);
    return match && views[match[1]] ? match[1] : null;
  }

  function show(name) {
    if (!views[name]) {
      name = 'home';
    }
    Object.keys(views).forEach(function (key) {
      views[key].hidden = key !== name;
    });
    inspector.setAttribute('data-showing', name);
    if (current !== null && current !== name) {
      inspector.scrollTop = 0;
    }
    current = name;
    // For whatever else wants to know which part of the design is being edited: the
    // preview's outline, later (the redesign's phase 4).
    document.dispatchEvent(new CustomEvent('appearance:section', { detail: { section: name } }));
  }

  function address(name) {
    return name === 'home' ? window.location.pathname + window.location.search : '#view-' + name;
  }

  /** The first view holding a message the owner has to act on, if there is one. */
  function viewWithAProblem() {
    var names = Object.keys(views);
    for (var i = 0; i < names.length; i++) {
      var alerts = views[names[i]].querySelectorAll('[role="alert"]');
      for (var j = 0; j < alerts.length; j++) {
        if (!alerts[j].hidden) {
          return names[i];
        }
      }
    }
    return null;
  }

  inspector.addEventListener('click', function (event) {
    var link = event.target.closest ? event.target.closest('a[data-open-section], a[data-back]') : null;
    if (!link || event.metaKey || event.ctrlKey || event.shiftKey) {
      return;
    }
    event.preventDefault();
    if (link.hasAttribute('data-back')) {
      if (pushedFromHome) {
        // The entry before this one IS the home: go back to it rather than add a third.
        window.history.back();
        return;
      }
      window.history.replaceState(null, '', address('home'));
      show('home');
      return;
    }
    var name = link.getAttribute('data-open-section');
    window.history.pushState(null, '', address(name));
    pushedFromHome = current === 'home';
    show(name);
  });

  function followAddress() {
    var name = fromHash() || 'home';
    if (name === 'home') {
      pushedFromHome = false;
    }
    show(name);
  }
  window.addEventListener('popstate', followAddress);
  window.addEventListener('hashchange', followAddress);

  if (form) {
    form.addEventListener('submit', function () {
      try {
        window.sessionStorage.setItem(RETURN, current || 'home');
      } catch (e) {
        // Nothing to note it in: the answer opens on the home.
      }
    });
  }

  /** For the search (appearance-search.js): the view the address says, or a section opened. */
  window.boxletAppearanceViews = {
    restore: function () { show(current || 'home'); },
    open: function (name) {
      window.history.pushState(null, '', address(name));
      pushedFromHome = current === 'home';
      show(name);
    },
  };

  inspector.classList.add('views-ready');
  var first = viewWithAProblem() || fromHash() || returning();
  if (!views[first]) {
    first = 'home';
  }
  if (first === 'home') {
    window.history.replaceState(null, '', address('home'));
  } else {
    // Over a home entry, so Back from here lands on the home and not on the page before.
    window.history.replaceState(null, '', address('home'));
    window.history.pushState(null, '', address(first));
    pushedFromHome = true;
  }
  show(first);
})();
