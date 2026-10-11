/*
 * A REPEATER'S ITEMS ON THE PAGE (PLAN.md D-178, README 4.4): "+ Card" where the next item
 * would stand adds one, saying it is new (D-179), and the item pressed in the selected block is
 * the selected item — before, after, remove. Moved by buttons rather than dragged (O-50).
 *
 * THE ITEM'S ACTIONS ARE THE BLOCK TOOLBAR'S (D-188, the owner): the selected item is outlined
 * and nothing more stands on the page; its actions are a second segment of the block's bar,
 * named for it — "Logo 2 · ← → ×". Tools in the item's corner covered its words and the
 * block's heading wherever an item was smaller than they were (D-187).
 */
(function () {
  'use strict';

  var pb = window.pb;
  if (!pb || !pb.overlay || !pb.inline || !pb.data.inline) {
    return;
  }
  var o = pb.overlay;
  var inline = pb.data.inline;
  // The selected item: its block's key, its repeater and its place, or null.
  pb.item = null;

  function addItem(block, field) {
    var declared = (inline.fields[block.type] || {})[field] || {};
    var items = block.content[field] || [];
    if (declared.max && items.length >= declared.max) {
      return;
    }
    pb.change(function () { block.content[field] = items.concat([pb.copy(inline.items[block.type][field])]); }, { sections: [block.section] });
  }
  function moveItem(block, field, at, by) {
    var items = block.content[field] || [];
    var to = at + by;
    if (to < 0 || to >= items.length) {
      return;
    }
    // The item stays selected where it went.
    pb.item = { key: block.key, field: field, at: to };
    pb.change(function () {
      var copy = items.slice();
      var moved = copy.splice(at, 1)[0];
      copy.splice(to, 0, moved);
      block.content[field] = copy;
    }, { sections: [block.section] });
  }
  function removeItem(block, field, at) {
    pb.item = null;
    pb.change(function () { block.content[field] = (block.content[field] || []).filter(function (_, i) { return i !== at; }); }, { sections: [block.section] });
  }

  /** The selected item's element in the canvas, when its block is the one selected. */
  function selectedEl() {
    var sel = pb.selection;
    var it = pb.item;
    if (!it || !sel || sel.kind !== 'block' || sel.key !== it.key) {
      return null;
    }
    var host = pb.canvas.blockEl(it.key);
    return host ? host.querySelector('[data-bx-item="' + it.field + '.' + it.at + '"]') : null;
  }

  /** The second segment of the block's bar: the item's name, then before, after, remove. */
  o.itemSegment = function (bar, block) {
    var itemEl = selectedEl();
    if (!itemEl || pb.item.key !== block.key) {
      return;
    }
    var segment = o.el('span', 'bx-toolbar-item');
    segment.setAttribute('data-bx-item-tools', pb.item.field + '.' + pb.item.at);
    var noun = ((inline.fields[block.type] || {})[pb.item.field] || {}).item || '';
    segment.appendChild(o.el('span', 'bx-toolbar-name', noun + ' ' + (pb.item.at + 1)));
    segment.appendChild(o.button('item-before', 'arrow-left', pb.t('inline.item_before')));
    segment.appendChild(o.button('item-after', 'arrow-right', pb.t('inline.item_after')));
    segment.appendChild(o.button('item-remove', 'x', pb.t('inline.remove_item')));
    bar.appendChild(segment);
  };

  /** The selected item outlined, and no other. */
  function outline() {
    var doc = pb.canvas.doc();
    var itemEl = selectedEl();
    Array.prototype.forEach.call(doc ? doc.querySelectorAll('[data-bx-item-selected]') : [], function (n) {
      if (n !== itemEl) { n.removeAttribute('data-bx-item-selected'); }
    });
    if (itemEl) { itemEl.setAttribute('data-bx-item-selected', ''); }
  }

  function onClick(event) {
    var t = event.target;
    if (!t.closest) { return; }
    var tool = t.closest('[data-bx-action^="item-"]');
    if (tool) {
      var parts = tool.closest('[data-bx-item-tools]').getAttribute('data-bx-item-tools').split('.');
      var block = pb.selection ? pb.block(pb.selection.key) : null;
      if (!block) { return; }
      var at = Number(parts[1]);
      var act = tool.getAttribute('data-bx-action');
      if (act === 'item-before') { moveItem(block, parts[0], at, -1); }
      if (act === 'item-after') { moveItem(block, parts[0], at, 1); }
      if (act === 'item-remove') { removeItem(block, parts[0], at); }
      return;
    }
    var add = t.closest('[data-bx-add-item]');
    var owner = add ? pb.inline.blockOf(add) : null;
    if (owner) {
      addItem(owner, add.getAttribute('data-bx-add-item'));
    }
  }

  /**
   * A press on an item selects it, whatever in it was pressed — its words, which are then
   * written, or its picture, whose picker opens. A press anywhere else on the page lets it go;
   * one on the editor's own marks keeps it.
   */
  function onPress(event) {
    var t = event.target;
    if (!t.closest || t.closest('.bx-layer')) { return; }
    var itemEl = t.closest('[data-bx-item]');
    var host = itemEl ? itemEl.closest('[data-bx-key]') : null;
    var was = JSON.stringify(pb.item);
    if (host && !itemEl.closest('.bx-add-item-cell')) {
      var path = itemEl.getAttribute('data-bx-item').split('.');
      pb.item = { key: host.getAttribute('data-bx-key'), field: path[0], at: Number(path[1]) };
    } else {
      pb.item = null;
    }
    // Its block already selected, nothing else draws the bar again.
    if (JSON.stringify(pb.item) !== was) { setTimeout(o.paint, 0); }
  }

  pb.on('select', function (sel) {
    if (pb.item && (!sel || sel.kind !== 'block' || sel.key !== pb.item.key)) { pb.item = null; }
  });
  pb.on('painted', outline);
  pb.on('canvas', function () {
    var doc = pb.canvas.doc();
    if (!doc || doc.__bxItems) { return; }
    doc.__bxItems = true;
    doc.addEventListener('mousedown', onPress, true);
    doc.addEventListener('click', onClick, true);
  });
})();
