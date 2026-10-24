# Cliredas: Free Roadmap

This document tracks implementation status and delivery order for the Free
plugin. [Product scope](product-scope.md) owns feature placement; the
[Pro roadmap](pro-roadmap.md) covers the separate planned paid add-on.

The current implementation baseline is **1.9.0**, including FREE-04
stale-while-revalidate caching and the FREE-02 weekly owner digest. Additional dashboard
polish, custom dates/year-to-date, chart comparison
overlays, settings portability, and a basic Search Console
overview are **planned, not shipped**.
No future release numbers, dates, or effort estimates are assigned here.

## Completed milestones

The implementation and repository release history establish these milestones.
The [listing changelog](../readme.txt) records releases from 1.4.0 onward; earlier
milestones are recorded in Git history.

| Milestone | Implemented capabilities |
|---|---|
| Initial dashboard, through 1.0.1 | OAuth authorization/refresh and GA4 property selection; four KPI cards; Sessions/Users chart; Top pages, devices, and traffic sources; dashboard AJAX with initial localized data; optional Editor access; locally bundled chart assets; transient caching with a default 15-minute TTL, indexed cache clearing, and uninstall cleanup. |
| 1.1.0: Expanded date presets | This month, Last month, and Last 90 days alongside Last 7 days and Last 30 days; public OAuth base URL support for tunnels/reverse proxies. |
| 1.2.0: KPI comparisons | Previous-period KPI totals and percentage deltas, including aligned calendar comparisons. |
| 1.3.0: Top pages depth | Up to 25 Top pages rows. |
| 1.4.0: CSV export | Protected export of all built-in dashboard blocks for the current range, sample/fallback labeling, and spreadsheet formula-injection protection. |
| 1.5.0: Top pages sorting (FREE-01) | Accessible client-side sorting for every column, provider order until a sort is selected, and persistence across date changes/browser visits. |
| 1.6.0: Setup and diagnostics (FREE-03) | Rerunnable setup assistant; public URL, OAuth configuration, token-health, and property-access diagnostics; shared GA4 API client for settings requests. |
| 1.7.0: Security and maintenance (FREE-05) | Authenticated OAuth secret/token encryption where supported, legacy migration and recovery guidance, local diagnostics, administrator-only audit history capped at 100 events, safe OAuth recovery messages, scoped dismissible upgrade announcements, and local release notes. |
| 1.8.0: Report freshness and recovery (FREE-04) | Bounded last-good GA4 reports, background refresh with renewable locks and failure cooldowns, freshness status and automatic updates, exact-date/connection cache isolation, CSV freshness metadata, and lifecycle cleanup. |
| 1.9.0: Weekly owner digest (FREE-02) | Administrator opt-in for one recipient; fresh-only plain-text summaries, protected test/disable controls, local Monday scheduling, duplicate prevention, safe outcomes/audit events and lifecycle cleanup. |

Encryption uses Sodium secretbox or OpenSSL AES-256-GCM when available. Hosts
without either retain plaintext compatibility with a warning. Security salt or
site-context changes can require credential recovery; encryption is not a claim
of protection against a compromised installation or all backup exposure.

Current KPI comparisons use previous-period totals; the chart does not yet show
a previous-period series. Current caching uses a bounded last-good store and background stale recovery
introduced in 1.8.0. Disconnected or failed requests can show
explicitly identified mock data; CSV exports label sample/fallback output.
Background reporting must never treat that fallback as real analytics.

## Planned delivery order

Historical feature IDs are retained so earlier references remain understandable.
The order below determines delivery, not the numeric ID.

1. **FREE-06:** Dashboard filtering, empty states, and accessibility polish.
2. **FREE-07:** Custom dates and year-to-date for existing dashboard metrics.
3. **FREE-08:** Previous-period chart overlay.
4. **FREE-09:** Basic non-secret settings import/export.
5. **FREE-10:** Basic Search Console overview (later).

