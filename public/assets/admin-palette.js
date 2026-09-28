/*
 * The ⌘K palette (PLAN.md D-052): Ctrl+K or ⌘K, or the rail's Search, opens a search over
 * the whole admin; arrows move, Enter opens, Escape or a click outside closes.
 *
 * Optional, like everything the admin's scripts do. Without it the rail's Search is a link
 * to the Search screen, which shows the same results; the palette asks that screen for
 * them (?fragment=1) and puts its markup in the list, so the two cannot disagree.
 */
(function () {
  'use strict';

  var dialog = document.querySelector('[data-palette]');
  if (!dialog || typeof dialog.showModal !== 'function' || !window.fetch) {
    return;
  }
  var input = dialog.querySelector('[data-palette-input]');
  var results = dialog.querySelector('[data-palette-results]');
  var url = dialog.getAttribute('data-search-url');
  var asked = 0;
  var timer = null;

  function open() {
    if (dialog.open) {
      return;
    }
    input.value = '';
    results.innerHTML = '';
    dialog.showModal();
    input.focus();
  }

  function options() {
    return Array.prototype.slice.call(results.querySelectorAll('.search-result'));
  }

  function select(index) {
    var all = options();
    all.forEach(function (option, i) {
      if (i === index) {
        option.setAttribute('aria-selected', 'true');
        option.scrollIntoView({ block: 'nearest' });
        input.setAttribute('aria-activedescendant', option.id);
      } else {
        option.removeAttribute('aria-selected');
      }
    });
  }

  function selected() {
    var all = options();
    for (var i = 0; i < all.length; i++) {
      if (all[i].getAttribute('aria-selected') === 'true') {
        return i;
      }
    }
    return -1;
  }

  function go(link) {
    dialog.close();
    window.location.href = link.href;
  }

  function search() {
    var query = input.value.trim();
    var mine = ++asked;
    if (query === '') {
      results.innerHTML = '';
      return;
    }
    fetch(url + '?fragment=1&q=' + encodeURIComponent(query), { credentials: 'same-origin' })
      .then(function (response) { return response.ok ? response.text() : ''; })
      .then(function (html) {
        // Only the latest answer: a slow reply to "ti" must not replace the one to "time".
        if (mine === asked) {
          results.innerHTML = html;
        }
      })
      .catch(function () {});
  }

  document.addEventListener('keydown', function (event) {
    if (!(event.metaKey || event.ctrlKey) || event.altKey || event.key.toLowerCase() !== 'k') {
      return;
    }
    // Inside rich text, Ctrl+K is the editor's own: it makes a link (D-034), and a palette
    // that took it would have taken a feature away. Everywhere else it opens the palette.
    var target = event.target;
    if (target && target.closest && target.closest('[contenteditable="true"], .ProseMirror')) {
      return;
    }
    event.preventDefault();
    open();
  });

  Array.prototype.forEach.call(document.querySelectorAll('[data-palette-open]'), function (opener) {
    opener.addEventListener('click', function (event) {
      event.preventDefault();
      open();
    });
  });

  input.addEventListener('input', function () {
    window.clearTimeout(timer);
    timer = window.setTimeout(search, 120);
  });

  input.addEventListener('keydown', function (event) {
    var all = options();
    var index = selected();
    if (event.key === 'ArrowDown' && all.length) {
      event.preventDefault();
      select(Math.min(all.length - 1, index + 1));
    } else if (event.key === 'ArrowUp' && all.length) {
      event.preventDefault();
      select(Math.max(0, index - 1));
    } else if (event.key === 'Enter' && index >= 0) {
      // With a result chosen, Enter opens it; with none, the form goes to the Search screen.
      event.preventDefault();
      go(all[index]);
    }
  });

  results.addEventListener('mousemove', function (event) {
    var option = event.target.closest && event.target.closest('.search-result');
    if (option) {
      select(options().indexOf(option));
    }
  });

  results.addEventListener('click', function (event) {
    var option = event.target.closest && event.target.closest('.search-result');
    if (option) {
      event.preventDefault();
      go(option);
    }
  });

  // A click on the backdrop lands on the dialog itself, outside its content.
  dialog.addEventListener('click', function (event) {
    if (event.target === dialog) {
      dialog.close();
    }
  });
})();
