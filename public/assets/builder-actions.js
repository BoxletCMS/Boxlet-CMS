/*
 * What the selected block's and band's tools do: move, duplicate, remove (PLAN.md D-040,
 * D-102), from the canvas's toolbar and the panel's buttons alike.
 *
 * Split from builder-blocks.js (PLAN.md D-117). Every action finds its canvas element by
 * the key it shares with its field group, never by position (api.canvasBlock).
 */
(function () {
  'use strict';

  var api = window.boxletBuilder;
  if (!api) {
    return;
  }

  var keyCounter = 0;

  /**
   * Turn a cloned field group's rich text back into the plain textarea the server sent,
   * so place() can set it up as a new editor.
   *
   * A clone of a live editor is not an editor: it carries data-richtext-ready, so setup
   * skips it, and an editor host still bound to the block it was copied from. What is on
   * screen matters too — in rich mode the text lives in the hidden input, while the
   * textarea still holds what the server rendered, so a copy that ignored it would show
   * the old text and quietly discard the author's edits.
   */
  function unsetRichText(group) {
    group.querySelectorAll('textarea[data-richtext-source]').forEach(function (textarea) {
      var field = textarea.closest('[data-richtext]');
      if (!field) {
        return;
      }
      var hidden = field.querySelector('input[type="hidden"][name]');
      if (hidden) {
        textarea.value = hidden.value;
        textarea.name = hidden.name;
        hidden.remove();
      }
      var editor = field.querySelector('[data-richtext-editor]');
      if (editor) {
        editor.remove();
      }
      field.classList.remove('richtext-rich', 'richtext-plain');
      textarea.removeAttribute('data-richtext-ready');
    });
  }

  /**
   * Turn a cloned field group's pickers back into the plain selects the server sent, so
   * place() can raise new ones on them.
   *
   * The same reasoning as unsetRichText above: a clone of a live picker is not a picker.
   * It carries data-picker-ready, so scan() skips it, and the button and panel beside it
   * are dead markup whose listeners stayed with the block it was copied from — a duplicate
   * whose picture field could be read but never changed.
   */
  function unsetPicker(group) {
    group.querySelectorAll('select[data-picker-ready]').forEach(function (select) {
      var picker = select.parentNode.querySelector('.media-picker');
      if (picker) {
        picker.remove();
      }
      select.hidden = false;
      select.removeAttribute('data-picker-ready');
    });
  }

  /**
   * THE FOUR THINGS YOU CAN DO TO A WHOLE BAND (PLAN.md D-102).
   *
   * The same four a block has, acting on the band and everything standing in it. A band is
   * three things at once — an element on the canvas, a group of fields, and the blocks it
   * holds — so each of these has to move all three, and the block groups have to move as a
   * RUN: they are contiguous in the form because the form is in the page's reading order.
   */
  function actOnBand(action) {
    var doc = api.frame.contentDocument;
    var key = api.band;
    var band = doc && key ? doc.querySelector('[data-bx-section="' + key + '"]') : null;
    var group = key ? document.querySelector('[data-section-group="' + key + '"]') : null;
    if (!band || !group) {
      return;
    }
    var mine = api.groupNodes().filter(function (candidate) {
      return candidate.getAttribute('data-section-key') === key;
    });

    if (action === 'band-remove') {
      api.commit(api.panel.getAttribute('data-text-band-removed') || '');
      band.remove();
      group.remove();
      mine.forEach(function (candidate) {
        candidate.remove();
      });
      api.renumber();
      api.tellCanvas('refresh', {});
      api.show(-1);

      return;
    }

    if (action === 'band-duplicate') {
      api.commit();
      var freshKey = window.boxletBlocks ? window.boxletBlocks.mintSection() : 'm0';
      var bandCopy = band.cloneNode(true);
      bandCopy.setAttribute('data-bx-section', freshKey);
      bandCopy.classList.remove('bx-band-selected');
      var groupCopy = group.cloneNode(true);
      groupCopy.setAttribute('data-section-group', freshKey);
      groupCopy.hidden = true;
      if (window.boxletBlocks) {
        window.boxletBlocks.name(groupCopy, 'n0', freshKey);
      }
      /* A COPY IS A NEW BAND, SO IT CARRIES NO ID (D-117). The blocks' ids were taken out
         below from the start; the band's own was not, so the copy went on naming the band
         it came from — and the save wrote both into that one, which then held every block
         twice while the copy vanished. Measured on the form: `m0` carrying the id of `s159`. */
      groupCopy.querySelectorAll('[name$="[id]"]').forEach(function (field) {
        field.remove();
      });
      api.sectionGroups().appendChild(groupCopy);

      /* EVERY BLOCK IN IT COPIED TOO, each with a key of its own and no id — an id would
         make the save write over the block this was copied FROM. The same rule the block
         duplicate follows, applied once per block. Each drawn copy is found by the key it
         was copied under, not by counting: the form's order is not the page's (D-117). */
      mine.forEach(function (candidate) {
        var drawnCopy = bandCopy.querySelector(
          '.section-column > [data-bx-key="' + candidate.getAttribute('data-block-key') + '"]',
        );
        var copy = candidate.cloneNode(true);
        var freshBlock = window.boxletBlocks ? window.boxletBlocks.mint() : 'n0';
        copy.hidden = true;
        copy.setAttribute('data-section-key', freshKey);
        copy.setAttribute('data-block-key', freshBlock);
        copy.querySelectorAll('[name$="[id]"]').forEach(function (field) {
          field.remove();
        });
        if (window.boxletBlocks) {
          window.boxletBlocks.name(copy, freshBlock, freshKey);
        }
        if (api.unsetLive) {
          api.unsetLive(copy);
        }
        api.groups.appendChild(copy);
        if (drawnCopy) {
          drawnCopy.setAttribute('data-bx-key', freshBlock);
          drawnCopy.classList.remove('bx-selected');
        }
      });

      band.after(bandCopy);
      api.renumber();
      if (window.boxletRichText) {
        window.boxletRichText.scan(api.groups);
      }
      if (window.boxletPicker) {
        window.boxletPicker.scan(api.groups);
      }
      api.tellCanvas('refresh', {});
      api.selectBand(freshKey);

      return;
    }

    var bands = api.bands();
    var was = bands.indexOf(band);
    var to = action === 'band-up' ? was - 1 : was + 1;
    if (to < 0 || to >= bands.length) {
      return;
    }
    api.commit();
    if (action === 'band-up') {
      bands[to].before(band);
    } else {
      bands[to].after(band);
    }
    /* THE FIELD GROUPS FOLLOW, AND ARE NOT MOVED BY HAND. renumber() puts the band groups
       into the canvas's order (D-102), and the block groups are re-read from it too — so a
       move is one statement about the page and the rest is derived. */
    var after = api.bands();
    var order = [];
    after.forEach(function (one) {
      api.groupNodes().forEach(function (candidate) {
        if (candidate.getAttribute('data-section-key') === one.getAttribute('data-bx-section')) {
          order.push(candidate);
        }
      });
    });
    order.forEach(function (candidate) {
      api.groups.appendChild(candidate);
    });
    api.renumber();
    api.tellCanvas('refresh', {});
    api.selectBand(key);
  }

  function act(action) {
    if (action.indexOf('band-') === 0) {
      actOnBand(action);

      return;
    }
    var index = api.selected();
    var group = api.groups.querySelector('[data-block-group="' + index + '"]');
    // By key (D-117): the form's position is not the page's.
    var section = api.canvasBlock(group);
    if (!group || !section) {
      return;
    }

    if (action === 'remove') {
      // The one action that destroys work, so it is the one that says it can be undone:
      // the shortcut is not discoverable, and the people who most need it are the ones
      // who do not know it is there (D-079).
      var labelled = group.querySelector('[data-block-label]');
      api.commit(api.panel.getAttribute('data-text-removed').replace(
        ':block',
        labelled ? labelled.getAttribute('data-block-label') : '',
      ));
      section.remove();
      group.remove();
      api.renumber();
      api.tellCanvas('refresh', {});
      api.show(-1);
      api.tellCanvas('select', { index: -1 });
      return;
    }

    if (action === 'duplicate') {
      api.commit();
      var key = window.boxletBlocks ? window.boxletBlocks.mint() : 'n' + keyCounter++;
      var copy = section.cloneNode(true);
      copy.setAttribute('data-bx-key', key);
      copy.classList.remove('bx-selected');
      var groupCopy = group.cloneNode(true);
      groupCopy.setAttribute('data-block-key', key);
      groupCopy.hidden = true;
      unsetRichText(groupCopy);
      unsetPicker(groupCopy);
      // A duplicate is a new block. Keeping the id would make the save overwrite the
      // block it was copied from instead of adding one.
      var id = groupCopy.querySelector('[name$="[id]"]');
      if (id) {
        id.remove();
      }
      /* BESIDE THE BLOCK IT WAS COPIED FROM, in the same column (PLAN.md D-103). It used to
         become a band of its own at the next position on the page, which was the only
         answer there was while a page was a list of blocks and is the wrong one now: a copy
         of one of three things in a column belongs in that column. */
      window.boxletBlocks.name(groupCopy, key, group.getAttribute('data-section-key'));
      groupCopy.setAttribute('data-section-key', group.getAttribute('data-section-key'));
      section.after(copy);
      group.after(groupCopy);
      api.renumber();
      if (window.boxletRichText) {
        window.boxletRichText.scan(groupCopy);
      }
      if (window.boxletPicker) {
        window.boxletPicker.scan(groupCopy);
      }
      api.tellCanvas('refresh', {});
      var born = Number(groupCopy.getAttribute('data-block-group'));
      api.show(born);
      api.showTab('content');
      api.selectOnCanvas(born);

      return;
    }

    /* WITHIN ITS OWN COLUMN (PLAN.md D-103). It used to be within the page: a flat list of
       blocks had one order and moving down meant the next place in it. In a tree the next
       place is in the same column, and stepping outside it would drop the block into a
       neighbouring band — leaving one band empty and another holding something nobody put
       there. Across columns is dragging; the whole band has arrows of its own. */
    var column = section.parentNode;
    var siblings = Array.prototype.slice.call(column.children);
    var here = siblings.indexOf(section);
    var to = action === 'up' ? here - 1 : here + 1;
    if (to < 0 || to >= siblings.length) {
      return;
    }
    api.commit();
    var neighbour = siblings[to];
    var neighbourGroup = null;
    api.groupNodes().forEach(function (candidate) {
      if (candidate.getAttribute('data-block-key') === neighbour.getAttribute('data-bx-key')) {
        neighbourGroup = candidate;
      }
    });
    if (action === 'up') {
      neighbour.before(section);
      if (neighbourGroup) {
        neighbourGroup.before(group);
      }
    } else {
      neighbour.after(section);
      if (neighbourGroup) {
        neighbourGroup.after(group);
      }
    }
    api.renumber();
    api.tellCanvas('refresh', {});
    // Which block it is NOW, read back rather than worked out: renumber() has just decided.
    var moved = Number(group.getAttribute('data-block-group'));
    api.show(moved);
    api.selectOnCanvas(moved);
  }

  api.act = act;

  /* Undo puts the field groups back as HTML, which leaves every rich text and picker in
     them dead markup for the same reason a clone's is — hence the second caller these two
     were written for, and the reason they are reachable from outside this file (D-079). */
  api.unsetLive = function (root) {
    unsetRichText(root);
    unsetPicker(root);
  };


  api.form.addEventListener('click', function (event) {
    var action = event.target.closest && event.target.closest('[data-block-action]');
    if (action) {
      event.preventDefault();
      act(action.getAttribute('data-block-action'));
    }
  });
})();
