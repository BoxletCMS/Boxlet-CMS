/*
 * AN ADDRESS FROM THE ONE LONG SETTINGS PAGE, SENT TO ITS TAB (PLAN.md D-294). Settings was one
 * page of sections reached by anchor (/admin/settings#mail); now each is a tab at its own
 * address. A bookmark, an old link or an email that names a section by anchor reaches General,
 * since the anchor never reaches the server, and this sends it on, replacing the entry so Back
 * does not land on General first. The map is the server's (data-moved on the tabs), so the
 * paths are the site's own. An anchor still on General (#branding, #site_name) stays.
 */
(function () {
  'use strict';

  var nav = document.querySelector('[data-moved]');
  var name = window.location.hash.slice(1);
  if (!nav || name === '') {
    return;
  }
  var moved;
  try {
    moved = JSON.parse(nav.getAttribute('data-moved'));
  } catch (e) {
    return;
  }
  if (Object.prototype.hasOwnProperty.call(moved, name)) {
    window.location.replace(moved[name]);
  }
})();
