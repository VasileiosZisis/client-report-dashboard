# Cliredas Pro Feature Roadmap

Status: Forward-looking roadmap for a future separate paid add-on repository.

The Pro repository slug, plugin slug, text domain, package name, and commercial
identifiers are **TBD**. They must be finalized before repository bootstrap. This
document uses "Pro" only as a product-tier label.

Related documents:

- [Free feature roadmap](./free-feature-roadmap.md)
- [Deep research report](./deep-research-report.md)
- [Historical combined build plan](./Client%20Reporting%20Dashboard%20%E2%80%93%20Build%20Plan.md)

## Product contract

Pro is a separate add-on for agency workflow, branded delivery, additional data
sources, and operations across properties or sites. It extends a fully functional
Free plugin; it does not unlock dormant implementations shipped in the
WordPress.org repository.

| Area | Free | Pro |
|---|---|---|
| GA4 dashboard | Current built-in metrics and blocks | Flexible metrics, conversions, and composed reports |
| Date controls | Five current presets | Custom ranges and advanced calendar ranges |
| Delivery | One unbranded weekly recipient | Branded schedules and multiple recipients |
| Exports | Built-in CSV export | Branded PDF and HTML output |
| Branding | Cliredas admin experience | Agency logo, colors, naming, and report footer |
| Property model | One active property per site | Multiple properties and switching |
| Site model | Independent per-site operation | Multisite defaults, overrides, and portfolio views |
| Integrations | GA4 read-only | Search Console, read-only APIs, and signed webhooks |

Existing Free capabilities and raw GA4 report access remain usable when Pro is
inactive, expired, incompatible, or unable to reach a licensing service.

## Priority definitions

- **Priority 0:** Repository and commercial foundation required before features.
- **Priority 1:** Core paid value for report composition, branding, and delivery.
- **Priority 2:** SEO and multi-property expansion.
- **Priority 3:** Agency-scale operations and automation.

## PRO-01: Separate add-on foundation

**Priority:** 0

**Intent:** Establish a failure-safe paid plugin that extends compatible Free
versions without changing Free behavior.

**Behavior:**

- Finalize the repository slug, plugin slug, text domain, namespace/prefix,
  package identifier, and support/update endpoints before scaffolding.
- Package Pro as a separate installable plugin with its own activation,
  deactivation, migration, and uninstall paths.
- Verify the Free plugin and a supported `CLIREDAS_VERSION` before registering Pro
  hooks or screens.
- Show a scoped, dismissible admin notice when Free is absent or incompatible;
  never fatal and never modify Free data during the failed load.
- Build against existing Free hooks where they represent real Free behavior,
  including report, range, KPI, dashboard-section, menu-title, capability,
  provider, cache, and OAuth-scope filters.
- Do not add placeholder Pro settings, schemas, or locked UI to the Free
  repository solely to support this add-on.

**Dependencies:** Final package identity and a documented Free compatibility
matrix.

**Acceptance criteria:**

- Pro activates and runs with every declared compatible Free version.
- Missing or incompatible Free produces a recoverable admin state without a PHP
  fatal, redirect loop, or data loss.
- Deactivating Pro restores the unchanged Free dashboard and settings.
- Pro symbols, options, cron hooks, cache keys, scripts, AJAX actions, and routes
  use a distinct finalized prefix.

## PRO-02: Licensing, updates, privacy, and lifecycle

**Priority:** 0

**Intent:** Make the future commercial repository operable without making Free
dependent on its infrastructure.

**Behavior:**

- Keep license storage, validation, commercial update delivery, and related
  network calls inside Pro.
- Document every external service, data field, purpose, privacy policy, and terms
  link used by licensing or delivery infrastructure.
- Fail gracefully when licensing or update services are unavailable. Preserve
  local configuration and never disable Free functionality.
- Define versioned Pro migrations, bounded retry behavior, and complete cleanup
  of Pro-owned data when the administrator chooses full uninstall.
- Ensure expired or invalid licensing cannot corrupt schedules, reports, Free
  options, or OAuth credentials.

**Dependencies:** PRO-01 and finalized commercial service design.

**Acceptance criteria:**

- Activation, validation, update, outage, expiry, deactivation, and uninstall
  paths are independently testable.
- Network requests are documented, authenticated, time-bounded, and contain no
  analytics data unrelated to the requested service.
- A licensing outage leaves Free operational and gives administrators a clear,
  non-site-wide status on Pro screens.

## PRO-03: Saved templates and report composition

**Priority:** 1

**Intent:** Create one reusable report definition for on-screen previews, email,
HTML, and PDF delivery.

