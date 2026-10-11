/*
 * THE INSTALLER AT WORK.
 *
 * The site step (PLAN.md D-212): once the form is sent, its button says so and stops taking
 * presses, "Start over" too (it would throw away an install half done), and a moving bar with
 * a sentence shows under it until the next page arrives.
 *
 * The demo's pictures (D-214): one request's worth after another, sent without leaving the
 * page, the bar and the count brought up to date from each answer, until the last brings the
 * finished install. A request the server or Cloudflare does not answer (a 524, a 500) is sent
 * again a few seconds later: a cut-short request loses nothing, the next goes on from where it
 * got. Ten failures in a row and it stops, with Continue to press.
 *
 * Without script both forms post as before, the second by its Continue button.
 */
(function () {
  'use strict';

  var form = document.querySelector('form[data-install-busy]');
  if (form) {
    var button = form.querySelector('button[type="submit"]');
    var busy = form.querySelector('.install-busy');
    var label = button ? button.textContent : '';
    var restart = document.querySelectorAll('form.restart button');
    var lock = function (locked) {
      Array.prototype.forEach.call(restart, function (b) { b.disabled = locked; });
    };

    // 'submit' fires only once the browser's own checks have passed.
    form.addEventListener('submit', function () {
      form.setAttribute('aria-busy', 'true');
      if (button) {
        button.disabled = true;
        button.textContent = button.getAttribute('data-busy-label') || label;
      }
      if (busy) {
        busy.hidden = false;
      }
      lock(true);
    });

    // Back from the next page, the browser may show this one as it was left: ready again.
    window.addEventListener('pageshow', function (event) {
      if (!event.persisted) {
        return;
      }
      form.removeAttribute('aria-busy');
      if (button) {
        button.disabled = false;
        button.textContent = label;
      }
      if (busy) {
        busy.hidden = true;
      }
      lock(false);
    });
  }

  var onward = document.querySelector('form[data-install-continue]');
  if (onward && window.fetch && window.DOMParser) {
    var next = onward.querySelector('button[type="submit"]');
    var idle = next ? next.textContent : '';
    var bar = onward.querySelector('progress');
    var count = document.getElementById('install-count');
    var retry = onward.querySelector('[data-install-retry]');
    var failures = 0;
    var working = function (on) {
      if (next) {
        next.disabled = on;
        next.textContent = on ? (next.getAttribute('data-busy-label') || idle) : idle;
      }
    };
    // The installer's own answer, drawn as the page: the finished install, or what went wrong.
    var show = function (doc) {
      document.title = doc.title;
      document.body.innerHTML = doc.body.innerHTML;
    };
    var step = function () {
      fetch(onward.action, { method: 'POST', body: new FormData(onward), credentials: 'same-origin' })
        .then(function (response) {
          return response.text().then(function (html) {
            return { status: response.status, doc: new DOMParser().parseFromString(html, 'text/html') };
          });
        })
        .then(function (answer) {
          var nextBar = answer.doc.querySelector('progress.install-progress');
          if (answer.status >= 500 || (nextBar === null && answer.doc.querySelector('.card') === null)) {
            throw new Error('no answer');
          }
          if (nextBar === null) {
            show(answer.doc);
            return;
          }
          failures = 0;
          if (retry) {
            retry.hidden = true;
          }
          bar.max = nextBar.max;
          bar.value = nextBar.value;
          var nextCount = answer.doc.getElementById('install-count');
          if (count && nextCount) {
            count.textContent = nextCount.textContent;
          }
          step();
        })
        .catch(function () {
          failures += 1;
          if (failures >= 10) {
            if (retry) {
              retry.textContent = next ? next.getAttribute('data-stopped') : retry.textContent;
            }
            working(false);
            return;
          }
          if (retry) {
            retry.hidden = false;
          }
          window.setTimeout(step, 3000);
        });
    };
    working(true);
    onward.addEventListener('submit', function (event) {
      event.preventDefault();
      failures = 0;
      working(true);
      step();
    });
    window.setTimeout(step, 300);
  }
})();
