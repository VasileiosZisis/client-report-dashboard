(function () {
  if (typeof window.cliredasDashboard === 'undefined') return;

  const cfg = window.cliredasDashboard;
  const i18n = (cfg && cfg.i18n) || {};
  const topPagesSortStorageKey = 'cliredas_top_pages_sort';
  const topPagesColumns = {
    title: { type: 'text', initialDirection: 'ascending' },
    url: { type: 'text', initialDirection: 'ascending' },
    sessions: { type: 'number', initialDirection: 'descending' },
    views: { type: 'number', initialDirection: 'descending' },
    avg_engagement_seconds: { type: 'number', initialDirection: 'descending' },
  };
  let topPagesSort = loadTopPagesSort();
  let topPagesQuery = '';
  let pollTimer = null;
  let pollController = null;
  let reportEpoch = 0;
  let retentionTimer = null;
  let pollDeadline = 0;
  let rangeController = null;

  function validNumber(value) {
    return (typeof value === 'number' || (typeof value === 'string' && /^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?$/i.test(value.trim()))) && Number.isFinite(Number(value)) && Number(value) >= 0;
  }

  function blockState(report, block, metric = 'sessions') {
    const data = report[block];
    if (report.source === 'unavailable' || !data || typeof data !== 'object' || (block === 'traffic_sources' && report.traffic_sources_unavailable)) return 'unavailable';
    if (block === 'top_pages') return Array.isArray(data) && data.every(row => row && typeof row === 'object' && !Array.isArray(row)) ? (data.length ? 'ready' : 'empty') : 'unavailable';
    let values = Object.values(data);
    if (block === 'totals') values = ['sessions', 'users', 'pageviews', 'avg_engagement_seconds'].map(key => data[key]);
    if (block === 'timeseries') {
      if (!Array.isArray(data) || !data.every(row => row && typeof row.date === 'string')) return 'unavailable';
      values = data.map(row => row[metric]);
    }
    if (!values.every(validNumber)) return 'unavailable';
    return values.some(value => Number(value) > 0) ? 'ready' : 'empty';
  }

  function blockMessage(block, state) {
    return state === 'unavailable' ? i18n.unavailableMessage || i18n.blockUnavailable || 'Unavailable' : (i18n.emptyMessages || {})[block] || i18n.noData || 'No data.';
  }

  function updateText(el, text) {
    if (el && el.textContent !== text) el.textContent = text;
  }

  function showBlockMessage(selector, block, state) {
    const el = $(selector);
    if (!el) return;
    el.hidden = state === 'ready';
    updateText(el, state === 'ready' ? '' : blockMessage(block, state));
  }

  function stopPolling() {
    reportEpoch += 1;
    clearTimeout(pollTimer);
    if (pollController) pollController.abort();
    pollController = null;
  }

  function renderFreshness(report) {
    clearTimeout(retentionTimer);
    const retention = report.freshness && report.freshness.retained_until;
    if (retention) {
      retentionTimer = setTimeout(function () {
        stopPolling();
        clearDisplayedAnalytics(i18n.reportExpired);
      }, Math.max(0, retention * 1000 - Date.now()));
    }
    const el = $('#cliredas-freshness');
    if (!el) return;
    const freshness = report.freshness || {};
    let text = report.source === 'mock' ? i18n.sampleData : freshness.label || (report.source === 'unavailable' ? i18n.blockUnavailable : '');
    if (freshness.last_success_label) {
      text += ' · ' + sprintf(i18n.lastSuccess, freshness.last_success_label);
    }
    updateText(el, text || '');
    el.dataset.state = freshness.state || report.source || '';
    setStatus(text || i18n.reportUpdated || 'Report updated.', true);
  }

  function clearDisplayedAnalytics(message) {
    const report = { source: 'unavailable', totals: {}, timeseries: [], top_pages: [], devices: {}, traffic_sources: {}, error_message: message };
    cfg.initialReport = report;
    setLoading(false);
    const range = $('#cliredas-date-range');
    if (range && cfg.selectedRange) range.value = cfg.selectedRange;
    renderAll(report);
  }

  function watchReport(report, range, previousDeadline) {
    stopPolling();
    const freshness = report.freshness || {};
    if (!freshness.pending || !freshness.context_id) return;
    const epoch = reportEpoch;
    const deadline = previousDeadline || Date.now() + 180000;
    pollDeadline = deadline;
    async function poll() {
      if (epoch !== reportEpoch) return;
      if (freshness.retained_until && Date.now() >= freshness.retained_until * 1000) {
        clearDisplayedAnalytics(i18n.refreshWaiting);
        stopPolling();
        return;
      }
      if (Date.now() >= deadline) {
        setStatus(i18n.refreshWaiting);
        stopPolling();
        return;
      }
      if (document.hidden) {
        pollTimer = setTimeout(poll, 5000);
        return;
      }
      pollController = new AbortController();
      try {
        const result = await fetchReport(range, freshness.context_id, pollController.signal);
        const next = result.report;
        if (epoch !== reportEpoch) return;
        if (!next.freshness || next.freshness.context_id !== freshness.context_id) {
          clearDisplayedAnalytics(i18n.refreshWaiting);
          stopPolling();
          return;
        }
        cfg.initialReport = next;
        renderAll(next);
        if (!next.freshness.pending) {
          stopPolling();
          return;
        }
      } catch (error) {
        if (epoch !== reportEpoch || error.name === 'AbortError') return;
        if ([401, 403, 409].includes(error.status)) clearDisplayedAnalytics(error.message);
        else setStatus(i18n.refreshWaiting);
        stopPolling();
        return;
      }
      pollTimer = setTimeout(poll, 5000);
    }
    pollTimer = setTimeout(poll, 5000);
  }

  window.addEventListener('pagehide', function () {
    stopPolling();
    if (rangeController) rangeController.abort();
  });

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }
  function $all(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function sprintf(template, value) {
    template = String(template || '');
    // Support "%s" and "%1$s".
    return template.replace(/%1\$s|%s/g, function () { return String(value); });
  }

  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  function setStatus(msg, quiet = false) {
    const el = $('#cliredas-status');
    if (!el) return;
    if (el.classList) el.classList.toggle('screen-reader-text', quiet);
    updateText(el, msg || '');
  }

  function setLoading(isLoading) {
    const controls = $('.cliredas-controls');
    const select = $('#cliredas-date-range');
    const exportButton = $('#cliredas-export-csv-button');
    if (controls) controls.classList.toggle('is-loading', !!isLoading);
    if (select) select.disabled = !!isLoading;
    if (exportButton) exportButton.disabled = !!isLoading;
    ['#cliredas-start-date', '#cliredas-end-date', '#cliredas-apply-dates'].forEach(selector => { const el = $(selector); if (el) el.disabled = !!isLoading; });
    const content = $('#cliredas-report-content');
    if (content) content.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    if (isLoading) setStatus(i18n.loadingPrevious || i18n.loading || 'Loading...');
  }

  function showError(message) {
    const notice = $('#cliredas-notice');
    if (!notice) return;

    const p = $('p', notice);
    if (p) p.textContent = message || i18n.errorGeneric || 'An error occurred.';
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

  function removeQueryParams(params) {
    try {
      const url = new URL(window.location.href);
      let changed = false;

      params.forEach(function (key) {
        if (url.searchParams.has(key)) {
          url.searchParams.delete(key);
          changed = true;
        }
      });

      if (changed) {
        window.history.replaceState({}, document.title, url.toString());
      }
    } catch (e) {}
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

  function formatPercent(value) {
    try {
      return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
      }).format(value);
    } catch (e) {
      return Number(value).toFixed(1);
    }
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function normalizeTopPagesSort(value) {
    if (
      !value ||
      !Object.prototype.hasOwnProperty.call(topPagesColumns, value.key) ||
      (value.direction !== 'ascending' && value.direction !== 'descending')
    ) {
      return null;
    }

    return { key: value.key, direction: value.direction };
  }

  function loadTopPagesSort() {
    try {
      const saved = window.localStorage.getItem(topPagesSortStorageKey);
      return saved ? normalizeTopPagesSort(JSON.parse(saved)) : null;
    } catch (e) {
      return null;
    }
  }

  function saveTopPagesSort() {
    try {
      window.localStorage.setItem(
        topPagesSortStorageKey,
        JSON.stringify(topPagesSort)
      );
    } catch (e) {}
  }

  function compareTopPagesText(a, b) {
    try {
      return String(a).localeCompare(String(b), undefined, {
        numeric: true,
        sensitivity: 'base',
      });
    } catch (e) {
      const left = String(a).toLowerCase();
      const right = String(b).toLowerCase();
      if (left < right) return -1;
      if (left > right) return 1;
      return 0;
    }
  }

  function getTopPagesRows(pages) {
    const query = topPagesQuery.trim().toLowerCase();
    const rows = pages.slice(0, 25).map(function (page, index) {
      return { page: page, originalIndex: index };
    }).filter(function (row) {
      return !query || [row.page.title, row.page.url].some(value => String(value || '').toLowerCase().includes(query));
    });

    if (!topPagesSort) {
      return rows.map(function (row) {
        return row.page;
      });
    }

    const column = topPagesColumns[topPagesSort.key];
    const direction = topPagesSort.direction === 'ascending' ? 1 : -1;

    rows.sort(function (left, right) {
      const leftValue = left.page[topPagesSort.key];
      const rightValue = right.page[topPagesSort.key];
      let comparison = 0;

      if (column.type === 'number') {
        const leftKnown = validNumber(leftValue);
        const rightKnown = validNumber(rightValue);
        if (leftKnown !== rightKnown) return leftKnown ? -1 : 1;
        if (!leftKnown) return left.originalIndex - right.originalIndex;
        const leftNumber = Number(leftValue) || 0;
        const rightNumber = Number(rightValue) || 0;
        comparison = leftNumber - rightNumber;
      } else {
        comparison = compareTopPagesText(leftValue || '', rightValue || '');
      }

      return comparison === 0
        ? left.originalIndex - right.originalIndex
        : comparison * direction;
    });

    return rows.map(function (row) {
      return row.page;
    });
  }

  function updateTopPagesSortHeaders() {
    $all('#cliredas-top-pages .cliredas-sort-button').forEach(function (button) {
      button.disabled = false;
      const key = button.getAttribute('data-cliredas-sort');
      const column = topPagesColumns[key];
      const heading = button.closest('th');
      const icon = $('.dashicons', button);
      const label = $('.cliredas-sort-label', button);
      const isActive = !!topPagesSort && topPagesSort.key === key;
      const currentDirection = isActive ? topPagesSort.direction : null;
      const nextDirection = isActive
        ? currentDirection === 'ascending'
          ? 'descending'
          : 'ascending'
        : column.initialDirection;
      const actionTemplate =
        nextDirection === 'ascending'
          ? i18n.sortAscending || 'Sort %s ascending'
          : i18n.sortDescending || 'Sort %s descending';

      if (heading) {
        heading.setAttribute('aria-sort', currentDirection || 'none');
      }

      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-label', sprintf(actionTemplate, label.textContent));

      if (icon) {
        icon.classList.remove('dashicons-sort', 'dashicons-arrow-up', 'dashicons-arrow-down');
        icon.classList.add(
          currentDirection === 'ascending'
            ? 'dashicons-arrow-up'
            : currentDirection === 'descending'
              ? 'dashicons-arrow-down'
              : 'dashicons-sort'
        );
      }
    });
  }

  function initializeTopPagesSorting() {
    $all('#cliredas-top-pages .cliredas-sort-button').forEach(function (button) {
      button.addEventListener('click', function () {
        const key = button.getAttribute('data-cliredas-sort');
        const column = topPagesColumns[key];
        if (!column) return;

        topPagesSort = {
          key: key,
          direction:
            topPagesSort && topPagesSort.key === key
              ? topPagesSort.direction === 'ascending'
                ? 'descending'
                : 'ascending'
              : column.initialDirection,
        };

        saveTopPagesSort();
        renderTopPages((cfg && cfg.initialReport) || {});
        const label = $('.cliredas-sort-label', button);
        updateText($('#cliredas-sort-status'), sprintf(topPagesSort.direction === 'ascending' ? i18n.sortedAscending : i18n.sortedDescending, label ? label.textContent : key));
      });
    });

    updateTopPagesSortHeaders();
  }

  function initializeTopPagesFiltering() {
    const container = $('#cliredas-top-pages-filter');
    const input = $('#cliredas-page-query');
    const clear = $('#cliredas-page-query-clear');
    if (!container || !input || !clear) return;
    input.value = '';
    container.hidden = false;
    function reset() {
      topPagesQuery = '';
      input.value = '';
      renderTopPages(cfg.initialReport || {});
      input.focus();
    }
    input.addEventListener('input', function () {
      topPagesQuery = input.value;
      renderTopPages(cfg.initialReport || {});
    });
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') { event.preventDefault(); reset(); }
    });
    clear.addEventListener('click', reset);
  }

  // ---- Rendering: KPIs, Devices, Top Pages ----

  function getKpiDelta(report, key) {
    const metricKeys = {
      sessions: 'sessions',
      users: 'users',
      pageviews: 'pageviews',
      engagement_time: 'avg_engagement_seconds',
    };
    const metricKey = metricKeys[key];
    const totals = report && report.totals;
    const previousTotals =
      report && report.comparison && report.comparison.totals;

    if (!metricKey || !totals || !previousTotals) return null;
    if (!validNumber(totals[metricKey]) || !validNumber(previousTotals[metricKey])) return null;

    const current = Number(totals[metricKey]) || 0;
    const previous = Number(previousTotals[metricKey]) || 0;

    if (previous <= 0 && current > 0) {
      return {
        className: 'is-positive',
        text: i18n.newDelta || 'New vs previous period',
      };
    }

    const rawChange = previous > 0 ? ((current - previous) / previous) * 100 : 0;
    const change = Math.round(rawChange * 10) / 10;
    let className = 'is-neutral';
    let sign = '';

    if (change > 0) {
      className = 'is-positive';
      sign = '+';
    } else if (change < 0) {
      className = 'is-negative';
      sign = '-';
    }

    const percentage = sign + formatPercent(Math.abs(change)) + '%';

    return {
      className: className,
      text: sprintf(
        i18n.deltaTemplate || '%s vs previous period',
        percentage
      ),
    };
  }

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
      const deltaEl = $('.cliredas-kpi-delta', card);

      if (valueEl && typeof map[key] !== 'undefined') {
        const metric = key === 'engagement_time' ? 'avg_engagement_seconds' : key;
        valueEl.textContent = report.source === 'unavailable' || !validNumber(totals[metric]) ? '—' : map[key];
      }

      if (!deltaEl) return;

      const delta = report.source === 'unavailable' ? null : getKpiDelta(report, key);
      deltaEl.classList.remove('is-positive', 'is-negative', 'is-neutral');

      if (!delta) {
        deltaEl.textContent = '';
        deltaEl.hidden = true;
        deltaEl.classList.add('is-neutral');
        return;
      }

      deltaEl.textContent = delta.text;
      deltaEl.hidden = false;
      deltaEl.classList.add(delta.className);
    });
    showBlockMessage('#cliredas-kpis-message', 'totals', blockState(report, 'totals'));
  }

  function renderDevices(report) {
    const tbody = $('#cliredas-devices tbody');
    if (!tbody) return;
    const state = blockState(report, 'devices');
    showBlockMessage('#cliredas-devices-message', 'devices', state);
    if (state === 'unavailable' || !Object.keys(report.devices).length) {
      tbody.innerHTML = '<tr><td colspan="2">' + escapeHtml(blockMessage('devices', state)) + '</td></tr>';
      return;
    }

    const devices = report.devices || {};
    const order = Object.keys(devices);

    tbody.innerHTML = order
      .map(function (k) {
        const val = devices[k] || 0;
        return (
          '<tr>' +
          '<td>' +
          escapeHtml((i18n.devices || {})[k] || k.charAt(0).toUpperCase() + k.slice(1)) +
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
    const state = blockState(report, 'traffic_sources');
    showBlockMessage('#cliredas-sources-message', 'traffic_sources', state);
    const wrap = $('#cliredas-sources-wrap');
    if (wrap) wrap.hidden = true;
    if (state === 'unavailable' || !Object.keys(report.traffic_sources).length) {
      tbody.innerHTML = '<tr><td colspan="2">' + escapeHtml(blockMessage('traffic_sources', state)) + '</td></tr>';
      canvas.style.display = 'none';
      if (trafficChart) {
        trafficChart.destroy();
        trafficChart = null;
      }
      return;
    }

    const sources = report.traffic_sources || {};
    const srcLabels = i18n.trafficSources || {};
    const rows = [
      ['organic_search', srcLabels.organic_search || 'Organic Search'],
      ['direct', srcLabels.direct || 'Direct'],
      ['referral', srcLabels.referral || 'Referral'],
      ['social', srcLabels.social || 'Social'],
      ['other', srcLabels.other || 'Other'],
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

    // No data: hide chart and destroy existing instance.
    if (total <= 0) {
      canvas.style.display = 'none';
      if (trafficChart) {
        trafficChart.destroy();
        trafficChart = null;
      }
      return;
    }

    if (typeof window.Chart === 'undefined') {
      showBlockMessage('#cliredas-sources-message', 'traffic_sources', 'ready');
      const message = $('#cliredas-sources-message');
      if (message) { message.hidden = false; updateText(message, i18n.chartUnavailable); }
      return;
    }

    canvas.style.display = 'block';
    if (wrap) wrap.hidden = false;

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

    const state = blockState(report, 'top_pages');
    const pages = state === 'unavailable' ? [] : report.top_pages;
    const rows = getTopPagesRows(pages);
    const count = $('#cliredas-page-count');
    const template = i18n.pageCount || 'Showing %1$s of %2$s loaded pages.';
    updateText(count, state === 'unavailable' ? blockMessage('top_pages', state) : template.replace(/%1\$s/g, () => formatNumber(rows.length)).replace(/%2\$s/g, () => formatNumber(Math.min(25, pages.length))));
    updateTopPagesSortHeaders();
    if (state !== 'ready' || !rows.length) {
      const noData = escapeHtml(state === 'ready' ? i18n.noMatches : blockMessage('top_pages', state));
      tbody.innerHTML = '<tr><td colspan="5">' + noData + '</td></tr>';
      return;
    }

    tbody.innerHTML = rows
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
          (validNumber(p.sessions) ? formatNumber(p.sessions) : '—') +
          '</td>' +
          '<td>' +
          (validNumber(p.views) ? formatNumber(p.views) : '—') +
          '</td>' +
          '<td>' +
          (validNumber(p.avg_engagement_seconds) ? escapeHtml(formatDuration(p.avg_engagement_seconds)) : '—') +
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
    const data = buildChartData(report);
    const state = blockState(report, 'timeseries', data.metric);
    showBlockMessage('#cliredas-trend-message', 'timeseries', state);
    const wrap = $('#cliredas-trend-wrap');
    if (wrap) wrap.hidden = true;

    const metricLabel =
      data.metric === 'users'
        ? i18n.totalUsers || 'Total users'
        : i18n.sessions || 'Sessions';
    const metricColor = data.metric === 'users' ? '#1d9b6c' : '#2271b1';
    const titleEl = $('#cliredas-chart-title');
    if (titleEl) {
      const tmpl = i18n.overTimeTemplate || '%s over time';
      titleEl.textContent = sprintf(tmpl, metricLabel);
    }

    if (state !== 'ready' || typeof window.Chart === 'undefined') {
      if (sessionsChart) { sessionsChart.destroy(); sessionsChart = null; }
      if (state === 'ready') {
        const message = $('#cliredas-trend-message');
        if (message) { message.hidden = false; updateText(message, i18n.chartUnavailable); }
      }
      return;
    }
    const ctx = getChartCtx();
    if (!ctx) return;
    if (wrap) wrap.hidden = false;

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

  function renderTrendTable(report) {
    const tbody = $('#cliredas-trend-data tbody');
    if (!tbody) return;
    const series = report.timeseries;
    if (report.source === 'unavailable' || !Array.isArray(series) || !series.every(row => row && typeof row.date === 'string')) {
      tbody.innerHTML = '<tr><td colspan="3">' + escapeHtml(blockMessage('timeseries', 'unavailable')) + '</td></tr>';
      return;
    }
    tbody.innerHTML = series.length ? series.map(point => '<tr><th scope="row">' + escapeHtml(point.date) + '</th>' + ['sessions', 'users'].map(key => '<td>' + (validNumber(point[key]) ? formatNumber(point[key]) : '—') + '</td>').join('') + '</tr>').join('')
      : '<tr><td colspan="3">' + escapeHtml(blockMessage('timeseries', 'empty')) + '</td></tr>';
  }

  // ---- AJAX ----

  async function fetchReport(rangeKey, contextId, signal) {
    const body = new URLSearchParams();
    body.set('action', contextId ? 'cliredas_report_status' : 'cliredas_get_report');
    body.set('cliredas_nonce', cfg.cliredasNonce || '');
    body.set('cliredas_range', rangeKey);
    const period = cfg.selectedPeriod;
    if (rangeKey === 'custom') {
      body.set('cliredas_start', contextId && period ? period.dates.startDate : ($('#cliredas-start-date') || {}).value || '');
      body.set('cliredas_end', contextId && period ? period.dates.endDate : ($('#cliredas-end-date') || {}).value || '');
    }
    if (contextId) {
      body.set('cliredas_context', contextId);
      if (period) addExpectedDates(body, period);
    }

    const res = await fetch(cfg.ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      body: body.toString(),
      credentials: 'same-origin',
      signal,
    });

    const error = new Error([401, 403, 409].includes(res.status) ? i18n.accessChanged : i18n.requestFailed || i18n.failedToLoadReport);
    error.status = res.status;
    let json;
    try { json = await res.json(); } catch (parseError) { throw error; }
    if (res.status >= 400 || !json || !json.success || !json.data || !json.data.report || typeof json.data.report !== 'object') throw error;
    if (cfg.selectedPeriod && !contextId && (!json.data.period || json.data.period.key !== rangeKey || !json.data.period.dates || !json.data.period.comparison)) throw error;
    return { report: json.data.report, period: json.data.period || null };
  }

  function addExpectedDates(body, period) {
    body.set('cliredas_expected_start', period.dates.startDate);
    body.set('cliredas_expected_end', period.dates.endDate);
    body.set('cliredas_expected_comparison_start', period.comparison.startDate);
    body.set('cliredas_expected_comparison_end', period.comparison.endDate);
    body.set('cliredas_timezone', period.timezone);
  }

  function renderAppliedPeriod(period) {
    if (!period) return;
    const values = { start: period.dates.startDate, end: period.dates.endDate, expected_start: period.dates.startDate, expected_end: period.dates.endDate,
      expected_comparison_start: period.comparison.startDate, expected_comparison_end: period.comparison.endDate, timezone: period.timezone };
    Object.entries(values).forEach(([key, value]) => { const field = $('#cliredas-export-' + key); if (field) field.value = value; });

  }

  function customDatesError() {
    const start = ($('#cliredas-start-date') || {}).value || '';
    const end = ($('#cliredas-end-date') || {}).value || '';
    function day(value) {
      if (!/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/.test(value) || value.startsWith('0000')) return NaN;
      const date = new Date(value + 'T00:00:00Z');
      return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value ? date.getTime() : NaN;
    }
    const first = day(start); const last = day(end);
    const previous = first - (last - first + 86400000);
    const valid = Number.isFinite(first) && Number.isFinite(last) && first <= last && last - first < 366 * 86400000 && end <= cfg.siteToday && Number.isFinite(previous) && new Date(previous).getUTCFullYear() >= 1;
    return valid ? '' : i18n.invalidDates;
  }

  function renderAll(report) {
    renderFreshness(report);
    setGa4Warning(report && report.error_message ? report.error_message : '');
    renderKPIs(report);
    renderTrafficSources(report);
    renderDevices(report);
    renderTopPages(report);
    renderTrendTable(report);
    renderOrUpdateSessionsChart(report);
  }

  onReady(function () {
    removeQueryParams(['cliredas_cache_cleared', 'cliredas_cache_cleared_nonce']);

    const rangeSelect = $('#cliredas-date-range');
    if (!rangeSelect) return;

    const exportRange = $('#cliredas-export-range');
    $all('.cliredas-js-only').forEach(function (el) { el.hidden = false; });

    initializeTopPagesSorting();
    initializeTopPagesFiltering();

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
        const r = cfg && cfg.initialReport;
        if (r) {
          renderOrUpdateSessionsChart(r);
        }
      });
    }

    // Render immediately from embedded initial report (no initial AJAX).
    clearError();
    if (cfg.initialReport) {
      renderAll(cfg.initialReport);
      watchReport(cfg.initialReport, rangeSelect.value);
    }

    const periodForm = $('#cliredas-period-form');
    const customControls = $('#cliredas-custom-dates');
    const applyButton = $('#cliredas-apply-dates');
    const dateError = $('#cliredas-date-error');
    function updateDateControls() {
      const custom = rangeSelect.value === 'custom';
      if (customControls) customControls.hidden = !custom;
      if (applyButton) applyButton.hidden = !custom;
    }
    function restoreControls() {
      rangeSelect.value = cfg.selectedRange;
      if (cfg.selectedPeriod) {
        const start = $('#cliredas-start-date'); const end = $('#cliredas-end-date');
        if (start) start.value = cfg.selectedPeriod.dates.startDate;
        if (end) end.value = cfg.selectedPeriod.dates.endDate;
      }
      updateDateControls();
    }
    updateDateControls();
    renderAppliedPeriod(cfg.selectedPeriod);

    async function loadSelection() {
      const range = rangeSelect.value;
      const validation = range === 'custom' ? customDatesError() : '';
      updateText(dateError, validation || '');
      ['#cliredas-start-date', '#cliredas-end-date'].forEach(selector => { const el = $(selector); if (el) el.setAttribute('aria-invalid', validation ? 'true' : 'false'); });
      if (validation) { const start = $('#cliredas-start-date'); if (start) start.focus(); return; }
      stopPolling();
      const epoch = reportEpoch;
      const previousDeadline = pollDeadline;
      if (rangeController) rangeController.abort();
      rangeController = new AbortController();
      clearError();
      const requestFocus = document.activeElement;
      function restoreRequestFocus() {
        if (requestFocus && typeof requestFocus.focus === 'function' && (document.activeElement === document.body || document.activeElement === requestFocus)) {
          const target = requestFocus.hidden || requestFocus.closest('[hidden]') ? rangeSelect : requestFocus;
          target.focus();
        }
      }
      setLoading(true);
      try {
        const result = await fetchReport(range, null, rangeController.signal);
        if (epoch !== reportEpoch) return;
        cfg.initialReport = result.report;
        if (result.period) cfg.selectedPeriod = result.period;
        cfg.selectedRange = range;
        renderAll(result.report);
        renderAppliedPeriod(cfg.selectedPeriod);
        if (exportRange) exportRange.value = range;
        setLoading(false);
        restoreRequestFocus();
        watchReport(result.report, range);
      } catch (e) {
        if (epoch !== reportEpoch) return;
        setLoading(false);
        restoreControls();
        restoreRequestFocus();
        if ([401, 403, 409].includes(e.status)) {
          clearDisplayedAnalytics(i18n.accessChanged);
          showError(i18n.accessChanged);
        } else {
          showError(i18n.requestFailed || i18n.errorLoadingReport);
          setStatus(i18n.requestFailed || i18n.errorLoadingReport);
          watchReport(cfg.initialReport || {}, cfg.selectedRange, previousDeadline);
        }
      }
    }
    rangeSelect.addEventListener('change', function () {
      updateText(dateError, '');
      if (rangeSelect.value === 'custom') {
        if (cfg.selectedPeriod) {
          const start = $('#cliredas-start-date'); const end = $('#cliredas-end-date');
          if (start) start.value = cfg.selectedPeriod.dates.startDate;
          if (end) end.value = cfg.selectedPeriod.dates.endDate;
        }
        updateDateControls();
        return;
      }
      updateDateControls();
      return loadSelection();
    });
    if (periodForm) periodForm.addEventListener('submit', function (event) { event.preventDefault(); return loadSelection(); });

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

