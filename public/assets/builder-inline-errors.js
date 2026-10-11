/*
 * WHAT THE SERVER SAYS IS WRONG WITH WHAT WAS TYPED (PLAN.md D-178, README 4.4): leaving a field
 * asks /fields about the whole block, the form's own parser judging it, and each error is shown
 * on the element it is about — an outline, and its words just under the element, in the page's
 * flow (D-179): they move what follows down rather than lie over it — and in the inspector.
 */
(function () {
  'use strict';

  var pb = window.pb;
  if (!pb || !pb.inline) {
    return;
  }

  pb.inline.check = function (key) {
    var block = pb.block(key);
    if (!block) {
      return;
    }
    pb.api(pb.data.endpoints.fields, { block: { key: key, type: block.type, content: block.content, layout: block.layout, options: block.options || {} } }).then(function (answer) {
      if (answer.status !== 200) { return; }
      pb.errors[key] = answer.json.errors || {};
      pb.emit('errors', key);
      // The inspector's All content shows what was typed here, unless it is being typed in.
      var sel = pb.selection;
      if (sel && sel.kind === 'block' && sel.key === key && !document.querySelector('[data-pb-inspector]').contains(document.activeElement)) {
        pb.emit('inspect');
      }
    });
  };

  /**
   * Each error on its element. Kept where it already stands, so a repaint — which this itself
   * causes, by moving the page — changes nothing and draws nothing again.
   */
  function marks() {
    var doc = pb.canvas.doc();
    if (!doc || !pb.canvas.main()) { return; }
    var wanted = [];
    Object.keys(pb.errors).forEach(function (key) {
      var host = pb.canvas.blockEl(key);
      Object.keys(pb.errors[key] || {}).forEach(function (path) {
        if (!host) { return; }
        var el = host.querySelector('[data-bx-field="' + path + '"]') || host.querySelector('[data-bx-field^="' + path + '."]') || host;
        wanted.push({ el: el, inside: el === host, words: pb.errors[key][path] });
      });
    });
    Array.prototype.forEach.call(doc.querySelectorAll('[data-bx-error]'), function (n) {
      if (!wanted.some(function (w) { return w.el === n; })) { n.removeAttribute('data-bx-error'); }
    });
    Array.prototype.forEach.call(doc.querySelectorAll('[data-bx-note]'), function (note) {
      var match = wanted.filter(function (w) { return w.note === undefined && note.textContent === w.words && (w.inside ? note.parentNode === w.el : note.previousElementSibling === w.el); })[0];
      if (match) { match.note = note; } else { note.remove(); }
    });
    wanted.forEach(function (w) {
      w.el.setAttribute('data-bx-error', '');
      if (w.note) { return; }
      var note = doc.createElement('span');
      note.className = 'bx-error';
      note.setAttribute('data-bx-note', '');
      note.setAttribute('role', 'alert');
      note.setAttribute('contenteditable', 'false');
      var words = doc.createElement('span');
      words.className = 'bx-error-words';
      words.textContent = w.words;
      note.appendChild(words);
      if (w.inside) { w.el.appendChild(note); } else { w.el.insertAdjacentElement('afterend', note); }
    });
  }
  pb.on('painted', marks);
  pb.on('errors', marks);
})();
