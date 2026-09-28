/*
 * Drawing a block again as it is edited, and a band again when its arrangement changes,
 * so the canvas shows what a save would store rather than what was stored last.
 *
 * Split from builder-blocks.js (PLAN.md D-117), whose post() and era it uses through the
 * builder's api object. The HTML always comes from the server; this file never builds one.
 */
(function () {
  'use strict';

  var api = window.boxletBuilder;
  if (!api) {
    return;
  }

  var timer = null;
  // The newest redraw sent per block key, so a slow answer cannot overwrite a fast one.
  var drawing = {};
  // The same, per band key, for a band redrawn because its arrangement changed.
  var drawingBand = {};

  /**
   * The selected block's values, renamed from blocks[n][...] to block[...] so the server
   * can clean and re-render just this one.
   */
  /**
   * What the server needs to draw this block as it stands: its own fields, and no more.
   *
   * The band's went with them for one day (D-099), because a redraw then drew the block as
   * a whole band and needed the band's style to draw it right. Since D-103 the canvas always
   * draws columns and a redraw replaces the BLOCK alone, so the band's classes are never in
   * what comes back and never at risk.
   */
  /**
   * THE VALUES A GROUP OF FIELDS STANDS FOR, under the names the server reads.
   *
   * A CONTROL THAT IS NOT CHECKED IS NOT AN ANSWER. A checkbox was excluded here from the
   * start; radios arrived with D-107 and were not, and a group of them shares ONE name — so
   * writing every one into this map left the LAST option standing. The band came back
   * gradient, centred, full width and curve-edged the moment anything redrew it: every
   * closed set's final value at once. The owner saw the whole page turn purple after
   * choosing two columns.
   *
   * ONE FUNCTION, WHICH IS THE REAL FIX. Three places serialised a group of fields and each
   * had decided separately what to do about an unchecked control — so fixing the first left
   * the bug alive in the other two, and it was still there on the next run. The rule is one
   * rule and now lives in one place.
   */
  function collect(node, rename) {
    var out = {};
    node.querySelectorAll('[name]').forEach(function (element) {
      if ((element.type === 'checkbox' || element.type === 'radio') && !element.checked) {
        return;
      }
      out[rename ? rename(element.name) : element.name] = element.value;
    });

    return out;
  }

  function values(group) {
    return collect(group, function (name) {
      return name.replace(/^blocks\[[^\]]*\]/, 'block');
    });
  }

  /**
   * A WHOLE BAND REDRAWN, because its arrangement changed (PLAN.md D-099).
   *
   * Everything the band needs is already on the screen: its own fields, and the fields of
   * every block standing in it. They go to the server with the names they already have, the
   * same parser the save runs cleans them, and what comes back is the band as the visitor
   * would get it.
   *
   * THE KEYS COME BACK ON THE BLOCKS (D-117). They were zipped on here by position, in the
   * FORM's order — which is not the order the band draws its columns in once a block has
   * been added to the first of two, so a redraw could hand one block another's name.
   *
   * THE BAND IS LOOKED UP WHEN THE ANSWER ARRIVES, not when the question is asked. Holding
   * the element across the request lost the second of two quick changes: the first answer
   * replaced the node, and the second replaced a node that was no longer in the page. The
   * ticket drops an answer a later change has overtaken; the era drops one an undo has.
   *
   * NO UNDO STEP IS TAKEN HERE. The choice that caused this was already recorded, before
   * it was made, as an edit (builder-undo.js). One taken now held the new arrangement in
   * the form beside the old one on the canvas, and undoing it could only restore that mix.
   */
  function redrawBand(group) {
    var doc = api.frame.contentDocument;
    var key = group.getAttribute('data-section-group');
    if (!doc || !doc.querySelector('[data-bx-section="' + key + '"]')) {
      return;
    }
    var params = collect(group, function (name) {
      return name.replace(/^sections\[[^\]]*\]/, 'section');
    });
    api.groupNodes().forEach(function (candidate) {
      if (candidate.getAttribute('data-section-key') === key) {
        Object.assign(params, collect(candidate));
      }
    });
    var ticket = (drawingBand[key] || 0) + 1;
    drawingBand[key] = ticket;
    var asked = api.era();

    api.post(params, api.panel.getAttribute('data-band-url'))
      .then(function (parts) {
        var band = doc.querySelector('[data-bx-section="' + key + '"]');
        if (drawingBand[key] !== ticket || api.era() !== asked || !band) {
          return; // overtaken by a later choice or an undo, or the band is gone
        }
        if (!parts.drawn) {
          throw new Error('malformed');
        }
        var fresh = doc.importNode(parts.drawn.content, true).firstElementChild;
        if (!fresh) {
          throw new Error('empty');
        }
        fresh.setAttribute('data-bx-section', key);
        /* THE EDITOR'S OWN MARKS DO NOT COME BACK FROM THE SERVER, which has never heard
           of them: which block was selected, and what has fallen behind its source
           (D-043) — which since D-099 is marked on the band itself. Read off the old band
           and put back, the blocks' by key. */
        if (band.hasAttribute('data-bx-stale')) {
          fresh.setAttribute('data-bx-stale', '');
        }
        band.querySelectorAll('.section-column > [data-bx-key]').forEach(function (block) {
          var now = fresh.querySelector('.section-column > [data-bx-key="' + block.getAttribute('data-bx-key') + '"]');
          if (!now) {
            return;
          }
          if (block.hasAttribute('data-bx-stale')) {
            now.setAttribute('data-bx-stale', '');
          }
          if (block.classList.contains('bx-selected')) {
            now.classList.add('bx-selected');
          }
        });
        band.replaceWith(fresh);
        api.tellCanvas('refresh', {});
      })
      .catch(function (error) {
        api.say(api.panel.getAttribute('data-text-failed'));
        if (window.console) {
          window.console.error('boxlet: could not redraw the band', error);
        }
      });
  }


  // Re-draw the selected block from what is currently typed, so the canvas shows what a
  // save would store rather than what was stored last.
  function redraw() {
    var index = api.selected();
    var group = index >= 0 ? api.groups.querySelector('[data-block-group="' + index + '"]') : null;
    var section = api.canvasBlock(group);
    if (!group || !section) {
      return;
    }
    var type = group.querySelector('[name$="[type]"]');
    if (!type) {
      return;
    }

    // Pin the request to the block, not to where it currently sits. Moving or dragging a
    // block while an answer is in flight would otherwise drop that answer onto whichever
    // block had taken over the position, quietly replacing its content with another's.
    var key = section.getAttribute('data-bx-key');
    var ticket = (drawing[key] || 0) + 1;
    drawing[key] = ticket;
    var asked = api.era();

    api.post(Object.assign({ type: type.value, index: String(index) }, values(group)))
      .then(function (parts) {
        var doc = api.frame.contentDocument;
        var current = doc && doc.querySelector('[data-bx-key="' + key + '"]');
        if (drawing[key] !== ticket || api.era() !== asked || !parts.canvas || !current) {
          return; // superseded by a later edit or an undo, or the block is gone
        }
        var fragment = doc.importNode(parts.canvas.content, true);
        var fresh = fragment.querySelector('section') || fragment.firstElementChild;
        fresh.setAttribute('data-bx-key', key);
        // A stale mark belongs to the block, not to what was typed into it: only "Mark as
        // up to date" clears it, so a redraw carries it over (D-043, step 3).
        if (current.hasAttribute('data-bx-stale')) {
          fresh.setAttribute('data-bx-stale', '');
        }
        /* AND THE NAME OF THE BAND, for the same reason and a newer one (D-099). The server
           has never heard of these marks; they are the editor's, and a redraw that dropped
           this one left the band unaddressable — so choosing a surface and THEN choosing two
           columns did nothing at all, while choosing the columns first worked. Measured,
           after the second choice quietly stopped working in a probe that did both. */
        if (current.hasAttribute('data-bx-section')) {
          fresh.setAttribute('data-bx-section', current.getAttribute('data-bx-section'));
        }
        if (current.classList.contains('bx-selected')) {
          fresh.classList.add('bx-selected');
        }
        current.replaceWith(fragment);
        api.tellCanvas('refresh', {});
      })
      .catch(function (error) {
        // The canvas keeps its last good drawing and Save still validates on the server,
        // but a redraw that fails silently is a canvas quietly telling the truth about
        // nothing, so say so where it can be seen.
        if (window.console) {
          window.console.error('boxlet: could not redraw the block', error);
        }
      });
  }

  // On blur, and shortly after typing stops: often enough to feel live, rarely enough
  // not to render on every keystroke. 300ms is the figure in the 2h brief — at 500 it
  // read as lag rather than as the page following you.
  /*
   * A ROW SIZE THAT ASKS FOR MORE COLUMNS GETS THEM (PLAN.md D-091).
   *
   * Choosing "four in a row" on a block with three columns left an empty cell in the grid
   * and no fourth field to type into. The server tops the content up when it parses, so
   * the canvas would have drawn the fourth column on its own — and the panel would still
   * have had three, which is half the complaint.
   *
   * WHAT "FOUR" MEANS IS NOT KNOWN HERE. The block declares it, the view writes it onto
   * the option as data-wants, and this reads the number off the option that was chosen. It
   * presses the repeater's own Add rather than building an item: one way to add a row,
   * which the fallback editor uses too and which already knows about numbering, the
   * maximum and the empty-state message.
   */
  api.groups.addEventListener('change', function (event) {
    var option = event.target.tagName === 'SELECT' && /\[layout\]$/.test(event.target.name || '')
      ? event.target.options[event.target.selectedIndex]
      : null;
    var wants = option && option.getAttribute('data-wants');
    if (!wants) {
      return;
    }
    var group = event.target.closest('[data-block-group]');
    var asked = {};
    try {
      asked = JSON.parse(wants);
    } catch (error) {
      return;
    }
    Object.keys(asked).forEach(function (field) {
      var repeater = group && group.querySelector('[data-repeater="' + field + '"]');
      var add = repeater && repeater.querySelector('[data-repeater-action="add"]');
      if (!repeater || !add) {
        return;
      }
      // Counted again each time: Add refuses at the maximum, and a loop that trusted its
      // own arithmetic would spin when it did.
      var guard = 0;
      while (repeater.querySelectorAll('[data-repeater-item]').length < asked[field] && guard < 32) {
        if (add.disabled) {
          break;
        }
        add.click();
        guard += 1;
      }
    });
  });

  api.groups.addEventListener('change', redraw);
  api.groups.addEventListener('input', function () {
    window.clearTimeout(timer);
    timer = window.setTimeout(redraw, 300);
  });

  /*
   * AND THE BAND'S OWN FIELDS, which stopped reaching this the day they moved into a group
   * of their own (D-099): the Section tab was a panel where nothing you chose did anything
   * until you saved. It listened here all along; it just listened to the wrong container.
   *
   * TWO THINGS HAPPEN, because a band is drawn by two different pieces of markup. The five
   * style keys are class names on the band's own <section>, which no block redraw can reach
   * when the band holds several — so they are swapped straight onto it, which is instant
   * and needs no round trip. Everything else the server has to draw, so the selected block
   * is redrawn too, carrying the band's fields with it now (values()).
   */
  var bands = document.querySelector('[data-section-groups]');
  if (bands) {
    var STYLE_KEYS = ['surface', 'rhythm', 'width', 'align', 'divider'];
    var paint = function (group) {
      var doc = api.frame.contentDocument;
      var key = group.getAttribute('data-section-group');
      var band = doc && doc.querySelector('[data-bx-section="' + key + '"]');
      if (!band) {
        return;
      }
      STYLE_KEYS.forEach(function (name) {
        // :checked, because the control is a radio group since D-107 and the first radio's
        // value is the first OPTION, not the chosen one. As a <select> this read right by
        // accident of there being only one element to find.
        var field = group.querySelector('[name$="[style][' + name + ']"]:checked');
        if (!field) {
          return;
        }
        // A snapshot, because classList is LIVE: removing while iterating it skips the
        // entry after each removal, which leaves a second `surface-` class behind and lets
        // the old one win or lose by document order.
        Array.prototype.slice.call(band.classList).forEach(function (had) {
          if (had.indexOf(name + '-') === 0) {
            band.classList.remove(had);
          }
        });
        band.classList.add(name + '-' + field.value);
      });
    };
    bands.addEventListener('change', function (event) {
      var group = event.target.closest && event.target.closest('[data-section-group]');
      if (!group) {
        return;
      }
      var name = event.target.name || '';
      /* THE NUMBER OF COLUMNS IS NOT A CLASS, it is the markup around every block in the
         band, so it is the one choice here the browser cannot make look right by itself.
         The server draws the band — the same SectionRender the page uses, so the two
         shapes stay declared once instead of being written out again in JavaScript. */
      if (/\[(layout|stack)\]$/.test(name)) {
        redrawBand(group);

        return;
      }
      paint(group);
      redraw();
    });
    bands.addEventListener('input', function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(redraw, 300);
    });
  }
})();
