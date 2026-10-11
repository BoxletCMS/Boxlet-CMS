/*
 * NAVIGATION'S LANGUAGE (PLAN.md D-182): chosen once at the top, and every card shows that
 * language's words. Without this file every language stands in turn, under its name.
 */
(function () {
  'use strict';

  var switcher = document.querySelector('[data-navigation-language]');
  if (!switcher) {
    return;
  }
  function show() {
    var chosen = switcher.querySelector('input:checked');
    var code = chosen ? chosen.value : '';
    Array.prototype.forEach.call(document.querySelectorAll('.words-for[data-locale]'), function (panel) {
      panel.hidden = panel.getAttribute('data-locale') !== code;
    });
    document.querySelector('#navigation-form').classList.add('one-language');
  }
  switcher.addEventListener('change', show);
  show();
})();
