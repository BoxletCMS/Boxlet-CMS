/*
 * WHERE A LINK LEADS, ON THE PAGE (PLAN.md D-178, README 4.4): pressing a link's words edits
 * them in place (builder-inline.js) and opens this under them — a page of the site, or another
 * address — and the rich text toolbar's link button opens the same. A page is stored as its
 * reference (`page:3`, D-034), so the link follows the page wherever it moves.
 *
 * In the admin's own document, over the canvas, and in the admin's own fields and buttons
 * (D-179): a form the inspector would draw, not a control of the page's. It is placed by the
 * words where the scaled canvas shows them, never over them, and placed again when the page
 * scrolls or is drawn again. While it is open the block's toolbar is put away (D-183).
 */
(function () {
  'use strict';

  var pb = window.pb;
  if (!pb || !pb.inline || !pb.overlay) {
    return;
  }
  var pages = (pb.data.inline || {}).pages || [];
  var open = null;

  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) { node.className = cls; }
    if (text) { node.textContent = text; }
    return node;
  }

  /** The block's toolbar put away while the popover is open, back when it closes. */
  function linking(on) {
    var layer = pb.overlay.layer();
    if (layer) { layer.classList.toggle('is-linking', on); }
  }

  function close() {
    if (open) {
      open.panel.remove();
      open = null;
      linking(false);
    }
  }

  /**
   * NEVER OVER THE WORDS IT EDITS (D-183), where the scaled canvas shows them: under them, at
   * their start; above them; beside them, right then left, level with them; and only when the
   * window has room for none of these, under them all the same.
   */
  function place() {
    if (!open) {
      return;
    }
    if (!open.anchor.isConnected) {
      close();
      return;
    }
    linking(true);
    var frame = document.querySelector('[data-pb-canvas]').getBoundingClientRect();
    var scale = pb.canvas.scale || 1;
    var r = open.anchor.getBoundingClientRect();
    var a = { top: frame.top + r.top * scale, bottom: frame.top + r.bottom * scale, left: frame.left + r.left * scale, right: frame.left + r.right * scale };
    var panel = open.panel;
    var h = panel.offsetHeight;
    var w = panel.offsetWidth;
    var gap = 8;
    var high = window.innerHeight - gap;
    var wide = window.innerWidth - gap;
    function along(left) { return Math.max(gap, Math.min(left, wide - w)); }
    function level(top) { return Math.max(gap, Math.min(top, high - h)); }
    var places = [
      { top: a.bottom + gap, left: along(a.left), fits: a.bottom + gap + h <= high },
      { top: a.top - gap - h, left: along(a.left), fits: a.top - gap - h >= gap },
      { top: level(a.top), left: a.right + gap, fits: a.right + gap + w <= wide },
      { top: level(a.top), left: a.left - gap - w, fits: a.left - gap - w >= gap },
    ];
    var at = places.filter(function (p) { return p.fits; })[0] || { top: level(places[0].top), left: places[0].left };
    panel.style.top = at.top + 'px';
    panel.style.left = at.left + 'px';
  }

  /**
   * The popover under `anchor`, showing `url` and the link's words, `text` (D-182: "Link
   * text", for a page and for another address alike). `onChange(url, text)` is told every
   * change; `onDone(url, text)` when it closes. `onRemove`, when given, offers taking the link
   * away. Choosing a page fills the words with its title only when there are none (D-183):
   * the words are never written over. `selected`, for rich text, says the words are text
   * already on the page — the selection, or the link the caret is in — and then a page never
   * fills them, even emptied (D-183, the owner: Link text is the selection's, always). Done
   * waits for both words and an address.
   */
  function picker(anchor, url, onChange, onDone, onRemove, text, selected) {
    close();
    var panel = el('div', 'pb-link');
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', pb.t('inline.link_title'));
    panel.setAttribute('data-pb-link', '');
    panel.appendChild(el('p', 'pb-link-title', pb.t('inline.link_title')));

    function field(id, words, control) {
      var holder = el('div', 'field');
      var label = el('label', '', words);
      label.htmlFor = id;
      control.id = id;
      holder.appendChild(label);
      holder.appendChild(control);
      return holder;
    }
    var words = el('input');
    words.type = 'text';
    words.setAttribute('data-pb-link-text', '');
    words.value = text || '';
    var select = el('select');
    var other = el('option', '', pb.t('inline.link_other'));
    other.value = '';
    select.appendChild(other);
    pages.forEach(function (page) {
      var option = el('option', '', new Array(page.depth + 1).join('— ') + page.title);
      option.value = page.ref;
      option.setAttribute('data-title', page.title);
      select.appendChild(option);
    });
    var input = el('input');
    input.type = 'text';
    input.inputMode = 'url';
    panel.appendChild(field('pb-link-text', pb.t('inline.link_text'), words));
    panel.appendChild(field('pb-link-page', pb.t('inline.link_page'), select));
    var urlField = field('pb-link-url', pb.t('inline.link_url'), input);
    panel.appendChild(urlField);

    var isPage = /^page:\d+$/.test(url || '');
    select.value = isPage ? url : '';
    input.value = isPage ? '' : (url || '');
    urlField.hidden = isPage;

    var row = el('div', 'pb-link-actions');
    var done = el('button', 'button button-secondary', pb.t('inline.link_done'));
    done.type = 'button';
    done.setAttribute('data-pb-link-done', '');
    row.appendChild(done);
    if (onRemove) {
      var remove = el('button', 'button button-ghost', pb.t('inline.link_remove'));
      remove.type = 'button';
      remove.setAttribute('data-pb-link-remove', '');
      remove.addEventListener('click', function () { close(); onRemove(); });
      row.appendChild(remove);
    }
    panel.appendChild(row);

    function value() { return select.value !== '' ? select.value : input.value.trim(); }
    function ready() { done.disabled = value() === '' || words.value.trim() === ''; }
    function changed() { ready(); onChange(value(), words.value); }
    select.addEventListener('change', function () {
      urlField.hidden = select.value !== '';
      var title = select.value !== '' ? select.selectedOptions[0].getAttribute('data-title') : null;
      if (title !== null && words.value.trim() === '' && !selected) {
        words.value = title;
      }
      changed();
    });
    input.addEventListener('input', changed);
    words.addEventListener('input', changed);
    function finish() { var v = value(); var w = words.value; close(); if (onDone) { onDone(v, w); } }
    done.addEventListener('click', function () { if (!done.disabled) { finish(); } });
    panel.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' && (event.target === input || event.target === words)) {
        event.preventDefault();
        if (!done.disabled) { finish(); }
      }
      if (event.key === 'Escape') { event.preventDefault(); finish(); }
    });
    ready();

    document.querySelector('[data-pb]').appendChild(panel);
    open = { panel: panel, anchor: anchor };
    place();
    return panel;
  }

  /**
   * A link's words as they are written, on one line. Not innerText, which gives them as drawn:
   * a design that sets buttons in capitals put "BOOK A CONSULTATION" in Link text (D-183).
   */
  function shown(anchor) { return anchor.textContent.replace(/\s+/g, ' '); }

  /** A link field's address and words, the words also typed where they are shown. */
  pb.inline.link = function (anchor, block, path) {
    var now = pb.inline.get(block, path) || { label: '', url: '' };
    picker(anchor, now.url || '', function (url, text) {
      var b = pb.block(block.key);
      if (!b) { return; }
      var link = pb.copy(pb.inline.get(b, path) || { label: '', url: '' });
      link.url = url;
      if (link.label !== text) {
        link.label = text;
      }
      // The words on the page are always the popover's (D-183): a page's title filled in
      // while the words were being typed in place stood only in the popover. Words typed in
      // place are already the same, and are left alone with their caret.
      if (shown(anchor) !== text) { anchor.textContent = text; }
      pb.inline.write(b, path, link);
    }, function () {
      pb.inline.check(block.key);
    }, function () {
      var b = pb.block(block.key);
      if (!b) { return; }
      pb.change(function () { pb.inline.set(b, path, { label: '', url: '' }); }, { sections: [b.section] });
      pb.inline.check(block.key);
    }, now.label || '');
    // Words typed on the page reach the popover's (listened for once per element).
    if (anchor.__bxLinkWords) { return; }
    anchor.__bxLinkWords = true;
    anchor.addEventListener('input', function () {
      var words = document.querySelector('[data-pb-link] [data-pb-link-text]');
      if (words && open && open.anchor === anchor) {
        words.value = shown(anchor);
        words.dispatchEvent(new Event('input'));
      }
    });
  };

  pb.inline.picker = picker;
  pb.inline.closeLink = close;

  // Placed again whenever the page under it may have moved.
  pb.on('painted', place);
  pb.on('scale', place);
  window.addEventListener('resize', place);
  // Pressing anywhere else, on the page or in the admin, closes it.
  function outside(event) {
    if (open && !open.panel.contains(event.target) && !open.anchor.contains(event.target)) {
      close();
    }
  }
  document.addEventListener('mousedown', outside, true);
  pb.on('canvas', function () {
    var doc = pb.canvas.doc();
    if (!doc || doc.__bxLink) { return; }
    doc.__bxLink = true;
    doc.addEventListener('mousedown', outside, true);
    doc.defaultView.addEventListener('scroll', place, { passive: true });
  });
})();
