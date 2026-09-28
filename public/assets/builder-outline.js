/*
 * THE PAGE OUTLINE (PLAN.md D-100): reaching a block, and seeing where you are.
 *
 * The rows arrive drawn by the server, so the tree is right before this file has run and
 * right again after a save. What this adds is the three things a script is for: pressing a
 * row selects what it names, the row you are on is marked, and the rows are rebuilt as
 * blocks come and go.
 *
 * REBUILT FROM THE PANEL, NEVER FROM A SECOND MODEL. Every field group already says which
 * band it stands in, what its block is called and which icon it wears, and every band group
 * says what its arrangement is — that is the same data the save posts, so an outline built
 * from it cannot drift from what would be stored. The alternative is a copy of the page's
 * shape in JavaScript, and a copy is a thing to keep in step.
 */
(function () {
  var form = document.querySelector('form[data-builder]');
  var api = window.boxletBuilder;
  var rail = document.querySelector('[data-outline]');
  if (!form || !api || !rail) {
    return;
  }
  var rows = rail.querySelector('[data-outline-rows]');
  var count = rail.querySelector('[data-outline-count]');
  var sprite = rail.getAttribute('data-icons') || '';
  var LAYOUT_NOTE = {
    one: '1', halves: '1/2', thirds: '1/3', quarters: '1/4',
    'wide-left': '2/3+', 'wide-right': '+2/3', sidebar: '3/4+',
  };
  var COLUMNS = {
    one: 1, halves: 2, thirds: 3, quarters: 4,
    'wide-left': 2, 'wide-right': 2, sidebar: 2,
  };

  /**
   * WHAT THIS EDITOR CALLS A BLOCK: `b42` for one the database knows, `n7` for one added in
   * this session (D-094). Read off a field name, where it is the one thing that is certainly
   * there — and NOT `data-block-key`, which is the key the canvas is PAIRED by and a
   * different thing entirely. Mistaking one for the other drew an outline whose rows named
   * blocks nothing could find.
   */
  function nameOf(group) {
    var field = group.querySelector('input[type="hidden"][name$="[type]"]');
    var found = field && String(field.name).match(/^blocks\[([^\]]+)\]/);

    return found ? found[1] : '';
  }

  function numbered(attribute, n) {
    return (rail.getAttribute(attribute) || '%').replace('%', String(n));
  }

  function row(kind, label, note, extra) {
    var el = document.createElement(kind === 'column' ? 'p' : 'button');
    el.className = 'outline-row outline-row-' + kind + (extra || '');
    if (kind !== 'column') {
      el.type = 'button';
    }
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'icon');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', sprite + '#i-' + (kind === 'section' ? 'panels-top-left' : kind === 'column' ? 'columns-3' : label.icon));
    svg.appendChild(use);
    el.appendChild(svg);
    var name = document.createElement('span');
    name.className = 'outline-label';
    name.textContent = kind === 'block' ? label.text : label;
    el.appendChild(name);
    var right = document.createElement('span');
    right.className = 'outline-note';
    right.textContent = note;
    el.appendChild(right);

    return el;
  }

  /** The page as the panel holds it: bands in order, each with the blocks standing in it. */
  function shape() {
    var bands = [];
    var byKey = {};
    document.querySelectorAll('[data-section-group]').forEach(function (group) {
      var key = group.getAttribute('data-section-group');
      // A radio group since D-107; :checked is the chosen one, where a <select> was itself.
      var chosen = group.querySelector('input[name$="[layout]"]:checked');
      var band = { key: key, layout: chosen ? chosen.value : 'one', blocks: [] };
      byKey[key] = band;
      bands.push(band);
    });
    api.groupNodes().forEach(function (group) {
      var band = byKey[group.getAttribute('data-section-key')];
      var block = group.querySelector('[data-block]');
      if (!band || !block) {
        return;
      }
      band.blocks.push({
        key: nameOf(group),
        column: Number((group.querySelector('[data-block-column]') || {}).value || 0),
        text: block.getAttribute('data-block-label') || '',
        icon: block.getAttribute('data-block-icon') || 'file-text',
      });
    });

    /* BANDS IN THE PAGE'S ORDER, WHICH THE CANVAS KNOWS AND THE PANEL DOES NOT.
       A band group is appended when the band is made and stays put; the page order is the
       order of the bands on the canvas. Reading it from the blocks instead dropped a band
       with nothing in it — which is exactly the band somebody has just added and is looking
       at (D-101). An empty band belongs here and nowhere else: the visitor's page never
       shows one, and Sections::prune() takes it on the next save if it is still empty. */
    var ordered = [];
    var seen = {};
    api.bands().forEach(function (band) {
      var key = band.getAttribute('data-bx-section');
      if (key && byKey[key] && !seen[key]) {
        seen[key] = true;
        ordered.push(byKey[key]);
      }
    });
    // Anything the canvas has not drawn yet — it loads in its own time, and the outline is
    // drawn before it answers — keeps the order the panel has.
    bands.forEach(function (band) {
      if (!seen[band.key]) {
        ordered.push(band);
      }
    });

    return ordered;
  }

  api.drawOutline = function () {
    var bands = shape();
    var blocks = 0;
    rows.textContent = '';
    bands.forEach(function (band, at) {
      var many = (COLUMNS[band.layout] || 1) > 1;
      var head = row('section', numbered('data-label-section', at + 1), LAYOUT_NOTE[band.layout] || '1');
      head.setAttribute('data-outline-section', band.key);
      rows.appendChild(head);
      for (var column = 0; column < (COLUMNS[band.layout] || 1); column += 1) {
        var inside = band.blocks.filter(function (block) { return block.column === column; });
        if (many) {
          rows.appendChild(row('column', numbered('data-label-column', column + 1), String(inside.length)));
        }
        inside.forEach(function (block) {
          blocks += 1;
          var line = row('block', block, block.key, many ? ' outline-deep' : '');
          line.setAttribute('data-outline-block', block.key);
          rows.appendChild(line);
        });
      }
    });
    if (count) {
      count.textContent = bands.length + ' / ' + blocks;
    }
    api.markOutline(api.selected());
  };

  /**
   * WHERE YOU ARE. The panel shows one group at a time and the canvas outlines one block;
   * this is the third place that has to agree with them, and it is told rather than asked,
   * so it cannot answer for a moment that has passed.
   */
  api.markOutline = function (index) {
    var group = index >= 0 ? form.querySelector('[data-block-group="' + index + '"]') : null;
    var key = group ? nameOf(group) : null;
    var band = group ? group.getAttribute('data-section-key') : api.outlineBand;
    rows.querySelectorAll('.outline-row').forEach(function (line) {
      var isBlock = line.getAttribute('data-outline-block');
      var isBand = line.getAttribute('data-outline-section');
      var on = (key !== null && isBlock === key) || (key === null && band && isBand === band);
      if (on) {
        line.setAttribute('aria-current', 'true');
      } else {
        line.removeAttribute('aria-current');
      }
    });
  };

  rows.addEventListener('click', function (event) {
    var line = event.target.closest && event.target.closest('[data-outline-block], [data-outline-section]');
    if (!line) {
      return;
    }
    var blockKey = line.getAttribute('data-outline-block');
    if (blockKey !== null) {
      /* A KEY, NOT A POSITION. The row was drawn from the panel and the panel may have been
         reordered since; a block goes on meaning the same block whatever has moved (D-094). */
      var group = null;
      api.groupNodes().forEach(function (candidate) {
        if (group === null && nameOf(candidate) === blockKey) {
          group = candidate;
        }
      });
      if (group === null) {
        return;
      }
      api.outlineBand = null;
      var index = Number(group.getAttribute('data-block-group'));
      // A block the person pressed, so the panel turns to Content (D-108) — the same rule
      // whether it was pressed on the page or here.
      api.chooseBlock(index);
      api.selectOnCanvas(index);

      return;
    }
    // A BAND, selected as a band (D-101): its own fields in the panel, its own edge on the
    // canvas, and no block pretending to stand in for it.
    api.selectBand(line.getAttribute('data-outline-section'));
  });

  var toggle = form.querySelector('[data-outline-toggle]');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var shown = rail.hidden;
      rail.hidden = !shown;
      toggle.setAttribute('aria-pressed', shown ? 'true' : 'false');
      toggle.title = toggle.getAttribute(shown ? 'data-hide' : 'data-show') || toggle.title;
    });
  }

  api.drawOutline();
})();
