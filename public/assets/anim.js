/*
 * Sections that arrive as they are scrolled to (PLAN.md D-165): data-anim="fade|up|zoom" on a
 * section, set by its owner or its character.
 *
 * VISIBLE WITHOUT THIS FILE. Nothing is hidden by the stylesheet until this script has added
 * `anim-ready` to <html>, so a page whose script never runs — blocked, failed, or a reader
 * that does not run one — shows every section as it is. And nothing moves for a visitor who
 * has asked for less motion: the script stops before it adds the class.
 */
(function () {
  'use strict';

  var sections = document.querySelectorAll('[data-anim]');
  if (!sections.length || !('IntersectionObserver' in window)) {
    return;
  }
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('anim-in');
        observer.unobserve(entry.target);
      }
    });
  }, { rootMargin: '0px 0px -10% 0px' });

  // A section already on screen when the page opens is shown at once, not animated in: the
  // first thing a visitor sees should not be something arriving.
  var fold = window.innerHeight || document.documentElement.clientHeight;
  Array.prototype.forEach.call(sections, function (section) {
    if (section.getBoundingClientRect().top < fold) {
      section.classList.add('anim-in');
    } else {
      observer.observe(section);
    }
  });
  document.documentElement.classList.add('anim-ready');
})();
