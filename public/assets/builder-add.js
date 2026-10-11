/*
 * THE RAIL'S ADD TAB (PLAN.md D-175, README 4.2): blocks and patterns as a list — an icon, a
 * name and a line about what it is for, no live pictures — with a search, and a line saying
 * where the next one goes: after the selected band, else at the end.
 */
(function () {
  'use strict';

  var pb = window.pb;
  var panel = document.querySelector('[data-pb-panel="add"]');
  if (!pb || !panel) {
    return;
  }
  var search = panel.querySelector('[data-pb-add-search]');
  var where = panel.querySelector('[data-pb-add-where]');
  var none = panel.querySelector('[data-pb-add-none]');
  var kind = 'blocks';

  function filter() {
    var words = search.value.trim().toLowerCase();
    var shown = 0;
    Array.prototype.forEach.call(panel.querySelectorAll('[data-pb-add-list]'), function (list) {
      list.hidden = list.getAttribute('data-pb-add-list') !== kind;
    });
    Array.prototype.forEach.call(panel.querySelectorAll('[data-pb-add-list="' + kind + '"] [data-words]'), function (item) {
      var hit = words === '' || item.getAttribute('data-words').indexOf(words) >= 0;
      item.parentElement.hidden = !hit;
      shown += hit ? 1 : 0;
    });
    // A shelf with nothing on it after a search goes, its name with it.
    Array.prototype.forEach.call(panel.querySelectorAll('[data-pb-shelf]'), function (shelf) {
      shelf.hidden = shelf.querySelector('li:not([hidden])') === null;
    });
    none.hidden = shown > 0;
  }

  function placeLine() {
    var at = pb.insertAt();
    where.textContent = at < pb.doc.sections.length
      ? pb.t('add.where_after', { section: pb.sectionName(pb.doc.sections[at - 1].key) })
      : pb.t('add.where_end');
  }

  panel.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-pb-add-kind]');
    if (tab) {
      kind = tab.getAttribute('data-pb-add-kind');
      Array.prototype.forEach.call(panel.querySelectorAll('[data-pb-add-kind]'), function (b) { b.setAttribute('aria-pressed', b === tab ? 'true' : 'false'); });
      filter();
      return;
    }
    var block = event.target.closest('[data-add-block]');
    if (block) {
      pb.addBlock(block.getAttribute('data-add-block'), pb.insertAt());
      return;
    }
    var pattern = event.target.closest('[data-add-pattern]');
    if (pattern) {
      pb.addPattern(pattern.getAttribute('data-add-pattern'), pb.insertAt());
    }
  });
  search.addEventListener('input', filter);

  // A pattern just kept joins "My patterns" at once.
  pb.on('pattern', function (saved) {
    var list = panel.querySelector('[data-pb-patterns-mine]');
    var li = document.createElement('li');
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'pb-add-item';
    b.setAttribute('data-add-pattern', saved.ref);
    b.setAttribute('data-words', saved.name.toLowerCase());
    var name = document.createElement('span');
    name.className = 'pb-add-name';
    name.textContent = saved.name;
    b.appendChild(name);
    li.appendChild(b);
    list.insertBefore(li, list.firstChild);
    var empty = panel.querySelector('[data-pb-mine-none]');
    if (empty) { empty.remove(); }
  });

  pb.on('select', placeLine);
  pb.on('change', placeLine);
  placeLine();
  filter();
})();
