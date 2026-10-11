/*
 * THE PAGE AS THE BUILDER HOLDS IT (PLAN.md D-163, D-175): the document, its history, and its
 * autosave. Everything else in the builder reads the document from here and changes it only
 * through change(), so there is one place that knows what changed, one undo stack and one
 * save.
 *
 * THE DOCUMENT is PageDocument's shape: settings, `sections` in page order, `blocks` each
 * naming its section's key and its column. A block's place down its column is its order among
 * the blocks of that column in the list.
 *
 * UNDO IS A SNAPSHOT of the whole document before each change (README 4: ⌘Z / ⇧⌘Z, structural
 * changes included). A page is small enough to copy, and a snapshot cannot drift from what it
 * undoes the way a list of inverse operations can. A run of changes to one control — a slider
 * dragged — is one step: it names a `coalesce` key, and a change with the same key within a
 * moment does not push another.
 *
 * THE AUTOSAVE (README 2.2) sends the whole document to /draft 800ms after the last change,
 * naming the version it was made from. A conflict stops it and says so; a failure is retried,
 * further apart each time, and says so too.
 */
(function () {
  'use strict';

  var holder = document.querySelector('[data-pb-data]');
  var root = document.querySelector('[data-pb]');
  if (!holder || !root) {
    return;
  }
  var data = JSON.parse(holder.textContent);
  var pb = window.pb = { data: data, doc: data.document, version: data.version, csrf: root.getAttribute('data-csrf') };
  var listeners = {};
  var undo = [];
  var redo = [];
  var lastCoalesce = null;
  var lastAt = 0;
  var LIMIT = 100;

  pb.on = function (event, fn) { (listeners[event] = listeners[event] || []).push(fn); };
  pb.off = function (event, fn) { listeners[event] = (listeners[event] || []).filter(function (f) { return f !== fn; }); };
  pb.emit = function (event, detail) { (listeners[event] || []).forEach(function (fn) { fn(detail); }); };

  /** A word from the admin's language files, its :placeholders filled. */
  pb.t = function (key, vars) {
    var text = data.strings[key] || key;
    Object.keys(vars || {}).forEach(function (name) { text = text.split(':' + name).join(String(vars[name])); });
    return text;
  };

  /** A JSON request to one of the builder's endpoints; the token travels in a header. */
  pb.api = function (url, body, method) {
    return fetch(url, {
      method: method || 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': pb.csrf, Accept: 'application/json' },
      body: body === undefined ? undefined : JSON.stringify(body),
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (json) { return { status: response.status, json: json }; });
    });
  };

  pb.copy = function (value) { return JSON.parse(JSON.stringify(value)); };
  /** What the server last said is wrong with a block, by key, then by field path (D-178). */
  pb.errors = {};

  /** A key no section or block of the document answers to: `m…` for a band, `n…` for a block. */
  pb.mint = function (prefix) {
    var taken = {};
    pb.doc.sections.forEach(function (s) { taken[s.key] = true; });
    pb.doc.blocks.forEach(function (b) { taken[b.key] = true; });
    var n = pb.doc.sections.length + pb.doc.blocks.length;
    while (taken[prefix + n]) { n += 1; }
    return prefix + n;
  };

  pb.section = function (key) { return pb.doc.sections.filter(function (s) { return s.key === key; })[0] || null; };
  pb.block = function (key) { return pb.doc.blocks.filter(function (b) { return b.key === key; })[0] || null; };
  pb.sectionIndex = function (key) { return pb.doc.sections.map(function (s) { return s.key; }).indexOf(key); };
  /** The blocks a band holds, column by column, each column top to bottom. */
  pb.blocksIn = function (key) {
    return pb.doc.blocks.filter(function (b) { return b.section === key; })
      .map(function (b, i) { return { b: b, i: i }; })
      .sort(function (x, y) { return (x.b.column - y.b.column) || (x.i - y.i); })
      .map(function (x) { return x.b; });
  };
  /** What a band is called: its name, else "Section N". */
  pb.sectionName = function (key) {
    var section = pb.section(key);
    var name = section && section.style && section.style.name ? String(section.style.name) : '';
    return name !== '' ? name : pb.t('section_n', { n: pb.sectionIndex(key) + 1 });
  };
  pb.libraryItem = function (type) { return data.library.filter(function (item) { return item.type === type; })[0] || null; };

  /**
   * Changes the document. `apply` changes pb.doc in place; `opts.sections` names the bands to
   * draw again, `opts.structure` says sections were added, removed or moved, `opts.coalesce`
   * folds a run of the same change into one undo step.
   */
  pb.change = function (apply, opts) {
    opts = opts || {};
    var now = Date.now();
    var folded = opts.coalesce && opts.coalesce === lastCoalesce && now - lastAt < 1500;
    if (!folded) {
      undo.push(JSON.stringify(pb.doc));
      if (undo.length > LIMIT) { undo.shift(); }
      redo = [];
    }
    lastCoalesce = opts.coalesce || null;
    lastAt = now;
    apply(pb.doc);
    pb.emit('change', { sections: opts.sections || [], structure: !!opts.structure, quiet: !!opts.quiet });
    pb.emit('history', { undo: undo.length, redo: redo.length });
    schedule();
  };

  function travel(from, to) {
    if (from.length === 0) {
      return;
    }
    var before = pb.doc;
    to.push(JSON.stringify(pb.doc));
    pb.doc = JSON.parse(from.pop());
    lastCoalesce = null;
    pb.emit('replace', { before: before });
    pb.emit('history', { undo: undo.length, redo: redo.length });
    schedule();
  }
  pb.undo = function () { travel(undo, redo); };
  pb.redo = function () { travel(redo, undo); };

  /** The whole document replaced from the server (Discard, a restore): no undo crosses it. */
  pb.reset = function (document, version) {
    var before = pb.doc;
    pb.doc = document;
    pb.version = version;
    undo = [];
    redo = [];
    pb.emit('replace', { before: before });
    pb.emit('history', { undo: 0, redo: 0 });
  };

  // ---- the autosave ---------------------------------------------------------------------------
  var timer = null;
  var saving = false;
  var again = false;
  var failures = 0;
  var stopped = false;

  function schedule(delay) {
    if (stopped) {
      return;
    }
    clearTimeout(timer);
    timer = setTimeout(save, delay === undefined ? 800 : delay);
    pb.emit('save', { state: 'pending' });
  }

  var waiting = [];

  function settle(ok) {
    var them = waiting;
    waiting = [];
    them.forEach(function (w) { (ok ? w.resolve : w.reject)(); });
  }

  function save() {
    timer = null;
    if (saving) {
      again = true;
      return;
    }
    saving = true;
    pb.emit('save', { state: 'saving' });
    pb.api(data.endpoints.draft, { version: pb.version, document: pb.doc }).then(function (answer) {
      saving = false;
      if (answer.status === 200) {
        failures = 0;
        pb.version = answer.json.version;
        pb.emit('save', { state: 'saved', at: new Date() });
      } else if (answer.status === 409) {
        stopped = true;
        pb.emit('save', { state: 'conflict' });
        settle(false);
        return;
      } else {
        throw new Error('save ' + answer.status);
      }
      if (again) {
        again = false;
        schedule(0);
      } else if (!timer) {
        settle(true);
      }
    }).catch(function () {
      saving = false;
      failures += 1;
      pb.emit('save', { state: 'failed' });
      schedule(Math.min(30000, 2000 * Math.pow(2, failures - 1)));
    });
  }

  /** Saved now, for Publish: resolves once the document on the server is this one. */
  pb.flush = function () {
    return new Promise(function (resolve, reject) {
      if (stopped) {
        reject(new Error('conflict'));
        return;
      }
      waiting.push({ resolve: resolve, reject: reject });
      clearTimeout(timer);
      if (!saving) {
        save();
      } else {
        again = true;
      }
    });
  };

  // The tab is not closed on unsaved work without the browser asking.
  window.addEventListener('beforeunload', function (event) {
    if (timer || saving) {
      event.preventDefault();
      event.returnValue = '';
    }
  });
})();
