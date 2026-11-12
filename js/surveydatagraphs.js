/**
 * Renders every SurveyDataGraphs canvas on the page once.
 * The chart configuration is read from the data-sdg-chart attribute (JSON).
 */
(function () {
  'use strict';

  function init() {
    if (typeof Chart === 'undefined') {
      return;
    }
    document.querySelectorAll('canvas[data-sdg-chart]').forEach(function (canvas) {
      if (canvas.dataset.sdgChartReady === '1') {
        return;
      }
      canvas.dataset.sdgChartReady = '1';
      new Chart(canvas, JSON.parse(canvas.dataset.sdgChart));
    });
  }

  window.il = window.il || {};
  window.il.SurveyDataGraphs = { init: init };
})();
