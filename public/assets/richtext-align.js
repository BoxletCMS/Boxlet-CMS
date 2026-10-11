/*
 * A PARAGRAPH'S OR HEADING'S ALIGNMENT (PLAN.md D-217, the owner): left, centred or right, in
 * every rich text field. Left is no alignment of its own: the words follow their block and
 * section. Centred and right are a class, text-center or text-right, which is all the server's
 * whitelist keeps of an alignment (RichText::ALIGNMENTS); TipTap's own text-align extension
 * writes a style attribute, which it would throw away.
 *
 * richtext.js adds this to the schema and its commands, for the inspector's editor and the
 * canvas's alike.
 */
(function () {
  'use strict';

  var TYPES = ['paragraph', 'heading'];
  var PATTERN = /(?:^|\s)text-(center|right)(?:\s|$)/;

  function extension(tiptap) {
    if (!tiptap.Extension) {
      return null;
    }
    return tiptap.Extension.create({
      name: 'boxletAlign',
      addGlobalAttributes: function () {
        return [{
          types: TYPES,
          attributes: {
            align: {
              default: null,
              parseHTML: function (element) {
                var found = PATTERN.exec(element.getAttribute('class') || '');
                return found ? found[1] : null;
              },
              renderHTML: function (attributes) {
                return attributes.align ? { class: 'text-' + attributes.align } : {};
              },
            },
          },
        }];
      },
      addCommands: function () {
        return {
          // 'left' takes the alignment away: the block's and the section's are what remain.
          // Only a paragraph or heading of the text's own, not one inside a list or a quote:
          // the stored shape has no paragraph there (BlockShape), so the alignment would be
          // shown and then lost on save.
          setAlign: function (value) {
            return function (props) {
              var align = value === 'center' || value === 'right' ? value : null;
              var state = props.state;
              var changed = false;
              state.doc.nodesBetween(state.selection.from, state.selection.to, function (node, pos, parent) {
                if (parent === state.doc && TYPES.indexOf(node.type.name) >= 0 && node.attrs.align !== align) {
                  if (props.dispatch) {
                    props.tr.setNodeMarkup(pos, undefined, Object.assign({}, node.attrs, { align: align }));
                  }
                  changed = true;
                }
                return false;
              });
              return changed;
            };
          },
        };
      },
    });
  }

  /** Lit when the paragraph or heading the caret is in has this alignment; left when it has none. */
  function active(editor, value) {
    var align = editor.getAttributes('paragraph').align || editor.getAttributes('heading').align || null;
    return value === 'left' ? align === null : align === value;
  }

  window.boxletRichTextAlign = { extension: extension, active: active };
})();
