/* Run with Node.js: node tests/dashboard-polling.js. No browser dependencies. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function fixture() {
  let now = 1000000;
  let nextTimer = 0;
  let calls = 0;
  const timers = new Map();
  const freshness = { textContent: '', dataset: {} };
  const status = { textContent: '' };
  const document = {
    hidden: false,
    readyState: 'loading',
    addEventListener() {},
    querySelector(selector) {
      return selector === '#cliredas-freshness' ? freshness : selector === '#cliredas-status' ? status : null;
    },
    querySelectorAll() { return []; },
  };
  const cfg = {
    initialReport: null,
    ajaxUrl: '/fixture',
    cliredasNonce: 'fixture-nonce',
    i18n: { refreshWaiting: 'Check WP-Cron', reportExpired: 'Expired report', blockUnavailable: 'Unavailable', lastSuccess: 'Last success: %s' },
  };
  const window = {
    cliredasDashboard: cfg,
    localStorage: { getItem() { return null; } },
    addEventListener() {},
  };
  let response = async () => ({ success: true, data: { report: cfg.initialReport } });
  const sandbox = {
    window, document, AbortController, URLSearchParams,
    Date: class extends Date {
      static now() {
        return now;
      }
    },
    setTimeout(callback, delay) {
      const id = ++nextTimer;
      timers.set(id, { callback, at: now + delay });
      return id;
    },
    clearTimeout(id) { timers.delete(id); },
    async fetch(url, options) {
      ++calls;
      assert.equal(new URLSearchParams(options.body).get('action'), 'cliredas_report_status');
      assert.equal(new URLSearchParams(options.body).get('cliredas_nonce'), 'fixture-nonce');
      return { status: 200, json: response };
    },
  };
  // Expose only the polling entry points in the isolated copy, without running DOM ready.
  const code = fs.readFileSync(path.join(__dirname, '../assets/js/cliredas-dashboard.js'), 'utf8')
    .replace(/\}\)\(\);\s*$/, 'window.cliredasTest = {watchReport, stopPolling, renderFreshness};})();');
  vm.runInNewContext(code, sandbox);
  const report = (pending = true) => ({ source: 'ga4_cache', totals: {}, freshness: {
    pending, context_id: 'fixture-context', state: pending ? 'refreshing' : 'fresh', label: pending ? 'Refreshing' : 'Fresh',
    retained_until: (now + 86400000) / 1000,
  } });
  async function tick(duration) {
    const target = now + duration;
    for (;;) {
      const next = [...timers.entries()].filter(([, timer]) => timer.at <= target).sort((a, b) => a[1].at - b[1].at)[0];
      if (!next) {
        break;
      }
      timers.delete(next[0]);
      now = next[1].at;
      await next[1].callback();
    }
    now = target;
  }
  return {
    api: window.cliredasTest, document, cfg, report, tick, freshness, status,
    get calls() { return calls; },
    respond(callback) { response = callback; },
  };
}

(async function () {
  const automatic = fixture();
  automatic.cfg.initialReport = automatic.report();
  automatic.respond(async () => ({ success: true, data: { report: automatic.report(false) } }));
  automatic.api.watchReport(automatic.cfg.initialReport, 'last_7_days');
  automatic.document.hidden = true;
  await automatic.tick(10000);
  assert.equal(automatic.calls, 0, 'Hidden dashboards do not poll');
  automatic.document.hidden = false;
  await automatic.tick(5000);
  assert.equal(automatic.calls, 1);
  assert.equal(automatic.freshness.textContent, 'Fresh', 'Completed refresh replaces displayed report');
  await automatic.tick(15000);
  assert.equal(automatic.calls, 1, 'Completed refresh stops polling');

  const timeout = fixture();
  timeout.cfg.initialReport = timeout.report();
  timeout.api.watchReport(timeout.cfg.initialReport, 'last_7_days');
  await timeout.tick(180000);
  assert.equal(timeout.status.textContent, 'Check WP-Cron');
  const callsAtTimeout = timeout.calls;
  await timeout.tick(10000);
  assert.equal(timeout.calls, callsAtTimeout, 'Polling stops at the three-minute deadline');
  assert.equal(timeout.cfg.initialReport.source, 'ga4_cache', 'Timeout preserves eligible last-good data');

  const obsolete = fixture();
  const old = obsolete.report();
  obsolete.cfg.initialReport = old;
  let complete;
  obsolete.respond(() => new Promise(resolve => { complete = resolve; }));
  obsolete.api.watchReport(old, 'last_7_days');
  const flight = obsolete.tick(5000);
  await new Promise(resolve => setImmediate(resolve));
  obsolete.api.stopPolling();
  complete({ success: true, data: { report: obsolete.report(false) } });
  await flight;
  assert.equal(obsolete.cfg.initialReport, old, 'A canceled range cannot apply an obsolete response');

  const expired = fixture();
  const nearlyExpired = expired.report();
  nearlyExpired.freshness.retained_until -= 86395;
  expired.cfg.initialReport = nearlyExpired;
  expired.api.renderFreshness(nearlyExpired);
  await expired.tick(5000);
  assert.equal(expired.cfg.initialReport.source, 'unavailable');
  assert.equal(expired.cfg.initialReport.error_message, 'Expired report', 'Open dashboards stop showing expired reports');

  const rejected = fixture();
  rejected.cfg.initialReport = rejected.report();
  rejected.respond(async () => ({ success: false, data: { message: 'Connection changed' } }));
  rejected.api.watchReport(rejected.cfg.initialReport, 'last_7_days');
  await rejected.tick(5000);
  assert.equal(rejected.cfg.initialReport.source, 'ga4_cache', 'Recoverable status errors preserve last-good data');
  console.log('Dashboard polling regressions passed (visibility, automatic update, timeout, cancellation, expiry and failure).');
})().catch(error => { console.error(error); process.exitCode = 1; });
