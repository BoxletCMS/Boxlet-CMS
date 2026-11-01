/*
 * THE VISITOR'S YES OR NO TO THE OWNER'S SCRIPTS (PLAN.md O-73).
 *
 * A statistics or marketing script is in the page inside a <template data-consent-kind>, which
 * the browser never runs. This file shows the box to a visitor who has not answered, remembers
 * the answer in their own browser (localStorage, the one thing kept, and necessary), and puts a
 * template's content into the page for a kind they said yes to — each <script> made anew, since
 * one copied out of a template does not run.
 *
 * Changing one's mind: "Cookie settings" in the footer opens the box with the choices. A kind
 * taken back has its listed cookies removed and the page reloaded, so the script stops.
 * Without this file nothing waiting for consent runs at all.
 */
(function () {
  'use strict';

  var box = document.querySelector('[data-consent]');
  if (!box) {
    return;
  }
  var key = box.getAttribute('data-consent-key');
  var choices = box.querySelector('[data-consent-choices]');
  var more = box.querySelector('[data-consent-more]');
  var save = box.querySelector('[data-consent-save]');
  var switches = Array.prototype.slice.call(box.querySelectorAll('[data-consent-choice]'));
  var asked = switches.map(function (input) { return input.getAttribute('data-consent-choice'); });

  function read() {
    try {
      var stored = JSON.parse(window.localStorage.getItem(key) || 'null');
      return stored && typeof stored === 'object' && stored.kinds && typeof stored.kinds === 'object' ? stored.kinds : null;
    } catch (e) {
      return null;
    }
  }

  function write(kinds) {
    try {
      window.localStorage.setItem(key, JSON.stringify({ kinds: kinds, at: new Date().toISOString() }));
    } catch (e) {
      // A browser that keeps nothing asks again on the next page; the scripts still follow
      // the answer on this one.
    }
  }

  /** Whether every kind this page asks about has an answer. */
  function answered(kinds) {
    return kinds !== null && asked.every(function (kind) { return typeof kinds[kind] === 'boolean'; });
  }

  function run(kinds) {
    Array.prototype.forEach.call(document.querySelectorAll('template[data-consent-kind]'), function (template) {
      if (template.hasAttribute('data-consent-ran') || kinds[template.getAttribute('data-consent-kind')] !== true) {
        return;
      }
      template.setAttribute('data-consent-ran', '');
      var content = template.content.cloneNode(true);
      Array.prototype.forEach.call(content.querySelectorAll('script'), function (old) {
        var fresh = document.createElement('script');
        Array.prototype.forEach.call(old.attributes, function (attribute) {
          fresh.setAttribute(attribute.name, attribute.value);
        });
        // In the order written, as the parser would have run them, unless the owner said async.
        if (fresh.src && !old.hasAttribute('async')) {
          fresh.async = false;
        }
        fresh.text = old.text;
        old.parentNode.replaceChild(fresh, old);
      });
      template.parentNode.insertBefore(content, template.nextSibling);
    });
  }

  /** The listed cookies of a kind taken back, on this host and the domains above it. */
  function forget(kind) {
    var row = box.querySelector('[data-consent-kind-row="' + kind + '"]');
    var names = row ? (row.getAttribute('data-consent-cookies') || '').split(' ').filter(Boolean) : [];
    var have = document.cookie.split(';').map(function (part) { return part.split('=')[0].trim(); });
    var hosts = [];
    var parts = window.location.hostname.split('.');
    for (var i = 0; i < parts.length - 1; i++) {
      hosts.push(parts.slice(i).join('.'));
    }
    have.forEach(function (name) {
      var listed = names.some(function (wanted) {
        // "_ga_XXXX" and "_ga_*" both stand for every _ga_ cookie: the owner copies what a
        // provider's documentation says, where the end of a name is the account's.
        var stem = wanted.replace(/(\*|X{2,})$/, '');
        return name === wanted || (stem !== wanted && name.indexOf(stem) === 0);
      });
      if (!listed) {
        return;
      }
      document.cookie = name + '=; max-age=0; path=/';
      hosts.forEach(function (host) {
        document.cookie = name + '=; max-age=0; path=/; domain=' + host;
      });
    });
  }

  function decide(kinds) {
    var before = read() || {};
    write(kinds);
    var takenBack = asked.filter(function (kind) { return before[kind] === true && kinds[kind] !== true; });
    if (takenBack.length > 0) {
      takenBack.forEach(forget);
      window.location.reload();
      return;
    }
    box.hidden = true;
    run(kinds);
  }

  function open(withChoices) {
    var kinds = read() || {};
    switches.forEach(function (input) {
      input.checked = kinds[input.getAttribute('data-consent-choice')] === true;
    });
    choices.hidden = !withChoices;
    save.hidden = !withChoices;
    more.hidden = withChoices;
    more.setAttribute('aria-expanded', withChoices ? 'true' : 'false');
    box.hidden = false;
  }

  Array.prototype.forEach.call(box.querySelectorAll('[data-consent-all]'), function (button) {
    button.addEventListener('click', function () {
      var yes = button.getAttribute('data-consent-all') === 'yes';
      var kinds = {};
      asked.forEach(function (kind) { kinds[kind] = yes; });
      decide(kinds);
    });
  });
  more.addEventListener('click', function () {
    open(true);
    choices.querySelector('input:not([disabled])') && choices.querySelector('input:not([disabled])').focus();
  });
  save.addEventListener('click', function () {
    var kinds = {};
    switches.forEach(function (input) { kinds[input.getAttribute('data-consent-choice')] = input.checked; });
    decide(kinds);
  });
  Array.prototype.forEach.call(document.querySelectorAll('[data-consent-open]'), function (link) {
    link.hidden = false;
    link.addEventListener('click', function (event) {
      event.preventDefault();
      open(true);
      box.focus();
    });
  });

  var kinds = read();
  if (answered(kinds)) {
    run(kinds);
  } else {
    open(false);
  }
})();
