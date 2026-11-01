/*
 * THE LIBRARY'S UPLOAD, ONE FILE AT A TIME, AND ITS PICTURES FINISHED BY THEMSELVES (PLAN.md
 * D-295; the owner, 2026-10-24: seventeen pictures dropped at once, twelve left "Unfinished",
 * each needing its own Finish processing, and nothing on screen but "Uploading…").
 *
 * All the files went in one request, with one time budget between them; what the budget did
 * not reach was left unfinished, on purpose, for a Finish press each. Now each file is its own
 * request, asking for JSON: it shows how much of it has gone, then its sizes are made, and
 * while the server answers that some are still missing, it is asked again (MediaController::
 * finish), as the media browser does for one picture (D-145). The form without a script
 * still posts every file at once, and the Finish buttons are still there.
 *
 * A picture left unfinished before this, or by a host that cut a request off, is finished the
 * same way when the library is opened: its row says so, and gets its thumbnail when done.
 *
 * media.js checks the sizes first and hands the files here (window.boxletLibraryUpload).
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-media-upload][data-drop-anywhere]');
  if (!form || !window.FormData || !window.XMLHttpRequest) {
    return;
  }
  var words = JSON.parse(form.getAttribute('data-upload-words') || '{}');
  var base = form.getAttribute('data-finish-url');
  var token = form.querySelector('input[name="_csrf"]').value;
  var panel = form.querySelector('[data-upload-progress]');
  var list = form.querySelector('[data-upload-list]');
  var total = form.querySelector('[data-upload-total]');
  var after = form.querySelector('[data-upload-after]');
  // As many times as a picture is asked to finish before it is left for the owner: each ask
  // makes progress or the host is cutting it off the same way every time (MediaController).
  var TRIES = 20;

  function fill(template, values) {
    return Object.keys(values).reduce(function (text, key) {
      return text.split(':' + key).join(values[key]);
    }, template || '');
  }

  function post(url, data, onProgress) {
    return new Promise(function (resolve, reject) {
      var request = new XMLHttpRequest();
      request.open('POST', url);
      request.setRequestHeader('Accept', 'application/json');
      request.responseType = 'json';
      if (onProgress && request.upload) {
        request.upload.addEventListener('progress', function (event) {
          if (event.lengthComputable) {
            onProgress(Math.round((event.loaded / event.total) * 100));
          }
        });
      }
      request.addEventListener('load', function () {
        if (request.status === 200 && request.response) {
          resolve(request.response);
        } else {
          reject(new Error(String(request.status)));
        }
      });
      request.addEventListener('error', function () { reject(new Error('network')); });
      request.send(data);
    });
  }

  /** Asks for a picture's missing sizes until it has them all, or the tries run out. */
  function finish(id, tries) {
    var data = new FormData();
    data.append('_csrf', token);
    return post(base + '/' + id + '/finish', data).then(function (answer) {
      if (answer.complete || tries + 1 >= TRIES) {
        return answer;
      }
      return finish(id, tries + 1);
    });
  }

  /** One row of the list: the file's name, its bar, and what is happening to it. */
  function row(file) {
    var item = document.createElement('li');
    item.className = 'upload-item';
    var name = document.createElement('span');
    name.className = 'upload-name';
    name.textContent = file.name;
    var bar = document.createElement('progress');
    bar.max = 100;
    bar.value = 0;
    bar.setAttribute('aria-label', file.name);
    var state = document.createElement('span');
    state.className = 'upload-state';
    state.textContent = words.waiting;
    item.appendChild(name);
    item.appendChild(bar);
    item.appendChild(state);
    list.appendChild(item);
    return {
      say: function (text, kind) {
        state.textContent = text;
        item.setAttribute('data-state', kind || '');
      },
      bar: function (value) { bar.value = value; },
    };
  }

  window.boxletLibraryUpload = function (files) {
    var queue = Array.prototype.slice.call(files);
    var done = 0;
    var failed = 0;
    list.textContent = '';
    after.hidden = true;
    panel.hidden = false;
    form.classList.add('is-uploading');
    var rows = queue.map(row);
    var count = function () {
      total.textContent = fill(words.progress, { done: String(done), total: String(queue.length) });
    };
    count();
    panel.scrollIntoView({ block: 'nearest' });

    // In order, one at a time: several at once would share the server's time again.
    return queue.reduce(function (chain, file, i) {
      return chain.then(function () {
        var line = rows[i];
        var data = new FormData();
        data.append('_csrf', token);
        data.append('files[]', file);
        line.say(fill(words.sending, { percent: '0' }), 'sending');
        return post(base, data, function (percent) {
          line.bar(percent);
          line.say(fill(words.sending, { percent: String(percent) }), 'sending');
        }).then(function (answer) {
          var stored = (answer.stored || [])[0];
          if (!stored) {
            failed++;
            line.say((answer.messages || []).join(' ') || words.failed, 'refused');
            return null;
          }
          // Already in the library: said so, and counted as there.
          if (stored.duplicate) {
            line.bar(100);
            line.say((answer.messages || []).join(' '), stored.complete ? 'done' : 'sizes');
            return stored.complete ? { complete: true, said: true } : finish(stored.id, 0);
          }
          if (stored.complete) {
            return stored;
          }
          line.say(words.sizes, 'sizes');
          return finish(stored.id, 0);
        }).then(function (result) {
          if (result === null) {
            return;
          }
          if (result.complete) {
            line.bar(100);
            if (!result.said) {
              line.say(words.done, 'done');
            }
          } else {
            failed++;
            line.say(words.unfinished, 'refused');
          }
        }, function () {
          failed++;
          line.say(words.failed, 'refused');
        }).then(function () {
          done++;
          count();
        });
      });
    }, Promise.resolve()).then(function () {
      form.classList.remove('is-uploading');
      // All in: the library drawn again shows them. Something refused: its reason stays on
      // screen, with the way to the library beside it.
      if (failed === 0) {
        window.location.reload();
      } else {
        after.hidden = false;
      }
    });
  };

  // A picture left unfinished, finished now, one after another, each row saying so (D-295).
  var waiting = Array.prototype.slice.call(document.querySelectorAll('[data-media-unfinished][data-media-id]'));
  if (waiting.length === 0) {
    return;
  }
  var status = document.createElement('p');
  status.className = 'upload-progress-total upload-finishing';
  status.setAttribute('role', 'status');
  // Above the list, where the rows that change are.
  var library = document.querySelector('.media-table') ? document.querySelector('.media-table').closest('.table-wrap') : document.querySelector('.media-grid');
  (library || form).parentNode.insertBefore(status, library || form);
  // What an unfinished row or card says while it is worked on: a row's badge, a card's hint.
  var sayPending = function (pending, text) {
    var target = pending && (pending.classList.contains('badge') ? pending : pending.querySelector('.hint'));
    if (target) {
      target.textContent = text;
    }
  };
  var finished = 0;
  var tell = function () {
    status.textContent = fill(words.finishing, { done: String(finished), total: String(waiting.length) });
  };
  tell();

  waiting.reduce(function (chain, item) {
    return chain.then(function () {
      var pending = item.querySelector('[data-media-pending]');
      sayPending(pending, words.sizes);
      return finish(item.getAttribute('data-media-id'), 0).then(function (answer) {
        if (!answer.complete) {
          sayPending(pending, words.unfinished);
          return;
        }
        if (pending) {
          pending.remove();
        }
        item.removeAttribute('data-media-unfinished');
        var place = item.querySelector('[data-media-thumb]');
        if (place && answer.thumb) {
          var img = place.querySelector('img.media-thumb');
          if (!img) {
            var size = place.getAttribute('data-media-thumb').split('x');
            img = document.createElement('img');
            img.className = 'media-thumb';
            img.alt = '';
            img.width = parseInt(size[0], 10);
            img.height = parseInt(size[1], 10);
            var empty = place.querySelector('.media-row-none, .media-thumb-none');
            if (empty) {
              empty.replaceWith(img);
            } else {
              place.insertBefore(img, place.firstChild);
            }
          }
          img.src = answer.thumb;
        }
      }, function () {
        sayPending(pending, words.unfinished);
      }).then(function () {
        finished++;
        tell();
      });
    });
  }, Promise.resolve()).then(function () {
    status.hidden = true;
  });
})();