Trustworthy reporting is the Free foundation: connection diagnostics are already
delivered in FREE-03/FREE-05; freshness status and last-good recovery are
delivered in FREE-04 (1.8.0). The owner digest builds on that work in 1.9.0 to create recurring
value and encourage continued use; it does not depend on custom dates or chart
overlays.

### FREE-04: Stale-while-revalidate report caching

**Status:** Implemented in 1.8.0; Free foundation.

**Implemented policy:** Fresh for 15 minutes by default; last-good data is
eligible for at most 24 hours from its successful fetch and only for identical
resolved dates. One non-autoloaded, conditionally updated `cliredas_report_cache`
option separates successful payloads from refresh-attempt metadata and retains
at most 20 contexts. Two-minute renewable leases prevent concurrent fetching;
failures use a five-minute cooldown. Single WP-Cron refresh events run on demand;
daily cleanup removes expired inactive records. An open dashboard polls cached
status every five seconds for up to three minutes, pausing while hidden.

Google permission/property/credential failures invalidate retained reports.
Clear Cache, connection/access changes, deactivation and uninstall remove owned
work and invalidate in-flight publication. Legacy entries are discarded rather
than promoted. Background refresh depends on WP-Cron execution.

Provider/status/export regressions are documented in [tests](../tests/README.md).

**Purpose:** Show the last successful real report promptly while refreshing an
expired entry, and retain usable data when a refresh fails.

**Scope and dependencies:** Build on the current GA4 provider, transient index,
and cache-clear tools. Keep last-good data separately from freshness metadata,
schedule one background refresh per report context, and use a short lock to
avoid duplicate Google requests. Make the provider available to background work
without relying on wp-admin bootstrap.

**Acceptance criteria:**

- Fresh entries return immediately; stale entries return last-good GA4 data
  while one refresh is pending. An uncached request returns real data or an
  explicitly identified fallback/error, never fabricated success.
- Cache identity includes site, property, and resolved dates. Access/connection
  changes cannot expose a report from an old context.
- Successful refreshes replace data and freshness metadata together; failures
  preserve last-good data and show actionable status to authorized users.
- Show fresh, refreshing, and stale states plus the last successful refresh time
  in the site timezone. Last-good retention is bounded to 24 hours from retrieval.
- Clear cache removes both fresh and last-good entries; the next load fetches
  again. Expired locks recover, and refresh work/retained data are cleaned up
  when no longer valid or on uninstall.

### FREE-02: Optional weekly, unbranded owner digest

**Status:** Implemented in 1.9.0.

**Implemented policy:** Explicit administrator opt-in, one saved recipient,
plain-text mail and fresh complete real GA4 reports only. Mondays at 08:00 use
the site timezone, with DST-safe single-event scheduling. Late work may run
only on the scheduled Monday; missed Mondays are skipped. Test sends do not
enable or advance the schedule. Atomic claims/leases prevent duplicate attempts;
unknown mail outcomes are not automatically retried. Settings distinguishes mail
handoff from inbox delivery and exposes next-run plus weekly/test outcomes.

**Purpose:** Create recurring value and encourage continued use through a simple
owner summary, while leaving branded client-report composition and delivery
operations to the separate add-on.

**Scope and dependencies:** Use delivered FREE-03 setup diagnostics, FREE-05
security/audit services, and FREE-04's background-capable provider/cache. Provide
administrator-only opt-in, disabled by default, with one validated recipient
defaulting to `admin_email`. Send Mondays at 08:00 in the WordPress site timezone
using Last 7 days and its preceding comparison period through `wp_mail()`.

**Acceptance criteria:**

- Include all four KPIs/deltas, the top five pages, device/source summaries, and
  a dashboard link in a simple unbranded email. Include no attachments, logo,
  custom template/message, or external email-service dependency.
- Show next-run status, last-run outcome, a protected test-send action, and a
  direct disable control. Validate recipients and escape external GA4 values.
- Create one uniquely prefixed scheduled event, prevent duplicate sends, and
  reschedule safely when timezone/daylight-saving changes affect local timing.
  Explain that actual delivery depends on WP-Cron execution and mail transport.
