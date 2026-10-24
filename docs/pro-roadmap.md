# Cliredas: Pro Roadmap

All functionality in this document is **planned, not available in the current
Free plugin**. This roadmap preserves the paid catalog and records delivery
dependencies without promising release dates, future versions, prices, or
effort estimates.

[Product scope](product-scope.md) owns feature placement. The
[Free roadmap](free-roadmap.md) records the implemented **1.9.0** baseline and
upcoming Free additions. Pro is one separate paid add-on; no additional premium
packs are planned.

## Relationship to Free

Pro extends a complete single-site GA4 dashboard into reusable, branded client
reporting and agency operations. It must not take away Free features or unlock
dormant implementations shipped in the WordPress.org plugin.

Custom dates, year-to-date, previous-period chart overlays, and basic non-secret
Free settings transfer belong to Free, alongside the high-priority optional
weekly owner digest and later basic Search Console overview. Pro owns composed
reports, branding, advanced delivery, specialized metrics, detailed Search Console
reporting, property/site operations, and managed portability.

Pro's repository slug, plugin slug, text domain, namespace/prefix, package name,
commercial endpoints, and supported Free versions remain undecided product
identifiers. Finalize them as part of the foundation before repository bootstrap;
historical placeholder slugs are not approved package identities.

## Planned delivery order

Historical feature IDs are retained; delivery follows this sequence rather than
numeric ID order. Client notes history is included with PRO-03.

1. **PRO-01 / PRO-02:** Separate add-on foundation and commercial lifecycle.
2. **PRO-03:** Saved templates, report composition, and client notes history.
3. **PRO-05:** White-label controls.
4. **PRO-06:** Branded multi-recipient scheduled email reports.
5. **PRO-07:** Branded PDF and HTML reports.
6. **PRO-04:** Advanced metrics and conversion KPIs.
7. **PRO-08:** Advanced Search Console reporting.
8. **PRO-09:** Multiple GA4 properties.
9. **PRO-10:** Multisite and portfolio operations.
10. **PRO-11:** Managed configuration transfer and template propagation.
11. **PRO-12:** Read-only API and signed webhooks.

### PRO-01: Separate add-on foundation

**Status:** Planned; foundation work before paid features.

**Purpose:** Establish an independently installable add-on that extends compatible
Free versions without making Free depend on commercial infrastructure.

**Scope and dependencies:** Finalize the package identity and Free compatibility
matrix. Provide activation, deactivation, migrations, capabilities, scoped admin
screens, setup guidance, documentation/support, and uninstall handling. Check
Free availability and `CLIREDAS_VERSION` before registering paid behavior.

**Acceptance criteria:**

- Missing/incompatible Free produces a recoverable, scoped, dismissible notice
  without a fatal error, redirect loop, or modification of Free data.
- Activation and removal preserve Free configuration, credentials, selected
  property, reports, and access behavior. Additive migrations preserve valid data.
- Build on existing hooks for reports, providers, KPIs, ranges, sections, menu
  title, capabilities, cache behavior, and optional OAuth scopes where those
  hooks represent real Free behavior. Do not assume every historical proposed
  hook exists or that a filter alone implements new provider behavior.
- Keep paid settings, schemas, assets, cron hooks, actions, routes, and data in
  Pro under its finalized distinct identifiers. Add no paid-only scaffolding or
  locked controls to the Free package.

### PRO-02: Licensing, updates, privacy, and lifecycle

**Status:** Planned; completes the foundation alongside PRO-01.

**Purpose:** Support commercial distribution and maintenance while preserving
Free operation during outages, expiry, and add-on removal.

**Scope and dependencies:** PRO-01 and a finalized commercial service design.
Keep license storage/validation, commercial update delivery, and related network
requests entirely in the separately distributed Pro package. Define versioned
migrations, bounded retries, and the administrator's Pro data-retention choice.

**Acceptance criteria:**

- Test activation, validation, update, service outage, expiry, deactivation, and
  uninstall independently. Preserve schedules/configuration and credentials
  without corrupting Free data or disabling Free reporting.
