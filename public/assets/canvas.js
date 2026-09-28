/*
 * Inside the canvas iframe.
 *
 * It knows nothing about block types or fields — the server owns those, and builder.js
 * owns every change to the page. This file does only what has to happen inside the
 * frame: report clicks, drag sections into a new order, and keep the insertion controls
 * sitting on the right boundaries.
 *
 * The insertion controls are an overlay, not elements between the sections: sections.css
 * styles a section by its position among its siblings, so anything placed between two of
 * them would change the page being edited.
 */
(function () {
  'use strict';

  var main = document.querySelector('[data-bx-blocks]');
  if (!main) {
    return;
  }

  var overlay = document.createElement('div');
  overlay.className = 'bx-overlay';
  document.body.appendChild(overlay);

  var counter = 0;

  /**
   * EVERY BLOCK ON THE PAGE, in the order the page reads (PLAN.md D-099).
   *
   * A band holding one block IS that block — the same <section> element it has always
   * been — so on every page written before columns existed this returns exactly what it
   * used to, and nothing about selection, the tools or the pairing with the form changes.
   * A band with columns holds its blocks in `.section-column`, and those are what a person
   * clicks, selects and moves.
   *
   * IT HAD TO BE THIS AND NOT THE BANDS. While this returned bands, clicking a block in
   * the second column selected the band — which pairs with the band's FIRST block — so
   * pressing Remove deleted the wrong one. It did, on the copy, twice.
   *
   * Column before place down the column, which is the order Page::blocks() reads them in:
   * a column is finished before the next one starts, the way a newspaper is read.
   */
  function blocks() {
    var found = [];
    Array.prototype.forEach.call(main.children, function (node) {
      if (node.tagName !== 'SECTION') {
        return;
      }
      Array.prototype.forEach.call(node.querySelectorAll('.section-column > *'), function (block) {
        found.push(block);
      });
    });

    return found;
  }

  /* How far the "+ Section" strip reaches either side of a band's edge: it is 2rem tall and
     centred on the boundary, so it covers this much of each neighbour. */
  var SEAM = 16;

  function tell(name, detail) {
    window.parent.postMessage(
      Object.assign({ source: 'boxlet-canvas', type: name }, detail || {}),
      window.location.origin,
    );
  }

  // Positional numbering, re-derived after every change, so it always matches the order
  // of the field groups in the parent's form. The key is stable across reordering, which
  // is how the parent knows which group belongs to which section.
  function renumber() {
    blocks().forEach(function (section, index) {
      section.setAttribute('data-bx-index', String(index));
      section.setAttribute('tabindex', '0');
      section.setAttribute('role', 'button');
      if (!section.hasAttribute('data-bx-key')) {
        section.setAttribute('data-bx-key', 'k' + counter++);
      }
    });
  }

  /**
   * A BAND ADDED HERE (PLAN.md D-101). It used to add a BLOCK, which then quietly got a
   * band of its own — which is why the owner could not find any way to add a section: there
   * was none, only a block that happened to bring one. A band is now the thing you add
   * between bands, and a block is the thing you add inside a column.
   */
  function insertButton(index, top, label, word) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'bx-insert';
    button.setAttribute('data-insert-at', String(index));
    button.setAttribute('aria-label', label);
    button.title = label;
    /* THE SAME SHAPE A COLUMN'S "+ Block" HAS: a strip the width of the page with the
       control centred in it, rather than a pill on its own. As a pill it sat ACROSS the
       band's edge — half in the band above, half in the one below — and cut straight
       through the dashed outline of a selected band. The owner saw it at once: "dodavanje
       sekcije i blok prostor prelaze izvan granice sekcije". A strip on the seam reads as
       the seam. It is also what the design artifact draws. */
    var pill = document.createElement('span');
    pill.textContent = '+';
    if (word) {
      var says = document.createElement('em');
      says.textContent = word;
      pill.appendChild(says);
    }
    button.appendChild(pill);
    // Never flush with the top edge: the control is centred on the boundary, so at y=0
    // half of it would sit above the page. An empty page has only this one.
    button.style.top = Math.max(16, Math.round(top)) + 'px';
    return button;
  }


  function drawInserts() {
    var labels = document.body.getAttribute('data-insert-labels')
      || 'Add a section here|Add a section at the end|Add a block in this column|Section|Block';
    var parts = labels.split('|');
    var focused = focusedAction();
    overlay.textContent = '';
    /* THE + BETWEEN BANDS COUNTS BANDS, not blocks: what it adds is a band of its own, and
       "before the third block" has no meaning when two of them stand side by side. The one
       that adds a block INSIDE a band is the slot in an empty column, below. */
    var bands = Array.prototype.filter.call(main.children, function (node) {
      return node.tagName === 'SECTION';
    });
    bands.forEach(function (band, index) {
      overlay.appendChild(insertButton(index, band.offsetTop, parts[0], parts[3]));
    });
    var last = bands[bands.length - 1];
    overlay.appendChild(insertButton(bands.length, last ? last.offsetTop + last.offsetHeight : 0, parts[1], parts[3]));
    drawSlots(parts[2] || parts[0], parts[4]);
    drawTools(focused);
  }

  /**
   * EVERY EMPTY COLUMN, DRAWN AS THE PLACE A BLOCK GOES (PLAN.md D-099).
   *
   * An arrangement nobody can see is not an arrangement: choosing "two columns" and getting
   * one block beside a stretch of nothing says neither that a column is there nor that
   * anything may be put in it. CLAUDE.md is explicit that no control is invisible at rest,
   * and an empty column is a control.
   *
   * Positioned from getBoundingClientRect against the OVERLAY's own rect, not from
   * offsetTop: a column sits inside a section that is position: relative, so its offset
   * parent is the section and not whatever the overlay is measured against. The insert
   * buttons above can use offsetTop because a section's offset parent IS that element.
   */
  /**
   * EVERY COLUMN OF EVERY BAND. The editor's canvas always draws them (D-103), so this is
   * a plain walk — it used to have a branch for the band that drew none, which is the
   * branch every other part of the editor would have needed too.
   */
  function columnBoxes() {
    var found = [];
    document.querySelectorAll('[data-bx-section]').forEach(function (band) {
      Array.prototype.forEach.call(band.querySelectorAll('.section-column'), function (column, at) {
        found.push({
          band: band,
          box: column,
          at: at,
          holds: column.children.length > 0 ? column.children[column.children.length - 1] : null,
        });
      });
    });

    return found;
  }

  function drawSlots(label, word) {
    var box = overlay.getBoundingClientRect();
    columnBoxes().forEach(function (found) {
      var band = found.band;
      var column = found.box;
      var at = found.at;
      var rect = column.getBoundingClientRect();
      var last = found.holds;
      var slot = document.createElement('button');
      slot.type = 'button';
      slot.className = 'bx-slot' + (last === null ? '' : ' bx-slot-after');
      slot.setAttribute('data-insert-into', band.getAttribute('data-bx-section'));
      slot.setAttribute('data-insert-column', String(at));
      slot.setAttribute('aria-label', label);
      slot.title = label;
      slot.style.left = Math.round(rect.left - box.left) + 'px';
      slot.style.width = Math.round(rect.width) + 'px';
      if (last === null) {
        /* AN EMPTY COLUMN IS THE PLACE ITSELF, so the slot is the whole box. It has no
           content, so its height is whatever the grid row gives it — beside a tall block
           that is tall and beside a short one it is nothing at all. A floor rather than a
           fixed size: it follows the row it is in and stays pressable either way. */
        slot.style.top = Math.round(rect.top - box.top) + 'px';
        slot.style.height = Math.max(64, Math.round(rect.height)) + 'px';
      } else {
        /* A COLUMN THAT HOLDS SOMETHING gets a strip UNDER what it holds — the artifact's
           "+ Block" line — because a slot over the content would cover the words and a
           person needs to see where the next block lands, not only that one can. */
        var after = last.getBoundingClientRect();
        var top = after.bottom - box.top + 6;
        /* IT NEVER RISES OVER THE CONTENT; IT GETS THINNER (PLAN.md D-106).
           "+ Section" lies across the band's bottom edge, half above and half below, so a
           band with little room under its last block had the two controls on top of each
           other — measured: a slot at 809..839 under a band ending at 835, with the seam at
           819..851. "traka za dodavanje bloka je ispod trake za dodavanje sekcija".

           Raising the strip to clear the seam covered the words, which is the one thing this
           control has always been forbidden to do, and shrinking it clipped its own label.
           Both were wrong and both were reported on sight. The room comes from the CANVAS
           now — canvas.css keeps a column's bottom clear in the editor — so the strip sits
           under the block at its own size, and this clamp is only the guarantee that it
           never reaches the seam whatever a design does with its spacing. */
        var bandBottom = band.getBoundingClientRect().bottom - box.top;
        slot.style.top = Math.round(top) + 'px';
        slot.style.height = Math.round(Math.max(10, Math.min(30, bandBottom - SEAM - 4 - top))) + 'px';
      }
      var plus = document.createElement('span');
      plus.textContent = '+';
      slot.appendChild(plus);
      if (word) {
        var says = document.createElement('em');
        says.textContent = word;
        slot.appendChild(says);
      }
      overlay.appendChild(slot);
    });
  }

  /**
   * @param announce false when the parent asked for this selection. Echoing it back
   *                 would make the parent treat it as a fresh click from the page, which
   *                 clears the position a "+" had just aimed at — so every insert landed
   *                 at the end of the page instead of at the boundary that was clicked.
   */
  /** Where the block with this key stands here, or -1. */
  function indexOfKey(key) {
    var found = -1;
    blocks().forEach(function (section, i) {
      if (section.getAttribute('data-bx-key') === key) {
        found = i;
      }
    });

    return found;
  }

  function select(index, announce) {
    document.querySelectorAll('.bx-band-selected').forEach(function (band) {
      band.classList.remove('bx-band-selected');
    });
    var list = blocks();
    list.forEach(function (section, i) {
      section.classList.toggle('bx-selected', i === index);
    });
    drawTools();
    if (announce !== false) {
      /* THE KEY, AND THE POSITION ONLY AS A FALLBACK (PLAN.md D-094).
         A position names a block only while the canvas and the form are in the same order,
         and adding one block ends that: the new block is drawn where it stands on the page
         and its field group is appended at the END of the form. Measured — canvas
         [n0, k0, k1 …] against form [k0, k1 … n0] — so every click opened the NEXT block's
         fields. The owner found it by pressing a Questions block and being given an Image
         and text one. The key is on both sides already and agrees; only the counting did
         not. */
      tell('select', {
        index: index,
        key: index >= 0 && list[index] ? list[index].getAttribute('data-bx-key') : null,
      });
    }
  }


  /**
   * A BLOCK THAT DRAWS NOTHING IS MARKED, so canvas.css can give it a place (D-117).
   *
   * Measured rather than decided from the block's fields: "empty" is whatever draws no
   * height, and that differs by type — a Form with no form chosen has no required field and
   * still draws nothing. Every mark comes off first and all are measured in one pass, so a
   * block is judged by its own drawing and never by the room the mark itself gives it.
   */
  function markEmpty() {
    var label = document.body.getAttribute('data-empty-label') || '';
    var list = blocks();
    list.forEach(function (block) {
      block.removeAttribute('data-bx-empty');
    });
    list.filter(function (block) {
      return block.getBoundingClientRect().height < 8;
    }).forEach(function (block) {
      block.setAttribute('data-bx-empty', label);
    });
  }

  function refresh() {
    renumber();
    markEmpty();
    drags();
    drawInserts();
    tell('size', { height: document.documentElement.scrollHeight });
  }

  document.addEventListener('click', function (event) {
    var action = event.target.closest && event.target.closest('[data-block-action]');
    if (action) {
      event.preventDefault();
      tell('action', { action: action.getAttribute('data-block-action') });
      return;
    }
    var insert = event.target.closest && event.target.closest('[data-insert-at]');
    if (insert) {
      event.preventDefault();
      // A BAND, not a block (D-101).
      tell('section', { index: Number(insert.getAttribute('data-insert-at')) });
      return;
    }
    // INTO A COLUMN, which is an address and not a position (D-099): which band, which of
    // its columns. The parent turns it into a request to the insert endpoint.
    var slot = event.target.closest && event.target.closest('[data-insert-into]');
    if (slot) {
      event.preventDefault();
      tell('insert', {
        section: slot.getAttribute('data-insert-into'),
        column: Number(slot.getAttribute('data-insert-column')),
      });
      return;
    }
    // Nothing in the canvas navigates: this is the page being edited, not browsed.
    if (event.target.closest && event.target.closest('a, button')) {
      event.preventDefault();
    }
    var section = event.target.closest && event.target.closest('[data-bx-index]');
    if (section) {
      select(Number(section.getAttribute('data-bx-index')));

      return;
    }
    /* A BAND PRESSED WHERE NO BLOCK STANDS (PLAN.md D-106).
       The canvas could be TOLD a band was selected but could never say so itself, so a band
       was reachable only from the outline — and an empty one, which is what you have the
       moment you add a section, has nothing else to press at all. The owner put it plainly:
       "sekcija se nakon dodavanja ne može označiti da bi se vidjele njene postavke". It
       cleared the selection instead, which is the opposite of what pressing a thing means. */
    var band = event.target.closest && event.target.closest('[data-bx-section]');
    if (band) {
      tell('selectband', { key: band.getAttribute('data-bx-section') });

      return;
    }
    select(-1);
  });

  document.addEventListener('keydown', function (event) {
    /* Escape and the undo shortcut are the editor's, not the page's, and the page is what
       has focus whenever the pointer is in here. Nothing in this document is editable —
       the fields are all in the parent — so neither can be taken from anything (D-079). */
    if (event.key === 'Escape') {
      event.preventDefault();
      select(-1);

      return;
    }
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'z') {
      event.preventDefault();
      tell('undo', {});

      return;
    }
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }
    var section = event.target.closest && event.target.closest('[data-bx-index]');
    if (section) {
      event.preventDefault();
      select(Number(section.getAttribute('data-bx-index')));
    }
  });

  window.addEventListener('message', function (event) {
    if (event.origin !== window.location.origin || !event.data || event.data.source !== 'boxlet-builder') {
      return;
    }
    if (event.data.type === 'select') {
      /* By key when the parent sent one — it is naming a block in the FORM's order, which
         is not this one. Same rule as the message going the other way. */
      select(typeof event.data.key === 'string' ? indexOfKey(event.data.key) : event.data.index, false);
    } else if (event.data.type === 'band') {
      /* A WHOLE BAND MARKED, not a block inside it (PLAN.md D-101). Every block's mark is
         cleared, because a band and a block are two things to be on and being on both says
         nothing. No tool bar: the tools act on a block, and a band's own are the next
         piece. */
      blocks().forEach(function (block) {
        block.classList.remove('bx-selected');
      });
      document.querySelectorAll('[data-bx-section]').forEach(function (band) {
        band.classList.toggle('bx-band-selected', band.getAttribute('data-bx-section') === event.data.key);
      });
      drawTools();
    } else if (event.data.type === 'refresh') {
      refresh();
    }
  });

  /* THE PARTS IN FILES OF THEIR OWN (PLAN.md D-117): the selected block's tool bar is
     canvas-tools.js and dragging is canvas-drag.js, split off at the hard size limit. They
     load after this file and hang their functions on this object; everything that calls
     them goes through these two, so a part that has not loaded is a part that does
     nothing rather than an error. */
  var bx = window.bxCanvas = {
    main: main,
    overlay: overlay,
    blocks: blocks,
    tell: tell,
    renumber: renumber,
    drawInserts: drawInserts,
    refresh: refresh,
    select: select,
  };

  function focusedAction() {
    return bx.focusedAction ? bx.focusedAction() : null;
  }

  function drawTools(focused) {
    if (bx.drawTools) {
      bx.drawTools(focused);
    }
  }

  function drags() {
    if (bx.drags) {
      bx.drags();
    }
  }

  /* Every deferred script has run by DOMContentLoaded, so the parts are here before the
     first drawing and before the editor is told the canvas is ready. */
  document.addEventListener('DOMContentLoaded', function () {
    refresh();
    window.addEventListener('resize', drawInserts);
    if (window.ResizeObserver) {
      new window.ResizeObserver(drawInserts).observe(document.body);
    }
    tell('ready', { count: blocks().length });
  });
})();
