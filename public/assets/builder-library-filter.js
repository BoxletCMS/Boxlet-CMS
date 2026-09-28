/*
 * FINDING A BLOCK IN THE LIBRARY (PLAN.md D-104, closing O-15).
 *
 * One column of cards is a list you read; with thirteen it is a list you scroll past. A
 * filter and a row of shelves narrow the SAME cards — there is no second list, nothing is
 * fetched, and nothing is rebuilt. Without a script neither control is rendered and the
 * library is exactly what it has always been.
 *
 * WHAT IS SEARCHED is what the server wrote onto each card: its name, its shelf and the
 * line saying what it is for, already lower-cased. Searching the rendered text instead
 * would reach into the preview iframe, which holds the demo's own words and would match a
 * block for something it merely happens to say.
 */
(function () {
  var library = document.querySelector('[data-library]');
  if (!library) {
    return;
  }
  var filter = library.querySelector('[data-library-filter]');
  var groups = library.querySelectorAll('[data-library-group]');
  var none = library.querySelector('[data-library-none]');
  var cards = library.querySelectorAll('.library-card');
  if (!filter || cards.length === 0) {
    return;
  }
  var shelf = '';

  function show() {
    var wanted = filter.value.trim().toLowerCase();
    var seen = 0;
    cards.forEach(function (card) {
      var mine = shelf === '' || card.getAttribute('data-group') === shelf;
      var found = wanted === '' || (card.getAttribute('data-find') || '').indexOf(wanted) >= 0;
      card.hidden = !(mine && found);
      if (!card.hidden) {
        seen += 1;
      }
    });
    if (none) {
      none.hidden = seen > 0;
    }
  }

  filter.addEventListener('input', show);
  groups.forEach(function (button) {
    button.addEventListener('click', function () {
      shelf = button.getAttribute('data-library-group');
      groups.forEach(function (other) {
        other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
      });
      show();
    });
  });
})();
