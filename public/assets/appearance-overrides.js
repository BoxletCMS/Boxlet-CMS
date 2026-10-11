/*
 * WHAT IS THE OWNER'S OWN, KEPT LIVE (PLAN.md D-158): the dot on each changed control, the
 * count on each group and section, the banner over the home, and the resets done in place.
 *
 * The server draws all of it, and every reset is an ordinary submit that works without this
 * file. What this adds is that none of it waits for a round trip: a control moved shows its
 * dot at once, a reset puts the value back where it stands and the preview follows.
 *
 * IT READS ONLY WHAT THE SERVER PRINTED. Each control's row says what it is when the owner
 * has not made it theirs (data-default) and what kind of value it holds (data-kind): a
 * DECISION or a LOOK choice is changed when it differs from the default; a colour BY HAND is
 * changed when its switch is on, whatever colour it holds — the dot says who chose it, not
 * that it differs from a colour the palette happened to work out.
 */
(function () {
  'use strict';

  var form = document.querySelector('form[data-design-form]');
  if (!form) {
    return;
  }
  var banner = document.querySelector('[data-overrides]');

  function rows(scope) {
    return [].slice.call((scope || form).querySelectorAll('[data-control][data-default]'));
  }

  /** The value a row holds now, in the terms its default is written in. */
  function valueOf(row) {
    var kind = row.getAttribute('data-kind');
    if (kind === 'by_hand') {
      var mine = row.querySelector('[data-by-hand-switch]');
      return mine && mine.checked ? 'set' : '';
    }
    if (kind === 'secondary') {
      var on = row.querySelector('input[name="use_secondary"]');
      var colour = row.querySelector('input[type="color"]');
      return on && on.checked && colour ? colour.value.toLowerCase() : '';
    }
    var pressed = row.querySelector('input[type="radio"]:checked');
    if (pressed) {
      return pressed.value;
    }
    var input = row.querySelector('input[type="range"], input[type="color"], select');
    return input ? input.value : '';
  }

  /** Equal text, or one number written two ways ("72" and "72.0"). */
  function same(a, b) {
    a = String(a).toLowerCase();
    b = String(b).toLowerCase();
    if (a === b) {
      return true;
    }
    var number = /^-?\d+(\.\d+)?$/;
    return number.test(a) && number.test(b) && Math.abs(parseFloat(a) - parseFloat(b)) < 1e-9;
  }

  function changedIn(scope, changed) {
    var keys = {};
    rows(scope).forEach(function (row) {
      var key = row.getAttribute('data-control');
      if (changed[key]) {
        keys[key] = true;
      }
    });
    return Object.keys(keys).length;
  }

  function update() {
    var changed = {};
    rows().forEach(function (row) {
      var mine = !same(valueOf(row), row.getAttribute('data-default'));
      row.classList.toggle('is-changed', mine);
      if (mine) {
        changed[row.getAttribute('data-control')] = true;
      }
    });
    [].slice.call(form.querySelectorAll('[data-group]')).forEach(function (group) {
      var count = changedIn(group, changed);
      group.classList.toggle('has-changes', count > 0);
      var said = group.querySelector('[data-group-count]');
      if (said) {
        said.textContent = String(count);
      }
    });
    [].slice.call(form.querySelectorAll('[data-view]')).forEach(function (view) {
      var name = view.getAttribute('data-view');
      if (name === 'home') {
        return;
      }
      var count = changedIn(view, changed);
      view.classList.toggle('has-changes', count > 0);
      var said = form.querySelector('[data-section-count="' + name + '"]');
      if (said) {
        said.textContent = String(count);
        said.closest('.section-link').classList.toggle('has-changes', count > 0);
      }
    });
    if (banner) {
      var total = Object.keys(changed).length;
      banner.hidden = total === 0;
      var text = banner.querySelector('[data-overrides-text]');
      if (text) {
        // Both forms of the sentence come from the markup: they are translated and this is not.
        text.textContent = (text.getAttribute(total === 1 ? 'data-one' : 'data-many') || '').replace(':count', String(total));
      }
    }
  }

  /** Writes one control back as its row's default says, and says so as a person would. */
  function putBack(key, type) {
    // The field itself, not Quick start's mirror of it: the mirror follows.
    var row = rows().filter(function (candidate) {
      return candidate.getAttribute('data-control') === key && !candidate.closest('[data-quick]');
    })[0];
    if (!row) {
      return;
    }
    var value = row.getAttribute('data-default');
    var kind = row.getAttribute('data-kind');
    var touched = null;
    if (kind === 'by_hand') {
      touched = row.querySelector('[data-by-hand-switch]');
      if (touched) {
        touched.checked = false;
      }
    } else if (kind === 'secondary') {
      touched = row.querySelector('input[name="use_secondary"]');
      var colour = row.querySelector('input[type="color"]');
      if (touched) {
        touched.checked = value !== '';
      }
      if (colour && value !== '') {
        colour.value = value;
      }
    } else {
      var radios = [].slice.call(row.querySelectorAll('input[type="radio"]'));
      if (radios.length > 0) {
        radios.forEach(function (radio) {
          if (radio.value === value) {
            radio.checked = true;
            touched = radio;
          }
        });
      } else {
        touched = row.querySelector('input[type="range"], input[type="color"], select');
        if (touched) {
          touched.value = value;
        }
      }
    }
    // One control put back is a choice made, and announced as one; a whole section is sent
    // as INPUT events, which appearance.js waits a quarter-second after, so it asks the server
    // once rather than once per control.
    if (touched) {
      touched.dispatchEvent(new Event(type, { bubbles: true }));
    }
  }

  form.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('button[name="action"]') : null;
    var action = button ? button.value : '';
    if (!/^reset:/.test(action)) {
      return;
    }
    var scope = action.slice('reset:'.length);
    var within = null;
    if (scope === 'all') {
      within = form;
    } else if (/^section:/.test(scope)) {
      within = form.querySelector('[data-view="' + scope.slice('section:'.length) + '"]');
    }
    var keys = {};
    if (within) {
      rows(within).forEach(function (row) {
        keys[row.getAttribute('data-control')] = true;
      });
    } else {
      keys[scope] = true;
    }
    event.preventDefault();
    var type = Object.keys(keys).length === 1 ? 'change' : 'input';
    Object.keys(keys).forEach(function (key) {
      putBack(key, type);
    });
    update();
  });

  form.addEventListener('input', update);
  form.addEventListener('change', update);
  update();
})();
