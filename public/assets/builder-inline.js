/*
 * TYPING ON THE PAGE (PLAN.md D-178, README 4.4). The canvas marks the element that shows each
 * field (`data-bx-field`, edit_attr()); pressing one edits it where it stands:
 *
 *   - a line or a paragraph: plain text in place (`contenteditable="plaintext-only"`); Enter
 *     leaves a one-line field, Escape leaves any, a paste arrives as plain text;
 *   - rich text: builder-inline-rich.js; a link's words: here, its address in the popover of
 *     builder-inline-link.js;
 *   - a picture: the inspector's own picker, opened;
 *   - a repeater: "+" at its end adds one, and a pointed-at item has its own tools
 *     (builder-inline-items.js).
 *
 * Every keystroke writes the document — an undo step per field, folded while typing — and
 * nothing is drawn again while typing: the page already shows what was typed. Leaving a field
 * asks the server what is wrong with the block (builder-inline-errors.js).
 */
(function () {
  'use strict';

  var pb = window.pb;
  if (!pb || !pb.overlay || !pb.data.inline) {
    return;
  }
  var inline = pb.data.inline;
  var editing = null;

  /** What a field path names in a block of this type: `heading`, `items.2.heading`. */
  function spec(type, path) {
    var parts = path.split('.');
    var field = (inline.fields[type] || {})[parts[0]];
    return parts.length === 3 ? (field && field.fields ? field.fields[parts[2]] : null) : field;
  }
  function get(block, path) {
    var parts = path.split('.');
    var value = block.content[parts[0]];
    return parts.length === 3 ? ((value || [])[Number(parts[1])] || {})[parts[2]] : value;
  }
  function set(block, path, value) {
    var parts = path.split('.');
    if (parts.length === 3) {
      block.content[parts[0]][Number(parts[1])][parts[2]] = value;
    } else {
      block.content[parts[0]] = value;
    }
  }
  function blockOf(el) {
    var host = el.closest('[data-bx-key]');
    return host ? pb.block(host.getAttribute('data-bx-key')) : null;
  }
  function write(block, path, value) {
    pb.change(function () { set(block, path, value); }, { coalesce: 'inline:' + block.key + ':' + path, quiet: true });
  }

  // ---- a line or a paragraph ---------------------------------------------------------------------
  /**
   * The words as written, never as drawn (D-186): innerText gave them as the design draws
   * them, and under a heading set in capitals "Homes" typed on was stored as "HOMES". A line
   * break, as a <br> or a new block the browser makes, is a new line.
   */
  function written(el) {
    var copy = el.cloneNode(true);
    Array.prototype.forEach.call(copy.querySelectorAll('br'), function (br) { br.replaceWith('\n'); });
    Array.prototype.forEach.call(copy.querySelectorAll('div, p'), function (block) { if (block.previousSibling) { block.prepend('\n'); } });
    return copy.textContent;
  }
  function words(el, kind) {
    var text = written(el).replace(/ /g, ' ');
    return kind === 'textarea' ? text.replace(/\n$/, '') : text.replace(/\s*\n\s*/g, ' ');
  }

  function startText(el, block, path, field) {
    el.setAttribute('contenteditable', 'plaintext-only');
    if (el.contentEditable !== 'plaintext-only') {
      el.setAttribute('contenteditable', 'true');
    }
    editing = { el: el, key: block.key, path: path, kind: field.type, stop: null };
    var link = field.type === 'link';
    function input() {
      var now = pb.block(block.key);
      if (!now) { return; }
      var value = words(el, field.type);
      if (link) {
        var current = pb.copy(get(now, path) || { label: '', url: '' });
        current.label = value;
        write(now, path, current);
      } else {
        write(now, path, value);
      }
    }
    function keys(event) {
      // A question's words are its <summary>, whose space opens and closes it and is never
      // typed (D-186): written here instead, and the question stays as it was.
      if (event.key === ' ' && el.closest('summary')) {
        event.preventDefault();
        el.ownerDocument.execCommand('insertText', false, ' ');
        return;
      }
      if (event.key === 'Escape' || (event.key === 'Enter' && field.type !== 'textarea')) {
        event.preventDefault();
        if (event.key === 'Escape' && pb.inline.closeLink) { pb.inline.closeLink(); }
        el.blur();
      }
    }
    function paste(event) {
      // A paste is plain text, whatever the browser offers (README 4.4).
      event.preventDefault();
      var text = (event.clipboardData || window.clipboardData).getData('text/plain');
      el.ownerDocument.execCommand('insertText', false, field.type === 'textarea' ? text : text.replace(/\s*\n\s*/g, ' '));
    }
    function leave() {
      // Emptied, it is empty again — a <br> the browser leaves would hide its placeholder.
      if (words(el, field.type).trim() === '') { el.innerHTML = ''; }
      el.removeEventListener('input', input);
      el.removeEventListener('keydown', keys);
      el.removeEventListener('paste', paste);
      el.removeAttribute('contenteditable');
      if (editing && editing.el === el) { editing = null; }
      pb.inline.check(block.key);
    }
    el.addEventListener('input', input);
    el.addEventListener('keydown', keys);
    el.addEventListener('paste', paste);
    el.addEventListener('blur', leave, { once: true });
    el.focus();
    if (link && pb.inline.link) {
      pb.inline.link(el, block, path);
    }
  }

  // ---- a picture: the inspector's picker -------------------------------------------------------------
  function picture(block, path) {
    var parts = path.split('.');
    var name = 'blocks[' + block.key + '][' + parts[0] + ']' + (parts.length === 3 ? '[' + parts[1] + '][' + parts[2] + ']' : '');
    function open() {
      var select = document.querySelector('[data-pb-inspector] select[name="' + name + '"]');
      if (!select) { return false; }
      var holder = select.closest('.field, .media-picker') || select.parentElement;
      var button = holder.querySelector('.media-picker-current') || select.parentElement.querySelector('.media-picker-current');
      if (button) { button.click(); } else { select.focus(); }
      return true;
    }
    if (!open()) {
      // The inspector is being drawn for the block just pressed: open it once it is there.
      var once = function (sel) {
        if (sel && sel.key === block.key) {
          pb.off('inspected', once);
          open();
        }
      };
      pb.on('inspected', once);
    }
  }

  /**
   * A press inside words being written belongs to the browser and the editor alone (D-182):
   * the caret goes where it lands, a drag or a double press selects. Taken by the builder — a
   * preventDefault meant for the press that starts the editor — a press inside a rich field
   * moved nothing and selected nothing.
   */
  function within(t) {
    return !!(t.closest && t.closest('[contenteditable="true"], [contenteditable="plaintext-only"]'));
  }

  /** How many characters of the element's words come before the point; null when none. */
  function offsetAt(el, x, y) {
    var doc = el.ownerDocument;
    var at = doc.caretRangeFromPoint ? doc.caretRangeFromPoint(x, y) : null;
    if (!at || !el.contains(at.startContainer)) {
      return null;
    }
    var before = doc.createRange();
    before.setStart(el, 0);
    before.setEnd(at.startContainer, at.startOffset);
    return before.toString().length;
  }

  // ---- the presses ------------------------------------------------------------------------------------
  function onDown(event) {
    var t = event.target;
    if (!t.closest || t.closest('.bx-layer') || within(t)) {
      return;
    }
    var el = t.closest('[data-bx-field]');
    var block = el ? blockOf(el) : null;
    if (!el || !block || (editing && editing.el === el)) {
      return;
    }
    var path = el.getAttribute('data-bx-field');
    var field = spec(block.type, path);
    if (!field) {
      return;
    }
    // Where in the words the press was, counted before anything moves: selecting the block
    // shows its placeholders, and the words move from under the point pressed (D-182).
    var caret = offsetAt(el, event.clientX, event.clientY);
    // Its block selected now: the press may land on an element a rich editor replaces.
    pb.select('block', block.key);
    if (field.type === 'text' || field.type === 'textarea' || field.type === 'link') {
      startText(el, block, path, field);
    } else if (field.type === 'richtext' && pb.inline.rich) {
      event.preventDefault();
      pb.inline.rich(el, block, path, field, { x: event.clientX, y: event.clientY, offset: caret });
    }
  }
  function onClick(event) {
    // A press in a question being written places the caret; it does not open the answer.
    if (event.target.closest && event.target.closest('summary') && within(event.target)) {
      event.preventDefault();
    }
    var el = event.target.closest ? event.target.closest('[data-bx-field]') : null;
    var block = el ? blockOf(el) : null;
    if (el && block && !event.target.closest('.bx-layer')) {
      var field = spec(block.type, el.getAttribute('data-bx-field'));
      if (field && field.type === 'media') { picture(block, el.getAttribute('data-bx-field')); }
    }
  }

  /*
   * ONE SELECTION ON SCREEN (D-185, the owner): the canvas and the inspector are two documents,
   * and each kept its own selection when the other was written in — a word still marked on
   * the page while Link text spoke of the inspector's. Whichever takes the focus, the other's
   * selection is let go.
   */
  function letGo(doc) {
    var selection = doc && doc.getSelection ? doc.getSelection() : null;
    if (selection && selection.rangeCount > 0) { selection.removeAllRanges(); }
  }
  document.addEventListener('focusin', function (event) {
    if (event.target.closest && event.target.closest('[data-pb-inspector]')) { letGo(pb.canvas.doc()); }
  });

  pb.on('canvas', function () {
    var doc = pb.canvas.doc();
    if (!doc || doc.__bxInline) { return; }
    doc.__bxInline = true;
    doc.addEventListener('mousedown', onDown, true);
    doc.addEventListener('click', onClick, true);
    doc.addEventListener('focusin', function () {
      var selection = document.getSelection();
      var at = selection && selection.anchorNode ? selection.anchorNode : null;
      if (at && (at.nodeType === 1 ? at : at.parentElement).closest('[data-pb-inspector]')) { letGo(document); }
    });
  });

  // check(), what the server says is wrong, is builder-inline-errors.js's.
  pb.inline = { spec: spec, get: get, set: set, write: write, blockOf: blockOf, within: within, editing: function () { return editing; } };
})();
