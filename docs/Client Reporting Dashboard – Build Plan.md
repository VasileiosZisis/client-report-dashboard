# Cliredas - Client Dashboard for Google Analytics (GA4) – Build Plan (Free → Pro)

This document is the single source of truth for building **Cliredas - Client Dashboard for Google Analytics (GA4)** as a freemium WordPress plugin.

- **Free plugin slug:** `client-report-dashboard`
- **Pro add-on slug:** `client-report-dashboard-pro` (separate plugin that extends Free)
- **Prefix:** `cliredas_` / `CLIREDAS_`
- **Key principle:** Free plugin provides extension points and a stable API so Pro can hook in cleanly.
- **No `if (!function_exists())` wrappers** (WP.org guidance).
- **Settings default:** `allow_editors = 0` on first install.

---

## Repository and conventions

### File structure (Free)

client-report-dashboard.php\
includes/ \
class-cliredas-plugin.php\
class-cliredas-admin-menu.php\
class-cliredas-settings.php\
class-cliredas-dashboard-page.php\
class-cliredas-upgrade-page.php\
class-cliredas-data-provider.php\
class-cliredas-provider-factory.php\
class-cliredas-admin-screens.php\
class-cliredas-assets.php\
class-cliredas-cache-manager.php\
class-cliredas-ga4-auth.php\
assets/ \
css/ \
cliredas-dashboard.css\
js/ \
cliredas-dashboard.js\
vendor/ \
chartjs/ \
chart.umd.min.js\
readme.txt\
readme.md\
uninstall.php

### Naming and constants

- Free detects Pro via:
  - `cliredas_pro_version()` which returns `defined('CLIREDAS_PRO_VERSION') ? constant('CLIREDAS_PRO_VERSION') : false`
- Pro defines:
  - `define('CLIREDAS_PRO_VERSION', 'x.y.z');`

### Settings option

- Option key: `cliredas_settings`
- Defaults include at least:
  - `allow_editors => 0`
  - GA4 auth fields (as milestones progress)

### Extension points (Free → Pro)

Filters/Actions already used/expected:

- `cliredas_loaded`
- `cliredas_init`
- `cliredas_admin_init`
- `cliredas_required_capability`
- `cliredas_menu_title`
- `cliredas_show_powered_by`
- `cliredas_date_ranges`
- `cliredas_kpis`
- `cliredas_dashboard_before_kpis`
- `cliredas_dashboard_after_kpis`
- `cliredas_dashboard_sections`
- `cliredas_data_provider`
- `cliredas_enable_cache`
- `cliredas_cache_ttl`
- `cliredas_cache_cleared`
- `cliredas_ga4_oauth_scopes`

---

## Test environment notes

- You use **symlinks**: repo lives outside WP install, symlinked into `wp-content/plugins/`.
- Prefer `__DIR__` for internal requires inside includes classes (symlink-safe).
- If redirects break with “headers already sent”, set:
  - `WP_DEBUG_DISPLAY` false and use `WP_DEBUG_LOG` true.

---

# FREE VERSION PLAN (with milestones)

## Milestone 0 – Repo bootstrap

**Goal:** project structure, git hygiene, documentation.
**Deliverables:**

- `readme.md` (dev notes)
- `.gitignore` (vendor, node, build, OS junk, etc.)
- Basic plugin headers in `client-report-dashboard.php`

**Tests:**

- Repo clean, plugin folder recognized by WP.

**Commit message:** `chore: initial repo scaffold`

---

## Milestone 1 – Bootstrap + core plugin class

**Goal:** plugin loads via main class; activation sets defaults.
**Implemented:**

- `client-report-dashboard.php` loads `CLIREDAS_Plugin`.
- Activation: creates option `cliredas_settings` with `allow_editors = 0`.

**Tests:**

- Activate plugin, no fatals.
- Option exists with defaults.

**Commit message:** `feat: plugin bootstrap and activation defaults`

---

## Milestone 2 – Admin menu + pages (Dashboard, Settings, Upgrade) + access control

**Goal:** visible admin UI with proper capability checks.
**Implemented:**

- Admin menu class and page renderers.
- Settings option: “Show dashboard to editors” checkbox.
- Dashboard access: admins by default, optionally editors.
- Upgrade page placeholder.

