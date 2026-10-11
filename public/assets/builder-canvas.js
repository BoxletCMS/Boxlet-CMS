/*
 * THE CANVAS (PLAN.md D-175, README 4.3): the page in a same-origin iframe, kept in step with
 * the document. A band that changed is drawn again by the server (PageBuilderApi::render) and
 * put in place of the old one; a band added is drawn and put where the document has it; the
 * order of the bands is the document's. Typing will not redraw (phase 5); everything else does,
 * one band at a time.
 *
 * Nothing is drawn here: the markup is the visitor's, from the server, and what the editor
 * adds over it is builder-overlay.js's.
 */
(function () {
  'use strict';

  var pb = window.pb;
  var frame = document.querySelector('[data-pb-canvas]');
  var wrap = document.querySelector('[data-pb-frame]');
  if (!pb || !frame) {
    return;
  }
  var tickets = {};
  var WIDTHS = { desktop: 0, tablet: 768, phone: 390 };
  var DESKTOP = 1200;
  pb.device = 'desktop';

  pb.canvas = {
    doc: function () { return frame.contentDocument; },
    main: function () { return frame.contentDocument ? frame.contentDocument.querySelector('[data-bx-blocks]') : null; },
    sectionEl: function (key) {
      var main = pb.canvas.main();
      return main ? main.querySelector(':scope > [data-bx-section="' + CSS.escape(key) + '"]') : null;
    },
    blockEl: function (key) {
      var main = pb.canvas.main();
      return main ? main.querySelector('[data-bx-key="' + CSS.escape(key) + '"]') : null;
    },
    /** The width the page is drawn at, and the scale it is shown at (fit(), below). */
    drawnWidth: 0,
    scale: 1,
  };

  /** Where a band's element goes: before the next band of the document that is drawn. */
  function place(key, element) {
    var main = pb.canvas.main();
    var keys = pb.doc.sections.map(function (s) { return s.key; });
    var after = keys.slice(keys.indexOf(key) + 1);
    for (var i = 0; i < after.length; i++) {
      var next = pb.canvas.sectionEl(after[i]);
      if (next) {
        main.insertBefore(element, next);
        return;
      }
    }
    main.appendChild(element);
  }

  /** One band drawn again from the document, or removed when the document has it no more. */
  function draw(key) {
    var section = pb.section(key);
    if (!pb.canvas.main()) {
      return Promise.resolve();
    }
    if (!section) {
      var gone = pb.canvas.sectionEl(key);
      if (gone) {
        gone.remove();
      }
      return Promise.resolve();
    }
    var ticket = (tickets[key] || 0) + 1;
    tickets[key] = ticket;
    return pb.api(pb.data.endpoints.render, { section: section, blocks: pb.blocksIn(key) }).then(function (answer) {
      // A later drawing of the same band has been asked for since: this one is out of date.
      if (tickets[key] !== ticket || answer.status !== 200) {
        return;
      }
      var holder = frame.contentDocument.createElement('div');
      holder.innerHTML = answer.json.html;
      var element = holder.querySelector('[data-bx-section]');
      var old = pb.canvas.sectionEl(key);
      if (!element || !pb.section(key)) {
        if (old) {
          old.remove();
        }
        return;
      }
      if (old) {
        old.replaceWith(element);
      } else {
        place(key, element);
      }
      pb.emit('drawn', { key: key });
    });
  }
  pb.canvas.draw = draw;

  /** The bands in the document's order, each one there, none that is not. */
  function sync() {
    var main = pb.canvas.main();
    if (!main) {
      return;
    }
    var keys = pb.doc.sections.map(function (s) { return s.key; });
    Array.prototype.forEach.call(main.querySelectorAll(':scope > [data-bx-section]'), function (element) {
      if (keys.indexOf(element.getAttribute('data-bx-section')) < 0) {
        element.remove();
      }
    });
    keys.forEach(function (key) {
      var element = pb.canvas.sectionEl(key);
      if (element) {
        main.appendChild(element);
      } else {
        draw(key);
      }
    });
    pb.emit('drawn', { key: null });
  }

  pb.on('change', function (detail) {
    if (detail.structure) {
      sync();
    }
    detail.sections.forEach(function (key) { draw(key); });
  });

  // After undo, redo, Discard or a restore: every band whose part of the document differs.
  pb.on('replace', function (detail) {
    var before = detail.before;
    var was = function (key) {
      var s = before.sections.filter(function (x) { return x.key === key; })[0];
      return JSON.stringify([s, before.blocks.filter(function (b) { return b.section === key; })]);
    };
    pb.doc.sections.forEach(function (s) {
      if (was(s.key) !== JSON.stringify([s, pb.doc.blocks.filter(function (b) { return b.section === s.key; })])) {
        draw(s.key);
      }
    });
    sync();
  });

  // ---- the device, and the scale it is shown at ---------------------------------------------------
  // THE PAGE IS DRAWN AT THE DEVICE'S WIDTH AND SHOWN SMALLER, as the mockup does ("1200 px ·
  // 63 %"): a desktop page squeezed into the column between the rail and the inspector would
  // be a tablet page, and every arrangement judged on it a wrong one. Desktop is never narrower
  // than 1200 and takes the whole column when that is wider; nothing is ever shown larger.
  function fit() {
    var room = wrap.clientWidth;
    var tall = wrap.clientHeight;
    if (!room || !tall) {
      return;
    }
    var width = WIDTHS[pb.device] || Math.max(DESKTOP, room);
    var scale = Math.min(1, room / width);
    pb.canvas.scale = scale;
    pb.canvas.drawnWidth = width;
    frame.style.width = width + 'px';
    frame.style.height = Math.floor(tall / scale) + 'px';
    frame.style.transform = scale < 1 ? 'scale(' + scale + ')' : '';
    frame.style.left = Math.max(0, (room - width * scale) / 2) + 'px';
    zoomControls();
    pb.emit('scale', { width: width, scale: scale });
  }
  /** The editor's own controls in the canvas keep their size on screen (canvas.css, --bx-z). */
  function zoomControls() {
    var root = frame.contentDocument ? frame.contentDocument.documentElement : null;
    if (root) {
      root.style.setProperty('--bx-z', String(1 / (pb.canvas.scale || 1)));
    }
  }
  if (window.ResizeObserver) {
    new ResizeObserver(fit).observe(wrap);
  }
  window.addEventListener('resize', fit);

  function device(name) {
    pb.device = name;
    Array.prototype.forEach.call(document.querySelectorAll('[data-device]'), function (button) {
      button.setAttribute('aria-pressed', button.getAttribute('data-device') === name ? 'true' : 'false');
    });
    wrap.setAttribute('data-device', name);
    fit();
    setTimeout(function () { pb.emit('device', name); }, 50);
  }
  document.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('[data-device]') : null;
    if (button) {
      device(button.getAttribute('data-device'));
    }
  });
  fit();

  // THE CANVAS IS ANNOUNCED ONCE EVERY SCRIPT IS LISTENING (D-178). This file runs before the
  // overlay and the rest; an announcement made from here — the frame's load, or a timer when
  // it had loaded already — could land between two of them, and the overlay never heard it:
  // a new page drew no "Add section at the end", in a whole run of the suite. Every deferred
  // script has run by DOMContentLoaded, so nothing is said before that.
  var listening = false;
  function announce() {
    zoomControls();
    if (listening && frame.contentDocument && frame.contentDocument.readyState === 'complete' && pb.canvas.main()) {
      pb.emit('canvas');
    }
  }
  frame.addEventListener('load', announce);
  document.addEventListener('DOMContentLoaded', function () {
    listening = true;
    announce();
  });
})();
