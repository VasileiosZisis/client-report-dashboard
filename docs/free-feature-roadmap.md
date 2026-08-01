# Cliredas Free Feature Roadmap

Status: Forward-looking roadmap for the current WordPress.org repository.

This document defines features that belong in the Free plugin after version
`1.4.0`. It is an actionable product roadmap, not a release schedule or an
implementation specification.

Related documents:

- [Pro feature roadmap](./pro-feature-roadmap.md)
- [Deep research report](./deep-research-report.md)
- [Historical combined build plan](./Client%20Reporting%20Dashboard%20%E2%80%93%20Build%20Plan.md)

## Product boundary

Free must remain a complete, useful, single-site GA4 dashboard. Features shipped
in this repository must work without a license, trial, quota, Pro activation
check, or code delivered by another service.

Free owns:

- One WordPress site and one active GA4 property.
- The current dashboard, supported date presets, KPI comparisons, and report
  blocks.
- CSV export of the built-in dashboard blocks.
- One optional, unbranded weekly digest sent to one recipient.
- Connection setup, diagnostics, caching, security, accessibility, and normal
  maintenance required by those features.

Free does not contain locked implementations, schemas, settings, or partial UI
for Pro-only features. Agency branding, multi-recipient delivery, PDF/HTML
reports, Search Console, multi-property operations, multisite portfolio tools,
and automation APIs belong to the separate Pro repository.

## Delivered baseline

The following recommendations are already delivered and are not backlog items:

| Capability | Status |
|---|---|
| This month, Last month, and Last 90 days | Delivered in `1.1.0` |
| KPI deltas against the previous period | Delivered in `1.2.0` |
| Up to 25 Top pages rows | Delivered in `1.3.0` |
| CSV export for all built-in dashboard blocks | Delivered in `1.4.0` |

The Top pages recommendation is only partially complete because interactive
table sorting is still pending.

## Priority definitions

- **Priority 0:** Complete before adding scheduled reporting.
- **Priority 1:** Retention and reliability work for the next Free releases.
- **Priority 2:** Product polish after the core workflow is stable.

## FREE-01: Sortable Top pages table

**Priority:** 0

**Intent:** Make the existing 25-row table easier to inspect without changing
the report provider or requesting more GA4 data.

**Behavior:**

- Make Page Title, URL, Sessions, Views, and Avg engagement time sortable.
- Preserve provider order until the user explicitly selects a sort.
- Toggle ascending and descending order from each column header.
- Use text-aware sorting for Page Title and URL and numeric sorting for metrics.
- Retain the active sort for the current browser session and reapply it after an
  AJAX date-range change.
- Expose the active direction with `aria-sort`; keyboard users must be able to
  operate every sortable header.
- Keep sorting client-side. Do not add API calls, options, pagination, or stored
  preferences.

**Dependencies:** None beyond the current Top pages table and dashboard script.

**Acceptance criteria:**

- Initial PHP rendering and AJAX rendering have the same default order.
- Every column sorts correctly in both directions, including equal and missing
  values.
- Sorting does not mutate the report payload or interfere with CSV export.
- Header controls remain usable on desktop and mobile without text overlap.

## FREE-02: Weekly admin digest

**Priority:** 1

**Intent:** Create a simple retention feature while reserving branded client
delivery and multi-recipient workflows for Pro.

**Behavior:**

- Provide an administrator-only opt-in setting, disabled by default.
- Support exactly one validated recipient, defaulting to `admin_email`.
- Send every Monday at 08:00 in the WordPress site timezone.
- Use the Last 7 days report and its immediately preceding comparison period.
- Include all four KPIs and deltas, the top five pages, device and traffic-source
  summaries, and a link to the dashboard.
- Send a simple unbranded email through `wp_mail()` with no attachment, template,
  logo, custom message, or external email service.
- Provide next-run status, last-run outcome, a manual test action, and a direct
  way to disable future sends.
- Unschedule the event when disabled or during uninstall, and reschedule it when
  the relevant timezone setting changes.
- Never email sample/mock fallback data. Record a safe failure state when GA4 is
  disconnected, no property is selected, or no real report is available.

**Dependencies:** FREE-03 diagnostics, a reusable provider bootstrap that works
outside wp-admin, and stable cached-report behavior from FREE-04.

**Acceptance criteria:**

- Enabling the feature creates one uniquely prefixed weekly cron event.
- Duplicate events are not created across settings saves or upgrades.
- Test and scheduled sends enforce capability checks and nonces where applicable.
- Recipient changes are sanitized and rejected when invalid.
- Disabling or uninstalling removes all scheduled events and digest-only data.
- Email output escapes external GA4 values and does not disclose credentials,
  tokens, internal API errors, or mock data.

## FREE-03: Setup wizard and connection diagnostics

**Priority:** 0

**Intent:** Reduce OAuth setup friction without taking over the WordPress admin
experience.

**Behavior:**

- Add a rerunnable wizard on the plugin's own settings surface.
- Check the effective public URL, HTTPS, computed redirect URI, client
  credentials, OAuth connection, refresh-token health, property availability,
  and selected property.
