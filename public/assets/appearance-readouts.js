/*
 * What the Appearance screen SHOWS of the server's answer: the inline messages, the derived
 * palette, the contrast gauge, and what every control comes to in words.
 *
 * Split from appearance.js when that passed 300 lines, along the seam it already had: that
 * file ASKS the server and decides what the picture needs; this one only writes down what
 * came back. Nothing here decides anything, which is why none of it needs to know about the
 * preview, the debounce or the reload list.
 *
 * It listens rather than being called, because the two files cannot import each other — the
 * admin has no module loader and by D-013 never will. The same way appearance-stage.js is
 * told what state the screen is in.
 *
 * Optional, like every script here: without it the server's answer simply is not drawn, and
 * Publish re-checks everything anyway.
 */
(function () {
  'use strict';

  var form = document.querySelector('form[data-design-form]');
  if (!form) {
    return;
  }

  // The hex next to each colour input, so the value is readable and not only visible.
  function showColourValues() {
    form.querySelectorAll('[data-colour-for]').forEach(function (output) {
      var input = document.getElementById(output.getAttribute('data-colour-for'));
      if (input) {
        output.textContent = input.value;
      }
    });
  }

  function showErrors(errors) {
    form.querySelectorAll('[data-error-for]').forEach(function (element) {
      var message = errors[element.getAttribute('data-error-for')];
      element.textContent = message || '';
      element.hidden = !message;
    });
  }

  function showColors(colors) {
    Object.keys(colors).forEach(function (name) {
      form.querySelectorAll('[data-swatch="' + name + '"]').forEach(function (rect) {
        rect.setAttribute('fill', colors[name]);
      });
      form.querySelectorAll('[data-swatch-value="' + name + '"]').forEach(function (code) {
        code.textContent = colors[name];
      });
    });

    /*
     * A ROLE LEFT TO THE PALETTE SHOWS WHAT THE PALETTE NOW SAYS (D-063).
     *
     * Each of those inputs starts at the colour the server worked out, which stops being
     * true the moment anything it depends on moves: set a dark page by hand and the five
     * other swatches still showed the near-white palette they were rendered with, while the
     * preview beside them was dark. The same stale-dependent bug the palette itself was
     * rearranged to prevent, this time on the screen.
     *
     * A role the owner has taken over is never touched: that colour is theirs.
     */
    form.querySelectorAll('[data-by-hand]').forEach(function (input) {
      var field = input.getAttribute('data-by-hand');
      var role = field.replace(/^color_/, '');
      var mine = form.querySelector('[data-by-hand-switch="' + field + '"]');
      if (mine && !mine.checked && colors[role]) {
        input.value = colors[role];
      }
    });
    showColourValues();
  }

  // The gauge, measured on the server: this only writes the numbers it is handed. A row
  // that fails keeps its place in the list rather than jumping to the top — a list that
  // reorders under the reader is a list nobody can follow.
  function showPairs(pairs) {
    pairs.forEach(function (pair) {
      var row = document.querySelector('[data-pair="' + pair.pair + '"]');
      if (!row) {
        return;
      }
      row.classList.toggle('gauge-fails', !pair.passes);
      var ratio = row.querySelector('[data-pair-ratio]');
      var verdict = row.querySelector('[data-pair-verdict]');
      var background = row.querySelector('[data-pair-background]');
      var foreground = row.querySelector('[data-pair-foreground]');
      if (ratio) ratio.textContent = pair.ratio.toFixed(2);
      // The two words come from the markup, because they are translated and this file is not.
      if (verdict) verdict.textContent = verdict.getAttribute(pair.passes ? 'data-pass' : 'data-fail') || verdict.textContent;
      if (background) background.setAttribute('fill', pair.background);
      if (foreground) foreground.setAttribute('fill', pair.foreground);
    });
  }

  /*
   * WHAT EVERY CONTROL COMES TO, as the server says it (D-066). Rendered once into the
   * markup and written again on every answer, because a readout rendered once goes stale
   * the moment anything moves — and a readout that lies is worse than none, since it is the
   * thing being read.
   */
  function showReadouts(readouts) {
    Object.keys(readouts || {}).forEach(function (name) {
      document.querySelectorAll('[data-readout="' + name.replace(/"/g, '') + '"]').forEach(function (slot) {
        // A group still following the character says so; that is a state, not a value.
        if (!slot.classList.contains('readout-following')) {
          slot.textContent = readouts[name];
          // The whole phrase, for a readout cut short at the end of its row (D-110).
          slot.title = readouts[name];
        }
      });
    });
  }

  /*
   * THE SPECIMEN IS THE SIZES, DRAWN (D-075).
   *
   * It was fixed at 1.6rem, so dragging the scale moved the number beside each line and the
   * lines themselves did not move at all — which took away the one thing a specimen is for:
   * the RELATION between the sizes.
   *
   * THE ARITHMETIC IS NOT REDONE HERE. The pixels in each readout are the server's, worked
   * out by the one formula that owns them (Derived::sizeOf, D-066); this only shrinks all
   * four by the SAME factor so the biggest fits the column. A second copy of that formula in
   * JavaScript is two answers waiting to differ, which is the bug D-066 exists to prevent.
   *
   * Written through the CSSOM, one property at a time: the admin's policy refuses a style
   * ATTRIBUTE and allows this (appearance-stage.js says the same, and measured it).
   */
  /*
   * THE BIGGEST LINE, and the only number here that is a judgement rather than arithmetic.
   * 34 was the handoff's suggestion and it is too small: at the default character the hero
   * is 72px and the body 17px, so everything below the subhead shrank to a smudge. 40 keeps
   * the body legible at the sizes people actually choose, and the hero gives way at the end
   * rather than growing the panel — this is a picture of a size, not something to read.
   */
  var BIGGEST_LINE = 40;

  function drawSpecimen() {
    var lines = [].slice.call(document.querySelectorAll('[data-specimen]'));
    var sizes = lines.map(function (line) {
      var said = line.querySelector('[data-specimen-size]');
      return said ? parseFloat(said.textContent) || 0 : 0;
    });
    var biggest = Math.max.apply(null, sizes.concat([0]));
    if (biggest <= 0) {
      return;
    }
    var factor = Math.min(1, BIGGEST_LINE / biggest);
    lines.forEach(function (line, at) {
      if (sizes[at] > 0) {
        /*
         * NO FLOOR WORTH THE NAME. A floor of 9px made the body and the small print the
         * same size at the default character — 17px and 13px both landed on it — which is
         * the one thing this must never do: the whole point is the RELATION, and two steps
         * drawn identically say the design has none. 5px only stops a line vanishing.
         */
        line.style.fontSize = Math.max(5, Math.round(sizes[at] * factor)) + 'px';
      }
    });
  }

  /** And in the face being chosen: a pairing is two faces, and the specimen shows both. */
  function showTypeface() {
    var specimen = document.querySelector('.specimen');
    var chosen = form.querySelector('input[name="typography"]:checked');
    if (specimen && chosen) {
      specimen.setAttribute('data-typeface', chosen.value);
    }
  }

  document.addEventListener('appearance:answer', function (event) {
    var answer = event.detail || {};
    showErrors(answer.errors || {});
    showColors(answer.colors || {});
    showPairs(answer.pairs || []);
    showReadouts(answer.readouts);
    drawSpecimen();
  });

  // The hex beside a colour keeps up with the HAND, not with the server: a value that only
  // appears a quarter of a second after the picker closes reads as a screen that did not
  // hear the choice. The typeface is the same: pressing a card changes the face the specimen
  // is set in at once, and the SIZES then follow when the server answers.
  form.addEventListener('input', showColourValues);
  form.addEventListener('change', showTypeface);

  // What the server already said, drawn: the readouts are in the markup before any change.
  drawSpecimen();
})();
