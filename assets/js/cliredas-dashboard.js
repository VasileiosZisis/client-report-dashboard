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

  function setRangeHint(rangeKey) {
    const cfg = window.CLIREDAS_DASHBOARD;
    const hint = $('#cliredas-range-hint');
    if (!hint || !cfg || !cfg.ranges) return;

    const label = cfg.ranges[rangeKey] || cfg.ranges['last_7_days'] || '';
    hint.textContent = label ? 'Showing: ' + label : '';
  }

  function setLoading(isLoading) {
    const controls = $('.cliredas-controls');
    const select = $('#cliredas-date-range');
    if (controls) controls.classList.toggle('is-loading', !!isLoading);
    if (select) select.disabled = !!isLoading;

    setStatus(isLoading ? 'Loading…' : '');
  }

  function showError(message) {
    const notice = $('#cliredas-notice');
    if (!notice) return;

    const p = $('p', notice);
    if (p) p.textContent = message || 'An error occurred.';
    notice.style.display = 'block';
  }

  function clearError() {
    const notice = $('#cliredas-notice');
    if (!notice) return;
    notice.style.display = 'none';
    const p = $('p', notice);
    if (p) p.textContent = '';
  }

  function setGa4Warning(message) {
    const notice = $('#cliredas-ga4-warning');
    if (!notice) return;

    const text = $('.cliredas-ga4-warning-text', notice);

    if (!message) {
      notice.style.display = 'none';
      if (text) text.textContent = '';
      return;
    }

    notice.style.display = 'block';
    if (text) text.textContent = message;
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

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // ---- Rendering: KPIs, Devices, Top Pages ----

  function renderKPIs(report) {
    const totals = report.totals || {};
    const map = {
      sessions: formatNumber(totals.sessions || 0),
      users: formatNumber(totals.users || 0),
      pageviews: formatNumber(totals.pageviews || 0),
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
    const order = ['desktop', 'mobile', 'tablet'];

    tbody.innerHTML = order
      .map(function (k) {
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
      })
      .join('');
  }

  function renderTrafficSources(report) {
    const tbody = $('#cliredas-traffic-sources tbody');
    const canvas = $('#cliredas-traffic-sources-chart');
    if (!tbody || !canvas) return;

    const sources = report.traffic_sources || {};
    const rows = [
      ['organic_search', 'Organic Search'],
      ['direct', 'Direct'],
      ['referral', 'Referral'],
      ['social', 'Social'],
      ['other', 'Other'],
    ].map(function (pair) {
      return { key: pair[0], label: pair[1], value: Number(sources[pair[0]] || 0) };
    });

    const total = rows.reduce(function (acc, r) {
      return acc + (Number(r.value) || 0);
    }, 0);

    tbody.innerHTML = rows
      .map(function (r) {
        return (
          '<tr>' +
          '<td>' +
          escapeHtml(r.label) +
          '</td>' +
          '<td>' +
          formatNumber(r.value || 0) +
          '</td>' +
          '</tr>'
        );
      })
      .join('');

    if (typeof window.Chart === 'undefined') return;

    // No data: hide chart and destroy existing instance.
    if (total <= 0) {
      canvas.style.display = 'none';
      if (trafficChart) {
        trafficChart.destroy();
        trafficChart = null;
      }
      return;
    }

    canvas.style.display = 'block';

    const labels = rows.map(function (r) {
      return r.label;
    });
    const values = rows.map(function (r) {
      return r.value;
    });

    const colors = ['#2271b1', '#1d9b6c', '#dba617', '#a05bbd', '#8c8f94'];

    const ctx = canvas.getContext('2d');

    if (!trafficChart) {
      trafficChart = new window.Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [
            {
              data: values,
              backgroundColor: colors,
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
          },
          cutout: '55%',
        },
      });
      return;
    }

    trafficChart.data.labels = labels;
    trafficChart.data.datasets[0].data = values;
    trafficChart.update();
  }

  function renderTopPages(report) {
    const tbody = $('#cliredas-top-pages tbody');
    if (!tbody) return;

    const pages = report.top_pages || [];
    if (!pages.length) {
      tbody.innerHTML = '<tr><td colspan="5">No data.</td></tr>';
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
          '<td>' +
          formatNumber(p.views || 0) +
          '</td>' +
          '<td>' +
          escapeHtml(formatDuration(p.avg_engagement_seconds || 0)) +
          '</td>' +
          '</tr>'
        );
      })
      .join('');
  }

  // ---- Chart.js ----

  let sessionsChart = null;
  let trafficChart = null;

  function getChartCtx() {
    const canvas = $('#cliredas-sessions-chart');
    if (!canvas) return null;
    return canvas.getContext('2d');
  }

  function buildChartData(report) {
    const series = report.timeseries || [];
    const labels = series.map(function (p) {
      return p.date;
    });
    const metricSelect = $('#cliredas-chart-metric');
    const metric = metricSelect ? metricSelect.value : 'sessions';

    const values = series.map(function (p) {
      const v = metric === 'users' ? p.users : p.sessions;
      return Number(v || 0);
    });

    return { labels, values, metric };
  }

  function renderOrUpdateSessionsChart(report) {
    const ctx = getChartCtx();
    if (!ctx) return;

    if (typeof window.Chart === 'undefined') {
      return;
    }

    const data = buildChartData(report);

    const metricLabel = data.metric === 'users' ? 'Total users' : 'Sessions';
    const metricColor = data.metric === 'users' ? '#1d9b6c' : '#2271b1';
    const titleEl = $('#cliredas-chart-title');
    if (titleEl) titleEl.textContent = metricLabel + ' over time';

    if (!sessionsChart) {
      sessionsChart = new window.Chart(ctx, {
        type: 'line',
        data: {
          labels: data.labels,
          datasets: [
            {
              label: metricLabel,
              data: data.values,
              tension: 0.25,
              pointRadius: 0,
              borderWidth: 2,
              borderColor: metricColor,
              backgroundColor: metricColor,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: { enabled: true },
          },
          scales: {
            x: {
              ticks: {
                maxRotation: 0,
                autoSkip: true,
                maxTicksLimit: 10,
              },
              grid: { display: false },
            },
            y: {
              ticks: { precision: 0 },
              title: { display: true, text: metricLabel },
              grid: { drawBorder: false },
            },
          },
        },
      });
      return;
    }

    sessionsChart.data.labels = data.labels;
    sessionsChart.data.datasets[0].data = data.values;
    sessionsChart.data.datasets[0].label = metricLabel;
    sessionsChart.data.datasets[0].borderColor = metricColor;
    sessionsChart.data.datasets[0].backgroundColor = metricColor;
    if (sessionsChart.options && sessionsChart.options.scales && sessionsChart.options.scales.y) {
      sessionsChart.options.scales.y.title = { display: true, text: metricLabel };
    }
    sessionsChart.update();
  }

  // ---- AJAX ----

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
    setGa4Warning(report && report.error_message ? report.error_message : '');
    renderKPIs(report);
    renderTrafficSources(report);
    renderDevices(report);
    renderTopPages(report);
    renderOrUpdateSessionsChart(report);
  }

  onReady(function () {
    const rangeSelect = $('#cliredas-date-range');
    if (!rangeSelect) return;

    const chartMetricSelect = $('#cliredas-chart-metric');
    if (chartMetricSelect) {
      try {
        const saved = window.localStorage.getItem('cliredas_chart_metric');
        if (saved === 'sessions' || saved === 'users') {
          chartMetricSelect.value = saved;
        }
      } catch (e) {}

      chartMetricSelect.addEventListener('change', function () {
        try {
          window.localStorage.setItem('cliredas_chart_metric', chartMetricSelect.value);
        } catch (e) {}

        // Re-render chart using already-loaded report.
        const r = window.CLIREDAS_DASHBOARD && window.CLIREDAS_DASHBOARD.initialReport;
        if (r) {
          renderOrUpdateSessionsChart(r);
        }
      });
    }

    // Render immediately from embedded initial report (no initial AJAX).
    clearError();
    if (window.CLIREDAS_DASHBOARD.initialReport) {
      setRangeHint(rangeSelect.value);
      renderAll(window.CLIREDAS_DASHBOARD.initialReport);
    }

    rangeSelect.addEventListener('change', async function () {
      const range = rangeSelect.value;

      clearError();
      setLoading(true);
      setRangeHint(range);

      try {
        const report = await fetchReport(range);
        // Keep the last loaded report so chart metric toggles don't require refetch.
        if (window.CLIREDAS_DASHBOARD) window.CLIREDAS_DASHBOARD.initialReport = report;
        renderAll(report);
        setLoading(false);
      } catch (e) {
        setLoading(false);
        showError(e && e.message ? e.message : 'Error loading report.');
      }
    });

    // WP dismissible notice behavior (optional; WP core adds handlers for .is-dismissible)
    const notice = $('#cliredas-notice');
    if (notice) {
      notice.addEventListener('click', function (ev) {
        if (
          ev.target &&
          ev.target.classList &&
          ev.target.classList.contains('notice-dismiss')
        ) {
          notice.style.display = 'none';
        }
      });
    }
  });
})();
