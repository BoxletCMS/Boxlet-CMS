/*
 * Repeater fields: adding, removing and reordering the items of one field (PLAN.md O-11).
 *
 * ALL OF IT IS OPTIONAL. Every control here is a real submit the save route understands —
 * item-add-{n}-{field}, item-up-{n}-{field}-{m} and its down — so a browser with no
 * JavaScript adds, removes and reorders items through the server, exactly as it moves
 * blocks (D-011: one route, both paths). This file only stops the round trip.
 *
 * ONE FILE FOR BOTH EDITORS. The plain editor and the visual editor's panel render the
 * same field group, so this is listed beside richtext.js and media-picker.js by both
 * controllers. It could not live in admin.js, which returns early on any form without a
 * [data-block-list] — and the builder's form has none.
 *
 * TWO INDICES, REWRITTEN INDEPENDENTLY. An item's inputs are named
 * blocks[n][{field}][m][{itemfield}]. Moving a BLOCK rewrites n and must not touch m;
 * moving an ITEM rewrites m and must not touch n. Both editors' own renumber() regexes are
 * anchored at the start of the name, so they stop before the item index by construction —
 * the block half needs no change. This file owns the other half, and rewrites the index in
 * the MIDDLE of the name, which is why it has to know the field's own name.
 */
(function () {
  'use strict';

  // The placeholders item.php renders instead of a real key and index. A <template>'s
  // contents are not live nodes, so nothing ever reaches inside one to correct them: a
  // template that had baked in a block's name would add items to the wrong block.
  var BLOCK = '__INDEX__';
  var ITEM = '__ITEM__';

  function items(repeater) {
    var list = repeater.querySelector('[data-repeater-items]');
    return list ? Array.prototype.filter.call(list.children, function (child) {
      return child.hasAttribute('data-repeater-item');
    }) : [];
  }

  /**
   * The KEY of the block this repeater sits in, read from the group's own hidden type
   * input rather than from an attribute that would go stale. It used to be a position that
   * both editors' renumber() rewrote; since D-094 it is a name the block keeps, and reading
   * it from the field that will be submitted is still the way to be sure it is the one.
   *
   * `type` is reserved as a field name by the block contract — for an item's fields as
   * much as a block's — so nothing inside the group can match this but the block's own.
   */
  function blockIndex(repeater) {
    var group = repeater.closest('[data-block]');
    var input = group && group.querySelector('input[type="hidden"][name$="[type]"]');
    var found = input && input.name.match(/^blocks\[([^\]]*)\]/);
    return found ? found[1] : null;
  }

  /**
   * Makes every item's name, id and number follow its position on screen.
   *
   * The field name is safe to put in a regular expression without escaping: the block
   * contract restricts a field name to [a-z][a-z0-9_]*, which holds no metacharacter.
   */
  function renumber(repeater) {
    var field = repeater.getAttribute('data-repeater');
    var namePattern = new RegExp('^(blocks\\[[^\\]]*\\]\\[' + field + '\\])\\[[^\\]]*\\]');
    var idPattern = new RegExp('^(block-[^-]+-' + field + '-)[^-]+-');
    var label = repeater.getAttribute('data-text-item') || '';
    var list = items(repeater);

    list.forEach(function (item, index) {
      item.querySelectorAll('[name]').forEach(function (element) {
        element.name = element.name.replace(namePattern, '$1[' + index + ']');
      });
      item.querySelectorAll('[id]').forEach(function (element) {
        element.id = element.id.replace(idPattern, '$1' + index + '-');
      });
      item.querySelectorAll('label[for]').forEach(function (element) {
        element.htmlFor = element.htmlFor.replace(idPattern, '$1' + index + '-');
      });
      var number = item.querySelector('[data-repeater-number]');
      if (number && label) {
        number.textContent = label.replace('%n', String(index + 1));
      }
    });

    // The `action` values of the item's own submit buttons are deliberately NOT rewritten.
    // They are read only on the path with no JavaScript, where every change is a round trip
    // that re-renders the form with correct values; here the buttons never submit. Blocks
    // behave the same way, and rewriting them would state a freshness this file cannot keep
    // — a block moving elsewhere changes the n in them and nothing tells this file.

    var empty = repeater.querySelector('[data-repeater-empty]');
    if (empty) {
      empty.hidden = list.length > 0;
    }
    // A control that cannot do anything says so, rather than looking broken when pressed
    // (D-012: a control is legible at rest, and that includes being legibly unavailable).
    var add = repeater.querySelector('[data-repeater-action="add"]');
    if (add) {
      add.disabled = list.length >= Number(repeater.getAttribute('data-repeater-max'));
    }
  }

  /**
   * One bubbling `change`, which two existing mechanisms already listen for: admin.js
   * marks the form dirty so leaving it warns, and builder-blocks.js redraws the block in
   * the canvas. Adding an item is a change to the block's content and should reach both by
   * the same path a person typing in a field takes.
   */
  function changed(repeater) {
    repeater.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function add(repeater) {
    var field = repeater.getAttribute('data-repeater');
    var type = repeater.getAttribute('data-repeater-type');
    var index = blockIndex(repeater);
    var template = document.querySelector('template[data-item-template="' + type + '.' + field + '"]');
    var list = repeater.querySelector('[data-repeater-items]');
    if (!template || !list || index === null) {
      return false; // nothing to clone: let the button submit and the server add the item
    }
    if (items(repeater).length >= Number(repeater.getAttribute('data-repeater-max'))) {
      return true;
    }

    // Substituted as text before parsing, so both indices are right in every attribute the
    // item carries — names, ids and label targets alike — without walking them one by one.
    var holder = document.createElement('template');
    holder.innerHTML = template.innerHTML
      .split(BLOCK).join(index)
      .split(ITEM).join(String(items(repeater).length));
    list.appendChild(holder.content);

    var added = items(repeater).pop();
    renumber(repeater);
    // The item arrives as markup, so its rich text field is a plain textarea and its
    // picture field a plain select until these turn them into editors.
    if (window.boxletRichText) {
      window.boxletRichText.scan(added);
    }
    if (window.boxletPicker) {
      window.boxletPicker.scan(added);
    }
    changed(repeater);

    var first = added && added.querySelector('input:not([type="hidden"]), textarea, select');
    if (first) {
      first.focus();
    }
    return true;
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest && event.target.closest('[data-repeater-action]');
    if (!button || button.disabled) {
      return;
    }
    var repeater = button.closest('[data-repeater]');
    if (!repeater) {
      return;
    }
    var action = button.getAttribute('data-repeater-action');

    if (action === 'add') {
      if (add(repeater)) {
        event.preventDefault();
      }
      return;
    }

    var item = button.closest('[data-repeater-item]');
    if (!item) {
      return;
    }
    event.preventDefault();

    if (action === 'remove') {
      item.remove();
    } else if (action === 'up' && item.previousElementSibling) {
      item.parentNode.insertBefore(item, item.previousElementSibling);
    } else if (action === 'down' && item.nextElementSibling) {
      item.parentNode.insertBefore(item.nextElementSibling, item);
    }

    renumber(repeater);
    changed(repeater);
    if (action !== 'remove') {
      button.focus();
    }
  });

  // The numbers and the empty state are rendered by the server and are already right; this
  // settles the Add button's availability for a repeater that loads full.
  document.querySelectorAll('[data-repeater]').forEach(renumber);
})();
