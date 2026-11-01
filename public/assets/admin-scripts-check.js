/*
 * CHECK WHAT THE SITE SETS (PLAN.md O-73; the owner: "kako ćemo zapravo znati koje cookie admin
 * servira posjetiteljima?").
 *
 * The site's home page is opened in a hidden frame of this page, with every kind allowed (the
 * visitor's answer, which site-consent.js reads, is set to yes for a moment), and after a few
 * seconds the cookies and storage on the site's own domain are read — the frame is the same
 * site, so it can be. Each name no script lists is offered as a row for this one. Then this
 * browser is put back as it was: the answer restored, and what the check made removed.
 *
 * What a script sets on another domain is not on this one, and no page can read it: those the
 * owner names from the provider's documentation.
 */
(function () {
  'use strict';

  var panel = document.querySelector('[data-scripts-check]');
  if (!panel) {
    return;
  }
  var run = panel.querySelector('[data-scripts-check-run]');
  var result = panel.querySelector('[data-scripts-check-result]');
  var listed = (panel.getAttribute('data-listed') || '').split(' ').filter(Boolean);
  var KEY = 'boxlet-consent';
  var WAIT = 5000;

  function names(cookieString) {
    return cookieString.split(';').map(function (part) { return part.split('=')[0].trim(); }).filter(Boolean);
  }

  function keys(storage) {
    var all = [];
    try {
      for (var i = 0; i < storage.length; i++) {
        all.push(storage.key(i));
      }
    } catch (e) {
      // Storage refused: nothing to read there.
    }
    return all;
  }

  /** Whether a listed name covers $name: equal, or its stem before a * or XXXX. */
  function covered(name) {
    return listed.some(function (wanted) {
      var stem = wanted.replace(/(\*|X{2,})$/, '');
      return name === wanted || (stem !== wanted && name.indexOf(stem) === 0);
    });
  }

  function forgetCookie(name) {
    var parts = window.location.hostname.split('.');
    document.cookie = name + '=; max-age=0; path=/';
    for (var i = 0; i < parts.length - 1; i++) {
      document.cookie = name + '=; max-age=0; path=/; domain=' + parts.slice(i).join('.');
    }
  }

  /** The first empty row of cookies, or a new one copied from the last. */
  function emptyRow() {
    var rows = document.querySelectorAll('[data-scripts-cookie]');
    for (var i = 0; i < rows.length; i++) {
      if (rows[i].querySelector('input').value === '') {
        return rows[i];
      }
    }
    var last = rows[rows.length - 1];
    var copy = last.cloneNode(true);
    Array.prototype.forEach.call(copy.querySelectorAll('input'), function (input) {
      input.value = '';
      input.name = input.name.replace(/cookies\[(\d+)\]/, function (match, n) { return 'cookies[' + (Number(n) + 1) + ']'; });
    });
    last.parentNode.appendChild(copy);
    return copy;
  }

  function show(found) {
    result.textContent = '';
    if (found.length === 0) {
      var none = document.createElement('p');
      none.className = 'hint';
      none.textContent = panel.getAttribute('data-none');
      result.appendChild(none);
      return;
    }
    var said = document.createElement('p');
    said.className = 'hint';
    said.textContent = panel.getAttribute('data-found');
    var list = document.createElement('ul');
    list.className = 'scripts-check-list';
    found.forEach(function (name) {
      var item = document.createElement('li');
      var code = document.createElement('code');
      code.textContent = name;
      var add = document.createElement('button');
      add.type = 'button';
      add.className = 'button button-secondary button-small';
      add.textContent = panel.getAttribute('data-add');
      add.addEventListener('click', function () {
        var row = emptyRow();
        var inputs = row.querySelectorAll('input');
        inputs[0].value = name;
        listed.push(name);
        item.remove();
        (inputs[2] || inputs[0]).focus();
      });
      item.appendChild(code);
      item.appendChild(add);
      list.appendChild(item);
    });
    result.appendChild(said);
    result.appendChild(list);
  }

  run.addEventListener('click', function () {
    run.disabled = true;
    result.textContent = panel.getAttribute('data-working');
    var cookiesBefore = names(document.cookie);
    var storedBefore = keys(window.localStorage);
    var answer = null;
    try {
      answer = window.localStorage.getItem(KEY);
      window.localStorage.setItem(KEY, JSON.stringify({ kinds: { statistics: true, marketing: true }, at: new Date().toISOString() }));
    } catch (e) {
      // Without storage the frame's scripts stay waiting: the check finds less, and says so.
    }
    var frame = document.createElement('iframe');
    frame.className = 'scripts-check-frame';
    frame.setAttribute('aria-hidden', 'true');
    frame.setAttribute('tabindex', '-1');
    frame.src = panel.getAttribute('data-home');
    var finish = function () {
      var found = [];
      try {
        var doc = frame.contentWindow.document;
        names(doc.cookie).concat(keys(frame.contentWindow.localStorage), keys(frame.contentWindow.sessionStorage)).forEach(function (name) {
          if (name !== KEY && name.indexOf('boxlet') !== 0 && found.indexOf(name) === -1 && !covered(name)) {
            found.push(name);
          }
        });
      } catch (e) {
        result.textContent = panel.getAttribute('data-failed');
      }
      // Put this browser back: the answer as it was, and what the check made removed.
      try {
        if (answer === null) {
          window.localStorage.removeItem(KEY);
        } else {
          window.localStorage.setItem(KEY, answer);
        }
        keys(window.localStorage).forEach(function (name) {
          if (storedBefore.indexOf(name) === -1) {
            window.localStorage.removeItem(name);
          }
        });
      } catch (e) {
        // Nothing was stored, so nothing is to be put back.
      }
      names(document.cookie).forEach(function (name) {
        if (cookiesBefore.indexOf(name) === -1) {
          forgetCookie(name);
        }
      });
      frame.remove();
      run.disabled = false;
      if (result.textContent !== panel.getAttribute('data-failed')) {
        show(found);
      }
    };
    frame.addEventListener('load', function () {
      window.setTimeout(finish, WAIT);
    });
    document.body.appendChild(frame);
  });
})();
