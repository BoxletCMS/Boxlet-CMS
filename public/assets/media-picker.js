/*
 * The picture picker: thumbnails instead of a list of names.
 *
 * It UPGRADES the select rather than replacing what posts. Without JavaScript that select
 * is the control and works on its own (tests/editor_test.php); here it stays in the form
 * as the field that submits, and a button and panel are put in front of it. Both paths
 * post the same field, so the server validates one thing.
 *
 * CHOOSING DISPATCHES A BUBBLING `change`. Assigning .value fires nothing by itself, and
 * builder-blocks.js redraws the canvas on `change` from the field groups — so without the
 * event the picture would be stored on save but the canvas would not follow, which is the
 * defect richtext.js already had to fix once. With it, choosing a picture redraws live by
 * exactly the path a person using the select takes.
 *
 * The listing is fetched as HTML from the library itself (/admin/media?picker=1), never a
 * JSON API: the picker shows precisely the cards the library screen shows, because it is
 * the same partial.
 */
(function () {
  'use strict';

  var open = null;

  /**
   * Upgrades every picker inside root that is not upgraded already.
   *
   * A ROOT, NOT THE DOCUMENT, because markup arrives after load: a block inserted into the
   * canvas, and an item added to a repeater, both come from the server as HTML whose
   * picture field is a plain select until this runs over it. richtext.js has had this from
   * the start (window.boxletRichText.scan) and builder-blocks.js calls it when it inserts
   * a block — the picker had no equivalent, so an inserted block's picture field stayed
   * the bare select. Postable and correct, but not the control every other field shows.
   */
  function scan(root) {
    Array.prototype.forEach.call((root || document).querySelectorAll('select[data-media-field]'), upgrade);
  }

  window.boxletPicker = { scan: scan };

  scan(document);

  // Anywhere else closes the panel. A picker left open over the fields it covers is a
  // control that has to be dismissed before the form can be used.
  document.addEventListener('click', function (event) {
    if (open && !open.root.contains(event.target)) {
      close();
    }
  });
  // In the capture phase and marked as handled, so the page editor's own Escape — which
  // drops the selection — sees that this one was for the picker (PLAN.md D-117).
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && open) {
      event.preventDefault();
      close();
      open = null;
    }
  }, true);

  function text(select, name) {
    return select.getAttribute('data-text-' + name) || '';
  }

  function close() {
    if (!open) return;
    open.panel.hidden = true;
    open.button.setAttribute('aria-expanded', 'false');
    open = null;
  }

  function upgrade(select) {
    var url = select.getAttribute('data-picker-url');
    // Marked, not counted: scan() runs over a subtree that may already hold upgraded
    // pickers — a duplicated block carries them in its clone — and upgrading one twice
    // would leave two buttons in front of one field. richtext.js marks the same way
    // (data-richtext-ready), and builder-blocks.js clears the mark when it strips a clone.
    if (!url || select.hasAttribute('data-picker-ready')) {
      return;
    }
    select.setAttribute('data-picker-ready', '');

    var root = document.createElement('div');
    root.className = 'media-picker';

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'button button-secondary media-picker-current';
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-haspopup', 'true');

    var panel = document.createElement('div');
    panel.className = 'media-picker-panel';
    panel.hidden = true;

    var search = document.createElement('input');
    search.type = 'search';
    search.className = 'media-picker-search';
    search.placeholder = text(select, 'search');
    search.setAttribute('aria-label', text(select, 'search'));

    var clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'button button-ghost media-picker-none';
    clear.textContent = text(select, 'none');

    var results = document.createElement('div');
    results.className = 'media-picker-results';

    panel.appendChild(search);
    panel.appendChild(clear);
    panel.appendChild(results);
    root.appendChild(button);
    root.appendChild(panel);

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

      // A square either way, so choosing and clearing never move the fields below. An
      // <img> when there is one to show, an empty box when the picture exists but its
      // thumbnail has not been generated yet.
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

    function choose(value, name, thumb) {
      select.value = value;
      // If the picture is not among the select's options — uploaded in another tab since
      // this form was rendered — add it, so the field can post what was chosen. It carries
      // its thumbnail too, or the button would show a blank square for a picture that has
      // one.
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
      close();
      button.focus();
    }

    function load() {
      results.setAttribute('aria-busy', 'true');
      var query = url + (url.indexOf('?') === -1 ? '?' : '&') + 'picker=1&q=' + encodeURIComponent(search.value);
      fetch(query, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
        .then(function (response) {
          if (!response.ok) throw new Error('HTTP ' + response.status);
          return response.text();
        })
        .then(function (html) {
          results.innerHTML = html;
          results.removeAttribute('aria-busy');
        })
        .catch(function () {
          // Says so rather than showing an empty panel, which would read as "no pictures".
          results.textContent = text(select, 'failed');
          results.removeAttribute('aria-busy');
        });
    }

    button.addEventListener('click', function () {
      if (open && open.root === root) {
        close();
        return;
      }
      close();
      panel.hidden = false;
      button.setAttribute('aria-expanded', 'true');
      open = { root: root, panel: panel, button: button };
      load();
      search.focus();
    });

    clear.addEventListener('click', function () {
      choose('', '');
    });

    results.addEventListener('click', function (event) {
      var card = event.target.closest && event.target.closest('[data-pick]');
      if (card) {
        var picture = card.querySelector('img');
        choose(
          card.getAttribute('data-pick'),
          card.getAttribute('data-pick-name') || '',
          picture ? picture.getAttribute('src') : null,
        );
      }
    });

    var timer = null;
    search.addEventListener('input', function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(load, 250);
    });

    paint();
  }
})();
