/*
 * THE QUICK INSERTER (PLAN.md D-175, README 4.3): the "+" on a boundary between bands opens it,
 * there, over the page — every block, then the design set's patterns — and what is chosen goes
 * in at that boundary. Opened from a column's "+ Block" (builder-columns.js) it offers blocks
 * only, a pattern being a band of its own, and what is chosen goes in at that column's end.
 * Drawn in builder-overlay.js's layer, with its pieces.
 */
(function () {
  'use strict';

  var pb = window.pb;
  if (!pb || !pb.overlay) {
    return;
  }
  var o = pb.overlay;
  var inserter = null;

  function item(kind, value, iconName, label) {
    var b = o.el('button', 'bx-inserter-item' + (kind === 'pattern' ? ' bx-inserter-pattern' : ''));
    b.type = 'button';
    b.setAttribute(kind === 'block' ? 'data-bx-add-block' : 'data-bx-add-pattern', value);
    b.appendChild(o.icon(iconName));
    b.appendChild(o.el('span', '', label));
    return b;
  }

  function close() {
    if (inserter) {
      inserter.remove();
      inserter = null;
    }
  }

  /** `into`: {section, column} for a column's "+ Block"; otherwise a boundary between bands. */
  function open(index, anchor, into) {
    close();
    inserter = o.el('div', 'bx-inserter');
    inserter.setAttribute('role', 'dialog');
    inserter.setAttribute('aria-label', pb.t('canvas.insert_title'));
    var head = o.el('div', 'bx-inserter-head', pb.t('canvas.insert_title'));
    head.appendChild(o.button('close-inserter', 'x', pb.t('canvas.close')));
    inserter.appendChild(head);
    var blocks = o.el('div', 'bx-inserter-grid');
    pb.data.library.forEach(function (entry) { blocks.appendChild(item('block', entry.type, entry.icon, entry.label)); });
    inserter.appendChild(blocks);
    var patterns = into ? [] : (pb.data.setPatterns || []);
    if (patterns.length) {
      inserter.appendChild(o.el('div', 'bx-inserter-group', pb.t('add.from_set', { set: pb.data.setName })));
      var grid = o.el('div', 'bx-inserter-grid');
      patterns.forEach(function (p) { grid.appendChild(item('pattern', 'set:' + p.id, 'square-plus', p.name)); });
      inserter.appendChild(grid);
    }
    inserter.setAttribute('data-bx-at', String(index));
    if (into) {
      inserter.setAttribute('data-bx-into', into.section);
      inserter.setAttribute('data-bx-column', String(into.column));
    }
    var a = o.box(anchor);
    o.at(inserter, { top: a.top + a.height + o.px(6), left: a.left + a.width / 2 });
    o.layer().appendChild(inserter);
    // Inside the page, side to side: centred on a "+ Block" at a column's left edge it stood
    // half off the canvas, its first column of blocks out of reach (82-one-column caught it).
    var r = inserter.getBoundingClientRect();
    var room = inserter.ownerDocument.documentElement.clientWidth;
    var shift = r.left < 8 ? 8 - r.left : (r.right > room - 8 ? room - 8 - r.right : 0);
    if (shift !== 0) {
      inserter.style.setProperty('left', (parseFloat(inserter.style.left) + shift) + 'px');
    }
  }

  /** What was chosen, in at the boundary the inserter was opened on. */
  function choose(kind, value) {
    var at = Number(inserter ? inserter.getAttribute('data-bx-at') : pb.doc.sections.length);
    var into = inserter ? inserter.getAttribute('data-bx-into') : null;
    var column = Number(inserter ? inserter.getAttribute('data-bx-column') : 0);
    close();
    if (into && kind === 'block') { pb.addBlockInto(value, into, column); return; }
    if (kind === 'block') { pb.addBlock(value, at); } else { pb.addPattern(value, at); }
  }

  // Escape closes it, from the page or from the shell around it.
  function escape(event) {
    if (event.key === 'Escape' && inserter) {
      close();
    }
  }
  document.addEventListener('keydown', escape);

  pb.inserter = { open: open, close: close, choose: choose, escape: escape };
})();