- Do not email mock data. Missing connections/properties, unavailable real
  reports, or data outside the documented freshness policy produce safe failure
  outcomes without leaking secrets or raw upstream errors.
- Add bounded, non-sensitive digest outcomes to local audit history. Distinguish
  mail handoff from confirmed inbox delivery.
- Disabling removes future events; deactivation stops scheduled work; uninstall
  removes digest events and owned data. Existing dashboard use remains available.

### FREE-06: Dashboard filtering, empty states, and accessibility polish

**Status:** Planned; next after FREE-02 (1.9.0).

**Purpose:** Make repeated dashboard use clear and accessible without expanding
the built-in report into a general BI interface.

**Scope and dependencies:** Reuse delivered FREE-01 sorting and FREE-04 freshness
states. Add a lightweight client-side text filter for the current Top pages,
explicit empty states for every block, and clearer loading/warning/error feedback.

**Acceptance criteria:**

- Filtering composes with sorting and affects only visible rows; it does not
  change provider data, KPI values, or CSV export contents.
- Document and apply predictable filter/reset behavior when the range changes.
- Zero-valued and empty reports do not render broken charts or blank unexplained
  tables. Stale data, mock data, loading, and failures remain distinguishable.
- Sort headers retain `aria-sort`; controls work by keyboard with visible focus,
  meaningful screen-reader announcements, and no overlap at mobile/desktop widths.
- Preserve existing server-rendered fallbacks and avoid extra API requests for
  local table operations.

### FREE-07: Custom dates and year-to-date

**Status:** Planned; follows FREE-06. Moved from the former paid date-controls scope.

**Purpose:** Let users inspect the period relevant to them by selecting custom
start/end dates for the existing dashboard metrics without an add-on.

**Scope and dependencies:** Extend the existing five presets with custom inclusive
start/end dates and year-to-date. Build on FREE-04 cache isolation and reuse the
same resolved period across PHP rendering, AJAX, providers, comparisons, and CSV.
A new dropdown entry alone is insufficient: the current providers resolve fixed
range keys and must support the new periods when this feature is implemented.

**Acceptance criteria:**

- Retain the five existing presets and their current comparison semantics.
- Validate dates on the server as well as in controls; handle malformed,
  reversed, future, one-day, leap-year, and timezone-boundary selections.
- Define and document comparison periods for the new ranges before shipping;
  show actual dates consistently in the dashboard and export.
- Distinct custom periods never reuse the wrong cache entry, and switching
  ranges cannot leave old data labeled as a new period.
- Preserve existing report consumers through compatible changes and explain
  unavailable GA4 data without presenting mock data as real results.

### FREE-08: Previous-period chart overlay

**Status:** Planned; follows FREE-07. Moved from the former paid trend-insights scope.

**Purpose:** Make traffic trends comparable alongside the existing KPI deltas.

**Scope and dependencies:** Add an optional previous-period series to the
Sessions/Users chart using FREE-07's comparison periods and FREE-04's caching.
Previous-period timeseries retrieval is new work; the delivered KPI comparison
totals do not supply this series.

**Acceptance criteria:**

- Both chart metrics support the overlay, with clearly labeled periods, a
  readable legend, and distinguishable series beyond color alone.
- Align periods by relative day while retaining actual dates in tooltips;
  missing dates and unequal calendar periods are handled explicitly.
- Chart and KPI comparisons refer to the same periods. Zero/missing data and
  failures do not imply a fabricated comparison.
- Range/metric changes refresh both series consistently; cached responses avoid
  unnecessary repeated requests.
- The toggle is keyboard accessible, and turning it off preserves the ordinary
  single-period chart.

### FREE-09: Basic non-secret settings import/export

**Status:** Planned; follows FREE-08. Basic Free settings transfer is no longer
reserved for Pro.

**Purpose:** Reuse ordinary Free configuration through manual file transfer
without copying a site's identity, connection, or delivery permissions.

