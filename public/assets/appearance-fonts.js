/*
 * THE TWO FAMILIES AND THE PAIRINGS (PLAN.md D-185), on Appearance's Typography.
 *
 * The pickers are radio inputs inside a <details> and need nothing from here to submit. This
 * adds the search over the library, the chosen family's name in the closed picker, and two
 * things a choice should do at once:
 *
 * - THE SLIDERS STILL FOLLOWING A FAMILY FOLLOW THE NEW ONE. A heading's weight, letter
 *   spacing and capitals are the heading family's, and the line height the text's, until the
 *   owner moves one. Each option says what its family brings (data-follows); a control that
 *   stands at its default (data-default, the server's) moves with it, and its default with it,
 *   so nothing reads as changed that the owner did not change.
 * - A PAIRING SETS BOTH FAMILIES and its treatment: '' for a value the family already brings,
 *   which is then the family's own.
 */
(function () {
  'use strict';

  var form = document.querySelector('form[data-design-form]');
  if (!form) {
    return;
  }

  function row(key) {
    return form.querySelector('[data-control="' + key + '"]');
  }

  /** A control's value, set as a person would: the event the preview and the readouts hear. */
  function set(key, value) {
    var radio = form.querySelector('input[type="radio"][name="' + key + '"][value="' + value + '"]');
    if (radio) {
      if (!radio.checked) {
        radio.checked = true;
        radio.dispatchEvent(new Event('change', { bubbles: true }));
      }
      return;
    }
    var input = form.querySelector('input[type="range"][name="' + key + '"]');
    if (input && input.value !== String(value)) {
      input.value = value;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }

  function current(key) {
    var radio = form.querySelector('input[type="radio"][name="' + key + '"]:checked');
    if (radio) { return radio.value; }
    var input = form.querySelector('input[type="range"][name="' + key + '"]');
    return input ? input.value : '';
  }

  function same(a, b) {
    return String(a) === String(b) || (!isNaN(parseFloat(a)) && Math.abs(parseFloat(a) - parseFloat(b)) < 1e-9);
  }

  /** A family chosen: its name in the closed picker, and what still follows it, moved. */
  function chosen(radio) {
    var picker = radio.closest('[data-font-picker]');
    if (picker) {
      var sample = picker.querySelector('summary .font-sample');
      sample.textContent = radio.parentElement.querySelector('.font-sample').textContent;
      sample.setAttribute('data-font', radio.value);
      picker.querySelector('[data-font-category]').textContent = radio.getAttribute('data-category');
    }
    var follows = JSON.parse(radio.getAttribute('data-follows') || '{}');
    Object.keys(follows).forEach(function (key) {
      var control = row(key);
      if (!control) { return; }
      var before = control.getAttribute('data-default');
      if (same(current(key), before)) {
        control.setAttribute('data-default', follows[key]);
        set(key, follows[key]);
      } else {
        control.setAttribute('data-default', follows[key]);
      }
    });
    var specimen = document.querySelector('.specimen');
    if (specimen) {
      specimen.setAttribute(radio.name === 'heading_font' ? 'data-heading-font' : 'data-body-font', radio.value);
    }
    pressed();
  }

  /** The pairing the two families make now, pressed. */
  function pressed() {
    var heading = current('heading_font');
    var body = current('body_font');
    [].forEach.call(document.querySelectorAll('.pairing-tile'), function (tile) {
      var values = JSON.parse(tile.getAttribute('data-pairing'));
      tile.setAttribute('aria-pressed', String(values.heading_font === heading && values.body_font === body));
    });
  }

  form.addEventListener('change', function (event) {
    var target = event.target;
    if (target.matches && target.matches('input[name="heading_font"], input[name="body_font"]')) {
      chosen(target);
    }
  });

  document.addEventListener('click', function (event) {
    var tile = event.target.closest('.pairing-tile');
    if (!tile) { return; }
    var values = JSON.parse(tile.getAttribute('data-pairing'));
    set('heading_font', values.heading_font);
    set('body_font', values.body_font);
    ['heading_weight', 'tracking', 'caps', 'line_height'].forEach(function (key) {
      var control = row(key);
      set(key, values[key] !== '' ? values[key] : (control ? control.getAttribute('data-default') : ''));
    });
    pressed();
  });

  // The search: what matches by name, in its group; a group with none put away.
  [].forEach.call(form.querySelectorAll('[data-font-search]'), function (search) {
    search.addEventListener('input', function () {
      var words = search.value.trim().toLowerCase();
      var picker = search.closest('[data-font-picker]');
      [].forEach.call(picker.querySelectorAll('[data-font-group]'), function (group) {
        var any = false;
        [].forEach.call(group.querySelectorAll('.font-option'), function (option) {
          var match = words === '' || option.getAttribute('data-font-name').indexOf(words) >= 0;
          option.hidden = !match;
          any = any || match;
        });
        group.hidden = !any;
      });
    });
    // Enter in the search chooses nothing and sends nothing.
    search.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') { event.preventDefault(); }
    });
  });
})();
