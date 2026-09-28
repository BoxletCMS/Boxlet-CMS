/*
 * HINTS ON DEMAND (PLAN.md D-078, D-087).
 *
 * A line of explanation under every control is what teaches a screen, and it is also what
 * fills a narrow column. The owner asked for the space back, first on the Appearance screen
 * and then in the page editor's panel, so they are off until asked for.
 *
 * ONE IMPLEMENTATION, TWO SCREENS, which is what turned this from a corner of the Appearance
 * screen into a file of its own: any element that carries data-hints-root and contains a
 * data-hints-toggle gets the behaviour, and its value is the name the preference is stored
 * under. A third screen needs the attribute and nothing else.
 *
 * SCOPED BY THAT ATTRIBUTE. The rest of the admin keeps its hints; it is these two columns
 * that are short of room, and a preference set in one must not quietly empty the other —
 * which is why the storage key carries the root's name.
 *
 * OPTIONAL, LIKE EVERY SCRIPT HERE. Without it the hints show and the button is not there:
 * the state that explains itself is the safe one, and a toggle that cannot toggle is worse
 * than no toggle at all.
 *
 * REMEMBERED IN THIS BROWSER AND NOWHERE ELSE. It is how one person likes to work, not a
 * decision about the site, so it is not a setting and it never reaches the database. Every
 * read and write is wrapped, because storage throws in a private window rather than
 * returning nothing.
 */
(function () {
  'use strict';

  function setUp(root) {
    var name = root.getAttribute('data-hints-root');
    var button = root.querySelector('[data-hints-toggle]');
    if (!name || !button) {
      return;
    }
    var key = 'boxlet.hints.' + name;

    function remembered() {
      try {
        return window.localStorage.getItem(key);
      } catch (e) {
        return null;
      }
    }

    function remember(value) {
      try {
        window.localStorage.setItem(key, value);
      } catch (e) {
        // A browser that will not store it still gets the screen it asked for, for this visit.
      }
    }

    function draw(showing) {
      root.setAttribute('data-hints', showing ? 'on' : 'off');
      button.setAttribute('aria-pressed', showing ? 'true' : 'false');
      button.textContent = button.getAttribute(showing ? 'data-hide' : 'data-show');
    }

    // OFF is the default, which is what the owner asked for; anything stored wins over it.
    var showing = remembered() === 'on';
    button.hidden = false;
    draw(showing);

    button.addEventListener('click', function () {
      showing = !showing;
      remember(showing ? 'on' : 'off');
      draw(showing);
    });
  }

  document.querySelectorAll('[data-hints-root]').forEach(setUp);
})();