- Support the existing Public OAuth base URL for tunnels and reverse proxies.
- Explain each failed check with a concrete corrective action.
- Keep Google Cloud configuration and consent as explicit administrator actions.
- Do not add site-wide advertising or an admin redirect after activation.

**Dependencies:** Existing settings, OAuth, Admin API, and property-selection
services.

**Acceptance criteria:**

- The wizard can start, stop, and resume without corrupting existing settings.
- Connected sites can run diagnostics without reconnecting unnecessarily.
- Non-administrators cannot view credentials or run connection-changing steps.
- Failures distinguish configuration, authentication, permission, quota, and
  property-selection problems without exposing secrets.

## FREE-04: Stale-while-revalidate report caching

**Priority:** 1

**Intent:** Show the last successful report immediately and refresh it without
making normal dashboard use wait on every expired cache entry.

**Behavior:**

- Keep a last-good report separately from freshness metadata.
- Return fresh cache entries immediately.
- Return stale last-good data while scheduling one background refresh when an
  entry expires.
- Use a short lock to prevent duplicate refreshes for the same site, property,
  and range.
- Show whether data is fresh, refreshing, or stale and display the last
  successful refresh time in the site timezone.
- Preserve last-good GA4 data when a refresh fails; do not replace it with mock
  data.
- Keep Clear cache behavior predictable and document whether it removes both
  fresh and last-good entries.

**Dependencies:** Current transient cache index and GA4 provider.

**Acceptance criteria:**

- Initial uncached requests still produce a real report or the current safe
  fallback behavior.
- Expired entries return last-good data without duplicate Google requests.
- Refresh success atomically replaces data and freshness metadata.
- Refresh failure leaves last-good data available and exposes an actionable
  warning to authorized users.
- Multisite cache keys remain isolated by site and property.

## FREE-05: OAuth secret hardening and local diagnostics

**Priority:** 0

**Intent:** Protect stored credentials and create enough local diagnostic state
to support setup, exports, and scheduled reporting safely.

**Behavior:**

- Encrypt client secrets and OAuth tokens at rest using authenticated encryption
  derived from WordPress-managed secret material when the runtime supports it.
- Migrate existing plaintext values transparently after a verified settings read
  or save; never display stored secrets back to the browser.
- Treat decryption failure, including WordPress salt changes, as a reconnect
  condition rather than silently discarding unrelated settings.
- Preserve GA4 read-only as the default and only Free OAuth scope.
- Add token-expiry and refresh-health diagnostics without logging token values.
- Keep a local audit log capped at the latest 100 connect, disconnect, refresh,
  cache-clear, CSV export, and digest events.
- Store only event type, timestamp, actor ID when available, and safe outcome.
  Do not store report contents, credentials, tokens, or external telemetry.

**Dependencies:** Existing settings sanitization, OAuth state handling, and
uninstall cleanup.

**Acceptance criteria:**

- Existing connected installations migrate without forcing a reconnect when
  decryption material is valid.
- Secrets are not present as plaintext in newly stored option values.
- Failed decrypt or token refresh paths are recoverable and actionable.
- Audit retention is bounded and all audit data is removed during uninstall.
- No new request leaves the site except documented Google API calls and enabled
  digest email delivery.

## FREE-06: Dashboard filtering, empty states, and accessibility polish

**Priority:** 2

**Intent:** Improve repeated dashboard use without expanding Free into a general
BI product.

**Behavior:**

- Add a lightweight client-side text filter for the current Top pages rows.
- Add explicit empty states for every built-in report block.
- Keep loading, stale, warning, and error states distinct and announced through
  appropriate live regions.
- Ensure tables, chart controls, export actions, and sorting remain keyboard
  accessible and responsive.
- Preserve raw values and current report interfaces; filtering changes only the
  visible table rows.

**Dependencies:** FREE-01 for sortable-header behavior and FREE-04 for cache
status language.

**Acceptance criteria:**

- Empty and zero-valued reports do not render broken charts or blank tables.
- Filtering and sorting compose correctly and reset predictably when the range
  changes.
- Controls do not overlap at supported mobile and desktop widths.
- Core workflows remain usable without JavaScript where they currently have a
  server-rendered fallback.

## Recommended sequence

1. FREE-01 Sortable Top pages table.
2. FREE-03 Setup wizard and connection diagnostics.
3. FREE-05 OAuth secret hardening and local diagnostics.
4. FREE-04 Stale-while-revalidate report caching.
5. FREE-02 Weekly admin digest.
6. FREE-06 Dashboard filtering, empty states, and accessibility polish.

## Recommended feature packaging coverage

This repository owns the Free recommendations from the research report:

| Recommendation | Roadmap status |
|---|---|
| Additional month and 90-day presets | Delivered in `1.1.0` |
| KPI deltas versus previous period | Delivered in `1.2.0` |
| Twenty-five Top pages rows and sorting | Row count delivered; sorting is FREE-01 |
| Basic CSV export | Delivered in `1.4.0` |
| Simple weekly one-recipient admin digest | Planned as FREE-02 |

