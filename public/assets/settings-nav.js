/*
 * The Settings screen's list of sections (PLAN.md D-052): marks the section in view as the
 * current one. The links work without it; this only says where you are.
 *
 * The current section is the topmost one beginning between the bar and a third of the way down
 * the screen; when none begins there, the one running under the bar. The first rule, "the
 * topmost section crossing a band of the screen", marked the one ABOVE the section filling the
 * screen whenever a strip of its foot still showed under the bar (D-227). "The last one
 * beginning in the upper third" marked the one below a short section at the top. The bar's
 * line is where a link lands a section (its scroll-margin-top), and a section a link has just
 * opened is current. At the foot of the page a short last section cannot rise that far, so
 * there the section the address names is current, if it is in view, or else the last.
 */
(function () {
  'use strict';

  var nav = document.querySelector('[data-settings-nav]');
  if (!nav) {
    return;
  }
  var links = {};
  var sections = [];
  Array.prototype.forEach.call(nav.querySelectorAll('a[href^="#"]'), function (link) {
    var id = link.getAttribute('href').slice(1);
    var section = document.getElementById(id);
    if (section) {
      links[id] = link;
      sections.push(section);
    }
  });
  if (sections.length === 0) {
    return;
  }

  function mark(id) {
    Object.keys(links).forEach(function (key) {
      if (key === id) {
        links[key].setAttribute('aria-current', 'true');
      } else {
        links[key].removeAttribute('aria-current');
      }
    });
  }

  function inView(section) {
    var box = section.getBoundingClientRect();
    return box.top < window.innerHeight && box.bottom > 0;
  }

  function current() {
    var root = document.documentElement;
    var named = document.getElementById(window.location.hash.slice(1));
    named = named && links[named.id] ? named : null;
    if (named) {
      var line = parseFloat(window.getComputedStyle(named).scrollMarginTop) || 0;
      if (Math.abs(named.getBoundingClientRect().top - line) <= 4) {
        return named.id;
      }
    }
    if (window.scrollY + window.innerHeight >= root.scrollHeight - 2) {
      return named && inView(named) ? named.id : sections[sections.length - 1].id;
    }
    var line = parseFloat(window.getComputedStyle(sections[0]).scrollMarginTop) || 0;
    var id = sections[0].id;
    var begins = null;
    sections.forEach(function (section) {
      var top = section.getBoundingClientRect().top;
      if (top <= line + 4) {
        id = section.id;
      } else if (begins === null && top <= window.innerHeight / 3) {
        begins = section.id;
      }
    });
    return begins !== null ? begins : id;
  }

  var pending = false;
  function update() {
    if (pending) {
      return;
    }
    pending = true;
    window.requestAnimationFrame(function () {
      pending = false;
      mark(current());
    });
  }

  window.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', update);
  window.addEventListener('hashchange', update);
  mark(current());
})();
