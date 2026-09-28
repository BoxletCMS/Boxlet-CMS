/*
 * Adding to the page: a block into a column, a band between bands — and the one request
 * helper and the era every piece of the editor that asks the server shares.
 *
 * A section in the canvas and its field group in the form are the same block seen twice,
 * so every change here moves both together and then renumbers. The block's HTML always
 * comes from the server; this file never builds one.
 *
 * SPLIT IN THREE when it passed the hard size limit (PLAN.md D-117), along the three things
 * it did: this file ADDS; builder-redraw.js draws a block again as it is edited;
 * builder-actions.js moves, copies and removes. They share post() and the era through the
 * builder's api object, and load in that order after builder.js.
 */
(function () {
  'use strict';

  var api = window.boxletBuilder;
  if (!api) {
    return;
  }

  var keyCounter = 0;
  /* An era, bumped whenever the page is replaced wholesale (undo, D-079). A per-key ticket
     pins an answer to its block; this pins it to the page that asked for it, which a
     per-key ticket cannot do — put the page back and the next request for that key would
     take the number the answer in flight already holds. */
  var era = 0;

  function post(params, url) {
    var body = new URLSearchParams();
    body.set('_csrf', api.form.querySelector('input[name="_csrf"]').value);
    Object.keys(params).forEach(function (key) {
      body.set(key, params[key]);
    });

    return fetch(url || api.panel.getAttribute('data-insert-url'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    }).then(function (response) {
      if (!response.ok) {
        throw new Error(String(response.status));
      }
      return response.text();
    }).then(function (html) {
      var holder = document.createElement('div');
      holder.innerHTML = html;
      return {
        canvas: holder.querySelector('template[data-block-canvas]'),
        fields: holder.querySelector('template[data-block-fields]'),
        // The band this block arrives in, when it arrives as one of its own (D-099).
        band: holder.querySelector('template[data-section-fields]'),
        // A whole band redrawn, from the other endpoint.
        drawn: holder.querySelector('template[data-band-canvas]'),
      };
    });
  }

  /**
   * A BLOCK PUT INTO A COLUMN OF A BAND THAT ALREADY EXISTS (PLAN.md D-099).
   *
   * Not place(): that inserts a band at a position on the page, with the canvas element and
   * the field group at the same index in two flat lists. Here the canvas side goes inside a
   * column and the form side goes after the last group already standing in that band — two
   * different questions, which is why this is its own function rather than a branch inside
   * that one.
   *
   * The group is named with the BAND'S OWN key, not a fresh one: nameGroup() mints a new
   * section key when it is not told which band to join, and a block put into an existing
   * band that minted its own would be saved into a band of its own — the arrangement the
   * author just made, undone by the act of filling it.
   */
  function placeInColumn(into, node, group) {
    var doc = api.frame.contentDocument;
    var band = doc.querySelector('[data-bx-section="' + into.section + '"]');
    var column = band && band.querySelectorAll('.section-column')[into.column];
    if (!column) {
      throw new Error('no column ' + into.column + ' in section ' + into.section);
    }
    column.appendChild(node);

    var key = group.getAttribute('data-block-key');
    if (key && window.boxletBlocks) {
      window.boxletBlocks.name(group, key, into.section);
    }
    // And which column, which nameGroup has no opinion about: it names things, it does not
    // place them.
    group.querySelectorAll('[data-block-column]').forEach(function (input) {
      input.value = String(into.column);
    });

    // AFTER THE LAST GROUP ALREADY IN THIS BAND, so the form's order goes on being the
    // page's order: sections in order, and within one, its blocks.
    var groups = api.groupNodes();
    var last = null;
    groups.forEach(function (candidate) {
      if (candidate.getAttribute('data-section-key') === into.section) {
        last = candidate;
      }
    });
    group.setAttribute('data-section-key', into.section);
    if (last && last.nextSibling) {
      api.groups.insertBefore(group, last.nextSibling);
    } else {
      api.groups.appendChild(group);
    }

    api.renumber();
    if (window.boxletRichText) {
      window.boxletRichText.scan(group);
    }
    if (window.boxletPicker) {
      window.boxletPicker.scan(group);
    }
    api.tellCanvas('refresh', {});
    var index = Number(group.getAttribute('data-block-group'));
    api.show(index);
    /* AND THE CONTENT TAB, because a block was just added and what you do with a new block
       is write in it. Adding one into an empty column left the panel on the Section tab,
       where a block's fields are display: none — so the block existed, was selected, and
       could not be typed into; its own field could not even be focused. */
    api.showTab('content');
    api.selectOnCanvas(index);
  }

  /**
   * A BAND ADDED BETWEEN BANDS (PLAN.md D-101).
   *
   * It arrives EMPTY, with one column, and is selected with the Section tab open — so the
   * next thing on the screen is its arrangement, which is the next thing a person wants.
   * Blocks go into it through the + in its column, which is the order the owner chose
   * (D-099): the shape first, then what stands in it.
   *
   * The server draws both halves, as it does for a block: a band nobody can see is not a
   * band, and a band with no fields is one nothing can be done to.
   */
  function addBand(at) {
    var doc = api.frame.contentDocument;
    if (!doc) {
      return;
    }
    api.say(api.panel.getAttribute('data-text-inserting'));
    var asked = era;
    post({ 'section[layout]': 'one', 'section[stack]': 'stack' }, api.panel.getAttribute('data-band-url'))
      .then(function (parts) {
        // Asked for by a page an undo has since replaced: it has nowhere true to go.
        if (era !== asked) {
          api.say('');

          return;
        }
        if (!parts.drawn || !parts.band) {
          throw new Error('malformed');
        }
        var key = window.boxletBlocks ? window.boxletBlocks.mintSection() : 'm0';
        var band = parts.band.content.firstElementChild.cloneNode(true);
        band.setAttribute('data-section-group', key);
        if (window.boxletBlocks) {
          window.boxletBlocks.name(band, 'n0', key);
        }
        api.sectionGroups().appendChild(band);

        var fresh = doc.importNode(parts.drawn.content, true).firstElementChild;
        fresh.setAttribute('data-bx-section', key);
        var bands = api.bands();
        var main = doc.querySelector('[data-bx-blocks]');
        api.commit();
        if (at >= bands.length) {
          main.appendChild(fresh);
        } else {
          main.insertBefore(fresh, bands[at]);
        }
        api.say('');
        api.target = null;
        // The page changed, so the outline is redrawn and the form is dirty — the same
        // renumber() every other structural change ends with.
        api.renumber();
        api.tellCanvas('refresh', {});
        /* NOTHING IS SELECTED IN THE PANEL, because a band holds no block yet and the panel
           shows blocks. What is shown is the band's own fields, which is what there is to
           do with it. */
        api.selectBand(key);
      })
      .catch(function (error) {
        api.say(api.panel.getAttribute('data-text-failed'));
        if (window.console) {
          window.console.error('boxlet: could not add the band', error);
        }
      });
  }

  api.addBand = addBand;

  function insert(type, button) {
    if (!api.frame.contentDocument) {
      return;
    }
    /* WHERE IT LANDS IS CHOSEN FIRST (PLAN.md D-103, and the design artifact): which band,
       which of its columns. A card pressed with nowhere aimed at used to add the block as a
       band of its own at the END of the page — a guess, and the one place nobody meant. Now
       it says where to press, which is what the library's own line has said since. */
    var into = api.target !== null && typeof api.target === 'object' ? api.target : null;
    if (into === null) {
      api.say(api.panel.getAttribute('data-text-aim') || '');

      return;
    }
    button.setAttribute('aria-busy', 'true');
    api.say(api.panel.getAttribute('data-text-inserting'));

    var asked = era;
    post({ type: type, index: '0', column: String(into.column) })
      .then(function (parts) {
        // The same as a band: an undo since the press means the column it aimed at may
        // not be the one on the page now.
        if (era !== asked) {
          api.say('');

          return;
        }
        if (!parts.canvas || !parts.fields) {
          throw new Error('malformed');
        }
        // Minted where every other new group's key is, so nothing can collide.
        var key = window.boxletBlocks ? window.boxletBlocks.mint() : 'n' + keyCounter++;
        var fragment = api.frame.contentDocument.importNode(parts.canvas.content, true);
        // A block going into a column is not a <section> — the band around it already is
        // one — so the thing to name is whatever the server drew (SectionRender's 'none').
        var drawn = fragment.querySelector('section') || fragment.firstElementChild;
        drawn.setAttribute('data-bx-key', key);

        var group = parts.fields.content.firstElementChild.cloneNode(true);
        group.setAttribute('data-block-key', key);
        group.hidden = true;

        api.say('');
        api.target = null;
        // Nothing changed until now: the request could still have failed.
        api.commit();
        placeInColumn(into, drawn, group);
      })
      .catch(function (error) {
        api.say(api.panel.getAttribute('data-text-failed'));
        // The message above is about the network, and this is anything at all. A mistake
        // in the code above read as "check your connection" until it was traced by hand.
        if (window.console) {
          window.console.error('boxlet: could not insert the block', error);
        }
      })
      .then(function () {
        button.removeAttribute('aria-busy');
      });
  }

  /* The request and the era are the other two files' too (builder-redraw.js,
     builder-actions.js): one way to ask the server, and one page it was asked for. */
  api.post = post;
  api.era = function () {
    return era;
  };

  /* Forget every answer still in flight: it was asked for by a page that is now gone. */
  api.forget = function () {
    era += 1;
  };

  api.form.addEventListener('click', function (event) {
    var add = event.target.closest && event.target.closest('[data-add-type]');
    if (add) {
      event.preventDefault();
      insert(add.getAttribute('data-add-type'), add);
    }
  });
})();
