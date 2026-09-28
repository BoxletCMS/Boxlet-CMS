/*
 * The Settings screen's list of sections (PLAN.md D-052): marks the section in view as the
 * current one. The links work without it; this only says where you are.
 */
(function () {
  'use strict';

  var nav = document.querySelector('[data-settings-nav]');
  if (!nav || !('IntersectionObserver' in window)) {
    return;
  }
  var links = {};
  Array.prototype.forEach.call(nav.querySelectorAll('a[href^="#"]'), function (link) {
    links[link.getAttribute('href').slice(1)] = link;
  });

  function mark(id) {
    Object.keys(links).forEach(function (key) {
      if (key === id) {
        links[key].setAttribute('aria-current', 'true');
      } else {
        links[key].removeAttribute('aria-current');
      }
    });
  }

  // The topmost section whose top has passed under the strip is the current one.
  var visible = {};
  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      visible[entry.target.id] = entry.isIntersecting;
    });
    var first = Object.keys(links).filter(function (id) { return visible[id]; })[0];
    if (first) {
      mark(first);
    }
  }, { rootMargin: '-60px 0px -55% 0px' });

  Object.keys(links).forEach(function (id) {
    var section = document.getElementById(id);
    if (section) {
      observer.observe(section);
    }
  });
  mark(Object.keys(links)[0]);
})();