**Behavior:**

- Support saved templates for owner, agency, and SEO-oriented reports.
- Let authorized administrators choose report sections, order, labels, notes,
  date behavior, and available metrics.
- Version template data so later feature additions and migrations remain safe.
- Preview templates with current data without sending or exporting.
- Keep templates owned by Pro and avoid changing the Free report-array contract
  unless a change is independently useful to Free.

**Dependencies:** PRO-01 and a stable internal report composition model.

**Acceptance criteria:**

- Templates can be created, copied, edited, previewed, and deleted with nonce and
  capability protection.
- Invalid or unavailable sections degrade predictably without corrupting the
  template.
- Deactivating Pro does not alter Free report output.

## PRO-04: Advanced ranges, metrics, and conversion KPIs

**Priority:** 1

**Intent:** Add analytical flexibility beyond the stable Free dashboard.

**Behavior:**

- Add custom start/end dates and advanced calendar ranges such as year-to-date.
- Add configurable KPI and report-section selection.
- Support GA4 key events/conversions and conversion-rate KPIs after validating
  metric compatibility.
- Add provider requests only for metrics used by the active report/template.
- Keep the five Free presets and four Free KPIs unchanged and available.

**Dependencies:** PRO-03 and the current Free range, KPI, report, and provider
extension points.

**Acceptance criteria:**

- Date validation handles reversed dates, future dates, site timezones, leap
  years, and comparison-period calculation.
- Unsupported GA4 metric combinations fail with actionable errors.
- Pro metrics render consistently in previews and every enabled delivery format.

## PRO-05: White-label controls

**Priority:** 1

**Intent:** Let agencies present reports under their own identity.

**Behavior:**

- Support an agency logo, restrained color controls, menu title, report footer,
  sender identity, and branding visibility settings.
- Apply branding through Pro-owned settings and assets to Pro report surfaces and
  delivery formats.
- Validate uploads through WordPress media APIs and maintain accessible color
  contrast.
- Do not inject public-site attribution or links without explicit opt-in.

**Dependencies:** PRO-03 and the current `cliredas_menu_title` filter.

**Acceptance criteria:**

- Branding previews match email, HTML, and PDF output within format limits.
- Removing or deleting an asset falls back safely without broken report output.
- Branding changes do not modify Free plugin files or global wp-admin styling.

## PRO-06: Branded multi-recipient scheduled email reports

**Priority:** 1

**Intent:** Automate recurring client delivery beyond the single Free digest.

**Behavior:**

- Support weekly and monthly schedules, multiple validated recipients, template
  selection, subject, introduction, notes, sender settings, and branding.
- Provide next-run, last-run, delivery outcome, test-send, pause, resume, and
  manual-send controls.
- Generate reports from the same composed and cached data used by previews.
- Prevent duplicate sends with idempotent schedule/run identifiers.
- Do not send mock data; retain a bounded local delivery log without storing
  credentials or full report bodies.

**Dependencies:** PRO-03, PRO-05, and the reliable provider/cache foundation from
the Free roadmap.

**Acceptance criteria:**

- Site-timezone and daylight-saving changes do not create skipped duplicates.
- Recipient validation, authorization, unsubscribe/disable behavior, and failure
  reporting are covered for test and scheduled sends.
- A failed recipient or mail transport does not mark unrelated deliveries as
  successful.

## PRO-07: Branded PDF and HTML reports

**Priority:** 1

**Intent:** Produce client-ready attachments and browser-viewable reports from
the shared template model.

**Behavior:**

- Generate branded PDF and HTML from the same report composition used by email.
- Support notes, selected sections, date/comparison context, and generation time.
- Keep output self-contained and avoid executable third-party code delivery.
- Define storage, expiration, authorization, and cleanup for generated files.

**Dependencies:** PRO-03 and PRO-05; email attachment support may additionally
depend on PRO-06.

**Acceptance criteria:**

- PDF and HTML values match the preview for the same cached report and template.
- Long titles, 25-row tables, empty sections, Unicode, page breaks, and mobile
  HTML layout render correctly.
- Generated files cannot be accessed by unauthorized users or guessed URLs.

## PRO-08: Google Search Console module

**Priority:** 2

**Intent:** Add a focused SEO report without expanding Cliredas into a general
connector platform.

**Behavior:**

- Request Search Console permission separately and only when the module is
  enabled by an administrator.
- Support clicks, impressions, CTR, average position, top queries, and top search
  landing pages.
- Provide site/property selection and clear permission diagnostics.
- Add SEO sections to templates, previews, and enabled delivery formats.

