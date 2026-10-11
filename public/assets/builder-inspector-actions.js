/*
 * WHAT THE INSPECTOR'S BUTTONS DO (PLAN.md D-175), apart from its controls: a ↺ gives one value
 * back to the character, "Reset to character" all of them, and the actions under it select,
 * duplicate, delete or keep a band as a pattern. A hint may take the owner to a block's own
 * option (D-187). Split from builder-inspector.js, which draws the panel and writes its
 * controls into the document, when the two passed the size limit.
 */
(function () {
  'use strict';

  var pb = window.pb;
  var panel = document.querySelector('[data-pb-inspector]');
  if (!pb || !panel) {
    return;
  }
  // What a band's "Reset to character" gives back: every style the character composes.
  var COMPOSED = ['surface', 'pad_top', 'pad_bottom', 'min_height', 'v_align', 'width', 'align', 'divider', 'animation'];

  function selectedSection() {
    var sel = pb.selection;
    return sel ? pb.section(sel.kind === 'section' ? sel.key : (pb.block(sel.key) || {}).section) : null;
  }

  panel.addEventListener('click', function (event) {
    var reset = event.target.closest('button[name="reset"]');
    var all = event.target.closest('[data-reset-all]');
    var action = event.target.closest('[data-action]');
    var sel = pb.selection;
    if (!sel || !(reset || all || action)) {
      return;
    }
    event.preventDefault();
    var section = selectedSection();
    var block = sel.kind === 'block' ? pb.block(sel.key) : null;
    if (reset) {
      var path = reset.value.split(':')[0].split('.');
      pb.change(function () {
        if (path[0] === 's') { if (path[1] === 'style') { section.style[path[2]] = ''; } else { section[path[1]] = ''; } }
        if (path[0] === 'b' && block) { if (path[1] === 'options') { block.options[path[2]] = ''; } else { block.layout = reset.value.split(':')[1]; } }
      }, { sections: [section.key] });
      pb.emit('inspect');
    } else if (all) {
      pb.change(function () {
        if (all.getAttribute('data-reset-all') === 'section') {
          COMPOSED.forEach(function (k) { section.style[k] = ''; });
        } else if (block) {
          Object.keys(block.options || {}).forEach(function (k) { block.options[k] = ''; });
          block.layout = all.getAttribute('data-layout');
        }
      }, { sections: [section.key] });
      pb.emit('inspect');
    } else {
      var name = action.getAttribute('data-action');
      var acts = {
        'select-section': function () { pb.select('section', section.key); },
        // To a block's own option, from a hint that names it (D-187): selected, and the option
        // brought into view with its choice focused.
        'select-block': function () {
          var key = action.getAttribute('data-key');
          var focus = action.getAttribute('data-focus');
          var once = function (now) {
            if (!now || now.key !== key) { return; }
            pb.off('inspected', once);
            var choice = panel.querySelector('[name$="[options][' + focus + ']"]:checked') || panel.querySelector('[name$="[options][' + focus + ']"]');
            if (choice) { choice.scrollIntoView({ block: 'center' }); choice.focus(); }
          };
          pb.on('inspected', once);
          pb.select('block', key);
        },
        duplicate: function () { if (block) { pb.duplicateBlock(block.key); } else { pb.duplicateSection(section.key); } },
        delete: function () { if (block) { pb.deleteBlock(block.key); } else { pb.deleteSection(section.key); } },
        pattern: function () { pb.savePattern(section.key); },
      };
      if (acts[name]) { acts[name](); }
    }
  });
})();
