/*
 * Cropping a picture by hand (PLAN.md D-026).
 *
 * The dialog is HIDDEN until this file runs, and that is deliberate. Without JavaScript
 * there is no crop box to drag, so a visible "Crop" form would be a control that cannot
 * do anything — worse than one that is honestly absent. Everything else on this screen
 * works without this file, as it does without media.js.
 *
 * WHAT IS SENT IS A RECTANGLE, NEVER AN IMAGE. Cropper is shown the `full` variant,
 * because originals are not public (D-020), so the numbers are in that variant's pixels
 * and travel with the size they were measured against. The server scales them to the
 * original and cuts it at full quality; nothing drawn here is trusted as a pixel.
 *
 * checkOrientation is OFF on purpose. Cropper would otherwise read the file over XHR to
 * find an EXIF orientation and turn the picture itself — but our variants already have the
 * pixels the right way up and the EXIF stripped (SPEC §5.5), so it would be turning an
 * upright picture a second time, and only for photographs taken in portrait.
 */
(function () {
  'use strict';

  var dialog = document.querySelector('[data-crop]');
  if (!dialog || typeof Cropper !== 'function') {
    return;
  }

  var image = dialog.querySelector('[data-crop-image]');
  var form = dialog.querySelector('[data-crop-form]');
  var opener = document.querySelector('[data-crop-open]');
  if (!image || !form || !opener) {
    return;
  }

  var cropper = null;

  // Only now does the control appear, and the button that opens it with it.
  opener.hidden = false;

  opener.addEventListener('click', function () {
    dialog.hidden = false;
    opener.hidden = true;
    if (cropper === null) {
      cropper = start(image);
    }
    dialog.scrollIntoView({ block: 'nearest' });
  });

  var cancel = dialog.querySelector('[data-crop-cancel]');
  if (cancel) {
    cancel.addEventListener('click', function () {
      dialog.hidden = true;
      opener.hidden = false;
    });
  }

  shapes(dialog, function (ratio) {
    if (cropper !== null) {
      // NaN is Cropper's own way of spelling "free", which is what the Free button means.
      cropper.setAspectRatio(ratio > 0 ? ratio : NaN);
    }
  });

  form.addEventListener('submit', function (event) {
    if (cropper === null) {
      event.preventDefault();
      return;
    }
    // Rounded: the server works in whole pixels and would round these anyway, so rounding
    // here means the numbers it validates are the numbers this box was showing.
    var data = cropper.getData(true);
    fill(form, {
      x: data.x,
      y: data.y,
      w: data.width,
      h: data.height,
      full_w: image.naturalWidth,
      full_h: image.naturalHeight,
    });
  });

  function start(img) {
    return new Cropper(img, {
      viewMode: 1,
      autoCropArea: 0.8,
      background: false,
      checkOrientation: false,
      responsive: true,
      zoomable: false,
      movable: false,
      rotatable: false,
      scalable: false,
    });
  }

  /**
   * The row of shapes. One is pressed at a time, and the pressed state is what the button
   * reports to assistive technology as well as what it looks like.
   */
  function shapes(root, onChange) {
    var buttons = root.querySelectorAll('[data-crop-ratio]');
    var chosen = root.querySelector('[name="ratio"]');

    Array.prototype.forEach.call(buttons, function (button) {
      button.addEventListener('click', function () {
        Array.prototype.forEach.call(buttons, function (other) {
          other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
        });
        if (chosen) {
          chosen.value = button.getAttribute('data-crop-name') || 'free';
        }
        onChange(parseFloat(button.getAttribute('data-crop-ratio')) || 0);
      });
    });
  }

  function fill(form, values) {
    Object.keys(values).forEach(function (name) {
      var field = form.querySelector('[name="' + name + '"]');
      if (field) {
        field.value = String(values[name]);
      }
    });
  }
}());
