/*
 * A RICH TEXT FIELD'S LINK PANEL (PLAN.md D-113, D-182), for richtext.js: the words the link
 * is on, a page of the site or another address, Link and Unlink.
 *
 * THE WORDS ("Link text"): the selection's, or the whole link the caret is in. Changed, or
 * typed with nothing selected, they are put in as the link. A page chosen fills them with its
 * title only for a new link at the caret, with nothing selected and no words typed (D-183):
 * selected words, or a link's own, are its text always. Link waits for both words and an
 * address.
 *
 * Editing a link without stealing the selection: the selection lives in the editor's state,
 * not in the browser, so moving focus to an input does not disturb it. extendMarkRange('link')
 * widens it to the whole link so editing one applies to all of it, and focus() hands the
 * caret back.
 */
(function () {
  'use strict';

  window.boxletRichTextLink = function (editor, link, refresh) {
    var input = link.querySelector('.rt-link-input');
    var page = link.querySelector('select');
    var words = link.querySelector('.rt-link-text');
    var apply = link.querySelector('[data-rt-link="apply"]');
    var range = null;

    function href() {
      return page && page.value !== '' ? page.value : input.value.trim();
    }
    function ready() {
      if (apply) { apply.disabled = href() === '' || (words !== null && words.value.trim() === ''); }
    }

    function open() {
      link.hidden = false;
      // The address of the link the cursor is in, so editing one starts from what it is.
      // extendMarkRange first: with only part of a link selected, getAttributes returns
      // nothing and the field came back empty when reopening on an existing link.
      editor.chain().extendMarkRange('link').run();
      var now = editor.getAttributes('link').href || '';
      range = { from: editor.state.selection.from, to: editor.state.selection.to };
      // A link to a page opens on that page; anything else opens on its address. A page
      // that is no longer offered falls back to the address, so it is seen rather than lost.
      var offered = page && Array.prototype.some.call(page.options, function (option) {
        return option.value !== '' && option.value === now;
      });
      if (page) { page.value = offered ? now : ''; }
      input.value = offered ? '' : now;
      if (words) { words.value = editor.state.doc.textBetween(range.from, range.to, ' '); }
      ready();
      (words && words.value === '' ? words : (offered ? page : input)).focus();
    }

    function close(refocus) {
      link.hidden = true;
      if (refocus) { editor.chain().focus().run(); }
    }

    link.addEventListener('click', function (event) {
      var action = event.target.closest('[data-rt-link]');
      if (!action) {
        return;
      }
      event.preventDefault();
      var address = href();
      var said = range ? editor.state.doc.textBetween(range.from, range.to, ' ') : '';
      var text = words ? words.value : said;
      var chain = editor.chain().focus();
      if (range) { chain = chain.setTextSelection(range); }
      if (action.getAttribute('data-rt-link') === 'apply' && address !== '') {
        if (text !== '' && text !== said) {
          chain.insertContent({ type: 'text', text: text, marks: [{ type: 'link', attrs: { href: address } }] }).run();
        } else {
          chain.extendMarkRange('link').setLink({ href: address }).run();
        }
      } else {
        chain.extendMarkRange('link').unsetLink().run();
      }
      // The address is in the mark now; the box is emptied so it holds nothing the form
      // could stumble on, and opens clean next time (D-113).
      input.value = '';
      close(true);
      refresh();
    });
    link.addEventListener('input', ready);
    if (page) {
      page.addEventListener('change', function () {
        var option = page.options[page.selectedIndex];
        var title = page.value !== '' && option ? option.getAttribute('data-title') : null;
        // Only for a new link at the caret: words selected, or a link's own, are the link's
        // text always (D-183), even emptied.
        if (words && title && words.value.trim() === '' && range && range.from === range.to) {
          words.value = title;
        }
        ready();
      });
    }
    link.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        close(true);
      } else if (event.key === 'Enter') {
        event.preventDefault();
        if (apply && !apply.disabled) { apply.click(); }
      }
    });

    return { open: open, close: close, isOpen: function () { return !link.hidden; } };
  };
})();