- Document every service, data field, purpose, Terms of Use, and Privacy Policy;
  require informed configuration before contacting it. Licensing requests do not
  transmit analytics data unrelated to licensing.
- Use authenticated, time-bounded requests and show actionable status on Pro
  screens when infrastructure is unavailable. A license service is not described
  as processing needed for local Free features.
- Deactivation stops Pro background work. Full uninstall removes Pro-owned
  schedules, caches, logs, files, and settings according to the documented choice,
  leaving Free operational. No commercial updater ships in Free.

### PRO-03: Saved templates, report composition, and client notes history

**Status:** Planned; first client-reporting feature after the foundation.

**Purpose:** Create one reusable report definition for previews and all delivery
formats, with a persistent record of client context and reporting notes.

**Scope and dependencies:** PRO-01/PRO-02 and a stable internal composition model.
Support owner, agency, and later SEO-oriented templates with section order,
labels, available metrics, date behavior, introductions, and highlights/next steps.
Keep a timeline of dated client notes, such as campaign launches or site changes,
visible on the authorized dashboard and selectable for reports. SEO sections
become usable when PRO-08 is delivered; they are not a prerequisite for templates.

**Acceptance criteria:**

- Authorized users can create, copy, edit, preview, and delete templates and
  manage notes with capability and nonce protection.
- Preview current data without sending mail or exporting. Version saved
  definitions and handle unavailable sections without corrupting the template.
- Scope notes by site/property, distinguish saved history from a report's selected
  notes, and preserve history through template edits. Escape notes in each output.
- Keep the composition and notes model owned by Pro. Do not modify Free's report
  contract solely to support paid templates or expose private notes to viewers
  without the required access.
- Subsequent email, HTML, and PDF use the same composition and selected notes;
  each format discloses its period, comparison context, and data freshness.

### PRO-05: White-label controls

**Status:** Planned; follows PRO-03.

**Purpose:** Let agencies present client dashboards and reports under their own
identity.

**Scope and dependencies:** PRO-03 and the existing `cliredas_menu_title` filter.
Support agency logo, restrained color/style controls, menu naming, report footer,
sender identity, branding visibility, and upgrade-link visibility. Store and
apply these through Pro-owned settings/assets on supported plugin surfaces.

**Acceptance criteria:**

- Dashboard/report previews reflect configured branding, with email/PDF/HTML
  parity checked as those formats ship and documented format limits.
- Validate assets through WordPress media APIs, maintain accessible contrast,
  and fall back safely when images are removed or unavailable.
- Keep changes scoped to Cliredas surfaces; never edit Free files or global
  wp-admin styling. Use controlled style options rather than arbitrary admin CSS.
- Do not add public-site attribution, footer links, or tracking without explicit
  opt-in. External recipients receive only intentionally included report content.

### PRO-06: Branded multi-recipient scheduled email reports

**Status:** Planned; follows PRO-05.

**Purpose:** Automate recurring client delivery using saved, branded reports.

**Scope and dependencies:** PRO-03, PRO-05, and the background-capable real-data
provider/cache foundation from the Free roadmap. Support weekly/monthly schedules,
multiple validated recipients, template selection, subject, introduction, notes,
and sender settings. PDF attachments follow PRO-07; email need not wait for them.

**Acceptance criteria:**

- Provide test/manual send, pause/resume, disable, next-run, last-run, and
  per-recipient outcome controls with appropriate authorization and intent checks.
- Use the same composed/cached data as previews. Do not send mock data or data
  outside the documented freshness policy, and never leak secrets/raw API errors.
- Use idempotent run identifiers, bounded retries, and site-timezone scheduling;
  timezone/daylight-saving changes must not create missed or duplicate runs.
  Document WP-Cron and mail-transport limits.
- Validate recipients, cover unsubscribe/disable behavior, and do not treat mail
  handoff as proof of inbox delivery. Failures for one recipient do not imply
  success for others.
- Keep bounded local delivery logs without credentials or full report bodies.
  Deactivation stops events; disabling and uninstall clean up owned work/data.

### PRO-07: Branded PDF and HTML reports

