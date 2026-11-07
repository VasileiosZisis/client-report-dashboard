/* Run with Node.js: node tests/dashboard-presentation.js. No browser dependencies. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
let checks = 0;
function check(value, message) { assert.ok(value, message); checks++; }

function report() {
  return { source: 'ga4', totals: { sessions: 10, users: 8, pageviews: 15, avg_engagement_seconds: 45 },
    comparison: { totals: { sessions: 20, users: 8, pageviews: 10, avg_engagement_seconds: 45 } },
    timeseries: [{ date: '2026-10-31', sessions: 10, users: 8 }],
    top_pages: [{ title: 'About', url: '/about', sessions: 7, views: 8 }, { title: 'Home', url: '/', sessions: 2, views: 5 }, { title: 'Blog', url: '/news', sessions: 1, views: 2 }],
    devices: { desktop: 6, mobile: 4, tablet: 0 }, traffic_sources: { direct: 10 },
    freshness: { state: 'fresh', label: 'Fresh', last_success_label: '2026-10-31 08:00' } };
}

function fixture(saved = {}, withChart = true, periods = false) {
  const elements = new Map();
  const document = { readyState: 'loading', hidden: false, activeElement: null,
    querySelector: key => elements.get(key) || null,
    querySelectorAll: key => key === '#cliredas-top-pages .cliredas-sort-button' ? buttons : key === '.cliredas-kpi' ? cards : key === '.cliredas-js-only' ? [el('.metric-control')] : [],
    addEventListener: (type, callback) => { if (type === 'DOMContentLoaded') document.ready = callback; } };
  function el(key) {
    if (elements.has(key)) return elements.get(key);
    const classes = new Set();
    const node = { value: '', hidden: false, disabled: false, style: {}, dataset: {}, attributes: {}, listeners: {}, innerHTML: '', textContent: '',
      classList: { add: (...names) => names.forEach(name => classes.add(name)), remove: (...names) => names.forEach(name => classes.delete(name)), toggle: (name, on) => on ? classes.add(name) : classes.delete(name) },
      setAttribute(name, value) { this.attributes[name] = value; }, getAttribute(name) { return this.attributes[name]; },
      addEventListener(type, callback) { this.listeners[type] = callback; }, focus() { document.activeElement = this; }, getContext() { return {}; },
      querySelector(selector) { return elements.get(key + ' ' + selector) || null; },
      closest() { return this.heading; },
      async fire(type, event = {}) { return this.listeners[type] && this.listeners[type]({ target: this, ...event }); } };
    elements.set(key, node); return node;
  }
  ['#cliredas-freshness', '#cliredas-status', '#cliredas-report-content', '.cliredas-controls', '#cliredas-date-range', '#cliredas-export-csv-button', '#cliredas-export-range', '#cliredas-notice', '#cliredas-notice p', '#cliredas-ga4-warning', '#cliredas-ga4-warning .cliredas-ga4-warning-text', '#cliredas-top-pages-filter', '#cliredas-page-query', '#cliredas-page-query-clear', '#cliredas-page-count', '#cliredas-sort-status', '#cliredas-chart-metric', '#cliredas-chart-title', '#cliredas-kpis-message', '#cliredas-devices-message', '#cliredas-sources-message', '#cliredas-trend-message', '#cliredas-trend-wrap', '#cliredas-sources-wrap', '#cliredas-devices tbody', '#cliredas-traffic-sources tbody', '#cliredas-top-pages tbody', '#cliredas-trend-data tbody', '#cliredas-sessions-chart', '#cliredas-traffic-sources-chart'].forEach(el);
  if (periods) ['#cliredas-period-form', '#cliredas-custom-dates', '#cliredas-apply-dates', '#cliredas-date-error', '#cliredas-start-date', '#cliredas-end-date', '#cliredas-displayed-period', ...['start','end','expected_start','expected_end','expected_comparison_start','expected_comparison_end','timezone'].map(key => '#cliredas-export-' + key)].forEach(el);
  el('#cliredas-date-range').value = el('#cliredas-export-range').value = 'last_7_days'; el('#cliredas-chart-metric').value = 'sessions';
  const buttons = ['title', 'url', 'sessions', 'views', 'avg_engagement_seconds'].map(key => {
    const button = el('.sort-' + key); button.attributes['data-cliredas-sort'] = key; button.heading = el('.heading-' + key);
    el('.sort-' + key + ' .cliredas-sort-label').textContent = key; el('.sort-' + key + ' .dashicons'); return button;
  });
  const cards = ['sessions', 'users', 'pageviews', 'engagement_time'].map(key => {
    const card = el('.card-' + key); card.attributes['data-kpi'] = key; el('.card-' + key + ' .cliredas-kpi-value'); el('.card-' + key + ' .cliredas-kpi-delta'); return card;
  });
  const storage = { ...saved }; let calls = 0; let now = 1000000; const timers = new Map(); let timer = 0;
  const charts = [];
  const cfg = { initialReport: report(), selectedRange: 'last_7_days', ajaxUrl: '/fixture', cliredasNonce: 'fixture', i18n: {
    unavailableMessage: 'Data unavailable.', noMatches: 'No pages match this filter.', chartUnavailable: 'Use the data table.', pageCount: 'Showing %1$s of %2$s loaded pages.',
    emptyMessages: { top_pages: 'No pages for this period.', devices: 'No device activity.', traffic_sources: 'No source activity.', timeseries: 'No chart activity.', totals: 'No activity.' },
    sortedAscending: 'Sorted %s ascending.', sortedDescending: 'Sorted %s descending.', sortAscending: 'Sort %s ascending', sortDescending: 'Sort %s descending',
    loadingPrevious: 'Loading; visible values belong to the previous report.', requestFailed: 'Reload failed safely.', accessChanged: 'Access changed.', reportExpired: 'Expired.', lastSuccess: 'Last success: %s', refreshWaiting: 'Check WP-Cron', blockUnavailable: 'Unavailable', sampleData: 'Sample data', reportUpdated: 'Updated' } };
  if (periods) {
    cfg.siteToday = '2026-11-07'; cfg.i18n.invalidDates = 'Invalid dates.';
    cfg.selectedPeriod = {key:'last_7_days',dates:{startDate:'2026-11-01',endDate:'2026-11-07'},comparison:{startDate:'2026-10-25',endDate:'2026-10-31'},days:7,timezone:'Europe/Chisinau'};
    el('#cliredas-start-date').value = cfg.selectedPeriod.dates.startDate; el('#cliredas-end-date').value = cfg.selectedPeriod.dates.endDate;
  }
  const requests = [];
  const window = { cliredasDashboard: cfg, localStorage: { getItem: key => storage[key] || null, setItem: (key, value) => { storage[key] = value; } }, addEventListener() {} };
  if (withChart) window.Chart = class { constructor(ctx, options) { this.data = options.data; this.options = options.options; charts.push(this); } update() { this.updates = (this.updates || 0) + 1; } destroy() { this.destroyed = true; } };
  let response = async () => ({ status: 200, json: async () => ({ success: true, data: { report: report() } }) });
  const sandbox = { window, document, AbortController, URLSearchParams, URL, Intl, Date: class extends Date { static now() { return now; } },
    setTimeout(fn, delay) { timers.set(++timer, { fn, at: now + delay }); return timer; }, clearTimeout(id) { timers.delete(id); },
    async fetch(url, options) { calls++; requests.push(new URLSearchParams(options.body)); return response(options); } };
  const code = fs.readFileSync(path.join(__dirname, '../assets/js/cliredas-dashboard.js'), 'utf8')
    .replace(/\}\)\(\);\s*$/, 'window.cliredasTest = {renderAll, blockState, getKpiDelta, watchReport, stopPolling, initializeTopPagesFiltering};})();');
  vm.runInNewContext(code, sandbox); document.ready();
  return { el, document, cfg, storage, charts, requests, api: window.cliredasTest, get calls() { return calls; }, respond(fn) { response = fn; },
    async search(query) { el('#cliredas-page-query').value = query; await el('#cliredas-page-query').fire('input'); },
    async range(key) { el('#cliredas-date-range').value = key; return el('#cliredas-date-range').fire('change'); },
    async tick(duration) { const end = now + duration; for (;;) { const next = [...timers].filter(([, task]) => task.at <= end).sort((a,b) => a[1].at - b[1].at)[0]; if (!next) break; timers.delete(next[0]); now = next[1].at; await next[1].fn(); } now = end; } };
}

(async function () {
  const f = fixture(); const original = JSON.stringify(f.cfg.initialReport);
  check(!f.el('#cliredas-top-pages-filter').hidden && f.calls === 0, 'Local controls initialize without requests');
  await f.search('  ABOUT  '); check(f.el('#cliredas-page-count').textContent.includes('1 of 3'), 'Trimmed case-insensitive title match');
  await f.search('/NEWS'); check(f.el('#cliredas-top-pages tbody').innerHTML.includes('Blog') && !f.el('#cliredas-top-pages tbody').innerHTML.includes('About'), 'URL match');
  await f.search('7'); check(f.el('#cliredas-top-pages tbody').innerHTML.includes('No pages match'), 'Numeric columns are not searched');
  await f.search('.*'); check(f.el('#cliredas-top-pages tbody').innerHTML.includes('No pages match'), 'Query is literal, not a regular expression');
  await f.el('#cliredas-page-query-clear').fire('click'); check(f.document.activeElement === f.el('#cliredas-page-query') && f.el('#cliredas-page-query').value === '', 'Clear restores focus and only resets query');
  await f.el('.sort-sessions').fire('click'); check(f.el('.heading-sessions').attributes['aria-sort'] === 'descending', 'Sorting retains aria-sort');
  check(f.el('#cliredas-sort-status').textContent.includes('Sorted sessions descending'), 'Sorting announces its result');
  await f.search('o'); check(f.el('#cliredas-top-pages tbody').innerHTML.indexOf('About') < f.el('#cliredas-top-pages tbody').innerHTML.indexOf('Home'), 'Filtering composes with numeric sort');
  await f.el('#cliredas-page-query').fire('keydown', { key: 'Escape', preventDefault() {} });
  check(f.el('#cliredas-page-query').value === '' && f.el('.heading-sessions').attributes['aria-sort'] === 'descending', 'Escape keeps the sort preference');
  check(JSON.stringify(f.cfg.initialReport) === original && f.calls === 0 && f.el('#cliredas-export-range').value === 'last_7_days', 'Local operations never mutate analytics or export parameters');
  const unknown = report();
  unknown.top_pages = [{title:'Unknown',url:'/unknown'}, {title:'Zero',url:'/zero',sessions:0}, {title:'Known',url:'/known',sessions:4}];
  f.cfg.initialReport = unknown; f.api.renderAll(unknown);
  check(f.el('#cliredas-top-pages tbody').innerHTML.includes('<td>—</td>'), 'Unknown row metrics remain unavailable');
  check(f.el('#cliredas-top-pages tbody').innerHTML.lastIndexOf('Unknown') > f.el('#cliredas-top-pages tbody').innerHTML.lastIndexOf('Zero'), 'Unknown values sort after known zeros descending');
  await f.el('.sort-sessions').fire('click');
  check(f.el('#cliredas-top-pages tbody').innerHTML.lastIndexOf('Unknown') > f.el('#cliredas-top-pages tbody').innerHTML.lastIndexOf('Known'), 'Unknown values sort after known values ascending');
  await f.el('.sort-sessions').fire('click');
  const limit = report(); limit.top_pages = Array.from({length: 26}, (_, i) => ({ title: 'Page ' + i, url: '/p' + i, sessions: i })); f.cfg.initialReport = limit; f.api.renderAll(limit); await f.search('Page 25');
  check(f.el('#cliredas-page-count').textContent.includes('0 of 25'), 'Filtering is limited before matching');
  await f.search('about'); const next = report(); next.top_pages[0].sessions = 100; f.cfg.initialReport = next; f.api.renderAll(next);
  check(f.el('#cliredas-page-count').textContent.includes('1 of 3'), 'Report replacement preserves the query');
  await f.range('last_30_days'); check(f.el('#cliredas-page-query').value === 'about' && f.el('#cliredas-export-range').value === 'last_30_days', 'Successful range change preserves query and updates export range');
  f.respond(async () => { throw new Error('sensitive transport error'); }); await f.range('last_90_days');
  check(f.el('#cliredas-date-range').value === 'last_30_days' && f.el('#cliredas-page-query').value === 'about', 'Failed range restores selection and keeps query');
  check(!f.el('#cliredas-notice p').textContent.includes('sensitive') && f.el('#cliredas-report-content').attributes['aria-busy'] === 'false', 'Safe recovery clears busy state');
  const reload = fixture(f.storage); check(reload.el('#cliredas-page-query').value === '' && reload.el('.heading-sessions').attributes['aria-sort'] === 'descending', 'Reload resets query but keeps saved sorting');
  check(!Object.keys(f.storage).some(key => key.includes('query') || key.includes('filter')), 'Query is not persisted');
  const zero = report(); Object.keys(zero.totals).forEach(key => zero.totals[key] = 0); zero.timeseries[0].sessions = zero.timeseries[0].users = 0; zero.devices = {desktop:0}; zero.traffic_sources = {direct:0}; zero.top_pages = [];
  f.api.renderAll(zero); check(f.el('.card-sessions .cliredas-kpi-value').textContent === '0' && f.el('.card-sessions .cliredas-kpi-delta').textContent.includes('-100'), 'Real zero KPIs retain negative deltas');
  check(!f.el('#cliredas-kpis-message').hidden && f.el('#cliredas-top-pages tbody').innerHTML.includes('No pages for this period'), 'Zero and empty states are explained');
  check(f.el('#cliredas-trend-wrap').hidden && f.el('#cliredas-sources-wrap').hidden && f.charts.every(chart => chart.destroyed), 'Zero datasets destroy and hide charts');
  check(f.el('#cliredas-trend-data tbody').innerHTML.includes('2026-10-31') && f.el('#cliredas-trend-data tbody').innerHTML.includes('<td>0</td>'), 'Zero-valued chart dates remain in the accessible table');
  f.api.renderAll(report()); check(!f.el('#cliredas-trend-wrap').hidden && !f.el('#cliredas-sources-wrap').hidden && f.charts.length === 4, 'Charts recover after an empty report');
  const missing = report(); delete missing.totals.users; delete missing.devices; missing.traffic_sources_unavailable = true; missing.timeseries = [];
  f.api.renderAll(missing); check(f.el('.card-users .cliredas-kpi-value').textContent === '—' && f.el('.card-users .cliredas-kpi-delta').hidden, 'Missing metric is unavailable, never zero');
  check(f.el('#cliredas-devices tbody').innerHTML.includes('unavailable') && f.el('#cliredas-traffic-sources tbody').innerHTML.includes('unavailable'), 'Missing and partial blocks are unavailable');
  check(f.el('#cliredas-trend-data tbody').innerHTML.includes('No chart activity'), 'Empty trend is explained');
  const unavailable = {...report(), source:'unavailable'}; f.api.renderAll(unavailable);
  check(f.el('#cliredas-top-pages tbody').innerHTML.includes('unavailable') && f.el('#cliredas-trend-data tbody').innerHTML.includes('unavailable'), 'Unavailable source overrides nonempty payloads');
  for (const value of [null, '', ' ', false, '0x10', -1, Infinity]) check(!f.api.blockState({totals:{sessions:value}}, 'totals').includes('ready'), 'Invalid numeric values are not activity');
  const html = report(); html.top_pages[0].title = '<script>unsafe()</script>'; f.cfg.initialReport = html; await f.search('<script>');
  check(f.el('#cliredas-top-pages tbody').innerHTML.includes('&lt;script&gt;') && !f.el('#cliredas-top-pages tbody').innerHTML.includes('<script>'), 'HTML-like values are safely escaped and searchable');
  const noChart = fixture({}, false); check(noChart.el('#cliredas-trend-wrap').hidden && noChart.el('#cliredas-trend-message').textContent.includes('data table') && noChart.el('#cliredas-trend-data tbody').innerHTML.includes('2026-10-31'), 'Missing Chart.js preserves exact values and recovery text');
  for (const status of [401,403,409]) { const auth = fixture(); auth.respond(async () => ({status, json:async () => {throw new Error('secret body');}})); await auth.range('last_30_days'); check(auth.cfg.initialReport.source === 'unavailable' && auth.el('#cliredas-trend-wrap').hidden && auth.el('#cliredas-notice p').textContent === 'Access changed.', 'Access/context failures clear old analytics: ' + status); }
  const parse = fixture(); parse.respond(async () => ({status:200,json:async () => {throw new SyntaxError('secret body');}})); await parse.range('last_30_days'); check(parse.cfg.initialReport.source === 'ga4' && parse.el('#cliredas-notice p').textContent === 'Reload failed safely.', 'Invalid JSON preserves eligible data and shows safe guidance');
  const busy = fixture(); let finish; busy.respond(() => new Promise(resolve => {finish=resolve;})); const flight = busy.range('last_30_days'); check(busy.el('#cliredas-report-content').attributes['aria-busy'] === 'true' && busy.el('#cliredas-status').textContent.includes('previous'), 'Pending range explains retained content'); busy.api.stopPolling(); finish({status:200,json:async()=>({success:true,data:{report:zero}})}); await flight; check(busy.cfg.initialReport.totals.sessions === 10, 'Obsolete range response is ignored');
  const background = fixture(); await background.search('about'); const pending = report(); pending.freshness = {pending:true, context_id:'context', retained_until:10000, state:'refreshing',label:'Refreshing'}; background.cfg.initialReport = pending; background.api.renderAll(pending); background.respond(async()=>({status:200,json:async()=>({success:true,data:{report:{...report(),freshness:{...pending.freshness,pending:false,state:'fresh',label:'Fresh'}}}})})); background.api.watchReport(pending,'last_7_days'); await background.tick(5000); check(background.el('#cliredas-page-count').textContent.includes('1 of 3') && background.cfg.initialReport.freshness.state === 'fresh', 'Background success preserves filter and replaces report');
  const sample = report(); sample.source='mock'; background.api.renderAll(sample); check(background.el('#cliredas-freshness').textContent.startsWith('Sample data'), 'Sample label overrides real freshness label');
  const dates = fixture({}, true, true); await dates.search('about');
  const displayed = dates.el('#cliredas-displayed-period').textContent;
  await dates.range('custom');
  check(dates.calls === 0 && !dates.el('#cliredas-custom-dates').hidden && dates.el('#cliredas-start-date').value === '2026-11-01', 'Custom selection opens a draft from the displayed dates without fetching');
  dates.el('#cliredas-start-date').value = '2024-02-01'; dates.el('#cliredas-end-date').value = '2024-02-29';
  check(dates.el('#cliredas-export-range').value === 'last_7_days' && dates.el('#cliredas-displayed-period').textContent === displayed, 'Editing a draft never relabels report or export');
  const customPeriod = {key:'custom',dates:{startDate:'2024-02-01',endDate:'2024-02-29'},comparison:{startDate:'2024-01-03',endDate:'2024-01-31'},days:29,timezone:'Europe/Chisinau'};
  dates.respond(async () => ({status:200,json:async()=>({success:true,data:{report:report(),period:customPeriod}})}));
  dates.el('#cliredas-end-date').focus(); await dates.el('#cliredas-period-form').fire('submit', {preventDefault(){}});
  check(dates.calls === 1 && dates.requests[0].get('cliredas_start') === '2024-02-01' && dates.requests[0].get('cliredas_end') === '2024-02-29', 'Apply sends the validated inclusive custom selection');
  check(dates.cfg.selectedPeriod === customPeriod && dates.el('#cliredas-export-expected_comparison_start').value === '2024-01-03' && dates.el('#cliredas-export-range').value === 'custom', 'Applied dates and comparisons update export only after success');
  check(dates.el('#cliredas-page-query').value === 'about' && dates.document.activeElement === dates.el('#cliredas-end-date'), 'Custom replacement preserves filter and focus');
  for (const [start,end] of [['2023-02-29','2023-03-01'],['2024-01-01','2025-01-01'],['2026-11-08','2026-11-08'],['2024-03-02','2024-03-01'],['','2024-03-01'],['0001-01-01','0001-01-01']]) {
    dates.el('#cliredas-start-date').value=start; dates.el('#cliredas-end-date').value=end;
    await dates.el('#cliredas-period-form').fire('submit',{preventDefault(){}});
    check(dates.calls === 1 && dates.el('#cliredas-date-error').textContent === 'Invalid dates.', 'Invalid custom draft cannot request a report');
  }
  dates.el('#cliredas-start-date').value='2024-01-01'; dates.el('#cliredas-end-date').value='2024-12-31';
  dates.respond(async()=>{throw new Error('fixture network');}); await dates.el('#cliredas-period-form').fire('submit',{preventDefault(){}});
  check(dates.calls===2 && dates.cfg.selectedPeriod===customPeriod && dates.el('#cliredas-start-date').value==='2024-02-01', 'A full leap-year draft is valid and a failed Apply restores the prior applied custom dates');
  const pendingCustom = {...report(),freshness:{pending:true,context_id:'custom-context',retained_until:10000,state:'refreshing'}};
  dates.cfg.initialReport=pendingCustom; dates.respond(async()=>({status:200,json:async()=>({success:true,data:{report:{...pendingCustom,freshness:{...pendingCustom.freshness,pending:false}}}})}));
  dates.api.watchReport(pendingCustom,'custom'); await dates.tick(5000);
  const poll=dates.requests[dates.requests.length-1];
  check(poll.get('action')==='cliredas_report_status' && poll.get('cliredas_start')==='2024-02-01' && poll.get('cliredas_expected_end')==='2024-02-29' && poll.get('cliredas_timezone')==='Europe/Chisinau', 'Custom polling uses applied dates and timezone even while drafts change');
  const failedDraft=fixture({},true,true); await failedDraft.range('custom');
  failedDraft.respond(async()=>{throw new Error('fixture');}); failedDraft.el('#cliredas-apply-dates').focus();
  await failedDraft.el('#cliredas-period-form').fire('submit',{preventDefault(){}});
  check(failedDraft.el('#cliredas-apply-dates').hidden && failedDraft.document.activeElement===failedDraft.el('#cliredas-date-range'), 'Failed first custom Apply returns focus to the visible preset selector');
  const ytd={key:'year_to_date',dates:{startDate:'2026-01-01',endDate:'2026-11-07'},comparison:{startDate:'2025-01-01',endDate:'2025-11-07'},days:311,timezone:'Europe/Chisinau'};
  dates.respond(async()=>({status:200,json:async()=>({success:true,data:{report:report(),period:ytd}})})); await dates.range('year_to_date');
  check(dates.cfg.selectedPeriod===ytd && dates.el('#cliredas-custom-dates').hidden && dates.el('#cliredas-export-expected_comparison_end').value==='2025-11-07', 'YTD loads automatically and displays actual comparison dates');
  check(fixture(dates.storage,true,true).cfg.selectedRange==='last_7_days' && !Object.keys(dates.storage).some(key=>key.includes('date')||key.includes('range')), 'Reload resets dates without a persisted range preference');
  console.log('Dashboard presentation regressions passed (' + checks + ' assertions).');
})().catch(error => { console.error(error); process.exitCode=1; });
