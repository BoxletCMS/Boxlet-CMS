/*
 * THE TREND CHART, READ POINT BY POINT (PLAN.md D-266, the owner): a line follows the pointer to
 * the nearest day, with a dot on each line and a small window with that day's visitors and views,
 * as Plausible and most statistics draw it. Focused, the left and right arrow keys move it, and
 * Escape puts it away. Without this script each day's figures are in a tooltip of its own and in
 * the table under the chart (App\Modules\Stats\Chart).
 *
 * The points come from the server in the same 100 by 100 box the lines are drawn in, so a
 * point's x and y are percentages of the plot. Text is set as text, never as markup.
 */
(function () {
  'use strict';

  Array.prototype.forEach.call(document.querySelectorAll('[data-chart-points]'), function (area) {
    var points;
    var words;
    try {
      points = JSON.parse(area.getAttribute('data-chart-points') || '[]');
      words = JSON.parse(area.getAttribute('data-chart-words') || '{}');
    } catch (e) {
      return;
    }
    if (!points.length) {
      return;
    }
    var cursor = area.querySelector('.stats-chart-cursor');
    var tip = area.querySelector('.stats-chart-tip');
    var dots = {
      visitors: area.querySelector('.stats-chart-dot-visitors'),
      views: area.querySelector('.stats-chart-dot-views'),
    };
    if (!cursor || !tip || !dots.visitors || !dots.views) {
      return;
    }
    // The window replaces the browser's own tooltips, which would stand over it.
    Array.prototype.forEach.call(area.querySelectorAll('title'), function (title) {
      title.parentNode.removeChild(title);
    });
    area.classList.add('stats-chart-live');
    area.tabIndex = 0;
    area.setAttribute('role', 'group');
    area.setAttribute('aria-label', words.label || '');

    // The window: the day, then a row for each line.
    var day = document.createElement('p');
    day.className = 'stats-chart-tip-day';
    tip.appendChild(day);
    var values = {};
    ['visitors', 'views'].forEach(function (key) {
      var row = document.createElement('p');
      row.className = 'stats-chart-tip-row stats-chart-tip-' + key;
      var name = document.createElement('span');
      name.textContent = words[key] || key;
      values[key] = document.createElement('strong');
      row.appendChild(name);
      row.appendChild(values[key]);
      tip.appendChild(row);
    });
    var live = document.createElement('span');
    live.className = 'visually-hidden';
    live.setAttribute('aria-live', 'polite');
    area.appendChild(live);

    var current = -1;

    function show(i) {
      var point = points[i];
      current = i;
      cursor.style.left = point.x + '%';
      dots.visitors.style.left = point.x + '%';
      dots.visitors.style.top = point.visitorsY + '%';
      dots.views.style.left = point.x + '%';
      dots.views.style.top = point.viewsY + '%';
      day.textContent = point.day;
      values.visitors.textContent = point.visitors;
      values.views.textContent = point.views;
      // Beside the line, on whichever side has the room.
      tip.style.left = point.x + '%';
      tip.classList.toggle('stats-chart-tip-before', point.x > 55);
      cursor.hidden = false;
      dots.visitors.hidden = false;
      dots.views.hidden = false;
      tip.hidden = false;
    }

    function hide() {
      current = -1;
      cursor.hidden = true;
      dots.visitors.hidden = true;
      dots.views.hidden = true;
      tip.hidden = true;
    }

    function nearest(clientX) {
      var box = area.getBoundingClientRect();
      var at = box.width > 0 ? (clientX - box.left) / box.width * 100 : 0;
      var best = 0;
      points.forEach(function (point, i) {
        if (Math.abs(point.x - at) < Math.abs(points[best].x - at)) {
          best = i;
        }
      });
      return best;
    }

    area.addEventListener('pointermove', function (event) {
      var i = nearest(event.clientX);
      if (i !== current) {
        show(i);
      }
    });
    area.addEventListener('pointerdown', function (event) {
      show(nearest(event.clientX));
    });
    area.addEventListener('pointerleave', function (event) {
      if (event.pointerType === 'mouse') {
        hide();
      }
    });
    area.addEventListener('keydown', function (event) {
      var step = event.key === 'ArrowRight' ? 1 : (event.key === 'ArrowLeft' ? -1 : 0);
      if (event.key === 'Escape') {
        hide();
        return;
      }
      if (step === 0) {
        return;
      }
      event.preventDefault();
      var i = current === -1 ? (step > 0 ? 0 : points.length - 1) : Math.min(points.length - 1, Math.max(0, current + step));
      show(i);
      live.textContent = points[i].day + ': ' + (words.visitors || '') + ' ' + points[i].visitors + ', ' + (words.views || '') + ' ' + points[i].views;
    });
    area.addEventListener('blur', hide);
  });
})();
