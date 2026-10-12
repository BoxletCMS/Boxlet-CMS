/*
 * THE MAPS' DOWNLOAD, WHERE ITS BUTTONS ARE (PLAN.md D-237, the owner): a location database
 * fetched piece by piece without leaving the page, its bar and its words beside the buttons.
 * Before, each piece was a whole page loaded again, and what happened was said at the top of
 * the page while the page stood at this part.
 *
 * Each step is a POST the panel's own buttons make, asking for JSON. A dropped connection is
 * tried again three times, then said, and Continue carries on from what came down: the part
 * file on the server is where it resumes. Leaving the page stops it; coming back carries on.
 * Without this script the buttons post and the settings come back, at this part.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-geo]');
  if (!root || !window.fetch) {
    return;
  }
  var words = {};
  try {
    words = JSON.parse(root.getAttribute('data-geo-words') || '{}');
  } catch (e) {
    words = {};
  }
  var csrf = root.getAttribute('data-geo-csrf') || '';
  var stepUrl = root.getAttribute('data-geo-step') || '';
  var box = root.querySelector('[data-geo-progress]');
  var bar = root.querySelector('[data-geo-bar]');
  var text = root.querySelector('[data-geo-text]');
  var busy = root.querySelector('[data-geo-busy]');
  var idle = root.querySelector('[data-geo-idle]');
  var inUse = root.querySelector('[data-geo-in-use]');
  var resume = root.querySelector('[data-geo-continue]');
  var stopped = false;
  var lastSaid = '';

  /** Continue is for a download that stopped: while this script carries on, it is not shown. */
  function offerContinue(shown) {
    if (resume) {
      resume.hidden = !shown;
    }
  }

  function notice(message, error) {
    var said = root.querySelector('[data-geo-notice]');
    if (!said) {
      said = document.createElement('p');
      said.setAttribute('data-geo-notice', '');
      inUse.insertAdjacentElement('afterend', said);
    }
    said.className = 'notice ' + (error ? 'notice-error' : 'notice-success');
    said.setAttribute('role', error ? 'alert' : 'status');
    said.textContent = message || '';
    said.hidden = !message;
  }

  /** The panel while a database comes down: the bar, the words, Continue and Cancel. */
  function downloading(state) {
    idle.hidden = true;
    busy.hidden = false;
    box.hidden = false;
    if (state && state.total > 0) {
      bar.max = state.total;
      bar.value = state.done;
    } else {
      bar.removeAttribute('value');
    }
    if (state) {
      lastSaid = state.message + (state.last ? ' ' + (words.unpacking || '') : '');
      text.textContent = lastSaid;
    }
  }

  /** The panel at rest again: the start buttons, and what happened. */
  function atRest(message, error) {
    box.hidden = true;
    busy.hidden = true;
    idle.hidden = false;
    notice(message, error);
  }

  function post(url) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf },
    }).then(function (response) {
      return response.json().then(function (body) {
        return { ok: response.ok, body: body };
      });
    });
  }

  function step(tries) {
    if (stopped) {
      return;
    }
    post(stepUrl).then(function (answer) {
      if (stopped) {
        return;
      }
      if (!answer.ok) {
        // What the server refused it with; the bar stays where it got to, and Continue.
        notice(answer.body.message, true);
        offerContinue(true);
        return;
      }
      if (answer.body.phase === 'installed') {
        if (answer.body.inUse) {
          inUse.textContent = answer.body.inUse;
        }
        atRest(answer.body.message, false);
        return;
      }
      downloading(answer.body);
      step(0);
    }).catch(function () {
      if (stopped) {
        return;
      }
      if (tries < 3) {
        text.textContent = words.retrying || '';
        window.setTimeout(function () { step(tries + 1); }, 2000 * (tries + 1));
        return;
      }
      // Under the bar, where it got to; above it, that the line was lost.
      text.textContent = lastSaid;
      notice(words.lost, true);
      offerContinue(true);
    });
  }

  Array.prototype.forEach.call(root.querySelectorAll('[data-geo-start]'), function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      stopped = false;
      notice('', false);
      downloading(null);
      text.textContent = words.finding || '';
      post(form.action).then(function (answer) {
        if (!answer.ok) {
          atRest(answer.body.message, true);
          return;
        }
        offerContinue(false);
        downloading(answer.body);
        step(0);
      }).catch(function () {
        atRest(words.lost, true);
      });
    });
  });

  if (resume) {
    resume.addEventListener('submit', function (event) {
      event.preventDefault();
      stopped = false;
      notice('', false);
      offerContinue(false);
      downloading(null);
      step(0);
    });
  }

  var cancel = root.querySelector('[data-geo-cancel]');
  if (cancel) {
    cancel.addEventListener('submit', function (event) {
      event.preventDefault();
      stopped = true;
      post(cancel.action).then(function (answer) {
        atRest(answer.body.message, false);
      }).catch(function () {
        atRest(words.lost, true);
      });
    });
  }

  // Part-way down when the page was opened: carry on.
  if (root.hasAttribute('data-geo-running')) {
    offerContinue(false);
    step(0);
  }
})();
