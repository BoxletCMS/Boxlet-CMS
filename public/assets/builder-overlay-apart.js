/*
 * NOTHING THE EDITOR DRAWS LIES OVER ANOTHER (PLAN.md D-179): once the layer is drawn, what
 * stands where it must — the selection's toolbar — stays, and everything that only has to be
 * somewhere along its line — the "+" on a boundary,
 * "Add section at the end", a band's badges — moves sideways to the first place
 * that crosses nothing already placed, nor the page's own "+ Card". Right first, then left;
 * a band's badges, which say what the page says elsewhere, are put away where neither fits.
 *
 * Its own file, its own listener, loaded last: it runs after everything else has drawn into
 * the layer, and again whenever one of them adds to it on its own.
 */
(function () {
  'use strict';

  var pb = window.pb;
  if (!pb || !pb.overlay) {
    return;
  }
  var FIXED = '.bx-toolbar';
  // Not the rich text bubble, which TipTap's BubbleMenu places over the selection (D-186). A
  // selected item's actions are the block's bar's (D-188), which stays where it is.
  var MOVING = ['.bx-plus', '.bx-add-end', '.bx-badges'];

  function crosses(a, b, gap) {
    return a.left < b.right + gap && b.left < a.right + gap && a.top < b.bottom + gap && b.top < a.bottom + gap;
  }
  function shifted(r, dx) {
    return { left: r.left + dx, right: r.right + dx, top: r.top, bottom: r.bottom, width: r.width };
  }

  function apart() {
    var layer = pb.overlay.layer();
    var doc = pb.canvas.doc();
    var main = pb.canvas.main();
    if (!layer || !layer.isConnected || !doc || !main) {
      return;
    }
    var gap = pb.overlay.px(4);
    var room = main.getBoundingClientRect();
    var placed = [];
    Array.prototype.forEach.call(layer.querySelectorAll(FIXED), function (n) { placed.push(n.getBoundingClientRect()); });
    // The page's "+ Card": the whole tile it stands in, not just its button.
    Array.prototype.forEach.call(doc.querySelectorAll('.bx-add-item-cell'), function (n) { placed.push(n.getBoundingClientRect()); });

    MOVING.forEach(function (selector) {
      Array.prototype.forEach.call(layer.querySelectorAll(selector), function (n) {
        n.hidden = false;
        var r = n.getBoundingClientRect();
        if (r.width === 0) {
          return;
        }
        var dx = place(r, placed, gap, room);
        if (dx === null) {
          if (selector === '.bx-badges') { n.hidden = true; return; }
          dx = 0;
        }
        if (dx !== 0) {
          n.style.left = (parseFloat(n.style.left) + dx) + 'px';
        }
        placed.push(shifted(r, dx));
      });
    });
  }

  /** How far sideways `r` must move to cross nothing: right first, then left; null if nowhere. */
  function place(r, placed, gap, room) {
    for (var side = 1; side >= -1; side -= 2) {
      var dx = 0;
      for (var tries = 0; tries < placed.length + 1; tries += 1) {
        var at = shifted(r, dx);
        var hit = placed.filter(function (p) { return crosses(at, p, gap); })[0];
        if (!hit) {
          if (at.left >= room.left - gap && at.right <= room.right + gap) { return dx; }
          break;
        }
        dx = side > 0 ? hit.right + gap * 2 - r.left : hit.left - gap * 2 - r.right;
      }
    }
    return null;
  }

  pb.overlay.apart = apart;
  pb.on('painted', apart);
})();
