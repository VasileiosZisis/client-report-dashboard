(function () {
  if (typeof window.CLIREDAS_DASHBOARD === 'undefined') return;

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }
  function $all(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function setStatus(msg) {
    const el = $('#cliredas-status');
    if (!el) return;
    el.textContent = msg || '';
  }

  function formatNumber(n) {
    try {
      return new Intl.NumberFormat().format(n);
    } catch (e) {
      return String(n);
    }
  }

  function formatDuration(seconds) {
    seconds = Math.max(0, parseInt(seconds, 10) || 0);
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    if (m <= 0) return s + 's';
    return m + 'm ' + s + 's';
  }

  function renderKPIs(report) {
    const totals = report.totals || {};
    const map = {
      sessions: formatNumber(totals.sessions || 0),
      users: formatNumber(totals.users || 0),
      engagement_time: formatDuration(totals.avg_engagement_seconds || 0),
    };

    $all('.cliredas-kpi').forEach(function (card) {
      const key = card.getAttribute('data-kpi');
      const valueEl = $('.cliredas-kpi-value', card);
      if (!valueEl) return;
      if (typeof map[key] !== 'undefined') valueEl.textContent = map[key];
    });
  }

  function renderDevices(report) {
    const tbody = $('#cliredas-devices tbody');
    if (!tbody) return;

    const devices = report.devices || {};
    const rows = ['desktop', 'mobile', 'tablet'].map(function (k) {
      const val = devices[k] || 0;
      return (
        '<tr>' +
        '<td>' +
        k.charAt(0).toUpperCase() +
        k.slice(1) +
        '</td>' +
        '<td>' +
        formatNumber(val) +
        '</td>' +
        '</tr>'
      );
    });

    tbody.innerHTML = rows.join('');
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderTopPages(report) {
    const tbody = $('#cliredas-top-pages tbody');
    if (!tbody) return;

    const pages = report.top_pages || [];
    if (!pages.length) {
      tbody.innerHTML = '<tr><td colspan="3">No data.</td></tr>';
      return;
    }

    tbody.innerHTML = pages
      .slice(0, 10)
      .map(function (p) {
        return (
          '<tr>' +
          '<td>' +
          escapeHtml(p.title || '') +
          '</td>' +
          '<td><code>' +
          escapeHtml(p.url || '') +
          '</code></td>' +
          '<td>' +
          formatNumber(p.sessions || 0) +
          '</td>' +
          '</tr>'
        );
      })
      .join('');
  }

  function renderChartPlaceholder(report) {
    // Milestone 4: still placeholder (Chart.js comes next milestone).
    const el = $('#cliredas-sessions-chart');
    if (!el) return;

    const points = (report.timeseries || []).length;
    el.textContent =
      'Mock chart data loaded (' +
      points +
      ' points). Chart.js wiring comes next.';
  }

  async function fetchReport(rangeKey) {
    const cfg = window.CLIREDAS_DASHBOARD;

    const body = new URLSearchParams();
    body.set('action', 'cliredas_get_report');
    body.set('nonce', cfg.nonce);
    body.set('range', rangeKey);

    const res = await fetch(cfg.ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      body: body.toString(),
      credentials: 'same-origin',
    });

    const json = await res.json();
    if (!json || !json.success) {
      const msg =
        (json && json.data && json.data.message) || 'Failed to load report.';
      throw new Error(msg);
    }

    return json.data.report;
  }

  function renderAll(report) {
    renderKPIs(report);
    renderDevices(report);
    renderTopPages(report);
    renderChartPlaceholder(report);
  }

  onReady(function () {
    const rangeSelect = $('#cliredas-date-range');
    if (!rangeSelect) return;

    rangeSelect.addEventListener('change', async function () {
      const range = rangeSelect.value;
      setStatus('Loading…');

      try {
        const report = await fetchReport(range);
        renderAll(report);
        setStatus('');
      } catch (e) {
        setStatus(e && e.message ? e.message : 'Error loading report.');
      }
    });

    // Optionally: render initial hint in placeholder with server-rendered data already present.
  });
})();
