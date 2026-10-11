/*
 * ALL CONTENT FOLLOWS THE PAGE (PLAN.md O-61). Words written on the canvas — bold, an
 * alignment, a line typed — reach the document at once, and the inspector's form for that block
 * was drawn again only once nobody was writing anywhere: until then it showed the old words,
 * and a letter typed into it was thrown away, the form being found out of date and drawn again.
 *
 * Now each change of the document is taken into the form where it can be, field by field: a
 * text input or a rich text editor whose words changed, unless it is the one being written in.
 * What cannot be (a picture chosen, an item added, a field in use) leaves the form as it was,
 * and builder-inspector.js draws it again as before.
 */
(function () {
  'use strict';

  var pb = window.pb;
  if (!pb) {
    return;
  }

  /** The path of a field's name inside its block's content: blocks[b3][items][0][body] → items, 0, body. */
  function pathOf(name, key) {
    var prefix = 'blocks[' + key + ']';
    if (name.indexOf(prefix) !== 0) {
      return null;
    }
    var parts = name.slice(prefix.length).match(/\[([^\]]*)\]/g);
    return parts ? parts.map(function (p) { return p.slice(1, -1); }) : null;
  }

  function at(content, path) {
    return path.reduce(function (value, step) { return value !== null && typeof value === 'object' ? value[step] : undefined; }, content);
  }

  /** Every leaf where two contents differ, as paths. */
  function differences(before, after, path, found) {
    if (before !== null && after !== null && typeof before === 'object' && typeof after === 'object') {
      Object.keys(Object.assign({}, before, after)).forEach(function (k) { differences(before[k], after[k], path.concat([k]), found); });
    } else if (before !== after) {
      found.push(path.join('.'));
    }
    return found;
  }

  /**
   * The document's content for this block taken into its form. True when every difference
   * from what the form was drawn from is now in it: the form holds the document again.
   */
  pb.followFields = function (form, key, drawnContent) {
    var block = pb.block(key);
    if (!block) {
      return false;
    }
    var changed = differences(JSON.parse(drawnContent), block.content, [], []);
    if (changed.length === 0) {
      return true;
    }
    var taken = {};
    Array.prototype.forEach.call(form.querySelectorAll('input[name], textarea[name]'), function (input) {
      var path = pathOf(input.name, key);
      var value = path ? at(block.content, path) : undefined;
      if (!path || typeof value !== 'string' || changed.indexOf(path.join('.')) < 0) {
        return;
      }
      if (input.boxletSetValue) {
        if (input.boxletIsFocused()) { return; }
        input.boxletSetValue(value);
      } else if (input.type === 'hidden' || document.activeElement === input) {
        return;
      } else {
        input.value = value;
      }
      taken[path.join('.')] = true;
    });
    return changed.every(function (p) { return taken[p]; });
  };
})();
