/*
 * Rich text fields, on TipTap (PLAN.md D-017).
 *
 * The contract belongs to the project, not to the editor, and survived replacing one:
 *
 *   - The textarea in the HTML is the real field. It carries the name, and without
 *     JavaScript it is a perfectly good way to edit HTML. This takes the name off it, puts
 *     it on a hidden input, and puts the editor above.
 *   - The plain toggle shows the textarea again. It is what was underneath all along, not
 *     a second editor kept in step with the first.
 *   - Nothing is bound by an id that encodes a block's position. Both editors renumber
 *     block-<n>- ids when a block moves, and an editor bound to one would start writing
 *     into another block's field (the 2a bug).
 *
 * The editor is a convenience. The server's whitelist decides what is stored, whatever
 * arrives, and the schema below is that whitelist so the editor cannot even offer markup
 * the server would throw away.
 */
(function () {
  'use strict';

  var seq = 0;

  /*
   * The schema is exactly the storage whitelist (SPEC §5.3).
   *
   * Every option here was settled by measuring the editor's output, not by reading about
   * it:
   *   trailingNode: false — TipTap otherwise appends an empty paragraph to any content
   *     that does not end in one, so <ul>…</ul> came back as <ul>…</ul><p></p> and a field
   *     changed on its first save. The cost is that a list or quote at the very end has no
   *     paragraph after it to click into; Enter twice still leaves the list.
   *   HTMLAttributes on the link — TipTap adds target and rel by default, which the
   *     whitelist does not allow. Nulling them there works; setting target/rel at the top
   *     level does not.
   */
  function extensions(tiptap, locale) {
    // The replacement tags' chip (richtext-tags.js, D-201), where the page loads it.
    var tags = window.boxletRichTextTags ? window.boxletRichTextTags.node(tiptap, locale) : null;
    // A paragraph's or heading's alignment (richtext-align.js, D-217), where the page loads it.
    var align = window.boxletRichTextAlign ? window.boxletRichTextAlign.extension(tiptap) : null;
    return [
      tiptap.StarterKit.configure({
        code: false,
        codeBlock: false,
        strike: false,
        underline: false,
        horizontalRule: false,
        link: false,
        trailingNode: false,
        heading: { levels: [2, 3, 4] },
      }),
      tiptap.Link.configure({
        openOnClick: false,
        autolink: false,
        HTMLAttributes: { target: null, rel: null },
        // page:{group}, a link to a page followed at render (PLAN.md D-034). Without it the
        // link extension refuses the scheme and drops the mark when the field loads.
        protocols: ['page'],
      }),
    ].concat(tags ? [tags] : [], align ? [align] : []);
  }

  /** The stored words with their tags as chips, and back: identity where tags are not loaded. */
  var T = {
    to: function (html, locale) { return window.boxletRichTextTags ? window.boxletRichTextTags.toChips(html, locale) : html; },
    from: function (html) { return window.boxletRichTextTags ? window.boxletRichTextTags.fromChips(html) : html; },
  };

  function aligned(editor, value) {
    return window.boxletRichTextAlign ? window.boxletRichTextAlign.active(editor, value) : false;
  }

  /**
   * Whether a button is lit: a mark or node by name, a name with its attributes, or a test of
   * the editor's own (an alignment, D-217).
   */
  function isOn(editor, active) {
    if (typeof active === 'function') {
      return active(editor);
    }
    return Array.isArray(active) ? editor.isActive(active[0], active[1]) : editor.isActive(active);
  }

  /** What each toolbar button does, and when it shows as active. */
  var COMMANDS = {
    bold: { run: function (c) { return c.toggleBold(); }, active: 'bold' },
    italic: { run: function (c) { return c.toggleItalic(); }, active: 'italic' },
    h2: { run: function (c) { return c.toggleHeading({ level: 2 }); }, active: ['heading', { level: 2 }] },
    h3: { run: function (c) { return c.toggleHeading({ level: 3 }); }, active: ['heading', { level: 3 }] },
    h4: { run: function (c) { return c.toggleHeading({ level: 4 }); }, active: ['heading', { level: 4 }] },
    quote: { run: function (c) { return c.toggleBlockquote(); }, active: 'blockquote' },
    bullet: { run: function (c) { return c.toggleBulletList(); }, active: 'bulletList' },
    ordered: { run: function (c) { return c.toggleOrderedList(); }, active: 'orderedList' },
    // Left is the block's own alignment: the paragraph's taken away (D-217).
    alignLeft: { run: function (c) { return c.setAlign('left'); }, active: function (e) { return aligned(e, 'left'); } },
    alignCenter: { run: function (c) { return c.setAlign('center'); }, active: function (e) { return aligned(e, 'center'); } },
    alignRight: { run: function (c) { return c.setAlign('right'); }, active: function (e) { return aligned(e, 'right'); } },
    undo: { run: function (c) { return c.undo(); } },
    redo: { run: function (c) { return c.redo(); } },
  };

  /** What the editor is called: the label the textarea had, its words, as a multi-line box. */
  function named(textarea) {
    var label = textarea.id ? document.querySelector('label[for="' + textarea.id + '"]') : null;
    var words = (label ? label.textContent : textarea.getAttribute('aria-label') || '').replace(/\s+/g, ' ').trim();
    var attributes = { role: 'textbox', 'aria-multiline': 'true' };
    if (words) { attributes['aria-label'] = words; }
    return attributes;
  }

  function setup(textarea) {
    var tiptap = window.BoxletTipTap;
    if (textarea.hasAttribute('data-richtext-ready') || !tiptap) {
      return;
    }
    textarea.setAttribute('data-richtext-ready', '');

    var field = textarea.closest('[data-richtext]');
    var uid = 'richtext-' + seq++;

    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = textarea.name;
    hidden.value = textarea.value;
    hidden.id = uid + '-value';
    textarea.removeAttribute('name');
    textarea.insertAdjacentElement('afterend', hidden);

    var host = document.createElement('div');
    host.className = 'richtext-editor';
    host.setAttribute('data-richtext-editor', '');
    hidden.insertAdjacentElement('afterend', host);

    var locale = window.boxletRichTextTags ? window.boxletRichTextTags.localeOf(textarea) : '';
    var editor = new tiptap.Editor({
      element: host,
      extensions: extensions(tiptap, locale),
      injectCSS: false,
      content: T.to(textarea.value, locale),
      // Read by the field's own label, as the textarea it stands for was (D-181): the
      // editable element is the control a screen reader meets, and it had no name.
      editorProps: { attributes: named(textarea) },
      onUpdate: function () {
        hidden.value = T.from(editor.getHTML());
        // Say so out loud. The builder redraws the canvas from an `input` event on the
        // field groups, and the unsaved-changes warning listens for the same thing —
        // but assigning .value in script fires nothing, so an edit made here was
        // invisible to both. Every other field type reaches them because a person typing
        // into a real control fires its own event; the editor has to do it itself.
        hidden.dispatchEvent(new Event('input', { bubbles: true }));
      },
    });

    // The words put in from outside, as the document has them (O-61: written on the page, they
    // reach All content's open editor), without saying so: they are the document's already.
    hidden.boxletSetValue = function (html) {
      hidden.value = html;
      editor.commands.setContent(T.to(html, locale), { emitUpdate: false });
    };
    hidden.boxletIsFocused = function () { return editor.isFocused; };

    var toolbar = field.querySelector('[data-richtext-toolbar]');
    var link = field.querySelector('[data-richtext-link]');
    field.classList.add('richtext-rich');

    /*
     * Which buttons are lit. ProseMirror keeps the selection in the editor's own state, so
     * this is asked of the document rather than of the browser's selection.
     */
    function refresh() {
      if (!toolbar) {
        return;
      }
      Object.keys(COMMANDS).forEach(function (name) {
        var button = toolbar.querySelector('[data-rt="' + name + '"]');
        var active = COMMANDS[name].active;
        if (!button || !active) {
          return;
        }
        button.setAttribute('aria-pressed', String(isOn(editor, active)));
      });
      var linkButton = toolbar.querySelector('[data-rt="link"]');
      if (linkButton) {
        linkButton.setAttribute('aria-pressed', String(editor.isActive('link')));
      }
    }
    editor.on('selectionUpdate', refresh);
    editor.on('transaction', refresh);

    if (toolbar) {
      toolbar.addEventListener('click', function (event) {
        var button = event.target.closest('[data-rt]');
        if (!button) {
          return;
        }
        event.preventDefault();
        var name = button.getAttribute('data-rt');
        if (name === 'link') {
          openLink();
          return;
        }
        if (name === 'tag' && window.boxletRichTextTags) {
          window.boxletRichTextTags.menu(editor, button);
          return;
        }
        if (COMMANDS[name]) {
          COMMANDS[name].run(editor.chain().focus()).run();
          refresh();
        }
      });
    }

    // The link panel is richtext-link.js's (D-182): the words, a page or an address.
    var panel = link && window.boxletRichTextLink ? window.boxletRichTextLink(editor, link, refresh) : null;
    function openLink() { if (panel) { panel.open(); } }

    // Ctrl+K opens it from the keyboard, and clicking back into the text puts it away:
    // the panel belongs to the selection, so returning to the writing ends it.
    host.addEventListener('keydown', function (event) {
      if ((event.ctrlKey || event.metaKey) && String(event.key).toLowerCase() === 'k') {
        event.preventDefault();
        openLink();
      }
    });
    host.addEventListener('mousedown', function () {
      if (panel && panel.isOpen()) {
        panel.close(false);
      }
    });

    var toggle = field.querySelector('[data-richtext-toggle]');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var plain = field.classList.toggle('richtext-plain');
        field.classList.toggle('richtext-rich', !plain);
        if (plain) {
          textarea.value = hidden.value;
          textarea.focus();
          toggle.textContent = toggle.getAttribute('data-label-rich');
        } else {
          editor.commands.setContent(T.to(textarea.value, locale), { emitUpdate: false });
          hidden.value = T.from(editor.getHTML());
          toggle.textContent = toggle.getAttribute('data-label-plain');
        }
      });
    }

    // In plain mode the textarea has no name, so what it holds has to reach the field
    // that does.
    textarea.addEventListener('input', function () {
      if (field.classList.contains('richtext-plain')) {
        hidden.value = textarea.value;
      }
    });

    refresh();
  }

  function scan(root) {
    (root || document).querySelectorAll('textarea[data-richtext-source]').forEach(setup);
  }

  // Blocks added to the canvas arrive as HTML from the server and need the same
  // treatment; builder-inspector.js calls this after drawing a block's fields. The schema and
  // the commands go with it: typing on the page (builder-inline-rich.js, D-178) is the same
  // editor on the same whitelist, never a second one.
  window.boxletRichText = { scan: scan, extensions: extensions, commands: COMMANDS, tags: T, isOn: isOn };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { scan(document); });
  } else {
    scan(document);
  }
})();
