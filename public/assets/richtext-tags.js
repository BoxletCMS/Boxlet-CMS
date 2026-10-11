/*
 * REPLACEMENT TAGS IN THE EDITOR (SPEC §5.6, PLAN.md D-201): `{{year}}`, `{{lang:switcher}}` and
 * `{{snippet:name}}` are written in rich text and kept as written; while a field is edited each
 * stands as a chip showing what a visitor will see (the owner's choice), removed as one
 * character, and turned back into the tag before the field is saved.
 *
 * The words a chip shows come from the admin's page (`#boxlet-tags`, Tags::editorData): the
 * year, the language switcher's name, and each snippet's words in plain text per language. A
 * field's language is the nearest `data-tag-locale`, else the site's first.
 *
 * Insert: a list under a toolbar button — the year, the switcher and every snippet — puts a
 * chip where the caret is. Typing a tag works too: it is a chip the next time the field opens.
 */
(function () {
  'use strict';

  var PATTERN = /\{\{(year|lang:switcher|snippet:[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?)\}\}/g;
  var cached = null;

  function data() {
    if (cached) { return cached; }
    var el = document.getElementById('boxlet-tags');
    try { cached = el ? JSON.parse(el.textContent) : null; } catch (e) { cached = null; }
    cached = cached || { year: '', switcher: '', primary: '', snippets: {}, words: {} };
    return cached;
  }

  function escape(text) {
    return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  /** What a chip says: what a visitor will see, or the tag itself where nothing would show. */
  function label(tag, locale) {
    var d = data();
    if (tag === 'year') { return d.year; }
    if (tag === 'lang:switcher') { return d.switcher; }
    var words = (d.snippets[tag.slice(8)] || {});
    return words[locale] || words[d.primary] || '{{' + tag + '}}';
  }

  /** The field's language. */
  function localeOf(el) {
    var near = el && el.closest ? el.closest('[data-tag-locale]') : null;
    return near ? near.getAttribute('data-tag-locale') : data().primary;
  }

  /** The tags in stored HTML as chips: between elements only, as the server reads them. */
  function toChips(html, locale) {
    return String(html || '').split(/(<[^>]*>)/).map(function (part) {
      if (part === '' || part.charAt(0) === '<') { return part; }
      return part.replace(PATTERN, function (all, tag) {
        return '<span data-tag="' + escape(tag) + '">' + escape(label(tag, locale)) + '</span>';
      });
    }).join('');
  }

  /**
   * The chips in an editor's HTML back as the tags they stand for. A space typed beside a chip
   * is one the browser keeps from collapsing as a no-break space (measured in Chrome, D-201):
   * there it is a plain space again, or a line could never break beside a tag.
   */
  function fromChips(html) {
    var TAG = '\\{\\{(?:year|lang:switcher|snippet:[a-z0-9-]+)\\}\\}';
    return String(html || '').replace(/<span[^>]*\sdata-tag="([^"]+)"[^>]*>[\s\S]*?<\/span>/g, function (all, tag) {
      return '{{' + tag.replace(/&amp;/g, '&') + '}}';
    }).replace(new RegExp('&nbsp;(?=' + TAG + ')|(' + TAG + ')&nbsp;', 'g'), function (all, before) {
      return before ? before + ' ' : ' ';
    });
  }

  /** The chip, for TipTap: an atom inside a line, nothing to type into. */
  function node(tiptap, locale) {
    if (!tiptap || !tiptap.Node) { return null; }
    return tiptap.Node.create({
      name: 'boxletTag',
      group: 'inline',
      inline: true,
      atom: true,
      selectable: true,
      addAttributes: function () {
        return { tag: { default: '', parseHTML: function (el) { return el.getAttribute('data-tag'); }, renderHTML: function (a) { return { 'data-tag': a.tag }; } } };
      },
      parseHTML: function () { return [{ tag: 'span[data-tag]' }]; },
      renderHTML: function (props) {
        // Both names: rt-tag in an admin field, bx-tag on the page builder's canvas, each styled
        // by its own document's sheet as the chip the server draws there.
        return ['span', Object.assign({ class: 'rt-tag bx-tag', contenteditable: 'false' }, props.HTMLAttributes), label(props.node.attrs.tag, locale)];
      },
    });
  }

  /** What Insert offers, in order. */
  function items() {
    var d = data();
    var w = d.words || {};
    var list = [
      { tag: 'year', text: (w.year || 'Year') + ' (' + d.year + ')' },
      { tag: 'lang:switcher', text: w.switcher || 'Language switcher' },
    ];
    Object.keys(d.snippets || {}).sort().forEach(function (name) {
      list.push({ tag: 'snippet:' + name, text: (w.snippet || 'Snippet') + ': ' + name });
    });
    return list;
  }

  /**
   * The Insert list under `button`: chosen, the chip goes where the caret is. Escape or a press
   * elsewhere closes it, and the focus goes back to the editor.
   */
  function menu(editor, button) {
    var doc = button.ownerDocument;
    var open = doc.querySelector('.rt-insert-menu');
    if (open) { open.remove(); if (open.__for === button) { return; } }
    var list = doc.createElement('div');
    list.className = 'rt-insert-menu';
    list.setAttribute('role', 'menu');
    list.__for = button;
    items().forEach(function (item) {
      var choice = doc.createElement('button');
      choice.type = 'button';
      choice.setAttribute('role', 'menuitem');
      choice.setAttribute('data-insert-tag', item.tag);
      choice.textContent = item.text;
      list.appendChild(choice);
    });
    // After the button's group, which clips what overflows it, and under the button: the
    // toolbar is what the list is placed against (admin-richtext.css).
    var group = button.closest('.rt-group') || button;
    group.insertAdjacentElement('afterend', list);
    list.style.insetInlineStart = group.offsetLeft + 'px';
    button.setAttribute('aria-expanded', 'true');
    function close() {
      list.remove();
      button.setAttribute('aria-expanded', 'false');
      doc.removeEventListener('mousedown', outside, true);
    }
    function outside(event) { if (!list.contains(event.target) && event.target !== button) { close(); } }
    doc.addEventListener('mousedown', outside, true);
    list.addEventListener('mousedown', function (event) { event.preventDefault(); });
    list.addEventListener('click', function (event) {
      var choice = event.target.closest('[data-insert-tag]');
      if (!choice) { return; }
      // Focused at once, not on the next frame as TipTap's focus() does: a key pressed straight
      // after the choice belongs in the words (measured: the first one was lost).
      editor.view.focus();
      editor.chain().focus().insertContent({ type: 'boxletTag', attrs: { tag: choice.getAttribute('data-insert-tag') } }).run();
      close();
    });
    list.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') { event.preventDefault(); close(); editor.commands.focus(); }
    });
    var first = list.querySelector('button');
    if (first) { first.focus(); }
  }

  window.boxletRichTextTags = { toChips: toChips, fromChips: fromChips, node: node, localeOf: localeOf, menu: menu, label: label };
})();