**Status:** Planned; follows PRO-06.

**Purpose:** Produce client-ready attachments and browser-viewable reports from
the shared template, branding, and notes model.

**Scope and dependencies:** PRO-03 and PRO-05; attachments integrate with PRO-06.
Provide one-click export, selected sections/notes, period/comparison context, and
generation time. Keep output self-contained, with GPL-compatible dependencies
and no arbitrary executable third-party delivery.

**Acceptance criteria:**

- Values and selected notes match the preview for the same report/template.
- Verify long titles, 25-row tables, Unicode, empty sections, page breaks, and
  responsive HTML. State any supported font or output-format limitations.
- Define storage, expiry, authorization, and cleanup before generating files;
  unauthorized users or guessed URLs cannot retrieve private reports.
- Export and attachment failures produce actionable status, leave no orphaned
  files, and do not disrupt the ordinary Free CSV export.

### PRO-04: Advanced metrics and conversion KPIs

**Status:** Planned; follows the initial client-delivery formats.

**Purpose:** Add specialized analytical flexibility to composed client reports.

**Scope and dependencies:** PRO-03, existing report/KPI/provider hooks, and Free's
planned date/comparison support. Add configurable KPI and section selection,
GA4 key events/conversions, and conversion-rate KPIs after verifying supported
metric definitions and combinations. Custom dates, year-to-date, and ordinary
chart comparisons are Free work, not paid prerequisites to be reimplemented.

**Acceptance criteria:**

- Fetch only metrics needed by the active report/template; avoid unnecessary
  provider requests and preserve the built-in Free KPI/report behavior.
- Unsupported combinations or missing configured events explain how to recover,
  rather than reporting invented zeros or silently selecting different metrics.
- Values, rates, date ranges, and comparison meaning agree across dashboard,
  preview, email, HTML, and PDF for supported sections.
- Reuse Free date validation and period resolution; removing Pro leaves Free's
  presets and any delivered custom dates/comparison controls available.

### PRO-08: Advanced Search Console reporting

**Status:** Planned; follows PRO-04.

**Purpose:** Extend the planned Free Search Console overview into detailed SEO
analysis and reusable client reporting without creating a general connector
platform.

**Scope and dependencies:** PRO-03 and FREE-10's planned functional Search Console
connector, property selection, diagnostics, and overview. Reuse that foundation
for detailed top-query and search-landing-page breakdowns, and add SEO sections
to templates, previews, and branded delivery formats. The basic clicks,
impressions, CTR, and average-position overview remains Free.

**Acceptance criteria:**

- Reuse the explicitly configured Free read-only connection without making its
  overview depend on Pro. Any additional permission or external communication
  requires informed administrator configuration and service/data disclosures.
- GA4-only connections keep working. Enabling, revoking, or reconnecting Search
  Console does not corrupt GA4 tokens, selected properties, or reports.
- Respect API date/dimension constraints and explain data-availability differences
  rather than implying GA4 and Search Console totals measure the same thing.
- Missing permissions or unavailable SEO sections degrade safely across previews
  and delivery; caches remain isolated by source/property/site/period.
- Removing Pro leaves the Free Search Console overview, property selection,
  diagnostics, and credentials intact.

### PRO-09: Multiple GA4 properties

**Status:** Planned; follows PRO-08.

**Purpose:** Report on more than one authorized property from one installation.

**Scope and dependencies:** PRO-03 and a versioned Pro property/settings model.
Store authorized property contexts and allow explicit switching in Pro report,
template, notes, and schedule workflows.

**Acceptance criteria:**

- Scope caches, notes, schedules, templates, logs, and generated files by site
  and property. Switching never reuses another property's data.
- Detect and explain revoked/removed Google access per property without
  silently falling back to an unrelated one.
- Preserve the original Free selected property and restore its ordinary context
  when Pro is inactive, without deleting Pro configurations unexpectedly.

### PRO-10: Multisite and portfolio operations

**Status:** Planned; follows PRO-09.

**Purpose:** Support agency reporting across a WordPress network.

