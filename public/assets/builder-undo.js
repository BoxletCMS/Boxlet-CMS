/*
 * UNDO FOR THE PAGE EDITOR (PLAN.md D-079).
 *
 * Removing a block dropped its field group from the form, and the only way back was to
 * leave without saving — which took every other change on the page with it. A Columns
 * block with twelve filled items was one click from gone. An editor whose destructive
 * action cannot be taken back is not solid, however good the rest of it is.
 *
 * ONE PATH FOR EVERY STRUCTURAL CHANGE. Insert, remove, duplicate, move and drag all go
 * through api.commit(), which pushes the state before the change. An undo that covered
 * removal but not a move would be worse than none, because nobody could predict it.
 *
 * ⌘Z BELONGS TO A FIELD THE AUTHOR HAS TYPED IN. TipTap has its own undo and so does every
 * text input, and taking the shortcut off them would make typing unpredictable. But focus
 * being in a field does not mean the author put it there: api.show() focuses the first
 * field of whatever block is selected, so after every insert, duplicate and move the
 * cursor is already sitting in one. Ignoring the shortcut on focus alone would therefore
 * have meant it never worked for those three — measured: duplicate, ⌘Z, nothing happened.
 * A field that has not been typed in has an empty undo stack of its own, so it loses
 * nothing by letting the page have the keystroke; from the first keystroke in it, it keeps
 * it. Typing is tracked from the `input` event and forgotten on every focus change.
 *
 * THE STATE IS ALREADY IN ONE PLACE, which is what makes this small: the field groups in
 * the form and the sections in the canvas are the same page seen twice. A snapshot is the
 * inner HTML of both, and a restore puts both back and re-raises the editors inside.
 *
 * NO REDO. Twenty steps back covers the mistake this exists for; a redo stack is a second
 * thing to reason about for a case nobody has asked for.
 *
 * Its own file rather than a corner of builder.js: that one is the shell — selection, the
 * panel's modes, the device width — and this is the page's history.
 */
