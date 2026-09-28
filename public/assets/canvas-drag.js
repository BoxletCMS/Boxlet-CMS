/*
 * Inside the canvas iframe: dragging bands, and blocks within and between columns. Split
 * from canvas.js at the hard size limit (PLAN.md D-117); canvas.js calls it through
 * window.bxCanvas.
 */
(function () {
  'use strict';

  var bx = window.bxCanvas;
  if (!bx) {
    return;
  }
  var main = bx.main;
  var overlay = bx.overlay;

  // Reordering happens here because drag events do not cross a document boundary.
  // SortableJS rather than native drag and drop: it handles touch, and a tablet is a
  // real case for this screen.
  /*
   * TWO LEVELS OF DRAG (PLAN.md D-103), which is what a tree needs and a list did not:
   * bands reorder among themselves, and blocks move WITHIN and BETWEEN columns. The column
   * sortables share one group name, which is the whole of what lets a block cross from one
   * column to another — Sortable's own mechanism rather than machinery of ours.
   *
   * A drag is reported when it ENDS, and by then this document has already moved: a
   * snapshot taken then would record the result, not what to go back to. So the start is
   * announced too, and the builder holds that state until it knows the drag changed
   * something (D-079).
   */
  function drags() {
    if (!window.Sortable) {
      return;
    }
    if (!main.bxSortable) {
      main.bxSortable = window.Sortable.create(main, {
        draggable: 'section.block',
        animation: 120,
        ghostClass: 'bx-dragging',
        onStart: function () {
          bx.tell('drag-start', {});
        },
        onEnd: function () {
          bx.renumber();
          bx.drawInserts();
          bx.tell('bands', {
            keys: Array.prototype.filter.call(main.children, function (node) {
              return node.tagName === 'SECTION';
            }).map(function (band) {
              return band.getAttribute('data-bx-section');
            }),
          });
        },
      });
    }
    /* ONE PER COLUMN, and made once each: a column is replaced when its band is redrawn, so
       this runs after every refresh and skips the ones it has already taken. */
    document.querySelectorAll('.section-column').forEach(function (column) {
      if (column.bxSortable) {
        return;
      }
      column.bxSortable = window.Sortable.create(column, {
        group: { name: 'bx-blocks', pull: true, put: true },
        animation: 120,
        ghostClass: 'bx-dragging',
        onStart: function () {
          bx.tell('drag-start', {});
        },
        onEnd: function () {
          bx.renumber();
          bx.drawInserts();
          /* WHERE EVERY BLOCK STANDS NOW, said as places rather than as an order: a flat
             list of keys could say that two blocks swapped and could not say that one of
             them crossed into another column. */
          bx.tell('placed', { at: placement() });
        },
      });
    });
  }

  /** Every block, with the band and the column it is standing in at this moment. */
  function placement() {
    var where = [];
    document.querySelectorAll('[data-bx-section]').forEach(function (band) {
      Array.prototype.forEach.call(band.querySelectorAll('.section-column'), function (column, at) {
        Array.prototype.forEach.call(column.children, function (block) {
          where.push({
            key: block.getAttribute('data-bx-key'),
            section: band.getAttribute('data-bx-section'),
            column: at,
          });
        });
      });
    });

    return where;
  }

  bx.drags = drags;
})();
