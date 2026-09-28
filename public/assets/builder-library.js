/*
 * A LIBRARY CARD IS AS TALL AS THE BLOCK IT SHOWS (PLAN.md D-083).
 *
 * The card was a fixed 16/9 window onto a block of whatever height, so what most of it
 * showed was the empty document under the block. Measured on the demo site, in a frame of
 * 397×223 with an iframe viewport of 1588×893: Text drew 201px of block and 692px of
 * nothing, Form 222 and 671, Columns 342, Hero 459, Image and text 501. Three quarters of
 * the Text card was a grey plane, every card was the same size whatever it held, and the
 * one thing a picture of a block should say — how much room it takes — was the one thing
 * it could not say.
 *
 * THE BLOCK IS MEASURED, NOT THE DOCUMENT. documentElement.scrollHeight was the obvious
 * reading and it is useless here: the body fills the viewport, so it answers 893 for every
 * block on the list. What has a height is the section the block rendered into.
 *
 * REMEMBERED BY THE PREVIEW'S FILE NAME, which is hashed against the block and the compiled
 * stylesheet (BlockPreview::file). A card therefore opens at the right height on the second
 * visit instead of settling into it, and the remembered figure cannot go stale: when the
 * block or the design changes, the file name changes with it and the old entry is simply
 * never asked for again.
 *
 * Without this script every card keeps the height the stylesheet gives it, which is the
 * min-block-size: short, honest and the same for all of them.
 */
(function () {
  'use strict';

  var STORE = 'boxlet.library.heights';
  var cards = document.querySelectorAll('.library-card');
  if (cards.length === 0) {
    return;
  }

  /* Browser storage is a convenience here and never the truth: a private window, cleared
     site data or a blocked origin makes it throw, and the card simply measures again. */
  function remembered() {
    try {
      var raw = window.localStorage.getItem(STORE);

      return raw ? JSON.parse(raw) : {};
    } catch (error) {
      return {};
    }
  }

  function remember(heights) {
    try {
      window.localStorage.setItem(STORE, JSON.stringify(heights));
    } catch (error) {
      // Nothing to do and nothing to say: the next visit measures again.
    }
  }

  var heights = remembered();

  function key(frame) {
    var iframe = frame.querySelector('iframe');
    var src = iframe ? iframe.getAttribute('src') || '' : '';

    return src.slice(src.lastIndexOf('/') + 1);
  }

  function draw(frame, height) {
    frame.style.setProperty('--library-block', height + 'px');
    /* The fade at the foot means "there is more below", so it is drawn only where there
       is: the stylesheet caps a card, and a block under the cap is shown whole. */
    var max = parseFloat(window.getComputedStyle(frame).maxBlockSize);
    var scale = parseFloat(window.getComputedStyle(frame).getPropertyValue('--library-scale')) || 1;
    frame.toggleAttribute('data-cut', !isNaN(max) && height * scale > max + 1);
  }

  function measure(frame) {
    var iframe = frame.querySelector('iframe');
    var doc = iframe ? iframe.contentDocument : null;
    var block = doc ? doc.querySelector('.block') : null;
    if (!block) {
      return;
    }
    var height = Math.round(block.getBoundingClientRect().height);
    if (height <= 0) {
      return;
    }
    draw(frame, height);
    heights[key(frame)] = height;
    remember(heights);
  }

  Array.prototype.forEach.call(cards, function (card) {
    var frame = card.querySelector('.library-frame');
    var iframe = frame && frame.querySelector('iframe');
    if (!frame || !iframe) {
      return;
    }

    var known = heights[key(frame)];
    if (typeof known === 'number' && known > 0) {
      draw(frame, known);
    }

    var settle = function () {
      measure(frame);
      /* Again once the webfonts are in: a block set in a font that has not arrived is a
         block of the wrong height, and the first measurement would be remembered as such. */
      var doc = iframe.contentDocument;
      if (doc && doc.fonts && doc.fonts.ready) {
        doc.fonts.ready.then(function () {
          measure(frame);
        });
      }
    };

    if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete') {
      settle();
    }
    iframe.addEventListener('load', settle);
  });
})();
