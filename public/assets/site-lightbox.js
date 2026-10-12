/*
 * A PICTURE OPENED LARGER, OVER THE PAGE (PLAN.md D-236, the owner): a press on a picture whose
 * block has "Open larger" on. Loaded only on a page that has one. Without it, or in a browser
 * without <dialog>, the press opens the picture itself, which is what the link is.
 *
 * One <dialog>, made at the first press: the picture in the sizes the link names (AVIF, WebP,
 * the original's format), its caption, and in a gallery the others a press away: the arrows,
 * the arrow keys, a swipe; from the last back to the first. Escape, the close button or a press
 * beside the picture closes it, and the picture that opened it has the focus again.
 */
(function () {
  'use strict';

  var links = document.querySelectorAll('a[data-enlarge]');
  if (links.length === 0 || typeof HTMLDialogElement !== 'function') {
    return;
  }

  var SVG = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="';
  var dialog = null;
  var parts = {};
  var set = [];
  var at = 0;
  var opener = null;

  function words(link) {
    try {
      return JSON.parse(link.getAttribute('data-words') || '{}');
    } catch (e) {
      return {};
    }
  }

  function button(cls, label, path) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'lightbox-button ' + cls;
    b.setAttribute('aria-label', label || '');
    b.innerHTML = SVG + path + '"/></svg>';
    return b;
  }

  function build(said) {
    dialog = document.createElement('dialog');
    dialog.className = 'lightbox';
    var stage = document.createElement('div');
    stage.className = 'lightbox-stage';
    var bar = document.createElement('div');
    bar.className = 'lightbox-bar';
    parts.count = document.createElement('p');
    parts.count.className = 'lightbox-count';
    parts.count.setAttribute('aria-live', 'polite');
    parts.close = button('lightbox-close', said.close, 'M18 6 6 18M6 6l12 12');
    bar.appendChild(parts.count);
    bar.appendChild(parts.close);
    parts.figure = document.createElement('figure');
    parts.figure.className = 'lightbox-figure';
    parts.caption = document.createElement('p');
    parts.caption.className = 'lightbox-caption';
    parts.previous = button('lightbox-previous', said.previous, 'm15 18-6-6 6-6');
    parts.next = button('lightbox-next', said.next, 'm9 18 6-6-6-6');
    stage.appendChild(bar);
    stage.appendChild(parts.figure);
    stage.appendChild(parts.caption);
    dialog.appendChild(stage);
    dialog.appendChild(parts.previous);
    dialog.appendChild(parts.next);
    document.body.appendChild(dialog);

    parts.close.addEventListener('click', function () { dialog.close(); });
    parts.previous.addEventListener('click', function () { show(at - 1); });
    parts.next.addEventListener('click', function () { show(at + 1); });
    // A press beside the picture closes it; on the picture, its caption or a button it does not.
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog || event.target === stage || event.target === parts.figure) {
        dialog.close();
      }
    });
    dialog.addEventListener('keydown', function (event) {
      if (set.length > 1 && event.key === 'ArrowLeft') { show(at - 1); }
      if (set.length > 1 && event.key === 'ArrowRight') { show(at + 1); }
    });
    var startX = null;
    dialog.addEventListener('touchstart', function (event) {
      startX = event.touches.length === 1 ? event.touches[0].clientX : null;
    }, { passive: true });
    dialog.addEventListener('touchend', function (event) {
      if (startX === null || set.length < 2) { return; }
      var dx = event.changedTouches[0].clientX - startX;
      startX = null;
      if (Math.abs(dx) > 50) { show(dx < 0 ? at + 1 : at - 1); }
    });
    dialog.addEventListener('close', function () {
      document.documentElement.classList.remove('lightbox-open');
      parts.figure.textContent = '';
      if (opener) { opener.focus(); }
    });
  }

  function show(index) {
    at = (index + set.length) % set.length;
    var link = set[at];
    var small = link.querySelector('img');
    var picture = document.createElement('picture');
    [['avif', 'image/avif'], ['webp', 'image/webp']].forEach(function (format) {
      var url = link.getAttribute('data-enlarge-' + format[0]);
      if (url) {
        var source = document.createElement('source');
        source.type = format[1];
        source.srcset = url;
        picture.appendChild(source);
      }
    });
    var img = document.createElement('img');
    img.src = link.getAttribute('href');
    img.alt = small ? small.alt : '';
    img.width = Number(link.getAttribute('data-width')) || 0;
    img.height = Number(link.getAttribute('data-height')) || 0;
    picture.appendChild(img);
    parts.figure.textContent = '';
    parts.figure.appendChild(picture);
    parts.caption.textContent = link.getAttribute('data-caption') || '';
    var many = set.length > 1;
    parts.previous.hidden = !many;
    parts.next.hidden = !many;
    parts.count.textContent = many ? (at + 1) + ' / ' + set.length : '';
    var said = words(link).count;
    if (many && said) {
      parts.count.setAttribute('aria-label', said.replace(':n', String(at + 1)).replace(':total', String(set.length)));
    } else {
      parts.count.removeAttribute('aria-label');
    }
  }

  Array.prototype.forEach.call(links, function (link) {
    link.addEventListener('click', function (event) {
      // A press meant for a new tab or window goes where the link goes.
      if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }
      event.preventDefault();
      if (dialog === null) {
        build(words(link));
      }
      opener = link;
      var grid = link.closest('.gallery-grid');
      set = grid ? Array.prototype.slice.call(grid.querySelectorAll('a[data-enlarge]')) : [link];
      show(set.indexOf(link));
      document.documentElement.classList.add('lightbox-open');
      dialog.showModal();
      parts.close.focus();
    });
  });
})();
