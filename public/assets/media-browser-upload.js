/*
 * The media browser's upload step (PLAN.md D-145): a file chosen or dropped, checked for
 * size, cropped if the owner wants, sent, and its sizes waited for before it is chosen.
 *
 * Split from media-browser.js, which passed 300 lines with it. The seam: that file finds
 * and chooses what is in the library; this one puts something new into it. It keeps the
 * crop's own state and reaches the dialog only through what media-browser.js hands it.
 *
 * WHAT IS SENT IS THE FILE AND A RECTANGLE, never a drawn image (D-026). The box is drawn
 * over the file itself, as the browser shows it — upright, as MediaEncoder::inspect measures
 * it — and the server cuts the file it received.
 */
(function () {
  'use strict';

  window.boxletBrowserUpload = function (api) {
    var browser = api.dialog;
    var cropper = null;
    var pending = null; // the file in the crop step, and its object URL

    // The crop step's own buttons.
    api.part('crop-cancel').addEventListener('click', api.back);
    api.part('crop-skip').addEventListener('click', function () {
      if (pending) {
        send(pending.file, null);
      }
    });
    api.part('crop-confirm').addEventListener('click', function () {
      if (!pending || !cropper) {
        return;
      }
      var data = cropper.getData(true);
      var image = api.part('crop-image');
      send(pending.file, {
        x: data.x,
        y: data.y,
        w: data.width,
        h: data.height,
        full_w: image.naturalWidth,
        full_h: image.naturalHeight,
        ratio: pressedShape(),
      });
    });
    Array.prototype.forEach.call(browser.querySelectorAll('[data-crop-ratio]'), function (shape) {
      shape.addEventListener('click', function () {
        press(shape.getAttribute('data-crop-name'));
      });
    });

    // Matches Bytes::human() on the server, so the two never disagree about a size.
    function human(bytes) {
      if (bytes >= 1048576) {
        return (bytes / 1048576).toFixed(1) + ' MB';
      }
      if (bytes >= 1024) {
        return Math.round(bytes / 1024) + ' KB';
      }
      return bytes + ' B';
    }

    /**
     * A file chosen or dropped. Refused here if it is too large — nginx would answer with a
     * page of its own that nothing of ours can explain — then cropped, or sent as it is.
     */
    function take(file) {
      api.say('');
      var limit = parseInt(browser.getAttribute('data-max-file'), 10) || 0;
      if (limit > 0 && file.size > limit) {
        api.say(browser.getAttribute('data-too-large').split(':name').join(file.name).split(':size').join(human(file.size)));
        return;
      }
      if (typeof Cropper !== 'function' || file.type === 'image/gif') {
        send(file, null);
        return;
      }

      var address = URL.createObjectURL(file);
      var image = api.part('crop-image');
      pending = { file: file, address: address };
      image.onload = function () {
        image.onload = null;
        image.onerror = null;
        startCrop(image);
      };
      // A file this browser cannot draw is sent as it is: the server either takes it or
      // says why not, in the same words the library uses.
      image.onerror = function () {
        image.onload = null;
        image.onerror = null;
        send(file, null);
      };
      image.src = address;
    }

    function startCrop(image) {
      api.part('pick').hidden = true;
      api.part('crop').hidden = false;
      api.heading('crop');
      cropper = new Cropper(image, {
        viewMode: 1,
        autoCropArea: 1,
        background: false,
        // The browser already shows the file upright, and the server measures it the same
        // way (MediaEncoder::inspect), so Cropper must not turn it a second time.
        checkOrientation: false,
        responsive: true,
        zoomable: false,
        movable: false,
        rotatable: false,
        scalable: false,
        ready: function () {
          press(preferredShape());
        },
      });
      api.part('crop-confirm').focus();
    }

    /** The shape the field's block draws its picture in, or Free (MediaReference::CROP_FOR). */
    function preferredShape() {
      var select = api.field().select;
      var fixed = select.getAttribute('data-picker-crop');
      if (fixed) {
        return fixed;
      }
      var name = select.getAttribute('data-picker-crop-field');
      var block = select.closest('[data-block]');
      if (name && block) {
        // An option since D-166, a row of buttons: the one pressed. Auto names no shape here,
        // so it opens on Free.
        var chooser = block.querySelector('select[name$="[' + name + ']"], input[name$="[' + name + ']"]:checked');
        try {
          var map = JSON.parse(select.getAttribute('data-picker-crop-map') || '{}');
          if (chooser && map[chooser.value]) {
            return map[chooser.value];
          }
        } catch (e) {
          // A map that does not parse is simply no preference.
        }
      }
      return 'free';
    }

    function press(name) {
      var ratio = 0;
      Array.prototype.forEach.call(browser.querySelectorAll('[data-crop-ratio]'), function (shape) {
        var on = shape.getAttribute('data-crop-name') === name;
        shape.setAttribute('aria-pressed', on ? 'true' : 'false');
        if (on) {
          ratio = parseFloat(shape.getAttribute('data-crop-ratio')) || 0;
        }
      });
      if (cropper) {
        // NaN is Cropper's own way of spelling "free".
        cropper.setAspectRatio(ratio > 0 ? ratio : NaN);
      }
    }

    function pressedShape() {
      var shape = browser.querySelector('[data-crop-ratio][aria-pressed="true"]');
      return shape ? shape.getAttribute('data-crop-name') : 'free';
    }

    function stopCrop() {
      if (cropper) {
        cropper.destroy();
        cropper = null;
      }
      if (pending) {
        URL.revokeObjectURL(pending.address);
        pending = null;
      }
      api.part('crop-image').removeAttribute('src');
    }

    /**
     * Sends the file, and the rectangle if one was drawn, then waits for the picture's sizes
     * before choosing it: a picture chosen half-made would show a blank on the canvas.
     */
    function send(file, rect) {
      var body = new FormData();
      var token = document.querySelector('input[name="_csrf"]');
      body.set('_csrf', token ? token.value : '');
      body.set('file', file);
      if (rect) {
        body.set('crop', '1');
        Object.keys(rect).forEach(function (key) {
          body.set(key, String(rect[key]));
        });
      }

      var cropping = !!rect;
      api.busy(api.text(browser, 'uploading'));
      post(api.field().url, body).then(function (card) {
        stopCrop();
        return complete(card, 0);
      }).then(function (card) {
        api.busy('');
        api.chooseCard(card);
      }, function (problem) {
        api.busy('');
        // A refused crop stays in the crop step, so the box can be moved and sent again;
        // anything else goes back to the pictures with its reason.
        if (!(cropping && problem.refused && pending)) {
          api.back();
        }
        api.say(problem.message || api.text(browser, 'failed'));
      });
    }

    /** The finish route, asked until the card says the picture has all its sizes. */
    function complete(card, tries) {
      if (card.getAttribute('data-pick-complete') !== '0' || tries >= 20) {
        return Promise.resolve(card);
      }
      api.busy(api.text(browser, 'finishing'));
      var body = new FormData();
      var token = document.querySelector('input[name="_csrf"]');
      body.set('_csrf', token ? token.value : '');
      return post(api.field().url + '/' + card.getAttribute('data-pick') + '/finish', body).then(function (next) {
        return complete(next, tries + 1);
      });
    }

    /** A post that answers with a card, or rejects with the server's reason. */
    function post(url, body) {
      return fetch(url, {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' },
      }).then(function (response) {
        return response.text().then(function (html) {
          var holder = document.createElement('div');
          holder.innerHTML = html;
          var card = holder.querySelector('[data-pick]');
          if (response.ok && card) {
            return card;
          }
          // A refusal is a short escaped sentence; anything longer is a page that is not ours.
          var words = response.status === 422 ? holder.textContent.trim() : '';
          throw { refused: response.status === 422, message: words };
        });
      }, function () {
        throw { refused: false, message: '' };
      });
    }

    return { take: take, stop: stopCrop };
  };
}());
