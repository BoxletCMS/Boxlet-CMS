/*
 * The Appearance screen's form. Optional: without JavaScript, "Update preview" submits the
 * separate preview form into the preview frame and Publish posts normally. With it, the preview,
 * the colour readouts, the contrast gauge and the inline messages follow every change.
 * All derivation and validation stays on the server; this only asks for it.
 *
 * TWO SPEEDS, because one was wrong for both (PLAN.md D-058). A select, a checkbox or a
 * radio is a DECISION — the person has already chosen, and a quarter-second of nothing
 * reads as a screen that did not hear them. A colour input and anything dragged fire
 * continuously, so those wait until the hand stops. Choosing is immediate; dragging is
 * settled.
 */
(function () {
  'use strict';

  var form = document.querySelector('form[data-design-form]');
  var preview = document.querySelector('iframe[data-design-preview]');
  if (!form || !preview) {
    return;
  }
  var previewUrl = form.getAttribute('data-preview-url');
  // Which page the picture is of (D-111): a choice only a script can act on, so it is drawn
  // only once one runs, and the name it stands in for goes.
  var picker = form.querySelector('[data-preview-page]');
  var pageName = form.querySelector('[data-page-name]');
  if (picker && picker.querySelector('option')) {
    picker.hidden = false;
    if (pageName) {
      pageName.hidden = true;
    }
  }
  var stylesheetUrl = form.getAttribute('data-stylesheet-url');
  var timer = null;
  var dirty = false;

  /*
   * WHICH CHANGES NEED THE PAGE BACK, AND WHICH ONLY NEED ITS STYLESHEET.
   *
   * Every decision in the four design tabs comes out of TokenCompiler, so the preview's
   * MARKUP is byte for byte the same and only the stylesheet differs. Reloading it was a
   * white flash, a lost scroll position, and the fonts and pictures fetched again — every
   * 250ms while a slider was being dragged.
   *
   * THE LIST NAMES WHAT RELOADS, and anything not named takes the fast path, so this is the
   * one thing here that has to be kept honest as decisions are added. It was MEASURED
   * rather than reasoned: the preview was fetched with each of the 52 controls moved in
   * turn and the HTML compared. Nine changed it — the two bleeds, which menu the header
   * draws, four of the chrome's layout choices, and the footer's small print. `boxed` did
   * NOT, which is the one this would have got wrong by reasoning: an unboxed page is a
   * frame of zero rather than a different sheet, deliberately (Derived::page, D-067).
   *
   * The whole Chrome tab is named even so, although three of its choices measured as
   * token-only: they are choices about a thing built out of markup, and the next one added
   * is more likely to be markup than not. A needless reload is the old behaviour; a missed
   * one is a screen showing something the site will not do.
   *
   * The two own-colour switches joined the list with D-110: a colour of the owner's own is
   * a class on the bar now, so flipping the switch changes the markup. The colour itself
   * still does not — it is a token — and 03-design's guard is what said so.
   */
  var RELOADS = /^(look_|header_button_|footer_title|footer_text|footer_col|footer_small_print|footer_menu_|(header_menu|header_bleed|footer_bleed|header_colour_on|footer_colour_on|character|page)$)/;
  var mustReload = false;

  /**
   * The stylesheet the preview's tokens arrive in, inside the frame's own document.
   *
   * FOUND BY ITS ADDRESS, not by a marker attribute: the frame draws the site's real page
   * layout, and a hook put there for one admin screen would be carried by every page of
   * every site Boxlet runs. Same-origin, so the document is readable; null whenever it is
   * not yet, and the caller then reloads as before.
   */
  function tokensLink() {
    if (!stylesheetUrl) {
      return null;
    }
    var doc = null;
    try {
      doc = preview.contentDocument;
    } catch (e) {
      return null;
    }
    if (!doc) {
      return null;
    }
    var links = doc.querySelectorAll('link[rel="stylesheet"]');
    for (var i = 0; i < links.length; i++) {
      if ((links[i].getAttribute('href') || '').indexOf(stylesheetUrl) === 0) {
        return links[i];
      }
    }
    return null;
  }

  /** The whole page again, back where the owner had scrolled it to. */
  function reload(params) {
    var at = 0;
    try {
      at = preview.contentWindow ? preview.contentWindow.scrollY : 0;
    } catch (e) {
      at = 0;
    }
    preview.addEventListener('load', function once() {
      preview.removeEventListener('load', once);
      try {
        preview.contentWindow.scrollTo(0, at);
      } catch (e) {
        // A frame that has navigated elsewhere: the position is not ours to restore.
      }
    });
    preview.src = previewUrl + '?' + params;
  }

  /*
   * What the frame is showing, as a query. Kept HERE rather than read back off the frame's
   * src, because writing that attribute is what navigates: a fast path that recorded where
   * it was by setting src would reload the very page it just avoided reloading.
   */
  var showing = '';

  function draw(params) {
    var link = mustReload ? null : tokensLink();
    showing = params;
    if (!link) {
      mustReload = false;
      reload(params);
      return;
    }
    // The old stylesheet stays in force until the new one has loaded, so there is no
    // moment of unstyled page.
    link.setAttribute('href', stylesheetUrl + '?' + params);
  }

  function query() {
    var params = new URLSearchParams();
    new FormData(form).forEach(function (value, key) {
      if (key !== '_csrf' && key !== 'action') {
        params.append(key, value);
      }
    });
    return params.toString();
  }

  /**
   * What the screen is in one word, for the toolbar over the picture (D-060). An event
   * rather than a reach into another file's elements: this one knows, because it is the one
   * asking the server.
   */
  function announce(name) {
    document.dispatchEvent(new CustomEvent('appearance:state', { detail: { state: name } }));
  }

  function refresh() {
    window.clearTimeout(timer);
    var params = query();
    draw(params);
    fetch(form.getAttribute('data-check-url') + '?' + params, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    })
      .then(function (response) {
        return response.ok ? response.json() : null;
      })
      .then(function (result) {
        if (result) {
          // Handed on rather than written here: appearance-readouts.js draws it.
          document.dispatchEvent(new CustomEvent('appearance:answer', { detail: result }));
          // A palette that would be refused is not "not published yet" — it is something to
          // fix, and the screen says which of the two it is.
          announce(Object.keys(result.errors || {}).length > 0 ? 'problem' : 'unpublished');
        }
      })
      .catch(function () {
        // The preview frame still updates; the server re-checks on Save.
      });
  }

  /*
   * COMPARE, HELD (D-060). The published design is this same preview with NO query at all:
   * with nothing submitted, the server draws what is stored.
   *
   * It is answered here rather than in appearance-stage.js, which raises it, because this
   * file is the one that knows what the frame is showing — and because a comparison you
   * have to keep holding must come back instantly, which a reload never does. The stage
   * owns the frame's box; this owns what is inside it.
   */
  var comparing = false;
  document.addEventListener('appearance:compare', function (event) {
    var held = !!(event.detail && event.detail.held);
    if (held === comparing) {
      return;
    }
    comparing = held;
    var link = tokensLink();
    if (link) {
      link.setAttribute('href', held ? stylesheetUrl : stylesheetUrl + '?' + showing);
    } else {
      preview.setAttribute('src', held ? previewUrl : previewUrl + '?' + showing);
    }
  });

  /** A control the person has finished with: a choice, rather than a value being dragged. */
  function isDiscrete(target) {
    if (!target || !target.tagName) {
      return false;
    }
    var tag = target.tagName.toLowerCase();
    return tag === 'select' || (tag === 'input' && /^(checkbox|radio)$/.test(target.type));
  }

  function changed(event) {
    dirty = true;
    announce('unpublished');
    // Remembered rather than decided at refresh time: a quarter-second of dragging can
    // carry several controls, and one of them wanting the page back settles it for all.
    if (RELOADS.test((event.target && event.target.name) || '')) {
      mustReload = true;
    }
    if (event.type === 'change' && isDiscrete(event.target)) {
      refresh();
      return;
    }
    window.clearTimeout(timer);
    timer = window.setTimeout(refresh, 250);
  }

  /*
   * PICKING A COLOUR IS TAKING IT OVER (D-065).
   *
   * Those six inputs show what the palette works out until the owner takes the role over,
   * and this file rewrites the untaken ones whenever the palette moves. That put the two in
   * a race the owner loses: choose a colour, and if a refresh lands before the switch is
   * flipped, the choice is written back over. Measured — the browser check waited fifteen
   * seconds for a colour that had been quietly replaced.
   *
   * So the act of choosing sets the switch. Giving the role back is still one press, which
   * is the direction that needs a deliberate gesture.
   */
  form.addEventListener('input', function (event) {
    var field = event.target.getAttribute && event.target.getAttribute('data-by-hand');
    var mine = field && form.querySelector('[data-by-hand-switch="' + field + '"]');
    if (mine && !mine.checked) {
      mine.checked = true;
    }
  });

  form.addEventListener('input', changed);
  form.addEventListener('change', changed);
  // Still the form's own event, although the buttons now sit beside the preview: a button
  // with form="design-form" submits that form, wherever it stands.
  form.addEventListener('submit', function () {
    dirty = false;
  });
  window.addEventListener('beforeunload', function (event) {
    if (dirty) {
      event.preventDefault();
      event.returnValue = '';
    }
  });

  // With JavaScript the preview follows every change, so the button has nothing left to do
  // and goes — a control that repeats what already happened is a control that makes the
  // person doubt whether it did.
  var previewButton = document.querySelector('[data-preview-button]');
  if (previewButton) {
    previewButton.remove();
  }

  // What the frame is showing before anything has been changed: the form as the server drew
  // it. Compare has to be able to come back to it without a refresh having happened first.
  showing = query();
})();
