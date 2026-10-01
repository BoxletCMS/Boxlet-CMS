/*
 * The picture picker: the control in a picture field (PLAN.md D-145). What it opens is the
 * media browser, media-browser.js, which is loaded before this.
 *
 * It UPGRADES the select rather than replacing what posts. Without JavaScript that select
 * is the control and works on its own (tests/editor_test.php); here it stays in the form
 * as the field that submits, and a button is put in front of it. Both paths post the same
 * field, so the server validates one thing.
 *
 * CHOOSING DISPATCHES A BUBBLING `change`. Assigning .value fires nothing by itself, and
 * builder-blocks.js redraws the canvas on `change` from the field groups — so without the
 * event the picture would be stored on save but the canvas would not follow, which is the
 * defect richtext.js already had to fix once.
 */
(function () {
  'use strict';

  /**
   * Upgrades every picker inside root that is not upgraded already.
   *
   * A ROOT, NOT THE DOCUMENT, because markup arrives after load: a block inserted into the
   * canvas, and an item added to a repeater, both come from the server as HTML whose
   * picture field is a plain select until this runs over it. builder-blocks.js calls it
   * when it inserts a block.
   */
  function scan(root) {
    Array.prototype.forEach.call((root || document).querySelectorAll('select[data-media-field]'), upgrade);
  }

  window.boxletPicker = { scan: scan };

  scan(document);

  function text(element, name) {
    return element.getAttribute('data-text-' + name) || '';
  }

  function upgrade(select) {
    var url = select.getAttribute('data-picker-url');
    // Marked, not counted: scan() runs over a subtree that may already hold upgraded
    // pickers — a duplicated block carries them in its clone — and upgrading one twice
    // would leave two buttons in front of one field. builder-blocks.js clears the mark
    // when it strips a clone.
    if (!url || select.hasAttribute('data-picker-ready')) {
      return;
    }
    select.setAttribute('data-picker-ready', '');

    var root = document.createElement('div');
    root.className = 'media-picker';

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'button button-secondary media-picker-current';
    button.setAttribute('aria-haspopup', 'dialog');

    // Where a failure to open says so, beside the control that failed.
    var failed = document.createElement('p');
    failed.className = 'hint media-picker-failed';
    failed.setAttribute('role', 'alert');
    failed.hidden = true;

    root.appendChild(button);
    root.appendChild(failed);

    // The select stays in the form — it is what posts — but stops being the control.
    select.hidden = true;
    select.parentNode.insertBefore(root, select.nextSibling);

    var label = document.querySelector('label[for="' + select.id + '"]');
    if (label) {
      button.setAttribute('aria-label', label.textContent.trim());
    }

    // The resting state has to say three things: which picture is chosen, that it IS a
    // picture, and what pressing this does. The first version set textContent to the bare
    // filename, which read as a text field somebody had typed into.
    function paint() {
      var chosen = select.options[select.selectedIndex];
      var has = !!(chosen && chosen.value);
      var thumb = has ? chosen.getAttribute('data-thumb') : null;

      button.textContent = '';
      button.classList.toggle('media-picker-empty', !has);

      // A square either way, so choosing and clearing never move the fields below.
      var picture;
      if (thumb) {
        picture = document.createElement('img');
        picture.src = thumb;
        picture.alt = '';
        picture.width = 40;
        picture.height = 40;
      } else {
        picture = document.createElement('span');
      }
      picture.className = 'media-picker-thumb'
        + (select.hasAttribute('data-picker-whole') ? ' media-picker-thumb-whole' : '');

      var name = document.createElement('span');
      name.className = 'media-picker-name';
      name.textContent = has ? chosen.textContent : text(select, 'none');

      var verb = document.createElement('span');
      verb.className = 'media-picker-verb';
      verb.textContent = has ? text(select, 'change') : text(select, 'choose');

      button.appendChild(picture);
      button.appendChild(name);
      button.appendChild(verb);
    }

    // Choosing, from the browser. The select is what posts, so choosing is writing it.
    function choose(value, name, thumb) {
      select.value = value;
      // A picture not among the select's options — uploaded in the browser, or in another
      // tab since this form was rendered — is added, so the field can post what was
      // chosen. It carries its thumbnail too, or the button would show a blank square.
      if (value !== '' && select.value !== value) {
        var option = document.createElement('option');
        option.value = value;
        option.textContent = name;
        if (thumb) {
          option.setAttribute('data-thumb', thumb);
        }
        select.appendChild(option);
        select.value = value;
      }
      // The event the canvas listens for. Bubbles, because the listener is on the group.
      select.dispatchEvent(new Event('change', { bubbles: true }));
      paint();
    }

    button.addEventListener('click', function () {
      failed.hidden = true;
      if (!window.boxletBrowser) {
        failed.textContent = text(select, 'failed');
        failed.hidden = false;
        return;
      }
      button.setAttribute('aria-busy', 'true');
      window.boxletBrowser.open({ select: select, button: button, choose: choose, url: url }).then(function () {
        button.removeAttribute('aria-busy');
      }, function () {
        button.removeAttribute('aria-busy');
        failed.textContent = text(select, 'failed');
        failed.hidden = false;
      });
    });

    paint();

    if (select.hasAttribute('data-poster-url')) {
      poster(select, root, choose);
    }
  }

  /**
   * An Embed block's cover, taken from the video it shows (PLAN.md D-147).
   *
   * The server fetches the video's own still once and keeps it in the library, so a visitor
   * never asks YouTube or Vimeo for it. Asked for by a button under the field, and by itself
   * when a video's address is pasted into a block that has no cover yet.
   */
  function poster(select, root, choose) {
    var block = select.closest('[data-block]');
    var address = block ? block.querySelector('input[name$="[url]"]') : null;
    if (!address) {
      return;
    }

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'button button-secondary media-picker-poster';
    button.textContent = text(select, 'poster-fetch');

    var status = document.createElement('p');
    status.className = 'hint';
    status.setAttribute('role', 'status');
    status.hidden = true;

    root.appendChild(button);
    root.appendChild(status);

    function say(words) {
      status.textContent = words;
      status.hidden = !words;
    }

    function post(url, body) {
      var token = document.querySelector('input[name="_csrf"]');
      body.set('_csrf', token ? token.value : '');
      return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
        .then(function (response) {
          return response.text().then(function (html) {
            var holder = document.createElement('div');
            holder.innerHTML = html;
            var card = holder.querySelector('[data-pick]');
            if (response.ok && card) {
              return card;
            }
            throw new Error(response.status === 422 ? holder.textContent.trim() : text(select, 'failed'));
          });
        });
    }

    // Its sizes, asked for until they are all made, as the media browser does.
    function complete(card, tries) {
      if (card.getAttribute('data-pick-complete') !== '0' || tries >= 20) {
        return Promise.resolve(card);
      }
      return post(select.getAttribute('data-picker-url') + '/' + card.getAttribute('data-pick') + '/finish', new FormData())
        .then(function (next) {
          return complete(next, tries + 1);
        });
    }

    function take() {
      if (!address.value.trim() || button.disabled) {
        return;
      }
      button.disabled = true;
      say(text(select, 'poster-fetching'));
      var body = new FormData();
      body.set('url', address.value.trim());
      post(select.getAttribute('data-poster-url'), body)
        .then(function (card) {
          return complete(card, 0);
        })
        .then(function (card) {
          var picture = card.querySelector('img');
          choose(card.getAttribute('data-pick'), card.getAttribute('data-pick-name') || '', picture ? picture.getAttribute('src') : null);
          say(text(select, 'poster-fetched'));
        }, function (problem) {
          say(problem.message || text(select, 'failed'));
        })
        .then(function () {
          button.disabled = false;
        });
    }

    button.addEventListener('click', take);
    // A pasted video fills an empty cover by itself; a cover already chosen is never
    // replaced without being asked.
    address.addEventListener('change', function () {
      if (select.value === '') {
        take();
      }
    });
  }
})();
