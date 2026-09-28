/*
 * The site's navigation on a narrow screen, and its submenus (PLAN.md D-032, D-036).
 *
 * The only script a visitor's page loads, and all of it optional. Without it the
 * navigation wraps openly under the logo and every submenu is listed under its parent:
 * nothing is out of reach, and no button is drawn that would do nothing. With it, the
 * buttons the header renders hidden are shown, the navigation folds under one Menu button
 * on a phone, and each submenu opens from its own button — never from hover alone.
 */
(function () {
  'use strict';

  var header = document.querySelector('[data-site-header]');
  if (!header) {
    return;
  }
  var toggle = header.querySelector('[data-site-nav-toggle]');
  var more = header.querySelectorAll('[data-site-nav-more]');
  if (!toggle && more.length === 0) {
    return;
  }

  header.classList.add('nav-ready');

  if (toggle) {
    toggle.hidden = false;
    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      header.classList.toggle('nav-open', open);
    });
  }

  function close(button) {
    button.setAttribute('aria-expanded', 'false');
    button.parentElement.classList.remove('is-open');
    button.parentElement.classList.remove('is-hover');
  }

  function open(button) {
    // One submenu at a time: two dropdowns over each other hide each other.
    Array.prototype.forEach.call(more, close);
    button.setAttribute('aria-expanded', 'true');
    button.parentElement.classList.add('is-open');
  }

  Array.prototype.forEach.call(more, function (button) {
    button.hidden = false;
    button.addEventListener('click', function () {
      /*
       * A click on a submenu that a resting mouse has already opened KEEPS it open, and
       * pins it: the mouse arrived before the click, so the panel was open when the finger
       * pressed, and a press that shut it read as a button that does the opposite of what
       * it says. Measured in the browser suite: the driver moves the mouse to the button
       * and then clicks, and the click closed what the move had opened. Pinned, the panel
       * no longer closes when the mouse leaves; the next click closes it.
       */
      if (button.getAttribute('aria-expanded') === 'true' && !button.parentElement.classList.contains('is-hover')) {
        close(button);
      } else {
        open(button);
        button.parentElement.classList.remove('is-hover');
      }
    });
    /*
     * A MOUSE RESTING ON THE PARENT OPENS IT TOO (PLAN.md D-114), and leaving the parent —
     * which holds the panel, so leaving means leaving both — closes it. A mouse and nothing
     * else: a finger's tap arrives as a pointer event too, and a submenu that opened under a
     * tap on the parent link would open and navigate in the same instant. The button stays
     * for the keyboard and for a finger. Asked of the event rather than of a media query,
     * because the same device can have both, and the pointer in use is the one that counts.
     */
    var parent = button.parentElement;
    parent.addEventListener('pointerenter', function (event) {
      if (event.pointerType === 'mouse' && button.getAttribute('aria-expanded') !== 'true') {
        open(button);
        parent.classList.add('is-hover');
      }
    });
    parent.addEventListener('pointerleave', function (event) {
      if (event.pointerType === 'mouse' && parent.classList.contains('is-hover')) {
        close(button);
      }
    });
  });

  // Escape closes whatever is open and returns focus to the button that opened it.
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') {
      return;
    }
    Array.prototype.forEach.call(more, function (button) {
      if (button.getAttribute('aria-expanded') === 'true') {
        close(button);
        button.focus();
      }
    });
    if (toggle && toggle.getAttribute('aria-expanded') === 'true') {
      toggle.setAttribute('aria-expanded', 'false');
      header.classList.remove('nav-open');
      toggle.focus();
    }
  });

  // A click anywhere else closes an open submenu.
  document.addEventListener('click', function (event) {
    if (!event.target.closest || !event.target.closest('.site-nav li')) {
      Array.prototype.forEach.call(more, close);
    }
  });
})();
