/*
 * The picture's own toolbar on the Appearance screen (PLAN.md D-060): three widths, a zoom,
 * Compare, and what state the screen is in.
 *
 * THE ZOOM SCALES THE STAGE, NEVER THE FRAME'S WIDTH. A page judged at 1280 has to lay
 * itself out at 1280. Shrinking the frame would hand the page a narrower window, and it
 * would answer with its phone layout — a different question from the one being asked.
 *
 * THE TOOLS ARE HIDDEN UNTIL THIS FILE RUNS. Without JavaScript there is no way to scale a
 * frame, so a row of controls that did nothing would be worse than none: the frame is then
 * the column's width at full size, which is what it always was.
 *
 * Styles are set through the CSSOM, one property at a time. The admin's policy refuses a
 * style ATTRIBUTE — setAttribute('style', …) is blocked, measured in this very screen — and
 * allows this. No rule is invented here that the stylesheet does not already own.
 */
(function () {
  'use strict';

  var stage = document.querySelector('[data-stage]');
  var frame = document.querySelector('iframe[data-design-preview]');
  var tools = document.querySelector('[data-preview-tools]');
  var state = document.querySelector('[data-state]');
  var revert = document.querySelector('[data-revert]');
  var size = document.querySelector('[data-stage-size]');
  var tight = document.querySelector('[data-stage-tight]');
  if (!stage || !frame || !tools) {
    return;
  }
  tools.hidden = false;

  /** Under a half the text stops being text, so the zoom does not go there. */
  var SMALLEST = 0.5;
  /** Widest first: the screen opens on the widest one this column can actually carry. */
  var WIDTHS = [1280, 834, 390];
  var width = WIDTHS[0];
  var zoom = 'fit';
  /** Whether the owner has picked a width. Until they do, the screen picks one for them. */
  var picked = false;

  function scale() {
    if (zoom !== 'fit') {
      return Math.max(SMALLEST, parseFloat(zoom));
    }
    // Fit means the whole width is on screen, and never magnified past its own size.
    var room = stage.clientWidth;
    return Math.max(SMALLEST, Math.min(1, room / width));
  }

  function draw() {
    var factor = scale();
    var tall = stage.clientHeight;
    frame.style.width = width + 'px';
    // The frame is laid out at full size and then scaled, so its height is divided by the
    // factor to land back on the stage exactly: at half zoom you see twice as much page,
    // which is what zooming out means.
    frame.style.height = (tall > 0 ? tall / factor : 0) + 'px';
    frame.style.transform = 'scale(' + factor + ')';
    // Centred while it fits, and hard against the left edge when it does not — a frame
    // centred by a margin it cannot give back is a frame whose left side cannot be scrolled
    // to.
    var spare = stage.clientWidth - width * factor;
    frame.style.marginInlineStart = (spare > 0 ? spare / 2 : 0) + 'px';
    /*
     * AND THE BOX IS PULLED BACK TO WHAT IS DRAWN (PLAN.md D-078).
     *
     * The frame is laid out at FULL size and then scaled, so its layout box stays the width
     * it was given while the picture is smaller: measured at 1040px of stage reporting
     * 1280px of scrollable width, at every width the screen has. The stage grew a permanent
     * horizontal scrollbar with a band of nothing to the right of the page.
     *
     * THE ASYMMETRY WAS THERE TO READ. The height is already divided by the factor so it
     * lands back on the stage; the width never was. Only the OUTER box was ever wrong: the
     * transform has to stay, because the frame's inner viewport is the width the owner
     * chose, which is what the three viewport buttons are for.
     *
     * NEGATIVE END MARGINS ON BOTH AXES, so the margin box is exactly what is drawn. The
     * vertical one is not decoration: a horizontal scrollbar takes height off the stage,
     * which changes the frame's height, which fires the ResizeObserver again. Taking the
     * scrollbar away takes the loop with it.
     */
    frame.style.marginInlineEnd = (width * factor - width) + 'px';
    frame.style.marginBlockEnd = (tall > 0 ? tall - tall / factor : 0) + 'px';
    // What the picture IS: the page's own width and height, and how much of full size is on
    // screen. The strip had the slot for it from the first day and nothing ever filled it,
    // so the zoom was a number in a dropdown and the height was a guess.
    if (size) {
      size.textContent = width + '×' + Math.round(tall > 0 ? tall / factor : 0)
        + ' · ' + Math.round(factor * 100) + '%';
    }
    // The floor has been hit: the frame is wider than the stage and scrolls sideways. SAID,
    // never fixed by changing the width — the owner chose that width, or saw the screen open
    // on it, and a picture that becomes a phone by itself reads as a design that changed.
    if (tight) {
      tight.hidden = spare >= 0;
    }
  }

  var chooser = tools.querySelector('[data-zoom]');

  /** Which button is down, whoever chose it. */
  function show(chosen) {
    width = chosen;
    tools.querySelectorAll('[data-viewport]').forEach(function (button) {
      var mine = parseInt(button.getAttribute('data-viewport'), 10) === chosen;
      button.setAttribute('aria-pressed', mine ? 'true' : 'false');
    });
  }

  tools.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('[data-viewport]') : null;
    if (!button) {
      return;
    }
    picked = true;
    show(parseInt(button.getAttribute('data-viewport'), 10) || WIDTHS[0]);
    /*
     * A NEW WIDTH COMES WITH FIT (handoff §2.3). Zoom belongs to the width it was chosen
     * for: 100% of a desktop page in this column is a corner of it, and carrying that over
     * to the phone shows a phone page at twice its size. Fit is the only answer that means
     * the same thing at every width.
     */
    zoom = 'fit';
    if (chooser) {
      chooser.value = 'fit';
    }
    draw();
  });

  if (chooser) {
    chooser.addEventListener('change', function () {
      zoom = chooser.value;
      draw();
    });
  }

  /*
   * THE SCREEN OPENS ON A WIDTH THIS COLUMN CAN CARRY (handoff §2.3).
   *
   * Desktop at 1280 needs 640px of stage to stay above the floor; below that the picture
   * would be a page nobody can read, or one silently clipped — which is what the prototype
   * did twice by trusting a default width instead of measuring. So the width is chosen from
   * the room there actually is, and only until the owner picks one for themselves.
   *
   * ONCE, AT THE FIRST REAL MEASUREMENT. It ran from every ResizeObserver tick, so dragging
   * the window — or opening devtools — walked the picture from desktop to tablet to phone
   * and back under the owner's hand, and nothing on screen said why. From the second
   * measurement on the width is whatever is showing, and draw() says when there is no longer
   * room for it.
   */
  var chosenOnce = false;

  function fitsTheColumn() {
    var room = stage.clientWidth;
    if (room <= 0 || picked || chosenOnce) {
      return;
    }
    chosenOnce = true;
    for (var i = 0; i < WIDTHS.length; i++) {
      if (room / WIDTHS[i] >= SMALLEST) {
        show(WIDTHS[i]);
        return;
      }
    }
    show(WIDTHS[WIDTHS.length - 1]);
  }

  /*
   * COMPARE IS HELD, NOT TOGGLED. A comparison you have to keep holding is one you cannot
   * walk away from and mistake for the site.
   *
   * SAID, NOT DONE HERE. This file owns the frame's BOX — its width, its height, the scale
   * it is drawn at — and appearance.js owns what is inside it: it is the one that knows
   * what the frame is showing, and the one that can put the published design up by swapping
   * a stylesheet instead of reloading the document. Reaching in to set src from here meant
   * a full reload on every press of a button meant to be held down.
   */
  var compare = tools.querySelector('[data-compare]');
  if (compare) {
    var mine = false;
    var say = function (held) {
      mine = held;
      compare.setAttribute('aria-pressed', held ? 'true' : 'false');
      document.dispatchEvent(new CustomEvent('appearance:compare', { detail: { held: held } }));
    };
    var hold = function () {
      if (!mine) {
        say(true);
      }
    };
    var release = function () {
      if (mine) {
        say(false);
      }
    };
    compare.addEventListener('pointerdown', hold);
    ['pointerup', 'pointerleave', 'pointercancel', 'blur'].forEach(function (name) {
      compare.addEventListener(name, release);
    });
    // The keyboard holds it too: Space and Enter repeat while down and stop on release.
    compare.addEventListener('keydown', function (event) {
      if (event.key === ' ' || event.key === 'Enter') {
        event.preventDefault();
        hold();
      }
    });
    compare.addEventListener('keyup', release);
  }

  /*
   * What the screen is: the site as published, work not published yet, or something that
   * cannot be published until it is fixed. appearance.js knows which, because it is the one
   * asking the server; it says so through an event rather than reaching in here.
   */
  if (state) {
    document.addEventListener('appearance:state', function (event) {
      var name = event.detail && event.detail.state;
      var words = state.getAttribute('data-' + name);
      if (words) {
        state.textContent = words;
        state.classList.toggle('state-unpublished', name !== 'published');
        state.classList.toggle('state-problem', name === 'problem');
      }
      if (revert) {
        revert.hidden = name === 'published';
      }
    });
  }

  /*
   * MEASURED WHEN THE SIZE IS KNOWN, not once at load. A stage still being laid out reports
   * zero, and a screen that picked its width from that would open on the phone every time.
   * The observer fires when there is something to measure and again whenever the column
   * changes — the admin's rail folding, a window resized, a panel opening.
   */
  if (typeof ResizeObserver === 'function') {
    new ResizeObserver(function () {
      fitsTheColumn();
      draw();
    }).observe(stage);
  } else {
    window.addEventListener('resize', function () {
      fitsTheColumn();
      draw();
    });
  }

  fitsTheColumn();
  draw();
})();
