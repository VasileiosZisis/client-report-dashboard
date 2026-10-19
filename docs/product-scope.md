# Cliredas: Product Scope

This is the authoritative feature-placement document for Cliredas - Client
Dashboard for Google Analytics (GA4). It defines what belongs in Free and the
separate Pro add-on, including planned capabilities. Placement is not an
availability claim. The [Free roadmap](free-roadmap.md) tracks the implemented
baseline and upcoming Free work; the [Pro roadmap](pro-roadmap.md) describes the
planned paid catalog and delivery sequence.

## Purpose and audience

Cliredas provides readable GA4 reporting inside WordPress admin for people who
need useful website performance information without building a reporting system.

- Site owners and content teams need a clear view of traffic, engagement, pages,
  sources, devices, and changes over time.
- Clients need an understandable dashboard with appropriate viewing permissions.
- Freelancers and agencies need reusable reports, their own branding, recurring
  client delivery, and eventually operations across properties and sites.
- Developers need usable extension points for the existing reporting workflow.

Free must be a complete single-site dashboard for one active GA4 property.
Ordinary date controls, comparisons, data export, setup, reliability, security,
and accessibility belong in that foundation. Paid value centers on report
composition, branded client delivery, advanced reporting, and agency
operations. Pro is one separate add-on; no additional premium packs are planned.

## Feature placement

The Free boundary includes both shipped functionality and the planned additions
in the Free roadmap. The current implementation baseline is **1.8.0**, including FREE-04 reliable
caching. All Pro functionality remains planned.

| Capability | Placement | Reason |
|---|---|---|
| Google OAuth connection, one active GA4 property, setup assistant, connection diagnostics, and optional Editor viewing access | Free | A usable dashboard requires understandable setup and access control. |
| Four built-in KPIs, previous-period KPI deltas, Sessions/Users chart, up to 25 sortable Top pages, devices, and traffic sources | Free | These form the complete everyday dashboard. |
| Five existing date presets, custom start/end dates, and year-to-date | Free | Choosing the period is an ordinary dashboard control. |
| Previous-period chart overlay | Free | Trend comparison complements the existing KPI comparisons. |
| Built-in report CSV export and basic non-secret Free settings import/export | Free | Data access and ordinary manual settings reuse should remain available without Pro. |
| One optional, unbranded weekly owner digest to one recipient | Free — high priority | Recurring value encourages continued use; deliver immediately after the reporting foundation. |
| Data freshness, last-good report recovery, and connection diagnostics | Free foundation | Trustworthy reporting benefits every user. |
| Basic Search Console overview: clicks, impressions, CTR, and average position for one selected property | Free — later | Search performance makes the everyday dashboard more useful. |
| Caching, filtering, empty states, accessibility, and responsive layouts | Free | Reliability and everyday usability are baseline responsibilities. |
| OAuth storage hardening, local diagnostics, bounded administrator audit history, and scoped release announcements | Free | Security, recovery, and maintenance must serve all installations. |
| Existing developer hooks for reporting, providers, ranges, KPIs, sections, capabilities, caching, and OAuth | Free | Developers can extend real Free behavior without modifying plugin files. |
| Saved report templates, section/metric composition, previews, and client notes history | Pro | These support repeatable client-reporting workflows. |
| Agency logo, colors, menu naming, sender identity, branding visibility, and report footer controls | Pro | Agencies need their own presentation across reports and delivery. |
| Branded multi-recipient weekly/monthly schedules, custom messages, and delivery operations | Pro | Recurring client delivery extends beyond the simple admin digest. |
| Branded PDF and HTML reports | Pro | Client-ready documents build on the shared composition and branding model. |
| Configurable advanced metrics, GA4 key events/conversions, and conversion-rate KPIs | Pro | Specialized reporting extends the fixed Free KPI set. |
| Detailed Search Console query/landing-page breakdowns and composed SEO report sections | Pro | Advanced search reporting and client delivery build on the usable Free overview. |
| Multiple GA4 properties, multisite defaults/overrides, and portfolio views | Pro | Agency operations require explicit context, permissions, and isolation. |
| Pro configuration bundles, template propagation, remote transfer, and managed update/merge workflows | Pro | Managed reuse and fleet operations go beyond basic file transfer. |
| Authenticated read-only report APIs and signed outbound webhooks | Pro | These enable controlled integration with agency systems. |