**Scope and dependencies:** Export/import versioned JSON containing an allow-list
of supported non-secret Free settings. Build on the settled settings from the
preceding features and the portability boundary in [product scope](product-scope.md).
Do not serialize the complete settings option or introduce a second paid format
for ordinary Free settings.

**Acceptance criteria:**

- Validate file size, format/version, allowed fields/types, capabilities, and
  nonce protection before applying changes. Never execute imported code or
  fetch resources specified by an imported file.
- Preview supported changes and explain invalid, skipped, or incompatible
  input; reject unsafe imports without partially corrupting existing settings.
- Exclude credentials/tokens, connection state, redirect URLs, property
  bindings, report/cache data, diagnostics, audit history, user preferences,
  recipient addresses, and enabled schedules.
- Keep destination credentials/property untouched. Do not silently enable
  email or change access permissions; require explicit administrator choice
  for imported access settings.
- Round-trip supported configuration between compatible sites. Explain that
  basic file transfer does not include Pro templates, branding, remote copying,
  synchronization, or managed update/merge workflows.

### FREE-10: Basic Search Console overview

**Status:** Planned; later, after FREE-09.

**Purpose:** Make the Free dashboard more useful for understanding search
performance alongside traffic and engagement.

**Scope and dependencies:** Build on FREE-04's freshness/last-good foundation,
the Free date controls, and existing connection-diagnostics patterns. Add an
optional overview for one selected Search Console property with clicks,
impressions, CTR, and average position for the selected reporting period.
Keep the connector, property selection, caching, and diagnostics needed for this
overview fully functional in Free. Detailed query/landing-page breakdowns and
composed SEO reports remain planned Pro extensions.

**Acceptance criteria:**

- Require explicit administrator opt-in and separate read-only Search Console
  permission, with service/data disclosures and Terms of Use/Privacy Policy
  links before connection. Do not expand existing GA4 consent automatically.
- Provide property selection and actionable permission/connection diagnostics.
  GA4 remains usable when Search Console is disabled, disconnected, or unavailable.
- Show the four overview metrics for the actual supported period, with clear
  labels, empty/error states, source-specific freshness, and last-good status.
  Explain availability differences rather than silently substituting dates or
  implying Search Console and GA4 totals measure the same thing.
- Isolate caches by site, source, property, and dates. Reconnecting or revoking
  Search Console access must not damage GA4 credentials, selection, or reports.
- Apply dashboard viewing permissions and accessible rendering. The overview
  and its supporting integration require no Pro add-on or license.

## Shared delivery and release requirements

Preserve existing settings and reporting behavior through compatible changes.
Keep one active GA4 property per site, the four built-in KPIs, CSV access, and
the default GA4 read-only scope. The optional Search Console overview requests
its own read-only permission only when configured. Pro must not be required for
any Free capability.

Use consistent `cliredas_` identifiers, scoped assets, capability checks as well
as nonces, translated/escaped output, early input validation, and hooked request
processing. Keep cache/background work bounded, site/property aware, recoverable,
and removable. Never record secrets or complete reports in operational logs.

Before releasing each feature:

- Verify PHP/server-rendered and AJAX behavior, authorization failures, invalid
  input, disconnected/revoked access, upstream failures, mock/fallback labeling,
  empty/zero reports, cache behavior, and CSV consistency as applicable.
- Exercise timezone/calendar boundaries, concurrent refresh/send behavior,
  upgrades, deactivation, uninstall, and relevant per-site multisite isolation.
- Check keyboard/screen-reader use, responsive layouts, PHP/JavaScript syntax,
  PHPCS, and Plugin Check against the distributable. Use the existing security
  and release-notice regressions described in [tests](../tests/README.md).
- Keep README, listing text, screenshots, FAQs, the coming-soon page, changelog,
  and version metadata aligned with implemented functionality. Release version
  changes accompany actual releases, not planning-document edits.

Repeatable provider/cache, scheduling, and browser regression tooling can be
added alongside the corresponding implementation. Tooling is engineering work,
not an additional end-user capability or a claim that those tests already exist.
