/*
 * AN AI TRANSLATION, ONE PART AFTER ANOTHER (PLAN.md D-248): the screen asks the server to
 * translate the next waiting part, draws how far it is, and asks again until none is waiting.
 * A lost connection is tried three times, then said; the parts wait in the database, so
 * coming back to the screen carries on. A run the server stops (a wrong key, no credit) says
 * why and stops. Without this script, "Translate the next part" does one part per press; with
 * it, the button is hidden while the parts go by themselves, and each page says how far it is:
 * translating, waiting, done (D-259).
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-ai-run]');
  if (!root || !window.fetch) {
    return;
  }
  var words = {};
  try {
    words = JSON.parse(root.getAttribute('data-ai-words') || '{}');
  } catch (e) {
    words = {};
  }
  var bar = root.querySelector('[data-ai-bar]');
  var count = root.querySelector('[data-ai-count]');
  var stopped = root.querySelector('[data-ai-stopped]');
  var next = root.querySelector('[data-ai-next]');
  var running = root.querySelector('[data-ai-running]');
  if (next) {
    next.hidden = true;
  }
  if (running) {
    running.hidden = false;
  }

  // The run has stopped: the button back, for the owner to carry on by hand.
  function halt(message) {
    stopped.textContent = message;
    stopped.hidden = false;
    if (next) { next.hidden = false; }
    if (running) { running.hidden = true; }
  }

  function draw(progress) {
    var finished = progress.done + progress.failed;
    bar.max = progress.total;
    bar.value = finished;
    count.textContent = (words.of || ':done / :total').replace(':done', String(finished)).replace(':total', String(progress.total));
    var working = null;
    (progress.pages || []).forEach(function (page) {
      var state = root.querySelector('[data-ai-page="' + page.id + '"] [data-ai-page-state]');
      if (!state) {
        return;
      }
      var word = page.pending > 0 ? (working === null ? words.working : words.waiting) : (page.failed > 0 ? words.failed : words.done);
      if (page.pending > 0 && working === null) {
        working = page.id;
      }
      state.textContent = word || '';
      // A page never published takes its translated title as its part is done (D-253).
      var link = state.parentNode.querySelector('a');
      if (link && page.title) {
        link.textContent = page.title;
      }
    });
  }

  function step(tries) {
    fetch(root.getAttribute('data-ai-step'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': root.getAttribute('data-ai-csrf') },
    }).then(function (response) {
      return response.json().then(function (body) { return { ok: response.ok, body: body }; });
    }).then(function (answer) {
      if (answer.body && answer.body.progress) {
        draw(answer.body.progress);
      }
      if (!answer.ok) {
        halt((answer.body && answer.body.stopped) || words.lost || '');
        return;
      }
      if (answer.body.progress.pending > 0) {
        step(0);
        return;
      }
      // Done: the screen again, with what was done and where to go.
      window.location.reload();
    }).catch(function () {
      if (tries < 3) {
        count.textContent = words.retrying || '';
        window.setTimeout(function () { step(tries + 1); }, 2000 * (tries + 1));
        return;
      }
      halt(words.lost || '');
    });
  }

  step(0);
})();