**Tests:**

- Admin: sees Client Report menu + pages.
- Editor: sees Dashboard only when enabled; never sees Settings/Upgrade.
- Direct URL access blocked for unauthorized roles.

**Commit message:** `feat: add admin menu, settings, dashboard and upgrade pages`

---

## Milestone 3 – Dashboard layout scaffold + assets (CSS/JS only on dashboard)

**Goal:** stable HTML layout for KPI cards, chart, tables.
**Implemented:**

- Dashboard markup with date-range selector, KPIs, chart area, tables.
- Enqueue CSS/JS only on dashboard screen.

**Tests:**

- Dashboard renders blocks.
- Plugin assets load ONLY on Client Report dashboard page.
- No JS errors.

**Commit message:** `feat: add dashboard layout scaffolding and enqueue assets`

---

## Milestone 4 – Mock report provider + AJAX endpoint

**Goal:** dashboard loads data via provider shape; range switching works.
**Implemented:**

- `CLIREDAS_Data_Provider` returns mock report:
  - totals, timeseries, top_pages, devices
- `wp_ajax_cliredas_get_report` returns JSON for selected range.
- Nonce verification.

**Tests:**

- Switching range updates KPIs/sections without reload.
- Nonce enforced (tamper test → fails).
- No PHP notices.

**Commit message:** `feat: add mock data provider and ajax report endpoint`

---

## Milestone 5 – Chart.js sessions chart

**Goal:** real chart rendering from timeseries.
**Implemented:**

- Chart.js bundled locally (`assets/vendor/chartjs/chart.umd.min.js`)
- Chart renders and updates on range change.

**Tests:**

- Chart appears, updates with range.
- Works without external CDN.

**Commit message:** `feat: render sessions timeseries using Chart.js`

---

## Milestone 6 – UX polish (loading, error UI, no initial fetch)

**Goal:** better UX and fewer requests.
**Implemented:**

- Loading state disables range selector.
- Error display (inline status / optional WP notice).
- Localized initial report to avoid initial AJAX fetch (if adopted in code).

**Tests:**

- On load, chart renders immediately (if using initialReport).
- Range change shows loading state.
- Simulate AJAX failure (wrong action) → user sees error feedback.

**Commit message:** `chore: polish dashboard UX and reduce initial ajax`

---

## Milestone 7 – Uninstall + helpers + readme.txt + centralized assets

**Goal:** WP.org hygiene + cleaner structure.
**Implemented:**

- `uninstall.php` removes options and cache key index.
- `CLIREDAS_Admin_Screens` helper.
- `CLIREDAS_Assets` helper centralizes enqueue + localization.
- `readme.txt` added for WP.org format.

**Tests:**

- Delete plugin → option removed.
- Dashboard assets still only load on dashboard.
- readme.txt exists.

**Commit message:** `chore: add uninstall, admin screen helper, and centralized assets loader`

---

## Milestone 8 – Provider factory + GA4 connection scaffold UI

**Goal:** make provider swappable and create GA4 settings UI placeholder.
**Implemented:**

- `CLIREDAS_Provider_Factory::get_provider()` + `cliredas_data_provider` filter.
- Settings: GA4 connection section & status flag.
- Dashboard: notice when GA4 not connected.

**Tests:**

- Settings page shows GA4 section.
- Dashboard shows “mock data” notice when not connected.
- Provider override test using MU-plugin snippet changes output.

**Commit message:** `feat: add swappable data provider and GA4 connection settings scaffold`

---

## Milestone 9 – Cache index + Clear Cache tools

**Goal:** caching infrastructure for real GA4 + clear cache buttons.
**Implemented:**

- Provider records transient keys in an option index.
- Clear cache handler via `admin_post_cliredas_clear_cache`.
- Buttons on Dashboard and Settings.
- Notices after clearing (ensure “show once” behavior by removing query arg via history.replaceState or flash meta).

**Tests:**

- Clear Cache (settings) clears and shows notice.
- Clear Cache (dashboard) clears and shows notice.
- Editor cannot run clear cache endpoint.

**Commit message:** `feat: add cache key index and cache clearing actions`

---

