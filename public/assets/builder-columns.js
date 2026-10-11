/*
 * "+ BLOCK" IN A COLUMN (PLAN.md D-099, D-101, restored in D-206). A band is filled column by
 * column: an empty column of a band of two or more carries "+ Block" in its middle at all
 * times, it being the place itself; a column that holds something — the one column of a band
 * of one too (D-208, the owner: a Text under a Hero, Downloads under a Text) — carries one
 * under what it holds while its band, or a block in it, is selected, as a selected block shows
 * its "+ Card". Pressed, it opens the quick inserter for that column (builder-inserter.js),
 * blocks only. The "+" between bands stays the way to a new band.
 *
 * D-175's builder had lost it: a band could be given columns that nothing could be put in.
 */
(function () {
  'use strict';

  var pb = window.pb;
  if (!pb || !pb.overlay || !pb.inserter) {
    return;
  }
  var o = pb.overlay;

  /**
   * Clear of what else the layer draws: below a toolbar it would stand under (a band's sits at
   * its top right, where the empty column of a band of two is: measured, it covered half the
   * "+ Block"), and to the right of the next band's badges, which stand at its top left where
   * a column's "+ Block" ends on a tablet or a phone (62-overlay caught it under a Hero).
   */
  function clear(add, layer) {
    var mine = add.getBoundingClientRect();
    var crosses = function (r) { return r.width > 0 && mine.left < r.right && mine.right > r.left && mine.top < r.bottom && mine.bottom > r.top; };
    Array.prototype.forEach.call(layer.querySelectorAll('.bx-toolbar'), function (bar) {
      var r = bar.getBoundingClientRect();
      if (crosses(r)) {
        add.style.setProperty('top', (parseFloat(add.style.top) + (r.bottom - mine.top) + 6) + 'px');
        mine = add.getBoundingClientRect();
      }
    });
    Array.prototype.forEach.call(layer.querySelectorAll('.bx-badges'), function (badges) {
      var r = badges.getBoundingClientRect();
      if (crosses(r)) {
        add.style.setProperty('left', (parseFloat(add.style.left) + (r.right - mine.left) + 8) + 'px');
        mine = add.getBoundingClientRect();
      }
    });
  }

  pb.on('painted', function (layer) {
    var sel = pb.selection || {};
    var chosen = sel.kind === 'section' ? sel.key : (sel.kind === 'block' && pb.block(sel.key) ? pb.block(sel.key).section : null);
    pb.doc.sections.forEach(function (section) {
      var element = pb.canvas.sectionEl(section.key);
      var columns = element ? element.querySelectorAll('.section-column') : [];
      // A band of one column: only while it is chosen, under its blocks. Unchosen, the "+"
      // between bands is what adds near it.
      if (columns.length === 0 || (columns.length === 1 && section.key !== chosen)) {
        return;
      }
      Array.prototype.forEach.call(columns, function (column, index) {
        // Empty as canvas-marks.css counts it: no element, whatever whitespace is left. A band
        // of one empty column is the "+" between bands' business.
        var empty = column.querySelector('*') === null;
        if (empty && columns.length === 1) {
          return;
        }
        if (!empty && section.key !== chosen) {
          return;
        }
        var b = o.box(column);
        var add = o.el('button', 'bx-add-column' + (empty ? '' : ' bx-add-column-under'));
        add.type = 'button';
        add.title = pb.t('canvas.add_block_title');
        add.setAttribute('aria-label', pb.t('canvas.add_block_title'));
        add.setAttribute('data-bx-insert-into', section.key);
        add.setAttribute('data-bx-insert-column', String(index));
        add.appendChild(o.icon('plus'));
        add.appendChild(o.el('span', '', pb.t('canvas.add_block')));
        // In an empty column its middle. Under a column, from its left edge, far enough below
        // that its upper half stays clear of the last line (measured: at 10px it covered it),
        // and away from the middle, where the "+" between bands stands: columns stacked on a
        // tablet or a phone put the last one's button on that "+" (62-overlay caught it).
        o.at(add, empty
          ? { top: b.top + b.height / 2, left: b.left + b.width / 2 }
          : { top: b.top + b.height + o.px(24), left: b.left });
        layer.appendChild(add);
        clear(add, layer);
      });
    });
  });
})();
