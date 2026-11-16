/**
 * Renders every SurveyDataGraphs canvas on the page.
 * The chart configuration is read from the data-sdg-chart attribute (JSON).
 *
 * The page editor replaces the page content via AJAX (copy, cut, paste) and
 * the core offers no hook for page component plugins afterwards. A
 * MutationObserver therefore draws canvases that are added later.
 */
(function () {
  'use strict';

  if (window.il && window.il.SurveyDataGraphs) {
    return;
  }

  var scheduled = false;

  function destroyDetachedCharts() {
    Object.values(Chart.instances).forEach(function (chart) {
      if (!chart.canvas || !chart.canvas.isConnected) {
        chart.destroy();
      }
    });
  }

  function init() {
    if (typeof Chart === 'undefined') {
      return;
    }
    destroyDetachedCharts();
    document.querySelectorAll('canvas[data-sdg-chart]').forEach(function (canvas) {
      if (Chart.getChart(canvas)) {
        return;
      }
      new Chart(canvas, JSON.parse(canvas.dataset.sdgChart));
    });
  }

  function schedule() {
    if (scheduled) {
      return;
    }
    scheduled = true;
    window.requestAnimationFrame(function () {
      scheduled = false;
      init();
    });
  }

  function containsChart(node) {
    return node.nodeType === Node.ELEMENT_NODE
      && (node.matches('canvas[data-sdg-chart]') || node.querySelector('canvas[data-sdg-chart]') !== null);
  }

  function observe() {
    new MutationObserver(function (mutations) {
      var found = mutations.some(function (mutation) {
        return Array.prototype.some.call(mutation.addedNodes, containsChart);
      });
      if (found) {
        schedule();
      }
    }).observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', observe);
  } else {
    observe();
  }

  window.il = window.il || {};
  window.il.SurveyDataGraphs = { init: init };
})();