**Scope and dependencies:** PRO-09 and a tested multisite data-ownership model.
Add network activation handling, defaults, per-site overrides, explicit network
capabilities, and a portfolio view with safe aggregate status and site links.
Basic per-site isolation/cleanup is a Free responsibility, not a paid feature.

**Acceptance criteria:**

- Site and network administrators see only operations/data they are permitted
  to access, with clear inheritance and override behavior.
- Keep credentials/property access isolated unless sharing is explicitly
  configured by an authorized administrator.
- Use bounded queries/background work on large networks rather than fetching
  every site's analytics synchronously.
- Schedules, caches, notes, files, new/deleted sites, deactivation, and uninstall
  respect blog context and clean up only owned data.

### PRO-11: Managed configuration transfer and template propagation

**Status:** Planned; follows PRO-10.

**Purpose:** Reuse client-reporting configurations across managed sites and
properties without repeating setup or overwriting destination-specific choices.

**Scope and dependencies:** PRO-03, Free's basic settings-transfer foundation, and
PRO-10 for network propagation. Provide versioned Pro template/branding/config
bundles, configuration cloning, explicit remote transfer, revision propagation,
and managed update/merge workflows. Ordinary manual Free settings transfer and
essential validation/preview remain Free.

**Acceptance criteria:**

- Preview differences, choose create/skip/update behavior, and report incompatible
  fields. Do not overwrite site-specific configuration without explicit choice.
- Authenticate and authorize remote destinations; validate size, format/version,
  allowed fields, types, and assets without executing imported code.
- Exclude credentials, OAuth tokens, license secrets, property bindings, recipient
  addresses, enabled schedules, report files/data, diagnostics, audit history,
  and user preferences. Imports cannot silently activate delivery or permissions.
- Preserve supported Pro configuration on compatible round trips; reject malformed
  or future-version bundles without partially corrupting destination data.
- Changes preserve Free configuration and destination connections. Explain skipped
  branding assets and unavailable sections rather than fetching arbitrary URLs.

### PRO-12: Read-only API and signed webhooks

**Status:** Planned; follows PRO-11.

**Purpose:** Enable controlled integration with agency systems.

**Scope and dependencies:** PRO-03, PRO-06, and a reviewed authentication/signing
model. Add capability-protected read-only composed-report/delivery-status
endpoints and explicit outbound webhooks for completed schedules, delivery
failures, and selected report events.

**Acceptance criteria:**

- Define route/schema/authentication contracts in Pro, with explicit permissions,
  endpoint credentials, rotation/revocation, pagination where needed, and abuse
  protections. No unauthenticated general analytics access.
- Validate/minimize payloads; never include OAuth credentials, license data,
  private notes outside the authorized report, or unrelated WordPress data.
- Require explicit administrator configuration for destinations, use signatures
  with replay protection, and protect against unsafe outbound destinations.
- Keep retries bounded/idempotent, maintain bounded delivery logs, and provide
  a disable/recovery path for failing destinations.

## Shared delivery and documentation requirements

All privileged operations need capability checks and appropriate intent
validation; scheduled work must run only within its authorized configuration.
Sanitize/validate inputs, escape every output context, scope assets, preserve
keyboard/screen-reader use, and use compatible additive migrations. Never place
inaccessible Pro code, schemas, or future-only extension scaffolding in Free.

Verify the declared Free compatibility matrix, activation/removal, service
outages, invalid input, unauthorized access, report parity, property/site
isolation, background-work cleanup, timezone boundaries, and format rendering
as each capability ships. Keep operational logs bounded and non-sensitive.

Document supported services and optional permissions, actual behavior during
licensing/update outages, data retention/removal, cron/mail limitations, and
output-format limits. Update coming-soon copy, documentation, screenshots,
changelogs, and release metadata alongside implemented releases; never market
the planned catalog as available in Free or an already released Pro product.

Follow the exclusions in [product scope](product-scope.md). This roadmap does not
introduce premium packs, generic BI/connectors, tracking installation, mandatory
SaaS processing for local Free behavior, or arbitrary third-party code delivery.
