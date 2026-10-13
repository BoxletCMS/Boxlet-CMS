/*
 * THE RAIL'S STRUCTURE (PLAN.md D-175, README 4.2): the page as a tree drawn from the document —
 * each band by its name (or "Section N"), its columns in the notation the inspector uses (1, 1/2,
 * 2/3+…) and its #anchor; each block by its icon, its name and, in a band of columns, which.
 * Never a key or an id: those are the editor's names, not the owner's.
 *
 * A click selects. Bands are dragged into a new order, and blocks into another band or column
 * (SortableJS, vendored, SPEC §3), each column a list of its own (O-56): what is dropped is read
 * back off the tree into the document as one change.
 */
(function () {
  'use strict';

  var pb = window.pb;
  var tree = document.querySelector('[data-pb-tree]');
  var count = document.querySelector('[data-pb-count]');
  if (!pb || !tree) {
    return;
  }
  var NOTATION = { one: '1', halves: '1/2', thirds: '1/3', quarters: '1/4', 'wide-left': '2/3+', 'wide-right': '+2/3', sidebar: '3/4+' };
  var COLUMNS = { one: 1, halves: 2, thirds: 3, quarters: 4, 'wide-left': 2, 'wide-right': 2, sidebar: 2 };
  var icons = document.querySelector('[data-pb]').getAttribute('data-icons');
  var sortables = [];

  function icon(name) {
    return '<svg class="icon" aria-hidden="true"><use href="' + icons + '#i-' + name + '"></use></svg>';
  }
  function text(value) {
    var span = document.createElement('span');
    span.textContent = value;
    return span.innerHTML;
  }

  function draw() {
    sortables.forEach(function (s) { s.destroy(); });
    sortables = [];
    var html = '';
    pb.doc.sections.forEach(function (section) {
      var columns = COLUMNS[section.layout] || 1;
      // One column says nothing (the owner, D-176): only an arrangement is worth a mark.
      var parts = [];
      if (section.layout && section.layout !== 'one' && NOTATION[section.layout]) {
        parts.push(NOTATION[section.layout]);
      }
      if (section.style && section.style.anchor) {
        parts.push('#' + section.style.anchor);
      }
      var meta = parts.join(' · ');
      html += '<li class="pb-tree-section" data-tree-section="' + text(section.key) + '">'
        + '<div class="pb-tree-row" role="button" tabindex="0" data-tree-select="section" data-key="' + text(section.key) + '">'
        + '<span class="pb-tree-grip" data-tree-grip aria-hidden="true">' + icon('grip-vertical') + '</span>'
        + icon('rows-3') + '<span class="pb-tree-name">' + text(pb.sectionName(section.key)) + '</span>'
        + '<span class="pb-tree-meta">' + text(meta) + '</span></div>';
      // EACH COLUMN A LIST OF ITS OWN (O-56): a block dropped lands in the column it is dropped
      // in, an empty one among them. One list for the band took every drop into column one. A
      // block whose column is past the band's last is drawn in the last, as the page draws it.
      var blocks = pb.blocksIn(section.key);
      for (var c = 0; c < columns; c += 1) {
        if (columns > 1) {
          html += '<p class="pb-tree-column">' + text(pb.t('structure.column_name', { n: c + 1 })) + '</p>';
        }
        html += '<ol class="pb-tree-blocks' + (columns > 1 ? ' is-column' : '') + '" data-tree-blocks="' + text(section.key) + '" data-tree-column="' + c + '">';
        blocks.filter(function (block) { return Math.min(block.column, columns - 1) === c; }).forEach(function (block) {
          var item = pb.libraryItem(block.type) || { label: block.type, icon: 'circle-alert' };
          html += '<li class="pb-tree-block" data-tree-block="' + text(block.key) + '" data-tree-was="' + c + '">'
            + '<div class="pb-tree-row" role="button" tabindex="0" data-tree-select="block" data-key="' + text(block.key) + '">'
            + '<span class="pb-tree-grip" aria-hidden="true">' + icon('grip-vertical') + '</span>'
            + icon(item.icon) + '<span class="pb-tree-name">' + text(item.label) + '</span>'
            + '</div></li>';
        });
        html += '</ol>';
      }
      html += '</li>';
    });
    tree.innerHTML = html || '<li class="pb-tree-empty">' + text(pb.t('structure.empty')) + '</li>';
    // The form the count takes in the admin's language (D-240): structure.sections, _one, _few.
    var n = function (key, count) {
      var form = window.boxletPlural(count);
      return pb.t('structure.' + key + (form === 'other' ? '' : '_' + form), { count: count });
    };
    count.textContent = n('sections', pb.doc.sections.length) + ' · ' + n('blocks', pb.doc.blocks.length);
    mark();
    sortable();
  }

  function mark() {
    var sel = pb.selection;
    Array.prototype.forEach.call(tree.querySelectorAll('[data-tree-select]'), function (row) {
      var on = sel && row.getAttribute('data-tree-select') === sel.kind && row.getAttribute('data-key') === sel.key;
      row.classList.toggle('is-selected', !!on);
      if (on) { row.scrollIntoView({ block: 'nearest' }); }
    });
  }

  /** What a drop made of the tree, read back into the document as one change. */
  function readBack(event) {
    var order = Array.prototype.map.call(tree.querySelectorAll(':scope > [data-tree-section]'), function (li) { return li.getAttribute('data-tree-section'); });
    var placed = [];
    Array.prototype.forEach.call(tree.querySelectorAll('[data-tree-blocks]'), function (list) {
      var sectionKey = list.getAttribute('data-tree-blocks');
      var column = Number(list.getAttribute('data-tree-column')) || 0;
      Array.prototype.forEach.call(list.querySelectorAll(':scope > [data-tree-block]'), function (li) {
        placed.push({ key: li.getAttribute('data-tree-block'), section: sectionKey, column: column, was: Number(li.getAttribute('data-tree-was')) || 0 });
      });
    });
    var moved = [];
    pb.change(function (doc) {
      doc.sections.sort(function (a, b) { return order.indexOf(a.key) - order.indexOf(b.key); });
      var byKey = {};
      doc.blocks.forEach(function (b) { byKey[b.key] = b; });
      doc.blocks = placed.map(function (p) {
        var b = byKey[p.key];
        // A block keeps its column unless it was moved out of the one it was drawn in.
        if (b.section !== p.section || p.column !== p.was) {
          moved.push(b.section, p.section);
          b.section = p.section;
          b.column = p.column;
        }
        return b;
      });
      // A band a drop left empty goes, as a band of nothing does on the page.
      doc.sections = doc.sections.filter(function (s) { return doc.blocks.some(function (b) { return b.section === s.key; }); });
    }, { structure: true });
    // The bands a block left, joined or moved inside are drawn again with it there.
    [event && event.from, event && event.to].forEach(function (list) {
      var key = list && list.getAttribute ? list.getAttribute('data-tree-blocks') : null;
      if (key) { moved.push(key); }
    });
    moved.filter(function (key, i) { return moved.indexOf(key) === i && pb.section(key); }).forEach(function (key) { pb.canvas.draw(key); });
  }

  function sortable() {
    if (!window.Sortable) {
      return;
    }
    sortables.push(window.Sortable.create(tree, { handle: '[data-tree-grip]', draggable: '[data-tree-section]', animation: 120, onEnd: readBack }));
    Array.prototype.forEach.call(tree.querySelectorAll('[data-tree-blocks]'), function (list) {
      sortables.push(window.Sortable.create(list, { group: 'pb-blocks', draggable: '[data-tree-block]', animation: 120, onEnd: readBack }));
    });
  }

  tree.addEventListener('click', function (event) {
    var row = event.target.closest('[data-tree-select]');
    if (row) {
      pb.select(row.getAttribute('data-tree-select'), row.getAttribute('data-key'));
    }
  });
  tree.addEventListener('keydown', function (event) {
    var row = event.target.closest('[data-tree-select]');
    if (row && (event.key === 'Enter' || event.key === ' ')) {
      event.preventDefault();
      pb.select(row.getAttribute('data-tree-select'), row.getAttribute('data-key'));
    }
  });

  pb.on('change', function (detail) { if (!detail.quiet) { draw(); } });
  pb.on('replace', draw);
  pb.on('select', mark);
  draw();
})();