**Dependencies:** PRO-03, optional OAuth scope extension, and a dedicated Search
Console client/provider owned by Pro.

**Acceptance criteria:**

- Existing GA4-only connections continue working without the added scope.
- Enabling, revoking, and reconnecting Search Console does not damage GA4 tokens
  or reports.
- Date ranges and dimensions respect Search Console API limits and disclose data
  availability differences.

## PRO-09: Multiple GA4 properties

**Priority:** 2

**Intent:** Let one WordPress installation report on more than one authorized GA4
property.

**Behavior:**

- Store an authorized property collection and an explicit active context.
- Allow property switching in Pro report and template workflows.
- Scope caches, schedules, templates, logs, and generated files by property.
- Preserve the Free plugin's single selected property when Pro is inactive.

**Dependencies:** PRO-03 and a versioned Pro property/settings model.

**Acceptance criteria:**

- Switching properties never reuses another property's cached or generated data.
- Removed Google access is detected and reported per property.
- Deactivating Pro restores the original Free property selection predictably.

## PRO-10: Multisite and portfolio operations

**Priority:** 3

**Intent:** Support agencies operating many sites from a WordPress network.

**Behavior:**

- Support network activation, network defaults, per-site overrides, and explicit
  network capabilities.
- Provide a portfolio view with safe aggregate status and links into each site.
- Keep credentials and property access isolated unless an administrator
  explicitly configures network-level sharing.
- Make schedules, caches, generated files, and cleanup blog-aware.

**Dependencies:** PRO-09 and a tested multisite data-ownership model.

**Acceptance criteria:**

- Network and site administrators see only operations allowed by their
  capabilities.
- Large networks use bounded queries and background work rather than loading all
  reports synchronously.
- Site deletion, network deactivation, and uninstall clean up only owned data.

## PRO-11: Template propagation and settings import/export

**Priority:** 3

**Intent:** Reduce repeated agency setup across properties and sites.

**Behavior:**

- Export versioned, validated Pro templates and non-secret configuration.
- Exclude credentials, OAuth tokens, license secrets, generated files, and audit
  logs from portable packages.
- Preview changes before import and report skipped or incompatible fields.
- Propagate selected template revisions without overwriting site-specific values
  unless explicitly requested.

**Dependencies:** PRO-03 and, for network propagation, PRO-10.

**Acceptance criteria:**

- Round-trip export/import preserves supported configuration across compatible
  versions.
- Tampered, oversized, malformed, or future-version packages fail closed.
- Imports require explicit capability and nonce checks and never expose secrets.

## PRO-12: Read-only API and signed webhooks

**Priority:** 3

**Intent:** Enable controlled agency automation without exposing unrestricted
analytics or WordPress access.

**Behavior:**

- Add capability-protected, read-only endpoints for current composed reports and
  delivery status.
- Add signed outbound webhooks for completed schedules, delivery failures, and
  explicitly selected report events.
- Provide endpoint-specific credentials, rotation, revocation, retry limits, and
  delivery logs.
- Minimize payloads and never include OAuth credentials, tokens, license data, or
  unrelated WordPress information.

**Dependencies:** PRO-03, PRO-06, and a reviewed authentication/signing model.

**Acceptance criteria:**

- Every endpoint has explicit permission checks, schema validation, pagination
  where needed, and rate limits.
- Webhook signatures prevent tampering and replay within the documented window.
- Retries are bounded and idempotent, and failed destinations can be disabled.

## Recommended sequence

1. PRO-01 Separate add-on foundation.
2. PRO-02 Licensing, updates, privacy, and lifecycle.
3. PRO-03 Saved templates and report composition.
4. PRO-04 Advanced ranges, metrics, and conversion KPIs.
5. PRO-05 White-label controls.
6. PRO-06 Branded scheduled email reports.
7. PRO-07 Branded PDF and HTML reports.
8. PRO-08 Search Console module.
9. PRO-09 Multiple GA4 properties.
10. PRO-10 Multisite and portfolio operations.
11. PRO-11 Template propagation and settings import/export.
12. PRO-12 Read-only API and signed webhooks.

## Recommended feature packaging coverage

The future Pro repository owns the paid recommendations from the research report:

| Recommendation | Roadmap owner |
|---|---|
| Branded multi-recipient scheduled email reports | PRO-06 |
| PDF/HTML reports with templates and notes | PRO-03 and PRO-07 |
| Full white-labeling | PRO-05 |
| Search Console paid module | PRO-08 |
| Multi-property, multisite, propagation, import/export, API, and webhooks | PRO-09 through PRO-12 |

