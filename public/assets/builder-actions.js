/*
 * WHAT CAN BE DONE TO THE PAGE (PLAN.md D-175): adding a block or a pattern, moving, copying
 * and deleting a band or a block, a block's layout, keeping a band as a pattern, and what is
 * selected. The canvas's toolbars, the tree, the Add tab and the inspector all call these, so
 * an action means the same thing wherever it is pressed — one change() each, one undo step.
 */
(function () {
  'use strict';

  var pb = window.pb;
  if (!pb) {
    return;
  }
  pb.selection = null;

  /**
   * Selects a block or a band, or nothing (kind null). Selecting what is already selected does
   * nothing: drawing the inspector again would take the field from under the hand typing in it,
   * which a click on the block being edited did (D-175, found by 28-stale).
   */
  pb.select = function (kind, key) {
    var now = pb.selection;
    if ((now === null && !kind) || (now && kind === now.kind && key === now.key)) {
      return;
    }
    pb.selection = kind ? { kind: kind, key: key } : null;
    pb.emit('select', pb.selection);
  };

  /** Where something added goes: after the selected band (or the selected block's band), else at the end. */
  pb.insertAt = function () {
    var at = pb.doc.sections.length;
    if (pb.selection) {
      var key = pb.selection.kind === 'section' ? pb.selection.key : (pb.block(pb.selection.key) || {}).section;
      var index = pb.sectionIndex(key);
      if (index >= 0) {
        at = index + 1;
      }
    }
    return at;
  };

  function newSection(key) {
    return { key: key, id: null, layout: 'one', stack: 'stack', style: {} };
  }

  /** A new block of `type`, in a band of its own at `at` (a place among the sections). */
  pb.addBlock = function (type, at) {
    var item = pb.libraryItem(type);
    if (!item) {
      return;
    }
    var sectionKey = pb.mint('m');
    var section = newSection(sectionKey);
    var blockKey;
    pb.change(function (doc) {
      doc.sections.splice(at, 0, section);
      blockKey = pb.mint('n');
      doc.blocks.push({ key: blockKey, id: null, type: type, content: pb.copy(item.fresh), style: {}, options: {}, layout: item.layout, section: sectionKey, column: 0 });
    }, { sections: [sectionKey], structure: true });
    pb.select('block', blockKey);
  };

  /**
   * A new block of `type` at the end of a column of a band that already stands (D-099, D-101:
   * the "+ Block" in a column). After the band's last block in the document, so the page's
   * order holds; the band is drawn again with it there.
   */
  pb.addBlockInto = function (type, sectionKey, column) {
    var item = pb.libraryItem(type);
    if (!item || !pb.section(sectionKey)) {
      return;
    }
    var blockKey;
    pb.change(function (doc) {
      blockKey = pb.mint('n');
      var last = -1;
      doc.blocks.forEach(function (b, i) { if (b.section === sectionKey) { last = i; } });
      doc.blocks.splice(last + 1, 0, { key: blockKey, id: null, type: type, content: pb.copy(item.fresh), style: {}, options: {}, layout: item.layout, section: sectionKey, column: column });
    }, { sections: [sectionKey], structure: true });
    pb.select('block', blockKey);
  };

  /** A pattern (`user:3`, `set:hero-button`), as a band of its own at `at`. */
  pb.addPattern = function (ref, at) {
    var url = pb.data.endpoints.patterns + '?ref=' + encodeURIComponent(ref) + '&page=' + pb.data.page.id;
    return pb.api(url, undefined, 'GET').then(function (answer) {
      if (answer.status !== 200 || !answer.json.pattern) {
        return;
      }
      var pattern = answer.json.pattern;
      var sectionKey;
      pb.change(function (doc) {
        sectionKey = pb.mint('m');
        doc.sections.splice(at, 0, { key: sectionKey, id: null, layout: pattern.section.layout, stack: pattern.section.stack || 'stack', style: pattern.section.style || {} });
        pattern.blocks.forEach(function (b) {
          doc.blocks.push({ key: pb.mint('n'), id: null, type: b.type, content: b.content, style: {}, options: b.options || {}, layout: b.layout, section: sectionKey, column: b.column || 0 });
        });
      }, { sections: [sectionKey], structure: true });
      pb.select('section', sectionKey);
    });
  };

  pb.moveSection = function (key, delta) {
    var from = pb.sectionIndex(key);
    var to = from + delta;
    if (from < 0 || to < 0 || to >= pb.doc.sections.length) {
      return;
    }
    pb.change(function (doc) {
      doc.sections.splice(to, 0, doc.sections.splice(from, 1)[0]);
    }, { structure: true });
  };

  /** A band copied below itself: new keys, no ids, its anchor left behind (a place is one). */
  pb.duplicateSection = function (key) {
    var source = pb.section(key);
    if (!source) {
      return;
    }
    var copyKey;
    pb.change(function (doc) {
      copyKey = pb.mint('m');
      var copy = pb.copy(source);
      copy.key = copyKey;
      copy.id = null;
      copy.style.anchor = '';
      doc.sections.splice(pb.sectionIndex(key) + 1, 0, copy);
      pb.blocksIn(key).forEach(function (b) {
        var twin = pb.copy(b);
        twin.key = pb.mint('n');
        twin.id = null;
        twin.section = copyKey;
        doc.blocks.push(twin);
      });
    }, { sections: [copyKey], structure: true });
    pb.select('section', copyKey);
  };

  pb.deleteSection = function (key) {
    if (!window.confirm(pb.t('delete_confirm'))) {
      return;
    }
    pb.change(function (doc) {
      doc.sections = doc.sections.filter(function (s) { return s.key !== key; });
      doc.blocks = doc.blocks.filter(function (b) { return b.section !== key; });
    }, { structure: true });
    pb.select(null);
  };

  /** A block up or down its column; at the end of one, the band it is alone in moves instead. */
  pb.moveBlock = function (key, delta) {
    var block = pb.block(key);
    if (!block) {
      return;
    }
    var column = pb.blocksIn(block.section).filter(function (b) { return b.column === block.column; });
    var at = column.indexOf(block);
    var other = column[at + delta];
    if (!other) {
      if (pb.blocksIn(block.section).length === 1) {
        pb.moveSection(block.section, delta);
      }
      return;
    }
    pb.change(function (doc) {
      var i = doc.blocks.indexOf(block);
      var j = doc.blocks.indexOf(other);
      doc.blocks[i] = other;
      doc.blocks[j] = block;
    }, { sections: [block.section] });
  };

  pb.duplicateBlock = function (key) {
    var block = pb.block(key);
    if (!block) {
      return;
    }
    var twinKey;
    pb.change(function (doc) {
      var twin = pb.copy(block);
      twinKey = pb.mint('n');
      twin.key = twinKey;
      twin.id = null;
      doc.blocks.splice(doc.blocks.indexOf(block) + 1, 0, twin);
    }, { sections: [block.section] });
    pb.select('block', twinKey);
  };

  /** A block deleted; a band it leaves empty goes with it. */
  pb.deleteBlock = function (key) {
    var block = pb.block(key);
    if (!block || !window.confirm(pb.t('delete_confirm'))) {
      return;
    }
    var empty = pb.blocksIn(block.section).length === 1;
    pb.change(function (doc) {
      doc.blocks = doc.blocks.filter(function (b) { return b.key !== key; });
      if (empty) {
        doc.sections = doc.sections.filter(function (s) { return s.key !== block.section; });
      }
    }, empty ? { structure: true } : { sections: [block.section] });
    pb.select(null);
  };

  pb.setLayout = function (key, layout) {
    var block = pb.block(key);
    if (!block || block.layout === layout) {
      return;
    }
    pb.change(function () { block.layout = layout; }, { sections: [block.section] });
    pb.emit('inspect');
  };

  /** A band kept as one of the owner's patterns, under the name asked for. */
  pb.savePattern = function (key) {
    var name = window.prompt(pb.t('pattern_name'), pb.sectionName(key));
    if (!name) {
      return;
    }
    pb.api(pb.data.endpoints.patterns, { name: name, section: pb.section(key), blocks: pb.blocksIn(key) }).then(function (answer) {
      if (answer.status === 200) {
        pb.emit('pattern', answer.json);
        pb.emit('notice', pb.t('pattern_saved', { name: answer.json.name }));
      }
    });
  };
})();