## Milestone 10 – GA4 OAuth setup UI + Connect/Disconnect scaffolding

**Goal:** credentials fields + connect button that redirects to Google + callback stub.
**Implemented:**

- Settings fields: Client ID + Client Secret (do not wipe secret when blank).
- Redirect URI shown.
- Connect button uses nonce-protected URL to admin-post action.
- `CLIREDAS_GA4_Auth` registers:
  - `admin_post_cliredas_ga4_connect`
  - `admin_post_cliredas_ga4_oauth_callback` (stub)
  - `admin_post_cliredas_ga4_disconnect`
- IMPORTANT fix: use `wp_redirect()` for external Google OAuth URL (safe_redirect blocks external).

**Tests:**

- Saving settings does NOT end on All Settings.
- Connect button goes to Google consent screen (not wp-admin).
- Callback returns to settings with “callback reached” notice.
- Disconnect resets connection flags.
- No nested forms inside settings form.

**Commit message:** `feat: add GA4 oauth settings and connect/disconnect scaffolding`

---

# Remaining FREE milestones (to finish GA4 integration)

## Milestone 11 – OAuth callback: state validation + code exchange + token storage

**Goal:** real OAuth completion.
**Steps:**

1. Validate callback parameters:
   - If `error` is present (e.g. denied consent), stop and show a useful error notice.
   - Validate presence of `code`.
2. Verify `state` matches stored user meta `cliredas_ga4_oauth_state` (prevents CSRF).
3. Delete the stored state after successful validation (prevents replay).
4. Exchange code for tokens via Google OAuth token endpoint and store:
   - `ga4_access_token`
   - `ga4_token_expires` (Unix timestamp)
   - `ga4_refresh_token` (only update if Google returns one; do not overwrite an existing refresh token with blank)
5. Set `ga4_connected = 1` after successful exchange + storing tokens.
6. Handle errors: missing code, invalid state, token errors (show notices and keep the settings UI stable).

**Tests:**

- Connect → consent → callback sets connected status.
- Refresh token stored; access token stored with expiry.
- Token exchange without `refresh_token` does not wipe the stored refresh token.
- Deny consent → shows useful error notice.
- Tamper state → callback rejected.

**Criteria to move on:** can connect and persist tokens; page shows Connected.

**Commit message:** `feat: implement GA4 oauth callback token exchange and storage`

---

## Milestone 12 – Property listing + selection (Admin API)

**Goal:** user selects GA4 property to report on.
**Steps:**

1. Using stored tokens, call Analytics Admin API:
   - list properties the user can access.
2. Show dropdown in settings.
3. Store `ga4_property_id`.
4. Show status if no property selected.

**Tests:**

- Connected user sees property dropdown populated.
- Selecting property saves and persists.
- If tokens revoked → prompts reconnect.

**Criteria:** property selection working and saved.

**Commit message:** `feat: add GA4 property selection using Admin API`

---

## Milestone 13 – GA4 data provider (Data API v1) for real report

**Goal:** replace mock data with GA4 reports for selected property.
**Steps:**

1. Implement `CLIREDAS_GA4_Data_Provider` (in Free).
2. Ensure provider returns the same report shape used by the dashboard JS (and falls back to mock data with a warning on API failures).
3. Use GA4 Data API `runReport` queries:
   - totals: sessions, total users, pageviews (`screenPageViews`), avg engagement time (computed)
   - timeseries: sessions + total users per day (chart toggle in UI)
   - top pages: title + path + sessions + views + avg engagement time (per page)
   - devices: desktop/mobile/tablet sessions
   - traffic sources: Organic Search / Direct / Referral / Social / Other
4. Add clearer error mapping (permission, invalid property, quota) and include the raw Google error message in the warning.
5. Wire provider via factory logic based on GA4 connected state (keep `cliredas_data_provider` filter as an extension point).
6. Dashboard UX improvements:
   - show-once / human-readable warnings on dashboard when GA4 fetch fails
   - keep "Showing: Last X days" hint in sync with the selected range
   - top pages: stop merging different paths into "/" (only normalize trailing slashes); label "/" as "Landing Page"

**Tests:**

