/*
 * The page's own settings in the editor — its name in the toolbar, the address that follows
 * the title — the device width the canvas is shown at, and the guard against leaving with
 * unsaved changes. Split from builder.js at the hard size limit (PLAN.md D-117).
 */
(function () {
  'use strict';

  var api = window.boxletBuilder;
  if (!api) {
    return;
  }
  var form = api.form;

  form.addEventListener('click', function (event) {
    if (event.target.closest && event.target.closest('[data-deselect]')) {
      event.preventDefault();
      api.show(-1);
      api.tellCanvas('select', { index: -1 });
      return;
    }
    var device = event.target.closest && event.target.closest('[data-device]');
    if (device) {
      event.preventDefault();
      form.querySelectorAll('[data-device]').forEach(function (button) {
        button.setAttribute('aria-pressed', String(button === device));
      });
      api.frame.style.width = device.getAttribute('data-width');
    }
  });

  // The toolbar shows the page's name; the panel owns the field.
  var titleField = form.querySelector('#page-title');
  var titleEcho = form.querySelector('[data-title-echo]');
  var slugField = form.querySelector('[data-slug-field]');
  var statusField = form.querySelector('[data-status-field]');
  var slugEdited = false;

  if (slugField) {
    slugField.addEventListener('input', function () { slugEdited = true; });
  }

  /**
   * The address follows the title, but only while the page is a draft and only until the
   * address is touched. Two deliberate refusals: a published page's address never changes
   * behind its author, and an empty address is never generated over — empty means "the
   * home page of this language", so filling it in would move the site's root.
   *
   * The server's Slug is authoritative; this is a convenience that it validates.
   */
  function followTitle() {
    if (!slugField || !titleField || slugEdited) {
      return;
    }
    if (slugField.value === '' || (statusField && statusField.value !== 'draft')) {
      return;
    }
    slugField.value = titleField.value
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 90);
  }

  if (titleField) {
    titleField.addEventListener('input', function () {
      if (titleEcho) {
        titleEcho.textContent = titleField.value;
      }
      followTitle();
    });
  }

  form.addEventListener('input', function () { api.dirty = true; });
  form.addEventListener('change', function () { api.dirty = true; });
  form.addEventListener('submit', function () { api.dirty = false; });
  window.addEventListener('beforeunload', function (event) {
    if (api.dirty) {
      event.preventDefault();
      event.returnValue = '';
    }
  });
})();
