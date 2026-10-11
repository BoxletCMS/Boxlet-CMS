/*
 * QUICK START'S MIRRORS (PLAN.md D-157). The main colour, the typeface, the text size, the
 * corners and the spacing are asked again at the top of the home, because they are what a
 * person reaches for first — and a field can only be in a form once.
 *
 * So the real field stays in its section, and the copy at the top belongs to an empty form of
 * its own (#appearance-quick) that is never sent: it is not posted twice, and two radio groups
 * of one name are not one group. This file is what makes the copy a control at all — without
 * it the copies stay hidden, because a control that changes nothing is worse than none — and
 * it keeps the two the same in both directions.
 */
(function () {
  'use strict';

  var quick = document.querySelector('[data-quick]');
  var form = document.getElementById('design-form');
  if (!quick || !form) {
    return;
  }

  /** The control in the design form that a mirror stands for, or that stands for a mirror. */
  function counterpart(input, inForm) {
    var owner = inForm ? form : document.getElementById('appearance-quick');
    var named = owner ? owner.elements.namedItem(input.name) : null;
    if (!named) {
      return null;
    }
    if (input.type === 'radio') {
      var all = named.length !== undefined ? [].slice.call(named) : [named];
      return all.filter(function (radio) {
        return radio.value === input.value;
      })[0] || null;
    }
    return named.length !== undefined && !named.tagName ? named[0] : named;
  }

  function copy(from, to) {
    if (from.type === 'radio') {
      to.checked = from.checked;
    } else {
      to.value = from.value;
    }
  }

  // A mirror moved: the real field takes the value and is the one that announces it, so the
  // preview and the dots hear of a field in the form and nothing else.
  ['input', 'change'].forEach(function (type) {
    quick.addEventListener(type, function (event) {
      var input = event.target;
      if (!input.name || input.form === form) {
        return;
      }
      event.stopPropagation();
      if (input.type === 'radio' && !input.checked) {
        return;
      }
      var real = counterpart(input, true);
      if (real) {
        copy(input, real);
        real.dispatchEvent(new Event(type, { bubbles: true }));
      }
    });
    // A field moved — by hand, by a reset, by a mirror — and its mirror follows.
    form.addEventListener(type, function (event) {
      var input = event.target;
      if (!input.name || input.form !== form) {
        return;
      }
      var mirror = counterpart(input, false);
      if (mirror && (input.type !== 'radio' || input.checked)) {
        copy(input, mirror);
      }
    });
  });

  quick.hidden = false;
})();
