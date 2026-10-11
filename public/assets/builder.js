/*
 * THE BUILDER'S SHELL (PLAN.md D-175, README 4.1–4.2): the bar — what the page is, how its draft
 * is saved, undo and redo with their keys, Discard and Publish — the rail's tabs and its fold,
 * and the trail over the canvas. Last of the builder's scripts: it wires what the others drew.
 */
(function () {
  'use strict';

  var pb = window.pb;
  if (!pb) {
    return;
  }
  var q = function (selector) { return document.querySelector(selector); };
  var stateEl = q('[data-pb-state]');
  var saveText = q('[data-pb-save-text]');
  var saveEl = q('[data-pb-save]');
  var undoButton = q('[data-pb-undo]');
  var redoButton = q('[data-pb-redo]');
  var publishButton = q('[data-pb-publish]');
  var discardButton = q('[data-pb-discard]');
  var body = q('[data-pb-body]');
  function state(name) {
    pb.data.page.state = name;
    stateEl.className = 'pb-state status-' + name;
    stateEl.textContent = pb.t('state.' + name);
    discardButton.hidden = name !== 'changes';
  }
  pb.on('state', state);

  // ---- the save state --------------------------------------------------------------------------
  pb.on('save', function (detail) {
    saveEl.setAttribute('data-state', detail.state);
    if (detail.state === 'saving' || detail.state === 'pending') {
      saveText.textContent = pb.t('save.saving');
      // A published page with a draft has unpublished changes the moment it has one.
      if (pb.data.page.state === 'published') { state('changes'); }
    } else if (detail.state === 'saved') {
      var at = detail.at;
      saveText.textContent = pb.t('save.saved', { time: String(at.getHours()).padStart(2, '0') + ':' + String(at.getMinutes()).padStart(2, '0') });
    } else if (detail.state === 'failed') {
      saveText.textContent = pb.t('save.failed');
    } else if (detail.state === 'conflict') {
      saveText.textContent = pb.t('save.conflict');
      var reload = document.createElement('button');
      reload.type = 'button';
      reload.className = 'link-button';
      reload.textContent = pb.t('save.reload');
      reload.addEventListener('click', function () { window.location.reload(); });
      saveEl.appendChild(reload);
    }
  });

  // ---- undo and redo ---------------------------------------------------------------------------
  pb.on('history', function (detail) {
    undoButton.disabled = detail.undo === 0;
    redoButton.disabled = detail.redo === 0;
  });
  undoButton.addEventListener('click', pb.undo);
  redoButton.addEventListener('click', pb.redo);
  function keys(event) {
    var mod = event.metaKey || event.ctrlKey;
    if (!mod || event.key.toLowerCase() !== 'z') {
      return;
    }
    // A field keeps its own undo while it is being typed in.
    var t = event.target;
    if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) {
      return;
    }
    event.preventDefault();
    if (event.shiftKey) { pb.redo(); } else { pb.undo(); }
  }
  document.addEventListener('keydown', keys);
  pb.on('canvas', function () { pb.canvas.doc().addEventListener('keydown', keys); });

  // ---- Publish and Discard -----------------------------------------------------------------------
  function notice(text, kind) {
    var n = document.createElement('p');
    n.className = 'pb-notice' + (kind ? ' pb-notice-' + kind : '');
    n.setAttribute('role', 'status');
    n.textContent = text;
    document.body.appendChild(n);
    setTimeout(function () { n.remove(); }, 5000);
  }
  pb.on('notice', function (text) { notice(text); });
  // The admin's flash, from the request that led here, goes the way of the builder's own.
  var flash = document.querySelector('#admin-content > .notice');
  if (flash) { setTimeout(function () { flash.remove(); }, 6000); }

  publishButton.addEventListener('click', function () {
    publishButton.disabled = true;
    publishButton.textContent = pb.t('publishing');
    pb.flush().then(function () {
      return pb.api(pb.data.endpoints.publish, {});
    }).then(function (answer) {
      if (answer.json.ok) {
        state(answer.json.state);
        pb.version = answer.json.version;
        notice(pb.t('published'));
      } else {
        var problems = Object.keys(answer.json.errors || {}).map(function (k) { return answer.json.errors[k]; });
        notice(pb.t('publish_refused', { problem: problems.join(' ') }), 'error');
      }
    }).catch(function () {
      notice(pb.t('save.conflict'), 'error');
    }).then(function () {
      publishButton.disabled = false;
      publishButton.textContent = publishButton.getAttribute('data-label');
    });
  });
  publishButton.setAttribute('data-label', publishButton.textContent);

  discardButton.addEventListener('click', function () {
    if (!window.confirm(pb.t('discard_confirm'))) {
      return;
    }
    pb.flush().catch(function () {}).then(function () {
      return pb.api(pb.data.endpoints.discard, {});
    }).then(function (answer) {
      if (answer && answer.json.document) {
        pb.reset(answer.json.document, answer.json.version);
        state(answer.json.state);
      }
    });
  });

  // ---- the rail ---------------------------------------------------------------------------------
  function tab(name) {
    Array.prototype.forEach.call(document.querySelectorAll('[data-pb-tab]'), function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-pb-tab') === name ? 'true' : 'false');
    });
    Array.prototype.forEach.call(document.querySelectorAll('[data-pb-panel]'), function (p) {
      p.hidden = p.getAttribute('data-pb-panel') !== name;
    });
    fold(false);
  }
  function fold(folded) {
    body.classList.toggle('pb-folded', folded);
    q('[data-pb-fold]').setAttribute('aria-expanded', folded ? 'false' : 'true');
  }
  document.addEventListener('click', function (event) {
    var t = event.target.closest ? event.target.closest('[data-pb-tab], [data-pb-fold], [data-trail]') : null;
    if (!t) {
      return;
    }
    if (t.hasAttribute('data-pb-tab')) {
      var pressed = t.getAttribute('aria-pressed') === 'true' && !body.classList.contains('pb-folded');
      if (pressed) { fold(true); } else { tab(t.getAttribute('data-pb-tab')); }
    } else if (t.hasAttribute('data-pb-fold')) {
      fold(!body.classList.contains('pb-folded'));
    } else {
      var kind = t.getAttribute('data-trail');
      pb.select(kind === 'page' ? null : kind, t.getAttribute('data-key'));
    }
  });
  // Folded by default under 1200px (README 4.2): the canvas needs the room.
  if (window.innerWidth < 1200) {
    fold(true);
  }

  // ---- the trail: Page › Section › Block ------------------------------------------------------------
  var trail = q('[data-pb-trail]');
  pb.on('select', function (sel) {
    var parts = [{ kind: 'page', label: pb.t('canvas.page') }];
    if (sel) {
      var sectionKey = sel.kind === 'section' ? sel.key : (pb.block(sel.key) || {}).section;
      parts.push({ kind: 'section', key: sectionKey, label: pb.sectionName(sectionKey) });
      if (sel.kind === 'block') {
        var item = pb.libraryItem((pb.block(sel.key) || {}).type) || { label: '' };
        parts.push({ kind: 'block', key: sel.key, label: item.label });
      }
    }
    trail.textContent = '';
    parts.forEach(function (part, i) {
      if (i > 0) { trail.appendChild(document.createTextNode(' › ')); }
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'pb-trail-part';
      b.setAttribute('data-trail', part.kind);
      if (part.key) { b.setAttribute('data-key', part.key); }
      b.textContent = part.label;
      trail.appendChild(b);
    });
  });

  // The canvas's width and scale in words, as the mockup says them: "1200 px · 63 %".
  var size = q('[data-pb-size]');
  pb.on('scale', function (detail) {
    size.textContent = detail.width + ' px' + (detail.scale < 1 ? ' · ' + Math.round(detail.scale * 100) + ' %' : '');
  });
})();
