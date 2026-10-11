/*
 * UNDO AND REDO ON APPEARANCE (PLAN.md D-181, README 5.6): every change to the screen is a
 * step — a snapshot of the whole form — and a slider dragged or a colour picked is one step,
 * however many values it passed through (the same control within 700ms folds into the step
 * before). ⌘Z / Ctrl+Z goes back, ⇧⌘Z forward, and the bar has the two buttons. In a text
 * field the keys are the field's own (D-079), as in the builder.
 *
 * A STEP THE SERVER TOOK — a character loaded, a section reset, a kept design used — is a step
 * too. The form is posted and the screen drawn again, so the history waits in sessionStorage
 * for the screen that answers, which takes the screen as it was before the post as its last
 * step. Going back across a character is posted again (`keep`), because what every dot and
 * reset compares with is the character the server drew the screen for. Publishing ends the
 * history: what is published is where the next one starts.
 */
(function () {
  'use strict';

  var form = document.querySelector('[data-design-form]');
  if (!form) {
    return;
  }
  var KEY = 'boxlet.appearance.history';
  var MAX = 80;
  var FOLD = 700;
  var group = document.querySelector('[data-history]');
  if (group) { group.hidden = false; }
  var undoButton = document.querySelector('[data-undo]');
  var redoButton = document.querySelector('[data-redo]');
  var past = [];
  var future = [];
  var applying = false;
  var last = { name: '', at: 0 };

  function snap() {
    var fields = {};
    new FormData(form).forEach(function (value, name) {
      if (name === '_csrf' || name === 'action' || typeof value !== 'string') {
        return;
      }
      fields[name] = name in fields ? [].concat(fields[name], value) : value;
    });
    return fields;
  }
  function same(a, b) {
    return JSON.stringify(a) === JSON.stringify(b);
  }
  var current = snap();

  function store(value) {
    try {
      if (value === null) { window.sessionStorage.removeItem(KEY); } else { window.sessionStorage.setItem(KEY, JSON.stringify(value)); }
    } catch (e) {
      // A browser that keeps nothing still has the history of this one screen.
    }
  }
  try {
    var kept = JSON.parse(window.sessionStorage.getItem(KEY) || 'null');
    store(null);
    if (kept && kept.carry && kept.path === window.location.pathname) {
      past = kept.past || [];
      future = kept.future || [];
      if (kept.before && !same(kept.before, current)) {
        past.push(kept.before);
      }
    }
  } catch (e) {
    past = [];
  }

  function buttons() {
    if (undoButton) { undoButton.disabled = past.length === 0; }
    if (redoButton) { redoButton.disabled = future.length === 0; }
  }

  function record(event) {
    if (applying) {
      return;
    }
    var next = snap();
    if (same(next, current)) {
      return;
    }
    var name = (event.target && event.target.name) || '';
    var now = Date.now();
    if (!(name !== '' && name === last.name && now - last.at < FOLD && past.length > 0)) {
      past.push(current);
      if (past.length > MAX) { past.shift(); }
    }
    last = { name: name, at: now };
    current = next;
    future = [];
    buttons();
  }

  /** The form set to a snapshot, and told so, as if each changed control had been moved. */
  function apply(fields) {
    if ((fields.character || '') !== (current.character || '')) {
      // Across a character: the server draws the screen for that one (`keep`).
      store({ carry: true, path: window.location.pathname, past: past, future: future, before: null });
      set(fields);
      var action = document.createElement('input');
      action.type = 'hidden';
      action.name = 'action';
      action.value = 'keep';
      form.appendChild(action);
      form.submit();
      return;
    }
    applying = true;
    set(fields).forEach(function (el) {
      el.dispatchEvent(new Event('input', { bubbles: true }));
    });
    applying = false;
    current = snap();
    last = { name: '', at: 0 };
    buttons();
  }
  function set(fields) {
    var moved = [];
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.name === '_csrf' || el.name === 'action' || el.type === 'submit' || el.type === 'button' || el.type === 'file') {
        return;
      }
      var want = fields[el.name];
      var values = want === undefined ? [] : [].concat(want);
      if (el.type === 'radio' || el.type === 'checkbox') {
        var on = values.indexOf(el.value) >= 0;
        if (el.checked !== on) { el.checked = on; moved.push(el); }
      } else if (want !== undefined && el.value !== values[0]) {
        el.value = values[0];
        moved.push(el);
      }
    });
    return moved;
  }

  function undo() {
    if (past.length === 0) { return; }
    future.push(current);
    apply(past.pop());
  }
  function redo() {
    if (future.length === 0) { return; }
    past.push(current);
    apply(future.pop());
  }

  form.addEventListener('input', record);
  form.addEventListener('change', record);
  form.addEventListener('submit', function (event) {
    var action = event.submitter ? event.submitter.value : '';
    if (action === 'export') {
      return;
    }
    // Published, the history ends; any other post is a step the answering screen keeps.
    store(action === 'save' || action === 'save_design' || action === 'save_composition' ? null : { carry: true, path: window.location.pathname, past: past, future: [], before: current });
  });
  if (undoButton) { undoButton.addEventListener('click', undo); }
  if (redoButton) { redoButton.addEventListener('click', redo); }
  function keys(event) {
    if (!(event.metaKey || event.ctrlKey) || event.altKey || (event.key !== 'z' && event.key !== 'Z')) {
      return;
    }
    var t = event.target;
    // A field that holds words keeps its own undo (D-079).
    if (t && (t.isContentEditable || t.tagName === 'TEXTAREA' || (t.tagName === 'INPUT' && /^(text|search|url|email|number|tel|password)$/.test(t.type)))) {
      return;
    }
    event.preventDefault();
    if (event.shiftKey) { redo(); } else { undo(); }
  }
  document.addEventListener('keydown', keys);
  // And in the picture, which takes the keys once it has been pressed (same origin).
  var preview = document.querySelector('[data-design-preview]');
  function listen() {
    var doc = preview && preview.contentDocument;
    if (doc && !doc.__boxletHistory) {
      doc.__boxletHistory = true;
      doc.addEventListener('keydown', keys);
    }
  }
  if (preview) {
    preview.addEventListener('load', listen);
    listen();
  }
  buttons();
})();
