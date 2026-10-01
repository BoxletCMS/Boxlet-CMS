/*
 * A video or a Google map, loaded when the visitor presses it (PLAN.md D-147).
 *
 * Until then the page holds only a link with the site's own cover picture, and nothing of
 * the provider's has been asked for: no request, nothing stored in the visitor's browser.
 * The press is the visitor choosing to load it, and this puts the frame where the link was,
 * already playing. Every attribute the frame gets is read off the link, which the block's
 * template wrote: this file adds no provider, no address and no permission of its own.
 *
 * Without this file the link still works: it opens the video or the map on its own site.
 */
(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    var link = event.target.closest && event.target.closest('a[data-embed-src]');
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) {
      return;
    }
    event.preventDefault();

    var frame = document.createElement('iframe');
    frame.src = link.getAttribute('data-embed-src');
    frame.title = link.getAttribute('data-embed-title') || '';
    frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    frame.setAttribute('sandbox', link.getAttribute('data-embed-sandbox') || '');
    frame.setAttribute('allow', link.getAttribute('data-embed-allow') || '');
    frame.setAttribute('allowfullscreen', '');
    link.parentNode.replaceChild(frame, link);
    // Where the press was, so a keyboard user carries on from the player.
    frame.focus();
  });
}());
