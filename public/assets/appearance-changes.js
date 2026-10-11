/*
 * HOW MANY CHANGES ARE NOT PUBLISHED (PLAN.md D-181, README 5.8): the bar says "3 unpublished
 * changes" rather than only "not published yet", and says it as soon as the screen is drawn —
 * after a character is loaded too, when nothing has been touched and every value moved.
 *
 * Counted, not remembered: the server hands the screen what the published site's controls
 * show, field by field (`data-published-fields`), and a change is a key whose control shows
 * something else now. A value put back by hand, or by undo, is no change. A colour the
 * palette works out is compared only where one side has it switched on as the owner's own.
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-design-form]');
  if (!form) {
    return;
  }
  var published = {};
  try {
    published = JSON.parse(form.getAttribute('data-published-fields') || '{}');
  } catch (e) {
    published = {};
  }
  // A value's switch: the colours that can be the owner's, and the second colour.
  var SWITCH = { secondary: 'use_secondary' };
  Object.keys(published).forEach(function (key) {
    var m = /^(.+)_on$/.exec(key);
    if (m) { SWITCH[m[1]] = key; }
  });

  function same(a, b) {
    a = String(a == null ? '' : a).trim().toLowerCase();
    b = String(b == null ? '' : b).trim().toLowerCase();
    if (a === b) {
      return true;
    }
    return a !== '' && b !== '' && !isNaN(Number(a)) && !isNaN(Number(b)) && Number(a) === Number(b);
  }

  /** The keys whose control shows something other than the published site. */
  function changes() {
    var now = {};
    new FormData(form).forEach(function (value, key) {
      if (typeof value === 'string') { now[key] = value; }
    });
    var found = [];
    Object.keys(published).forEach(function (key) {
      if (/_on$/.test(key) || key === 'use_secondary') {
        return;
      }
      var gate = SWITCH[key];
      if (gate) {
        var was = published[gate] === '1';
        var is = now[gate] === '1';
        if (was !== is || (is && !same(published[key], now[key]))) { found.push(key); }
        return;
      }
      if (!(key in now)) {
        return;
      }
      if (!same(published[key], now[key])) { found.push(key); }
    });
    return found;
  }

  window.boxletAppearanceChanges = { list: changes, count: function () { return changes().length; } };

  // Said once every script listens (the bar's is loaded after this): a character loaded is
  // changes nobody typed.
  document.addEventListener('DOMContentLoaded', function () {
    if (changes().length > 0) {
      document.dispatchEvent(new CustomEvent('appearance:state', { detail: { state: 'unpublished' } }));
    }
  });
})();