- Dashboard shows real data after connecting + selecting property.
- Range change updates with real values.
- Chart metric toggle switches between Sessions and Total users.
- Top pages show columns: Sessions, Views, Avg engagement time.
- Traffic sources doughnut + table show values and update with range changes.
- “No data” and API failures show usable UI messages.

- API failures include the raw Google error message in the warning (for faster troubleshooting).

**Criteria:** real GA4 data displayed reliably.

**Commit message:** `feat: implement GA4 data provider and wire into dashboard`

---

## Milestone 14 – Caching ON for GA4 provider + cache clear verification

**Goal:** reduce API calls and make results fast.
**Steps:**

1. Enable `cliredas_enable_cache` for GA4 provider by default.
2. Cache key includes property + range (+ blog id for multisite).
3. TTL configurable (default 15 min).
4. Ensure cache index records all keys.
5. Clear Cache clears all variants.

**Tests:**

- Repeated loads hit cached result (log or timestamp proves it).
- Clear Cache forces new fetch.
- Multisite does not leak cache between sites.

**Criteria:** caching works and is controllable.

**Commit message:** `feat: enable caching for GA4 provider and validate cache clearing`

---

## Milestone 15 – WP.org finishing pass (Free v1.0)

**Goal:** publish-ready.
**Steps:**

1. Update `readme.txt` to reflect real GA4 support.
2. Add “External services” disclosure (Google APIs usage).
3. Add screenshots.
4. Verify no PHP notices with `WP_DEBUG` on.
5. Check i18n textdomain consistency.
6. Confirm uninstall cleanup removes settings and cache index.
7. Security review: nonces, caps, sanitization.

**Tests:**

- Fresh install walkthrough works.
- Connect/disconnect stable.
- No console errors.
- Plugin zip installs and activates cleanly.

**Criteria:** ready to submit to WordPress.org.

**Commit message:** `chore: prepare v1.0 release and WP.org documentation`

---

# PRO VERSION PLAN (after Free v1.0)

## Pro Milestone P1 – Pro bootstrap + dependency check

**Goal:** Pro loads only when Free is active.
**Steps:**

1. Separate plugin `client-report-dashboard-pro`.
2. On load, verify Free constants/class exist; otherwise show admin notice and deactivate.
3. Define `CLIREDAS_PRO_VERSION`.

**Tests:**

- Activate Pro without Free → shows notice, does not fatal.
- Activate Free then Pro → Pro hooks enabled.

**Commit message:** `feat: pro bootstrap with free dependency check`

---

## Pro Milestone P2 – Extend date ranges + metrics via hooks

**Goal:** demonstrate extension points.
**Steps:**

1. Add more ranges via `cliredas_date_ranges` (e.g., 90 days, custom).
2. Add KPIs via `cliredas_kpis`.
3. Add additional section blocks via `cliredas_dashboard_sections`.

**Tests:**

- New ranges appear and work.
- New KPIs display.
- Section shows.

**Commit message:** `feat: extend dashboard ranges and metrics in pro`

---

## Pro Milestone P3 – Scheduled email reports

**Goal:** automated reporting.
**Steps:**

1. Add settings for frequency, recipients.
2. Use WP-Cron to schedule.
3. Generate email report from same provider output.
4. Add hooks: `cliredas_email_reports_*`

**Tests:**

- Cron scheduled and sends.
- Manual “send test email” works.
- Unsubscribe / disable works.

**Commit message:** `feat: add scheduled email reports`

---

## Pro Milestone P4 – White-label options

**Goal:** branding controls.
**Steps:**

1. Allow setting logo, menu name, hide upgrade link.
2. Use filters: `cliredas_menu_title`, `cliredas_show_powered_by`, plus new filter for upgrade link.
3. Add CSS override option.

**Tests:**

- Branding changes apply.
- Upgrade link can be hidden.
- No breaking changes to Free.

**Commit message:** `feat: add white-label settings and branding controls`

---

## Pro Milestone P5 – Pro packaging + licensing

**Goal:** commercial readiness.
**Steps:**

1. License key validation (server-side).
2. Update mechanism (custom updater).
3. Documentation and onboarding.

**Tests:**

- License activation flow works.
- Updates delivered.
- No fatal if license server unreachable (graceful).

**Commit message:** `chore: pro licensing and updates`

---
