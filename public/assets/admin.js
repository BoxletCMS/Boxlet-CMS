/*
 * Admin behaviour. All of it is optional: without JavaScript every form still submits
 * and the server does the same work.
 *
 * The page editor part only adds, removes and reorders field groups, and renumbers
 * blocks[n] in their names. It never knows which fields a block has; new groups are
 * cloned from <template> elements the server rendered.
 */
(function () {
  'use strict';

  document.documentElement.classList.add('js');

  // Buttons with data-confirm ask before they submit.
  document.addEventListener('click', function (event) {
    var button = event.target.closest && event.target.closest('[data-confirm]');
    if (button && !window.confirm(button.getAttribute('data-confirm'))) {
      event.preventDefault();
    }
  });

  // A row's menu (<details data-menu>, D-052) is row-menu.js.

  /*
   * CHOOSING A PAGE FILLS IN THE REST (PLAN.md D-038). Anywhere a link can point at one of
   * the site's pages — a block's link field, the header's button, a menu item — choosing the
   * page shows its address, read-only because the page decides it, and offers its title as
   * the text. The text is only filled when it is empty or still holds the title this script
   * put there, so nothing the owner typed is ever overwritten.
   *
   * Delegated from the document, so blocks inserted after load are covered too. Without a
   * script the server ignores the address whenever a page is chosen.
   */
  document.addEventListener('change', function (event) {
    var select = event.target;
    if (!select.matches || !select.matches('select[data-link-page]')) {
      return;
    }
    var scope = select.closest('[data-link]');
    if (!scope) {
      return;
    }
    var address = scope.querySelector('[data-link-address]');
    var label = scope.querySelector('[data-link-label]');
    var option = select.options[select.selectedIndex];
    var url = option ? option.getAttribute('data-url') : null;
    var title = option ? option.getAttribute('data-title') : null;

    if (address) {
      if (url !== null) {
        address.value = url;
        address.readOnly = true;
      } else if (address.readOnly) {
        address.readOnly = false;
        address.value = '';
      }
    }
    if (label && title !== null
      && (label.value.trim() === '' || label.value === label.getAttribute('data-filled'))) {
      label.value = title;
      label.setAttribute('data-filled', title);
      // A real control fires its own input event; this one did not, and the canvas and the
      // unsaved-changes warning listen for it.
      label.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });

  /* Above the plain editor's own guard on purpose: the VISUAL editor has the same job
     to do when it places a block, and admin.js loads on every admin screen. Below it,
     this pair existed only on the screen that needed it least. */
  /*
   * A FIELD GROUP IS NAMED ONCE, WHEN IT IS BORN (PLAN.md D-094).
   *
   * Names used to follow POSITION, so every add, move and remove rewrote every name and id
   * on the page, in two editors, with three regexes that had to agree. They no longer do:
   * a block is `blocks[b42]` or `blocks[n7]` for as long as it exists, and the order it is
   * saved in comes from the order the groups appear in the request — which is what carried
   * that meaning all along, the index never did.
   *
   * So this runs on a clone of a <template>, whose names all read `__INDEX__`, and on a
   * group the server has just sent. Shared with the visual editor, which has the same job
   * to do when it inserts or duplicates a block.
   */
  function nameGroup(group, key, sectionKey) {
    var section = sectionKey || mintSection();
    group.querySelectorAll('[name]').forEach(function (element) {
      element.name = element.name
        .replace(/^blocks\[[^\]]*\]/, 'blocks[' + key + ']')
        .replace(/^sections\[[^\]]*\]/, 'sections[' + section + ']');
    });
    /* AND THE SECTION IT SAYS IT STANDS IN (PLAN.md D-098). The name alone is not enough:
       [section] is a hidden input whose VALUE names the section, and a clone that kept the
       template's would put every block added in this session into one band. It cost a test
       to find that out and it would have cost an owner an afternoon. */
    group.querySelectorAll('[data-block-section]').forEach(function (input) {
      input.value = section;
    });
    group.querySelectorAll('[id]').forEach(function (element) {
      element.id = element.id
        .replace(/^block-[^-]+-/, 'block-' + key + '-')
        .replace(/^section-[^-]+-/, 'section-' + section + '-');
    });
    group.querySelectorAll('label[for]').forEach(function (label) {
      label.htmlFor = label.htmlFor
        .replace(/^block-[^-]+-/, 'block-' + key + '-')
        .replace(/^section-[^-]+-/, 'section-' + section + '-');
    });
    // The no-JS controls name their block too: up-b42, item-add-b42-items.
    group.querySelectorAll('button[name="action"][value]').forEach(function (button) {
      button.value = button.value
        .replace(/^(up|down)-[^-]+$/, '$1-' + key)
        .replace(/^(item-(?:up|down|add))-[^-]+-/, '$1-' + key + '-');
    });
  }

  /**
   * A key no group on this page is using.
   *
   * Counted up from the highest `n` already here rather than from zero, because the server
   * renders new blocks as n0, n1 … and a second n0 would be two blocks with one name.
   */
  function mintKey() {
    var highest = -1;
    document.querySelectorAll('[data-block] input[type="hidden"][name$="[type]"]').forEach(function (input) {
      var found = input.name.match(/^blocks\[n([0-9]+)\]/);
      if (found && Number(found[1]) > highest) {
        highest = Number(found[1]);
      }
    });

    return 'n' + (highest + 1);
  }

  /**
   * A section key no group on this page is using, counted the same way for the same reason.
   *
   * A different letter from a block's on purpose: the two namespaces are read by the same
   * regexes above, and `m0` meaning a section while `n0` means a block is a difference a
   * reader can see without looking anything up.
   */
  function mintSection() {
    var highest = -1;
    var seen = function (key) {
      var found = String(key).match(/^m([0-9]+)$/);
      if (found && Number(found[1]) > highest) {
        highest = Number(found[1]);
      }
    };
    document.querySelectorAll('[data-block] [data-block-section]').forEach(function (input) {
      seen(input.value);
    });
    /* AND EVERY BAND'S OWN GROUP, because a band nobody has put a block in yet is named
       only there (D-117). Counting blocks alone gave the second "+ Section" pressed in a row
       the same `m0` as the first — two bands with one name, and "+ Block" in the second
       filled the first. */
    document.querySelectorAll('[data-section-group]').forEach(function (group) {
      seen(group.getAttribute('data-section-group'));
    });

    return 'm' + (highest + 1);
  }

  window.boxletBlocks = { name: nameGroup, mint: mintKey, mintSection: mintSection };

  var form = document.querySelector('form[data-page-editor]');
  if (!form) {
    return;
  }
  // The visual editor's form has no block list: its blocks live in the canvas iframe and
  // builder.js drives them. Everything below belongs to the fallback form editor.
  var list = form.querySelector('[data-block-list]');
  if (!list) {
    return;
  }
  var dirty = false;
  var dragged = null;

  function markDirty() {
    dirty = true;
  }

  form.addEventListener('input', markDirty);
  form.addEventListener('change', markDirty);
  form.addEventListener('submit', function () {
    dirty = false;
  });
  window.addEventListener('beforeunload', function (event) {
    if (dirty) {
      event.preventDefault();
      event.returnValue = '';
    }
  });

  function groups() {
    return Array.prototype.filter.call(list.children, function (child) {
      return child.hasAttribute('data-block');
    });
  }


  // Nothing follows position any more; what is left is telling the page it changed.
  function renumber() {
    markDirty();
  }

  function templateFor(type) {
    var found = null;
    document.querySelectorAll('template[data-block-template]').forEach(function (template) {
      if (template.getAttribute('data-block-template') === type) {
        found = template;
      }
    });
    return found;
  }

  form.addEventListener('click', function (event) {
    var button = event.target.closest && event.target.closest('[data-editor-action]');
    if (!button) {
      return;
    }
    var action = button.getAttribute('data-editor-action');

    if (action === 'add') {
      var template = templateFor(form.querySelector('[data-add-type]').value);
      if (!template) {
        return; // let the form submit; the server adds the block
      }
      event.preventDefault();
      var clone = template.content.cloneNode(true);
      var born = clone.querySelector('[data-block]');
      if (born) {
        nameGroup(born, mintKey());
      }
      list.appendChild(clone);
      renumber();
      var added = groups().pop();
      var field = added && added.querySelector('input:not([type="hidden"]), textarea, select');
      if (field) {
        field.focus();
      }
      return;
    }

    var group = button.closest('[data-block]');
    if (!group) {
      return;
    }
    event.preventDefault();
    if (action === 'remove') {
      group.remove();
    } else if (action === 'up' && group.previousElementSibling) {
      list.insertBefore(group, group.previousElementSibling);
    } else if (action === 'down' && group.nextElementSibling) {
      list.insertBefore(group.nextElementSibling, group);
    }
    renumber();
    if (action !== 'remove') {
      button.focus();
    }
  });

  // Drag and drop, started from a group's handle only, so text in fields stays selectable.
  list.addEventListener('mousedown', function (event) {
    var handle = event.target.closest && event.target.closest('[data-drag-handle]');
    if (handle) {
      handle.closest('[data-block]').setAttribute('draggable', 'true');
    }
  });
  document.addEventListener('mouseup', function () {
    if (!dragged) {
      groups().forEach(function (group) {
        group.removeAttribute('draggable');
      });
    }
  });
  list.addEventListener('dragstart', function (event) {
    dragged = event.target.closest && event.target.closest('[data-block]');
    if (!dragged) {
      return;
    }
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', '');
    dragged.classList.add('is-dragging');
  });
  list.addEventListener('dragover', function (event) {
    if (!dragged) {
      return;
    }
    event.preventDefault();
    var over = event.target.closest && event.target.closest('[data-block]');
    if (!over || over === dragged || over.parentNode !== list) {
      return;
    }
    var box = over.getBoundingClientRect();
    list.insertBefore(dragged, event.clientY > box.top + box.height / 2 ? over.nextElementSibling : over);
  });
  list.addEventListener('drop', function (event) {
    if (dragged) {
      event.preventDefault();
    }
  });
  list.addEventListener('dragend', function () {
    if (!dragged) {
      return;
    }
    dragged.classList.remove('is-dragging');
    dragged.removeAttribute('draggable');
    dragged = null;
    renumber();
  });
})();
