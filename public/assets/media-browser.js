/*
 * The media browser (PLAN.md D-145): one dialog for a whole screen, opened by a picture
 * field's control (media-picker.js) through window.boxletBrowser.open(field).
 *
 * ONE DIALOG FOR THE WHOLE SCREEN. The first field to open it fetches it from the server
 * (/admin/media/pick?dialog=1) — its words, its shapes and the first page of the library's
 * own cards — and every field after uses the same one. So this file carries no English and
 * no second list of crop shapes, and the cards are exactly the library's.
 *
 * Putting a NEW picture into the library — the drop, the crop, the upload — is
 * media-browser-upload.js, loaded before this and handed what it needs of the dialog.
 */
(function () {
  'use strict';

  var browser = null; // the dialog, once fetched
  var loading = null; // the fetch for it, while it is on its way
  var field = null; // the picker the dialog is open for: { select, button, choose, url }
  var upload = null; // the upload and crop step (media-browser-upload.js)
  var shown = null; // the first page's markup as it was last drawn, to redraw only a change

  window.boxletBrowser = {
    open: function (which) {
      return dialog(which.url).then(function () {
        open(which);
      });
    },
  };

  // In the capture phase and marked as handled, so the page editor's own Escape — which
  // drops the selection — sees that this one was for the picker (PLAN.md D-117). Escape
  // in the crop step goes back to the pictures; in the pictures it closes.
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape' || !browser || !browser.open) {
      return;
    }
    event.preventDefault();
    if (!part('crop').hidden) {
      leaveCrop();
    } else {
      close();
    }
  }, true);
  function text(element, name) {
    return element.getAttribute('data-text-' + name) || '';
  }

  function part(name) {
    return browser.querySelector('[data-browser-' + name + ']');
  }
  /** The dialog, fetched once and kept. */
  function dialog(url) {
    if (browser) {
      return Promise.resolve(browser);
    }
    if (!loading) {
      loading = get(url + '?dialog=1').then(function (html) {
        var holder = document.createElement('div');
        holder.innerHTML = html;
        var found = holder.querySelector('[data-browser]');
        if (!found) {
          throw new Error('no dialog');
        }
        document.body.appendChild(found);
        browser = found;
        wire();
        return browser;
      });
      loading.catch(function () {
        loading = null;
      });
    }
    return loading;
  }

  function get(url) {
    return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.text();
      });
  }

  function open(which) {
    var reopened = field !== null || browser.hasAttribute('data-opened');
    field = which;
    browser.setAttribute('data-opened', '');
    showPick();
    say('');
    browser.showModal();
    // The list may have grown since it was fetched — a picture uploaded from another
    // field — so every opening after the first asks for it again.
    if (reopened) {
      load(1, false);
    } else {
      mark();
    }
    part('search').focus();
  }

  function close() {
    upload.stop();
    if (browser.open) {
      browser.close();
    }
    if (field) {
      field.button.focus();
    }
    field = null;
  }

  /** The events of the dialog itself, wired once. */
  function wire() {
    // The upload and crop step, handed what it needs of this dialog.
    upload = window.boxletBrowserUpload({
      dialog: browser,
      part: part,
      text: text,
      say: say,
      busy: busy,
      heading: heading,
      back: leaveCrop,
      field: function () { return field; },
      chooseCard: chooseCard,
    });
    var search = part('search');
    var results = part('results');
    var timer = null;

    part('close').addEventListener('click', close);
    // A click on the backdrop lands on the dialog element itself.
    browser.addEventListener('click', function (event) {
      if (event.target === browser) {
        close();
      }
    });
    // The native Escape is answered by the capture listener above; this stops the dialog
    // closing itself behind that listener's back.
    browser.addEventListener('cancel', function (event) {
      event.preventDefault();
    });

    search.addEventListener('input', function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        load(1, false);
      }, 250);
    });

    part('none').addEventListener('click', function () {
      field.choose('', '', null);
      close();
    });

    results.addEventListener('click', function (event) {
      var more = event.target.closest && event.target.closest('[data-pick-more]');
      if (more) {
        load(parseInt(more.getAttribute('data-pick-more'), 10) || 2, true);
        return;
      }
      var card = event.target.closest && event.target.closest('[data-pick]');
      if (card) {
        chooseCard(card);
      }
    });

    part('file').addEventListener('change', function () {
      var input = part('file');
      if (input.files && input.files.length) {
        upload.take(input.files[0]);
      }
      input.value = '';
    });

    // A drop anywhere on the dialog while the pictures are showing. The zone is tinted
    // while something is over it.
    var drop = part('drop');
    ['dragenter', 'dragover'].forEach(function (name) {
      browser.addEventListener(name, function (event) {
        if (!part('pick').hidden) {
          event.preventDefault();
          drop.classList.add('is-dropping');
        }
      });
    });
    ['dragleave', 'drop'].forEach(function (name) {
      browser.addEventListener(name, function (event) {
        if (name === 'dragleave' && browser.contains(event.relatedTarget)) {
          return;
        }
        drop.classList.remove('is-dropping');
      });
    });
    browser.addEventListener('drop', function (event) {
      event.preventDefault();
      if (!part('pick').hidden && event.dataTransfer && event.dataTransfer.files.length) {
        upload.take(event.dataTransfer.files[0]);
      }
    });

  }

  /** Loads a page of cards: the first replaces the grid, a later one is added to it. */
  function load(page, append) {
    var results = part('results');
    var query = field.url + '?q=' + encodeURIComponent(part('search').value) + (page > 1 ? '&page=' + page : '');
    results.setAttribute('aria-busy', 'true');
    get(query).then(function (html) {
      results.removeAttribute('aria-busy');
      var more = results.querySelector('[data-pick-more]');
      if (!append) {
        // Replaced only when it changed: a reopening that finds the same pictures keeps
        // the grid where it was scrolled, and a card being pressed is never swapped out
        // from under the press.
        if (html !== shown) {
          results.innerHTML = html;
          shown = html;
        }
      } else {
        shown = null;
        var holder = document.createElement('div');
        holder.innerHTML = html;
        var grid = results.querySelector('.media-grid');
        var cards = holder.querySelectorAll('.media-grid > li');
        Array.prototype.forEach.call(cards, function (card) {
          grid.appendChild(card);
        });
        if (more) {
          more.remove();
        }
        var next = holder.querySelector('[data-pick-more]');
        if (next) {
          results.appendChild(next);
        }
      }
      mark();
    }, function () {
      results.removeAttribute('aria-busy');
      // Says so rather than showing an empty grid, which would read as "no pictures".
      say(text(field.select, 'failed'));
    });
  }

  /** The card of the picture the field holds now is shown as chosen. */
  function mark() {
    var value = field ? field.select.value : '';
    Array.prototype.forEach.call(part('results').querySelectorAll('[data-pick]'), function (card) {
      var chosen = value !== '' && card.getAttribute('data-pick') === value;
      card.classList.toggle('is-chosen', chosen);
      if (chosen) {
        card.setAttribute('aria-current', 'true');
        card.setAttribute('data-chosen-label', text(browser, 'chosen'));
      } else {
        card.removeAttribute('aria-current');
      }
    });
  }
  function chooseCard(card) {
    var picture = card.querySelector('img');
    field.choose(card.getAttribute('data-pick'), card.getAttribute('data-pick-name') || '', picture ? picture.getAttribute('src') : null);
    close();
  }

  function say(message) {
    var error = part('error');
    error.textContent = message;
    error.hidden = !message;
  }

  function leaveCrop() {
    upload.stop();
    showPick();
  }

  function showPick() {
    part('crop').hidden = true;
    part('busy').hidden = true;
    part('pick').hidden = false;
    heading('pick');
  }

  function heading(step) {
    var title = part('heading');
    title.textContent = title.getAttribute('data-title-' + step) || title.textContent;
  }

  function busy(message) {
    part('busy').hidden = !message;
    part('busy-text').textContent = message || '';
  }

})();
