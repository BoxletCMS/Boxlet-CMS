/*
 * WHAT A PRESS ON THE CANVAS DOES (PLAN.md D-175, D-181): a control of the editor's layer
 * acts; anything else on the page selects the block or band it is in, or nothing; a pointer
 * over the page outlines what it would select. Split from builder-overlay.js, which draws.
 */
(function () {
  'use strict';

  var pb = window.pb;
  if (!pb || !pb.overlay) {
    return;
  }
  var hovered = null;

  function onClick(event) {
    var t = event.target;
    // Words being written: the press was the editor's, and selects nothing again (D-182).
    if (pb.inline && pb.inline.within && pb.inline.within(t)) {
      pressedAt = null;
      return;
    }
    var control = t.closest('[data-bx-action], [data-bx-insert], [data-bx-insert-into], [data-bx-add-block], [data-bx-add-pattern]');
    if (control || t.closest('.bx-layer')) {
      event.preventDefault();
      if (!control) { return; }
      var sel = pb.selection || {};
      var action = control.getAttribute('data-bx-action');
      if (control.hasAttribute('data-bx-insert')) { pb.inserter.open(Number(control.getAttribute('data-bx-insert')), control); return; }
      if (control.hasAttribute('data-bx-insert-into')) {
        pb.inserter.open(0, control, { section: control.getAttribute('data-bx-insert-into'), column: Number(control.getAttribute('data-bx-insert-column')) });
        return;
      }
      if (control.hasAttribute('data-bx-add-block')) { pb.inserter.choose('block', control.getAttribute('data-bx-add-block')); return; }
      if (control.hasAttribute('data-bx-add-pattern')) { pb.inserter.choose('pattern', control.getAttribute('data-bx-add-pattern')); return; }
      var acts = {
        'close-inserter': function () { pb.inserter.close(); },
        'block-up': function () { pb.moveBlock(sel.key, -1); },
        'block-down': function () { pb.moveBlock(sel.key, 1); },
        'block-copy': function () { pb.duplicateBlock(sel.key); },
        'block-delete': function () { pb.deleteBlock(sel.key); },
        'section-up': function () { pb.moveSection(sel.key, -1); },
        'section-down': function () { pb.moveSection(sel.key, 1); },
        'section-copy': function () { pb.duplicateSection(sel.key); },
        'section-pattern': function () { pb.savePattern(sel.key); },
        'section-delete': function () { pb.deleteSection(sel.key); },
      };
      if (acts[action]) { acts[action](); }
      return;
    }
    // On the page: nothing it holds is followed or sent while it is being edited.
    if (t.closest('a, button, form, input, select, textarea, summary')) {
      event.preventDefault();
    }
    pb.inserter.close();
    // WHERE THE PRESS BEGAN decides what is selected (D-181). A press selects at once (typing
    // on the page), the selected block shows its placeholders and moves, and the click that
    // follows lands on whatever is now under the pointer: measured in a whole run, a press on
    // a heading selected its block and the click 9ms later selected nothing.
    var from = pressedAt && pressedAt.isConnected && !pressedAt.closest('.bx-layer') ? pressedAt : t;
    pressedAt = null;
    var blockEl = from.closest('[data-bx-key]');
    var sectionEl = from.closest('[data-bx-section]');
    if (blockEl) { pb.select('block', blockEl.getAttribute('data-bx-key')); } else if (sectionEl) { pb.select('section', sectionEl.getAttribute('data-bx-section')); } else { pb.select(null); }
  }

  var pressedAt = null;
  function onPress(event) {
    pressedAt = event.target && event.target.closest ? event.target : null;
  }

  function onMove(event) {
    var t = event.target.closest ? event.target.closest('[data-bx-key], [data-bx-section]') : null;
    if (t === hovered) { return; }
    if (hovered) { hovered.removeAttribute('data-bx-hover'); }
    hovered = t && !t.closest('.bx-layer') ? t : null;
    if (hovered) { hovered.setAttribute('data-bx-hover', ''); }
  }

  pb.on('canvas', function () {
    var doc = pb.canvas.doc();
    if (!doc || doc.__bxPress) {
      return;
    }
    doc.__bxPress = true;
    // Writing on the page: the block's toolbar and an item's tools are put away (canvas.css).
    var writing = function () {
      var layer = pb.overlay.layer();
      if (layer) { layer.classList.toggle('is-writing', !!(doc.activeElement && pb.inline && pb.inline.within && pb.inline.within(doc.activeElement))); }
    };
    doc.addEventListener('focusin', writing);
    doc.addEventListener('focusout', function () { setTimeout(writing, 0); });
    doc.addEventListener('mousedown', onPress, true);
    doc.addEventListener('click', onClick, true);
    doc.addEventListener('mousemove', onMove);
    doc.addEventListener('keydown', function (event) { pb.inserter.escape(event); });
    doc.addEventListener('submit', function (e) { e.preventDefault(); }, true);
    doc.addEventListener('change', function (e) {
      var s = e.target.closest ? e.target.closest('[data-bx-layout]') : null;
      if (s) { pb.setLayout(s.getAttribute('data-bx-layout'), s.value); }
    });
  });
})();
