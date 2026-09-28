/*
 * ONLY THE BLOCKS THAT CHANGED SEND THEIR FIELDS (PLAN.md D-081).
 *
 * Every block of every page went into every save. One Columns block is around sixty
 * fields, so ten blocks pass PHP's default max_input_vars of 1000 and the save is refused.
 * _end catches that rather than letting PHP silently drop half the page — but the refusal
 * arrives after an hour of work, and "your page is too big to save" is not an answer.
 *
 * WHICH BLOCKS CHANGED IS MEASURED, NOT INFERRED FROM EVENTS. The first design marked a
 * group dirty on input, change and click inside it. That is a guess about which gestures
 * mean "edited", and every gesture it fails to think of — the rich text toolbar, which
 * writes its hidden input directly and fires nothing; a repeater item dragged; something
 * added later — loses the author's work silently. So each group is fingerprinted from the
 * values it would submit, once when the page loads and again when it is saved. A group
 * whose fingerprint is unchanged cannot have changed, because the fingerprint IS what the
 * submit would carry.
 *
 * THE ERROR HAS A DIRECTION: send too much rather than too little. Sending a block that
 * did not change is waste; not sending one that did is lost work. Anything uncertain —
 * a block with no id, a group that appeared after the baseline was taken — submits whole.
 *
 * WHAT AN UNCHANGED BLOCK SENDS is its id and the marker _unchanged. The server restores
 * its content, style and layout from storage, so what it saves is exactly what a whole
 * submit would have saved; the block's PLACE still comes from where its skeleton sits in
 * the request, so reordering needs no fields at all.
 *
 * The fields are DISABLED rather than removed: a disabled field is not submitted, and if
 * anything stops the submit the form still holds every value.
 *
 * Without this script the form submits whole, exactly as before. _end and the field count
 * stay where they are — they guard the wall, and this moves it further away.
 */
(function () {
  'use strict';

  var api = window.boxletBuilder;
  /*
   * A BASELINE IS ONLY MEANINGFUL IF THE SCREEN CAME FROM STORAGE. After a save that did
   * not validate, the form holds what was SUBMITTED — including edits to blocks that were
   * perfectly valid and were still not written, because the save was refused as a whole.
   * Fingerprinting that screen would call those blocks unchanged, and the server would
   * restore them from storage: the author fixes the one error, saves, and their other
   * block quietly rolls back. So the server says whether these groups are the stored page,
   * and when it does not, this whole file stands down and the form submits as it always did.
   */
  if (!api || !api.form.hasAttribute('data-blocks-stored')) {
    return;
  }

  /* Baselines by BLOCK ID, not by element: undo replaces the field groups wholesale, and
     a group edited and then undone back to what it was must read as unchanged again. */
  var baseline = {};

  /* Named fields only, because a field without a name submits nothing — and because that
     is what makes the baseline survive TipTap. Raising an editor moves the name off the
     textarea onto a hidden input beside it, so a fingerprint that counted every field
     would change the moment the editor loaded and report every rich text block as edited. */
  function fields(group) {
    return group.querySelectorAll('input[name], textarea[name], select[name]');
  }

  /**
   * What this group would submit, as one string.
   *
   * Values only, never names: renumber() rewrites every name on a reorder, and a block
   * that merely moved has not changed.
   */
  function fingerprint(group) {
    var parts = [];
    Array.prototype.forEach.call(fields(group), function (field) {
      var value = field.type === 'checkbox' || field.type === 'radio'
        ? (field.checked ? '1' : '')
        : field.value;
      // Each value carries its own length rather than being joined on a separator: no
      // character is safe to separate on when the values are whatever an author typed,
      // and two fields of "a|b" and "" must not read the same as "a" and "b|".
      parts.push(value.length + ':' + value);
    });

    return parts.join('');
  }

  function idInput(group) {
    var input = group.querySelector('input[name$="[id]"]');

    return input && /^[0-9]+$/.test(input.value) ? input : null;
  }

  function remember() {
    api.groupNodes().forEach(function (group) {
      var input = idInput(group);
      if (input !== null && !Object.prototype.hasOwnProperty.call(baseline, input.value)) {
        baseline[input.value] = fingerprint(group);
      }
    });
  }

  /**
   * Leave the block's id and add the marker; silence everything else.
   *
   * The id input is kept rather than rebuilt so the skeleton is addressed by the very name
   * the rest of the group was using — the index renumber() last wrote — instead of one
   * counted again here and able to disagree with it.
   */
  function skeleton(group, input) {
    Array.prototype.forEach.call(fields(group), function (field) {
      field.disabled = field !== input;
    });
    var marker = document.createElement('input');
    marker.type = 'hidden';
    marker.name = input.name.replace(/\[id\]$/, '[_unchanged]');
    marker.value = '1';
    group.appendChild(marker);
  }

  api.form.addEventListener('submit', function () {
    api.groupNodes().forEach(function (group) {
      var input = idInput(group);
      if (input === null || !Object.prototype.hasOwnProperty.call(baseline, input.value)) {
        return;
      }
      if (fingerprint(group) === baseline[input.value]) {
        skeleton(group, input);
      }
    });
  });

  remember();
})();
