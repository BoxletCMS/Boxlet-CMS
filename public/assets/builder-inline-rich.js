/*
 * RICH TEXT ON THE PAGE (PLAN.md D-178, README 4.4): the inspector's editor — the same TipTap,
 * the same schema, the same commands (richtext.js) — mounted on the element that shows the
 * words, with a small toolbar offering only what the field allows (its `allow`, the list the
 * sanitiser keeps). TipTap is the page's, loaded once with the builder; it is started on the
 * element the first time it is pressed, and put away when the writing leaves it, when the band
 * is drawn again from the document as a visitor will get it.
 *
 * THE TOOLBAR IS A BUBBLE OVER THE SELECTION (D-186, the owner; Notion's model, TipTap's
 * BubbleMenu): nothing while there is only a caret; words selected, the toolbar 8px above
 * them, centred on them, kept inside the canvas, and under them where there is no room above.
 * ⌘B, ⌘I and ⌘K work with or without it; ⌘K opens the link popover beside the selection.
 *
 * Measured before it was built: an editor made in the builder's window edits an element of the
 * canvas's document, typing and bold included (D-178).
 */
(function () {
  'use strict';

  var pb = window.pb;
  var rt = window.boxletRichText;
  var T = window.BoxletTipTap;
  if (!pb || !pb.inline || !pb.overlay || !T || !T.BubbleMenu || !rt || !rt.extensions) {
    return;
  }
  var o = pb.overlay;
  // Each button: the feature that allows it, the command richtext.js runs, an icon, its words.
  var BUTTONS = [
    ['bold', 'bold', 'bold', 'rt.bold'],
    ['italic', 'italic', 'italic', 'rt.italic'],
    ['heading', 'h2', 'heading-2', 'rt.heading_2'],
    ['heading', 'h3', 'heading-3', 'rt.heading_3'],
    ['list', 'bullet', 'list', 'rt.bullets'],
    ['list', 'ordered', 'list-ordered', 'rt.numbers'],
    ['quote', 'quote', 'text-quote', 'rt.quote'],
    ['link', 'link', 'link', 'rt.link'],
    // Always there: a paragraph is in every field, and so its alignment (D-217).
    ['align', 'alignLeft', 'text-align-start', 'rt.align_left'],
    ['align', 'alignCenter', 'text-align-center', 'rt.align_center'],
    ['align', 'alignRight', 'text-align-end', 'rt.align_right'],
  ];
  var current = null;
  // The page's language, which a snippet's chip speaks (D-201).
  var tags = window.boxletRichTextTags;
  var locale = tags ? tags.localeOf(document.querySelector('[data-pb]')) : '';

  function bar() {
    var tools = o.el('div', 'bx-rich-tools');
    tools.setAttribute('role', 'toolbar');
    tools.setAttribute('aria-label', pb.t('rt.toolbar'));
    var allow = current.field.allow || ['bold', 'italic', 'link', 'heading', 'list', 'quote'];
    BUTTONS.forEach(function (b) {
      if (b[0] === 'align' ? !window.boxletRichTextAlign : allow.indexOf(b[0]) < 0) {
        return;
      }
      var button = o.button('rt-' + b[1], b[2], pb.t(b[3]));
      button.setAttribute('data-rt', b[1]);
      button.setAttribute('aria-pressed', 'false');
      tools.appendChild(button);
    });
    // Pressing a tool keeps the writing where it is: no focus is taken from the editor.
    tools.addEventListener('mousedown', function (event) { event.preventDefault(); });
    tools.addEventListener('click', function (event) {
      var button = event.target.closest('[data-rt]');
      if (button) { run(button.getAttribute('data-rt')); }
    });
    return tools;
  }

  /**
   * Where the bubble lives: a layer of its own over the page, beside the editor's layer rather
   * than in it — that one is emptied and drawn again on every change, and would take the
   * bubble with it as each letter is typed.
   */
  function host() {
    var doc = pb.canvas.doc();
    var layer = doc.querySelector('.bx-layer.bx-bubble-layer');
    if (!layer) {
      layer = o.el('div', 'bx-layer bx-bubble-layer');
      doc.body.appendChild(layer);
    }
    return layer;
  }

  /** The selected words, as the link popover places itself beside them. */
  function selected(editor, from, to, el) {
    return {
      get isConnected() { return el.isConnected && !editor.isDestroyed; },
      contains: function (node) { return el.contains(node); },
      getBoundingClientRect: function () {
        var a = editor.view.coordsAtPos(from);
        var b = editor.view.coordsAtPos(to);
        return { top: Math.min(a.top, b.top), bottom: Math.max(a.bottom, b.bottom), left: Math.min(a.left, b.left), right: Math.max(a.right, b.right) };
      },
    };
  }

  function refresh() {
    if (!current) {
      return;
    }
    var editor = current.editor;
    Array.prototype.forEach.call(current.tools.querySelectorAll('[data-rt]'), function (button) {
      var name = button.getAttribute('data-rt');
      var active = name === 'link' ? 'link' : (rt.commands[name] || {}).active;
      if (!active) { return; }
      button.setAttribute('aria-pressed', String(rt.isOn(editor, active)));
    });
  }

  function run(name, typed) {
    var editor = current.editor;
    if (name === 'link') {
      current.holding = true;
      var href = editor.getAttributes('link').href || '';
      // The words the link is on: the selection, or the whole link the caret is in.
      if (href) { editor.chain().extendMarkRange('link').run(); }
      var from = editor.state.selection.from;
      var to = editor.state.selection.to;
      var said = editor.state.doc.textBetween(from, to, ' ');
      var panel = pb.inline.picker(selected(editor, from, to, current.el), href, function () {}, function (url, words) {
        current.holding = false;
        var chain = editor.chain().focus().setTextSelection({ from: from, to: to });
        if (!url) {
          chain.extendMarkRange('link').unsetLink().run();
        } else if (words && words !== said) {
          // New words, or none were selected: the words are put in as the link.
          chain.insertContent({ type: 'text', text: words, marks: [{ type: 'link', attrs: { href: url } }] }).run();
        } else {
          chain.setLink({ href: url }).run();
        }
      }, href ? function () {
        current.holding = false;
        editor.chain().focus().setTextSelection({ from: from, to: to }).extendMarkRange('link').unsetLink().run();
      } : null, said, said !== '');
      // From the keyboard, the address is typed next: the caret goes there, as ⌘K does in any
      // editor, and Escape in the popover is the popover's.
      if (typed) {
        var address = panel.querySelector('#pb-link-url');
        (address && !address.closest('[hidden]') ? address : panel.querySelector('select')).focus();
      }
      return;
    }
    rt.commands[name].run(editor.chain().focus()).run();
  }

  function finish() {
    if (!current || current.holding) {
      return;
    }
    var done = current;
    current = null;
    done.editor.destroy();
    // Drawn again from the document, as a visitor will have it, and the server's word on it.
    pb.canvas.draw(done.section);
    pb.inline.check(done.key);
  }

  /**
   * The editor's position after `n` characters of its words, as the page counted them. A tag's
   * chip counts as the words it shows (D-201), and a press inside one lands after it.
   */
  function textPos(editor, n) {
    var found = null;
    editor.state.doc.descendants(function (node, pos) {
      var chip = node.type.name === 'boxletTag';
      if (found !== null || (!node.isText && !chip)) {
        return found === null;
      }
      var length = chip ? (tags ? tags.label(node.attrs.tag, locale) : '').length : node.text.length;
      if (n <= length) {
        found = chip ? pos + node.nodeSize : pos + n;
      } else {
        n -= length;
      }
      return false;
    });
    return found;
  }

  pb.inline.rich = function (el, block, path, field, point) {
    if (current && current.el === el) {
      return;
    }
    finish();
    var html = pb.inline.get(block, path) || '';
    el.innerHTML = '';
    // THE FIELD ITSELF IS THE EDITOR (D-186): mounted on it, not in a <div> made inside it. In
    // a div of its own the paragraphs were no longer the field's first and last children, took
    // back the margins the page takes from those, and the words grew up and down as they were
    // pressed. Now the page's rules for the field hold while it is written in.
    current = { editor: null, el: el, key: block.key, section: block.section, path: path, field: field, holding: false, tools: null };
    current.tools = bar();
    var win = el.ownerDocument.defaultView;
    var editor = new T.Editor({
      element: { mount: el },
      extensions: rt.extensions(T, locale).concat([T.BubbleMenu.configure({
        element: current.tools,
        appendTo: host,
        updateDelay: 0,
        // Words selected in the field being written, and nothing else: a caret has no bubble.
        shouldShow: function (props) {
          var sel = props.state.selection;
          return props.view.hasFocus() && !sel.empty && props.state.doc.textBetween(sel.from, sel.to).length > 0;
        },
        options: { placement: 'top', offset: 8, flip: { padding: 8 }, shift: { padding: 8 }, scrollTarget: win },
      })]),
      injectCSS: false,
      // Its tags as chips while it is written, and as tags again in the document (D-201).
      content: rt.tags ? rt.tags.to(html, locale) : html,
      editorProps: {
        handleKeyDown: function (view, event) {
          // ⌘K, bubble or no bubble: the link popover, beside the selection.
          if ((event.metaKey || event.ctrlKey) && !event.altKey && !event.shiftKey && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            if ((current.field.allow || ['link']).indexOf('link') >= 0) { run('link', true); }
            return true;
          }
          // Escape leaves the words, as it leaves a line: the block's toolbar comes back.
          if (event.key === 'Escape') {
            editor.commands.blur();
            return true;
          }
          return false;
        },
      },
      onUpdate: function () {
        var now = pb.block(block.key);
        if (now) { pb.inline.write(now, path, rt.tags ? rt.tags.from(editor.getHTML()) : editor.getHTML()); }
      },
    });
    current.editor = editor;
    editor.on('selectionUpdate', refresh);
    editor.on('transaction', refresh);
    editor.on('blur', function () {
      // After the press that blurred it has landed: a toolbar press keeps it, a link panel holds it.
      setTimeout(function () { if (current && current.editor === editor && !editor.isFocused) { finish(); } }, 0);
    });
    // The caret where the press was, as in any text; at the end when that is not a place in it.
    // Asked once the editor is laid out, and focused without scrolling, which moved the page
    // from under the point pressed: every first press put the caret at the end (D-182).
    editor.commands.focus('end', { scrollIntoView: false });
    var counted = point && point.offset !== null && point.offset !== undefined ? textPos(editor, point.offset) : null;
    if (counted !== null) {
      editor.commands.setTextSelection(counted);
    } else if (point) {
      el.ownerDocument.defaultView.requestAnimationFrame(function () {
        var at = editor.isDestroyed ? null : editor.view.posAtCoords({ left: point.x, top: point.y });
        if (at) { editor.commands.setTextSelection(at.pos); }
      });
    }
  };

  // Undo, Discard or a restore replace the document: whatever was being written goes with it.
  pb.on('replace', function () {
    if (current) {
      current.holding = false;
      finish();
    }
  });
})();
