/*
 * The visual editor shell: selection and the panel's two modes. The trail and the Section
 * tab's name are builder-trail.js; the page's own settings, the device width and the
 * unsaved-changes guard are builder-page.js — both split off at the hard size limit
 * (PLAN.md D-117).
 *
 * Changes to the page itself live in builder-blocks.js, which attaches to the object
 * this file exposes. Both are deferred, so this one runs first.
 *
 * No block definition, no field markup and no validation lives in either: a block's HTML
 * is always asked for from the server, which renders it from the definition it owns.
 */
(function () {
  'use strict';

  var form = document.querySelector('form[data-builder]');
  var frame = document.querySelector('iframe[data-canvas]');
  if (!form || !frame) {
    return;
  }

  var panel = form.querySelector('[data-insert-url]');
  var groups = form.querySelector('[data-block-groups]');
  var library = form.querySelector('[data-library]');
  var selectedPane = form.querySelector('[data-panel-selected]');
  var selectedName = form.querySelector('[data-selected-name]');
  var status = form.querySelector('[data-insert-status]');
  var selected = -1;

  var api = {
    form: form,
    frame: frame,
    panel: panel,
    groups: groups,
    // Where a "+" on the page aimed; null means the end.
    target: null,
    dirty: false,
  };

  /*
   * Record the page before a structural change, so it can be put back (D-079).
   *
   * A stub here and the real one in builder-undo.js, which loads last: every caller then
   * says what it means — "this is a change worth remembering" — without asking whether
   * the file that remembers is present. Without it the editor works and forgets, which is
   * exactly where it stood before.
   */
  api.commit = function () {};

  api.groupNodes = function () {
    return Array.prototype.slice.call(groups.querySelectorAll('[data-block-group]'));
  };

  /**
   * A BAND SELECTED, which is not a block being selected (PLAN.md D-101).
   *
   * The panel has shown one block at a time since it existed, and a band with nothing in it
   * has no block to show — so this is its own state: every block group hidden, the band's
   * own group shown, and the Section tab turned to, because a band's arrangement and its
   * surface are the whole of what there is to do with one.
   */
  api.selectBand = function (key) {
    selected = -1;
    api.band = key;
    api.groupNodes().forEach(function (group) {
      group.hidden = true;
    });
    document.querySelectorAll('[data-section-group]').forEach(function (part) {
      part.hidden = part.getAttribute('data-section-group') !== key;
    });
    /* AND THE LIBRARY GOES AWAY, because a selected band is showing you ITS settings.
       It used to stay, above the band's own fields, which put them 3,393 pixels down a
       panel nobody scrolls that far: the owner added a section and was shown a wall of
       blocks. "zašto nakon klika na dodavanje sekciju umjesto postavki sekcije kao na
       artifaktu imamo blokove desno" — and, for the same reason, "sekcija se nakon
       dodavanja ne može označiti da bi se vidjele njene postavke".

       The library is what you see when you have AIMED somewhere and are choosing what
       lands there (D-099). Pressing "+ Block" in this band's column brings it back. */
    if (library) {
      library.hidden = true;
    }
    if (selectedPane) {
      selectedPane.hidden = false;
    }
    if (selectedName) {
      selectedName.textContent = form.getAttribute('data-text-band') || '';
    }
    showTab('section');
    api.outlineBand = key;
    if (api.markOutline) {
      api.markOutline(-1);
    }
    if (api.trail) {
      api.trail(-1, key);
    }
    api.tellCanvas('band', { key: key });
  };

  /** The BANDS, for the things that are about bands: inserting one, and placing one. */
  api.bands = function () {
    var doc = frame.contentDocument;

    return doc ? Array.prototype.slice.call(doc.querySelectorAll('[data-bx-blocks] > section')) : [];
  };

  api.selected = function () {
    return selected;
  };

  api.say = function (message) {
    if (status) {
      status.textContent = message || '';
    }
  };

  api.tellCanvas = function (name, detail) {
    if (frame.contentWindow) {
      frame.contentWindow.postMessage(
        Object.assign({ source: 'boxlet-builder', type: name }, detail || {}),
        window.location.origin,
      );
    }
  };

  /*
   * WHICH GROUP THE PANEL SHOWS — and nothing else any more (PLAN.md D-094).
   *
   * This used to rewrite every name and id on the page after every add, move and remove,
   * so that the server received blocks[0..n] in screen order. A block now carries its own
   * name for as long as it exists, and the order comes from the order the groups appear in
   * the request, which is what carried it all along. Two of the three renumbering regexes
   * in this admin are gone with it; the third, in repeater.js, still has real work, because
   * an ITEM's place inside its block is genuinely positional.
   */
  /* Where a band's fields live (D-099). Looked up rather than held, because an undo puts
     the whole panel back as fresh markup and a reference taken at load would point at a
     node no longer in the document. */
  api.sectionGroups = function () {
    return form.querySelector('[data-section-groups]');
  };

  api.renumber = function () {
    api.groupNodes().forEach(function (group, index) {
      group.setAttribute('data-block-group', String(index));
    });
    api.dirty = true;
    orderBands();
    // The outline is a third view of the same page and is rebuilt wherever the other two
    // are (D-100). Guarded because it is a separate file and may not have loaded — and
    // because the plain editor has no outline at all.
    if (api.drawOutline) {
      api.drawOutline();
    }
  };

  /**
   * THE BANDS' FIELD GROUPS, PUT IN THE PAGE'S ORDER (PLAN.md D-102).
   *
   * Page::update() writes the sections in the order they are SUBMITTED, and that order is
   * the order these groups stand in the form. A band added in the middle of the page had
   * its group appended at the end — so the canvas said middle, the outline said middle, and
   * the save said last. Three views, two answers.
   *
   * DERIVED RATHER THAN MAINTAINED. The canvas is where a band's place on the page actually
   * is, so the order is read from it after every structural change instead of every change
   * being careful to insert in the right spot. Nothing is moved when nothing differs, which
   * keeps the keyboard where it was.
   */
  function orderBands() {
    var home = api.sectionGroups ? api.sectionGroups() : null;
    if (!home) {
      return;
    }
    var wanted = api.bands().map(function (band) {
      return band.getAttribute('data-bx-section');
    });
    var groups = Array.prototype.slice.call(home.querySelectorAll('[data-section-group]'));
    var has = groups.map(function (group) {
      return group.getAttribute('data-section-group');
    });
    // A band the canvas has not drawn yet — it loads in its own time — keeps its place.
    has.forEach(function (key) {
      if (wanted.indexOf(key) < 0) {
        wanted.push(key);
      }
    });
    if (wanted.join('|') === has.join('|')) {
      return;
    }
    wanted.forEach(function (key) {
      var group = home.querySelector('[data-section-group="' + key + '"]');
      if (group) {
        home.appendChild(group);
      }
    });
  }

  /*
   * CONTENT AND SECTION (PLAN.md D-086).
   *
   * The section's style sat at the foot of the group's scroll, behind every content field:
   * measured on the demo page, with the panel's first field on screen at y=287, the first
   * style control was at y=1308 on a Hero and y=4296 on a Columns block, in a window 1000px
   * tall. Four screens down, past twelve repeater items, for the thing most often changed
   * while looking at the page.
   *
   * The split is a class on the panel and two rules in the stylesheet, not a rearrangement
   * of the group: block.php is the PLAIN editor's view too, and there the group stays one
   * scroll with the style folded at its foot. Moving markup around would have meant two
   * shapes of one thing, and the plain editor is the fallback that has to keep working.
   *
   * Which tab is open is remembered for the session, not per block: somebody adjusting how
   * a page looks moves from block to block doing the same thing, and being thrown back to
   * Content on every selection would undo that.
   */
  var tabs = form.querySelector('[data-panel-tabs]');

  function showTab(name) {
    form.setAttribute('data-panel-tab', name);
    /* The style is a <details> because the plain editor folds it; behind a tab it must be
       open, since the tab is what unfolded it and its own summary is hidden. Without this
       the Section tab was EMPTY — and a check that read getBoundingClientRect() on a field
       inside the closed <details> reported a box 40px tall at y=340, because the browser
       lays out what it does not paint. The screenshot was right and the measurement was
       wrong; what is asserted now is the height of the <details> itself. */
    form.querySelectorAll('[data-panel-part="section"]').forEach(function (part) {
      part.open = true;
    });
    if (!tabs) {
      return;
    }
    tabs.querySelectorAll('[data-panel-tab]').forEach(function (tab) {
      tab.setAttribute('aria-selected', tab.getAttribute('data-panel-tab') === name ? 'true' : 'false');
    });
  }

  /* Shown to the outline (D-100), which turns to the Section tab when a band is pressed.
     A second caller, so it stops being a detail of the tab strip and becomes something the
     editor can ask for. */
  api.showTab = showTab;

  if (tabs) {
    tabs.addEventListener('click', function (event) {
      var tab = event.target.closest('[data-panel-tab]');
      if (tab) {
        showTab(tab.getAttribute('data-panel-tab'));
      }
    });
    showTab('content');
  }

  /**
   * A BLOCK CHOSEN BY THE PERSON — pressed on the page or in the page outline (D-108).
   *
   * THE PANEL TURNS TO CONTENT. The Section tab stays open across selections, so pressing a
   * block while it was open left you looking at the band's settings with a block's name
   * above them: "zbunjuje", and fairly — you asked for the block and were shown its
   * container. Only a selection somebody MADE does this. api.show() runs for a dozen other
   * reasons — an undo, a redraw, a block moved with the arrows — and turning the tab on
   * those would snap the panel away mid-edit.
   *
   * Selecting a BAND does the opposite and still turns to Section, in api.selectBand().
   */
  api.chooseBlock = function (index) {
    api.show(index);
    if (index >= 0 && form.getAttribute('data-panel-tab') === 'section') {
      showTab('content');
    }
  };

  api.show = function (index) {
    selected = index;
    api.band = null;
    /* Re-applied on every selection because a field group can arrive as FRESH MARKUP — an
       undo puts all of them back, an insert brings a new one from the server — and such a
       group's <details> is open only when block.php happened to render it open, which is
       when its style differs from the character's. On the Section tab a closed one shows
       nothing. It survived the first check by that accident; this is the rule. */
    if (tabs) {
      showTab(form.getAttribute('data-panel-tab') || 'content');
    }
    api.groupNodes().forEach(function (group) {
      group.hidden = Number(group.getAttribute('data-block-group')) !== index;
    });
    /* AND THE BAND THE SELECTED BLOCK STANDS IN (PLAN.md D-099). The section's fields are
       one group per band, not one per block, so selecting any block in a band shows the
       same controls — which is what makes "a tinted band of three text blocks is one
       setting" true rather than three settings that have to be kept in step. */
    var band = index >= 0 ? groups.querySelector('[data-block-group="' + index + '"]') : null;
    var bandKey = band ? band.getAttribute('data-section-key') : null;
    document.querySelectorAll('[data-section-group]').forEach(function (part) {
      part.hidden = part.getAttribute('data-section-group') !== bandKey;
    });
    if (api.markOutline) {
      api.markOutline(index);
    }
    if (library) {
      library.hidden = index >= 0;
    }
    if (selectedPane) {
      selectedPane.hidden = index < 0;
    }
    var group = index >= 0 ? groups.querySelector('[data-block-group="' + index + '"]') : null;
    if (group && selectedName) {
      var labelled = group.querySelector('[data-block-label]');
      selectedName.textContent = labelled ? labelled.getAttribute('data-block-label') : '';
    }
    /*
     * PUT THE CURSOR IN THE BLOCK'S FIRST FIELD — UNLESS THE PERSON IS IN THE CANVAS.
     *
     * Selecting a block from the library should leave you ready to type, which is what this
     * is for. But selection also happens as a CONSEQUENCE of something done in the canvas —
     * a click on a section, a press of Move down — and there this reached into the panel and
     * took the keyboard out of the canvas. Measured: focus a tool button, press it, and the
     * next press needs the mouse, because focus had left the iframe altogether. It is also
     * what made ⌘Z after a duplicate do nothing (D-079): the cursor was in a field the
     * author had never asked for.
     *
     * When focus is inside the canvas the parent's activeElement IS the iframe, so the rule
     * costs one comparison: the editor never takes the keyboard away from where the person
     * is working.
     */
    if (group && document.activeElement !== api.frame) {
      var field = group.querySelector('input:not([type="hidden"]), textarea, select');
      if (field) {
        field.focus({ preventScroll: true });
      }
    }
    if (api.trail) {
      api.trail(index, null);
    }
  };

  // Keys pair a section with its field group and survive reordering, so a drag in the
  // canvas can be replayed on the form without either side guessing.
  /**
   * WHICH FIELD GROUP CARRIES THIS KEY, and where it stands in the form (PLAN.md D-094).
   *
   * The canvas and the form hold the same blocks under the same keys and, the moment one
   * is added, in DIFFERENT ORDERS: the canvas draws the new block where it stands on the
   * page while its group is appended at the end of the form. So a position crossing between
   * them names the wrong block, and did — every click opened the next block's fields.
   */
  api.indexForKey = function (key) {
    var group = typeof key === 'string' && key !== ''
      ? groups.querySelector('[data-block-key="' + key + '"]')
      : null;

    return group ? Number(group.getAttribute('data-block-group')) : -1;
  };

  /** Select a block on the canvas by the FORM position, sending the key it stands for. */
  api.selectOnCanvas = function (index) {
    var group = index >= 0 ? groups.querySelector('[data-block-group="' + index + '"]') : null;
    api.tellCanvas('select', { index: index, key: group ? group.getAttribute('data-block-key') : null });
  };

  /**
   * THE CANVAS ELEMENT FOR A FIELD GROUP, found by the key both carry (PLAN.md D-117).
   *
   * Never by position. The form's order is not the page's: a block added to a band that has
   * no group yet goes to the end of the form, and a band dragged on the canvas leaves its
   * blocks' groups where they were. Every action that found its block as
   * `api.sections()[index]` — remove, move, duplicate, redraw — acted on whatever block
   * happened to stand at the form's position on the page. Measured: a block added fourth
   * on the page and seventh in the form, and Remove took the page's LAST block, off the
   * screen, while its own stayed. The server writes the key on both halves, so the pairing
   * exists from the first render and nothing has to reconstruct it.
   */
  api.canvasBlock = function (group) {
    var doc = frame.contentDocument;
    var key = group ? group.getAttribute('data-block-key') : null;

    return doc && key ? doc.querySelector('.section-column > [data-bx-key="' + key + '"]') : null;
  };

  /**
   * A DRAG REPLAYED ON THE FORM (PLAN.md D-103), as PLACES and not as an order.
   *
   * A flat list of keys could say that two blocks swapped; it could not say that one of
   * them crossed into another column, which is the thing a tree makes possible and the
   * thing a page is actually arranged by. So each block is told which band and which column
   * it now stands in, and the form is put in that order — bands in the canvas's order, and
   * within one, its columns, and within one, what stands in it.
   */
  function placeTo(at) {
    at.forEach(function (where) {
      var group = groups.querySelector('[data-block-key="' + where.key + '"]');
      if (!group) {
        return;
      }
      group.setAttribute('data-section-key', where.section);
      group.querySelectorAll('[data-block-section]').forEach(function (input) {
        input.value = where.section;
      });
      group.querySelectorAll('[data-block-column]').forEach(function (input) {
        input.value = String(where.column);
      });
      // Appended in the order they arrive, which is the page's reading order.
      groups.appendChild(group);
    });
    api.renumber();
    api.show(-1);
    api.tellCanvas('select', { index: -1 });
  }

  /** Bands reordered by a drag: the groups follow, and renumber() puts them in that order. */
  function bandsTo() {
    api.renumber();
    api.show(-1);
    api.tellCanvas('select', { index: -1 });
  }

  // A group carrying a validation error opens itself, so a rejected save does not hide
  // the reason behind a selection nobody has made yet.
  function failedGroup() {
    var failed = groups.querySelector('[data-block-group] .field-error');
    var group = failed && failed.closest('[data-block-group]');
    return group ? Number(group.getAttribute('data-block-group')) : -1;
  }

  window.addEventListener('message', function (event) {
    if (event.origin !== window.location.origin || !event.data || event.data.source !== 'boxlet-canvas') {
      return;
    }
    if (event.data.type === 'ready') {
      api.show(failedGroup());
    } else if (event.data.type === 'select') {
      api.target = null;
      // By key, never by index: the canvas's index counts the PAGE's order, not the form's.
      api.chooseBlock(api.indexForKey(event.data.key));
    } else if (event.data.type === 'selectband') {
      // A band pressed in the page. Named apart from the 'band' message going the other
      // way, which is this editor TELLING the canvas what is marked.
      api.target = null;
      if (api.selectBand) {
        api.selectBand(event.data.key);
      }
    } else if (event.data.type === 'section') {
      // A BAND ADDED BETWEEN BANDS (D-101). It used to be a block that brought a band with
      // it, which is why there was no way to add a section at all.
      if (api.addBand) {
        api.addBand(event.data.index);
      }
    } else if (event.data.type === 'insert') {
      /* WHERE THE NEXT BLOCK GOES: a position on the page, or an address inside a band —
         which band, which of its columns (D-099). A number and an object rather than two
         messages, because everything downstream asks the same question ("where?") and
         only insert() has to know there are two kinds of answer. */
      api.target = event.data.section
        ? { section: event.data.section, column: event.data.column || 0 }
        : event.data.index;
      api.show(-1);
      api.tellCanvas('select', { index: -1 });
      if (library) {
        library.scrollIntoView({ block: 'nearest' });
      }
    } else if (event.data.type === 'placed') {
      placeTo(event.data.at);
    } else if (event.data.type === 'bands') {
      bandsTo();
    } else if (event.data.type === 'action' && api.act) {
      // The selected block's controls in the canvas (D-040): the same act() the panel's
      // buttons used to call, so there is one way to move, copy or remove a block.
      api.act(event.data.action);
    }
  });

  window.boxletBuilder = api;
  /* THE FIRST SELECTION WAITS FOR THE REST OF THE EDITOR. The trail and the outline are
     files of their own (PLAN.md D-117) and run after this one; every deferred script has
     run by DOMContentLoaded, so this is the first moment all of them can answer. */
  document.addEventListener('DOMContentLoaded', function () {
    api.show(failedGroup());
  });
})();
