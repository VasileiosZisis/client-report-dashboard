(function () {
  // Guard if localized object isn't present.
  if (typeof window.CLIREDAS_DASHBOARD === 'undefined') {
    return;
  }

  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function updateQueryParam(url, key, value) {
    const u = new URL(url, window.location.origin);
    u.searchParams.set(key, value);
    return u.toString();
  }

  onReady(function () {
    const rangeSelect = document.getElementById('cliredas-date-range');
    if (rangeSelect) {
      rangeSelect.addEventListener('change', function () {
        const next = updateQueryParam(
          window.location.href,
          'range',
          rangeSelect.value
        );
        window.location.href = next;
      });
    }

    // Very light “mock” behavior for milestone 3:
    // Fill placeholders so the page feels alive even before GA4 wiring.
    const selectedRange = window.CLIREDAS_DASHBOARD.selectedRange;

    const chartEl = document.getElementById('cliredas-sessions-chart');
    if (chartEl) {
      chartEl.textContent = 'Mock chart placeholder for: ' + selectedRange;
    }

    const kpiEls = document.querySelectorAll('.cliredas-kpi');
    kpiEls.forEach(function (el) {
      const key = el.getAttribute('data-kpi');
      const valueEl = el.querySelector('.cliredas-kpi-value');
      if (!valueEl) return;

      // Simple deterministic mock numbers.
      const mock = {
        sessions: selectedRange === 'last_30_days' ? '12,480' : '3,120',
        users: selectedRange === 'last_30_days' ? '9,030' : '2,240',
        engagement_time: selectedRange === 'last_30_days' ? '1m 42s' : '1m 35s',
      };

      if (mock[key]) {
        valueEl.textContent = mock[key];
      }
    });
  });
})();
