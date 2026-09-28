/*
 * The Appearance screen's five tabs (PLAN.md D-059).
 *
 * The markup ships as a row of LINKS and five panels, all on the page: without this file
 * the screen is exactly the long form it used to be, and every control is reachable. This
 * upgrades that into a real tablist — roles, one panel at a time, arrow keys — rather than
 * inventing tabs the page does not otherwise have.
 *
 * THE TAB WITH THE PROBLEM OPENS BY ITSELF. A refused publish puts its messages beside the
 * controls at fault, and a message inside a closed panel is a message nobody reads.
 */
(function () {
  'use strict';

  var tabs = document.querySelector('[data-tabs]');
  var strip = document.querySelector('[data-tab-strip]');
  if (!tabs || !strip) {
    return;
  }

  var links = [].slice.call(strip.querySelectorAll('[data-tab]'));
  var panels = {};
  [].slice.call(tabs.querySelectorAll('[data-panel]')).forEach(function (panel) {
    panels[panel.getAttribute('data-panel')] = panel;
  });
  if (links.length === 0) {
    return;
  }

  var REMEMBERED = 'boxlet.appearance.tab';

  strip.setAttribute('role', 'tablist');
  links.forEach(function (link) {
    var name = link.getAttribute('data-tab');
    link.setAttribute('role', 'tab');
    link.setAttribute('aria-controls', 'panel-' + name);
    if (panels[name]) {
      panels[name].setAttribute('role', 'tabpanel');
      panels[name].setAttribute('tabindex', '0');
    }
  });

  function show(name, moveFocus) {
    links.forEach(function (link) {
      var current = link.getAttribute('data-tab') === name;
      link.setAttribute('aria-selected', current ? 'true' : 'false');
      // One stop in the tab order for the whole strip: the arrows move between tabs, as
      // they do in every other tablist a person has used.
      link.setAttribute('tabindex', current ? '0' : '-1');
      link.classList.toggle('tab-current', current);
      if (current && moveFocus) {
        link.focus();
      }
    });
    Object.keys(panels).forEach(function (key) {
      panels[key].hidden = key !== name;
    });
    try {
      window.sessionStorage.setItem(REMEMBERED, name);
    } catch (e) {
      // Private windows and blocked storage: the tabs still work, they just forget.
    }
  }

  strip.addEventListener('click', function (event) {
    var link = event.target.closest ? event.target.closest('[data-tab]') : null;
    if (link) {
      // The href is a real anchor for the no-script case; with tabs it would jump the page.
      event.preventDefault();
      show(link.getAttribute('data-tab'), false);
    }
  });

  strip.addEventListener('keydown', function (event) {
    var step = { ArrowRight: 1, ArrowLeft: -1, Home: 'first', End: 'last' }[event.key];
    if (step === undefined) {
      return;
    }
    event.preventDefault();
    var at = links.findIndex(function (link) {
      return link.getAttribute('aria-selected') === 'true';
    });
    var next = step === 'first' ? 0
      : step === 'last' ? links.length - 1
        : (at + step + links.length) % links.length;
    show(links[next].getAttribute('data-tab'), true);
  });

  /** The first panel holding a message the owner has to act on, if there is one. */
  function panelWithAProblem() {
    var names = Object.keys(panels);
    for (var i = 0; i < names.length; i++) {
      var problem = panels[names[i]].querySelector('[role="alert"]');
      if (problem && !problem.hidden) {
        return names[i];
      }
    }
    return null;
  }

  var remembered = null;
  try {
    remembered = window.sessionStorage.getItem(REMEMBERED);
  } catch (e) {
    remembered = null;
  }
  show(panelWithAProblem() || (panels[remembered] ? remembered : links[0].getAttribute('data-tab')), false);
})();