(function () {
  'use strict';

  var api = window.boxletBuilder;
  if (!api) {
    return;
  }

  var DEPTH = 20;
  var SHOWN = 6000;

  var history = [];
  /* The control that is there whether or not anything has happened (D-092). The strip below
     only appears after a removal, which meant somebody who had removed nothing never learnt
     that undo existed — reported by the owner, who asked whether it was keyboard-only. */
  var button = document.querySelector('[data-undo-button]');
  var strip = document.querySelector('[data-undo-strip]');
  var stripText = strip && strip.querySelector('[data-undo-text]');
  var hiding = null;
  /* A drag is reported when it ENDS, by which time the canvas has already moved. The
     canvas says when it starts too, and that snapshot is held here until the move is
     known to have changed something — a drag that ends where it began must not leave an
     undo step that appears to do nothing. */
  var beforeDrag = null;
  // And the arrangement it was taken from, both kinds, read at the same moment.
  var beforeArrangement = null;
  /* Whether the field that has focus has been typed in since it got it — which is what
     tells a cursor the author placed from one api.show() left behind. */
  var typed = false;

  /* Disabled rather than hidden when the stack is empty: a control that disappears teaches
     nobody that it is there, and the point of this one is that it can be found. */
  function offer() {
    if (button) {
      button.disabled = history.length === 0;
    }
  }

  function main() {
    var doc = api.frame.contentDocument;
    return doc ? doc.querySelector('[data-bx-blocks]') : null;
  }

  /**
   * Write what the author has typed into the markup, so that reading innerHTML reads it.
   *
   * A snapshot is HTML, and HTML carries a field's value in an ATTRIBUTE while typing
   * changes a PROPERTY. Without this the snapshot holds what the server rendered, and an
   * undo after ten minutes of writing would restore the removed block and throw the ten
   * minutes away — the precise loss this file exists to prevent. Rich text is covered by
   * the same pass: richtext.js keeps its hidden input current on every keystroke, and the
   * hidden input is an input like any other.
   */
  function sync(root) {
    root.querySelectorAll('input, textarea, select').forEach(function (field) {
      if (field.tagName === 'TEXTAREA') {
        field.textContent = field.value;
      } else if (field.tagName === 'SELECT') {
        Array.prototype.forEach.call(field.options, function (option) {
          option.toggleAttribute('selected', option.selected);
        });
      } else if (field.type === 'checkbox' || field.type === 'radio') {
        field.toggleAttribute('checked', field.checked);
      } else {
        field.setAttribute('value', field.value);
      }
    });
  }

  /**
   * The page as it stands: the blocks' fields, the BANDS' fields, and the canvas.
   *
   * The bands' fields are a sibling of the blocks' in the panel, not inside them, and were
   * left out when bands arrived (D-099). Measured (D-117): remove a band, undo, and the
   * band and its blocks came back on the canvas while its own fields did not — so its
   * Section tab was empty, and the save put each of its blocks in a band of its own at the
   * foot of the page.
   */
  function snapshot() {
    var page = main();
    var bands = api.sectionGroups();
    sync(api.groups);
    if (bands) {
      sync(bands);
    }

    return page === null ? null : {
      groups: api.groups.innerHTML,
      bands: bands ? bands.innerHTML : null,
      canvas: page.innerHTML,
      selected: api.selected(),
      band: api.band || null,
    };
  }

  function push(state) {
    history.push(state);
    if (history.length > DEPTH) {
      history.shift();
    }
    offer();
  }

  /**
   * The page's arrangement as the CANVAS holds it, said the way the canvas reports a drag,
   * so the two can be compared to decide whether a drag moved anything (PLAN.md D-103).
   *
   * READ WHEN THE DRAG STARTS, from the canvas (D-117). It used to be read from the FORM
   * when the drag was reported — after builder.js had already replayed the drag onto the
   * form, since its listener runs first — so before and after were always the same and no
   * drag was ever undoable. Measured on the copy, on the code before and after this entry:
   * a block moved into another column, and the undo control stayed disabled. The form was
   * never the right thing to compare with anyway: its order is not the page's.
   */
  function arrangement(kind) {
    var page = main();
    if (page === null) {
      return '';
    }
    var bands = Array.prototype.filter.call(page.children, function (node) {
      return node.tagName === 'SECTION';
    });
    if (kind === 'bands') {
      return bands.map(function (band) {
        return band.getAttribute('data-bx-section');
      }).join('|');
    }
    var where = [];
    bands.forEach(function (band) {
      Array.prototype.forEach.call(band.querySelectorAll('.section-column'), function (column, at) {
        Array.prototype.forEach.call(column.children, function (block) {
          where.push(block.getAttribute('data-bx-key') + '@' + band.getAttribute('data-bx-section') + ':' + at);
        });
      });
    });

    return where.join('|');
  }

  function show(message) {
    if (!strip || !stripText || !message) {
      return;
    }
    stripText.textContent = message;
    strip.hidden = false;
    window.clearTimeout(hiding);
    hiding = window.setTimeout(hide, SHOWN);
  }

  function hide() {
    window.clearTimeout(hiding);
    if (strip) {
      strip.hidden = true;
    }
  }

  /**
   * Record the page as it is, before the change about to be made.
   *
   * @param message shown in the strip with a way back, for a change that destroys work.
   *                A move or an insert needs none: what happened is on the screen.
   */
  api.commit = function (message) {
    var state = snapshot();
    if (state === null) {
      return;
    }
    /* A structural change ends any edit in progress, and the next keystroke is a new step
       — recorded from the page as it is once this change has been made, which is after the
       caller's own code has run. The cursor can stay in a field across the change, and
       that field would otherwise never be recorded again until it lost focus. */
    settle();
    push(state);
    if (message) {
      show(message);
    } else {
      hide();
    }
  };

  /*
   * AN EDIT IS A STEP OF ITS OWN (PLAN.md D-117).
   *
   * Undo restores the page as it was before the last change it knows about — and it used to
   * know only about structural ones. So everything typed since then went with it: remove
   * block A, rewrite block B's heading, press Undo, and A came back while B's heading
   * reverted. Measured on the development site, "… else PROBE" back to "… else".
   *
   * Now the page is recorded before a field can change. It is read whenever something
   * among the fields is entered — by focus, or by the pointer, which can change a value
   * before focus has moved: a label, a radio, a picture chosen in the picker — and pushed
   * only when a value actually changes. One entry, one step, however many keystrokes: typing a sentence is one thing
   * to take back, not forty. Undo then takes back the writing first and the structure
   * after it, in the order they happened.
   *
   * The canvas in that record is the canvas BEFORE the edit, so the redraw an edit causes
   * is undone with it; which is why the band redraw no longer takes a step of its own.
   */
  var editing = null;
  var armed = false;

  /**
   * RECORD THE PAGE AS IT NOW STANDS, ready for whatever changes next — once the canvas is
   * ready, after a structural change, after an undo, after a drag. Read after the caller's
   * own code has run, so it holds the change just made and not the page before it.
   *
   * Entering a field records it again (arm, below), which is what keeps one entry one step.
   * This is what covers a change that arrives WITHOUT anyone entering a field: the cursor
   * left in a field across a structural change, or a value set by a script — measured, the
   * 46-drag-columns scenario sets a band's columns that way, and the change took no step.
   */
  function settle() {
    editing = null;
    armed = false;
    window.setTimeout(function () {
      editing = snapshot();
      armed = editing !== null;
    }, 0);
  }

  function arm(event) {
    if (!event.target.closest || !event.target.closest('[data-block-groups], [data-section-groups]')) {
      return;
    }
    editing = snapshot();
    armed = true;
  }

  function edited(event) {
    if (!armed || editing === null || !event.target.closest
      || !event.target.closest('[data-block-groups], [data-section-groups]')) {
      return;
    }
    armed = false;
    push(editing);
    editing = null;
    hide();
  }

  api.form.addEventListener('focusin', arm);
  api.form.addEventListener('pointerdown', arm, true);
  api.form.addEventListener('input', edited, true);
  api.form.addEventListener('change', edited, true);

  api.undo = function () {
    var state = history.pop();
    var page = main();
    if (!state || page === null) {
      return;
    }
    hide();

    var bands = api.sectionGroups();
    api.groups.innerHTML = state.groups;
    if (bands && state.bands !== null) {
      bands.innerHTML = state.bands;
    }
    page.innerHTML = state.canvas;

    /* A restored group's rich text and picker are dead markup for exactly the reason a
       clone's are: the editor host and the picker panel are still bound to the elements
       they were raised on, which this has just replaced. So they are taken back down to
       the plain textarea and select the server sent, and raised again. A band's picture
       is a picker too. */
    [api.groups, bands].forEach(function (root) {
      if (!root) {
        return;
      }
      api.unsetLive(root);
      if (window.boxletRichText) {
        window.boxletRichText.scan(root);
      }
      if (window.boxletPicker) {
        window.boxletPicker.scan(root);
      }
    });

    api.renumber();
    // An answer already in flight was asked for by a page that no longer exists.
    api.forget();
    api.tellCanvas('refresh', {});
    if (state.band && api.selectBand) {
      api.selectBand(state.band);
    } else {
      api.show(state.selected);
      api.selectOnCanvas(state.selected);
    }
    settle();
    offer();
  };

  window.addEventListener('message', function (event) {
    if (event.origin !== window.location.origin || !event.data || event.data.source !== 'boxlet-canvas') {
      return;
    }
    if (event.data.type === 'ready') {
      settle();
    } else if (event.data.type === 'drag-start') {
      beforeDrag = snapshot();
      beforeArrangement = { bands: arrangement('bands'), placed: arrangement('placed') };
    } else if (event.data.type === 'placed' || event.data.type === 'bands') {
      /* Compared with the arrangement the drag started from. PLACES and not an order since
         D-103: a block that crossed into another column can leave the reading order
         untouched, and comparing orders called that "nothing happened" — an un-undoable
         move. */
      var now = event.data.type === 'bands'
        ? event.data.keys.join('|')
        : event.data.at.map(function (where) {
          return where.key + '@' + where.section + ':' + where.column;
        }).join('|');
      if (beforeDrag !== null && beforeArrangement !== null && now !== beforeArrangement[event.data.type]) {
        push(beforeDrag);
        settle();
      }
      beforeDrag = null;
      beforeArrangement = null;
    } else if (event.data.type === 'undo') {
      api.undo();
    }
  });

  document.addEventListener('focusin', function () {
    typed = false;
  });
  document.addEventListener('input', function () {
    typed = true;
  });

  document.addEventListener('keydown', function (event) {
    /* An Escape something else has already answered — the link box in rich text, the
       picture picker — was for that, and closing it must not also drop the selection and
       hide the panel the person is working in. */
    if (event.key === 'Escape' && event.defaultPrevented) {
      return;
    }
    if (event.key === 'Escape') {
      api.show(-1);
      api.tellCanvas('select', { index: -1 });

      return;
    }
    if (!(event.metaKey || event.ctrlKey) || event.key.toLowerCase() !== 'z') {
      return;
    }
    // A field the author has written in keeps its own undo (see the head of this file).
    var inField = event.target.closest && event.target.closest('input, textarea, select, [contenteditable="true"]');
    if (inField && typed) {
      return;
    }
    event.preventDefault();
    api.undo();
  });

  if (button) {
    button.addEventListener('click', function () {
      api.undo();
    });
  }

  if (strip) {
    strip.addEventListener('click', function (event) {
      if (event.target.closest('[data-undo-now]')) {
        event.preventDefault();
        api.undo();
      }
    });
  }
})();