## Portability and delivery boundaries

Basic Free settings portability means exporting a versioned file of supported,
non-secret Free configuration and manually importing it on another site. Schema
validation, safe input handling, understandable preview/feedback, and protection
against unintended overwrites are Free requirements.

Portable Free configuration excludes OAuth client credentials and tokens,
connection state, site/tunnel redirect URLs, property bindings, diagnostics,
audit history, caches, report data, and per-user preferences or dismissals.
Recipient addresses and enabled schedules are not transferred. Imports must not
silently enable email delivery or change access permissions without an explicit
administrator choice. Each destination keeps its own connection and property.

Pro portability covers Pro-specific templates, branding, and non-secret report
configuration, plus managed transfer and propagation. It must build on Free's
ordinary transfer workflow where applicable, not make basic safety or manual
Free settings reuse paid features. Credential, recipient, and activation safety
also applies to managed imports.

The Free digest remains a simple opt-in owner summary using the built-in report.
Pro owns customizable report composition, branded multi-recipient delivery,
multiple schedules, attachments, and delivery operations. Neither path may send
mock/sample reports as real analytics.

## Product and implementation principles

- Preserve existing Free capabilities and data when Pro is installed, removed,
  incompatible, expired, or unable to contact commercial infrastructure.
- Ship paid implementations entirely in the separate add-on. The WordPress.org
  plugin must contain no inaccessible Pro implementations, paid-only schemas or
  scaffolding, license gates, trials, or quotas on built-in functionality.
- Extend existing hooks only where they represent usable Free behavior. Preserve
  current report consumers and stored settings through compatible, additive work.
- Use the current Free plugin slug/text domain `cliredas-analytics-dashboard` and
  consistent `cliredas_` / `CLIREDAS_` identifiers. Finalize a distinct Pro identity
  and compatibility requirements before building the add-on.
- Require capabilities as well as intent validation for privileged actions.
  Sanitize and validate inputs, escape output, and process requests only in
  appropriate hooked callbacks. Keep keyboard and screen-reader operation usable.
- Separate real, stale, empty, and mock data visibly. Isolate reports, caches,
  schedules, notes, and generated files by site, property, and reporting period.
- Require explicit, informed configuration for external communication. Document
  services, transmitted data, purposes, Terms of Use, and Privacy Policy links.
  Retain GA4 read-only as Free's default scope; request optional integration
  scopes separately. Add no telemetry without informed opt-in.
- Bundle assets locally with verified GPL-compatible licenses, use WordPress
  libraries where provided, and do not deliver executable code through arbitrary
  third parties. Keep commercial licensing/updaters outside the Free package.
- Do not add public attribution without intentional opt-in. Keep notices and
  promotion sparse and scoped to plugin screens, make release notices dismissible,
  and give operational errors a recovery action that clears when resolved.

## Exclusions and documentation priorities

Cliredas does not install analytics tracking, replace GA4 with a local tracking
engine, provide general BI or an arbitrary connector marketplace, manage ads or
social publishing, or generate AI content. A mandatory Cliredas SaaS dependency
for ordinary GA4 reporting is outside scope. Search Console remains the focused
additional data source; portfolio operations follow the client-reporting core.

The existing in-plugin coming-soon page contains older placement descriptions
for dates, chart comparisons, settings transfer, and the basic Search Console
overview. This document supersedes
that planning copy; it does not change the page or implement those capabilities.
Align that page, listing copy, screenshots, FAQs, and release notes when the
corresponding feature work ships. Never present planned features as available.

This document and the two roadmaps replace the historical planning and research
documents. Previous versions remain recoverable through Git. Do not maintain
parallel numbered recommendation documents or treat historical market claims
as current verified facts.
