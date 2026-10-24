# Cliredas - Client Dashboard for Google Analytics (GA4)

Cliredas - Client Dashboard for Google Analytics (GA4) adds a clean, client-friendly Google Analytics 4 (GA4) dashboard inside WordPress admin (`wp-admin`).

This repository is the Free version intended for WordPress.org distribution. A separate Pro add-on is planned.

## Key Features (Free)

- Connect Google Analytics 4 via OAuth (no service account needed)
- Guided GA4 setup assistant with public URL, OAuth, token, and property diagnostics
- Encrypted OAuth storage where supported, local token diagnostics, and administrator-only audit history
- Dismissible administrator release announcements on plugin pages, with local release notes
- Select a GA4 Property from a dropdown (one active property per WordPress site)
- Dashboard KPIs with previous-period deltas: Sessions, Total Users, Pageviews, Avg engagement time
- Date presets: Last 7 days, Last 30 days, This month, Last month, Last 90 days
- Toggle line chart between Sessions over time and Total Users over time
- Sortable Top pages table with up to 25 rows: Path, Title, Sessions, Views, Avg engagement time (per page)
- Device breakdown (desktop / mobile / tablet)
- Traffic sources breakdown (Organic Search / Direct / Referral / Social / Other)
- CSV export for the current dashboard range, including all built-in report blocks
- Report caching with 15-minute freshness, up to 24-hour last-good recovery, and background refresh
- Freshness status, automatic updates after pending refreshes, and freshness/date metadata in CSV exports
- Clear cache button (forces a fresh fetch on the next load)
- Optional access control: allow Editors to view the dashboard
- Optional unbranded plain-text owner digest, fresh real data only, Mondays at 08:00 in the site timezone

## Documentation and Roadmaps

- [Product scope](docs/product-scope.md): authoritative Free/Pro feature placement and product boundaries
- [Free roadmap](docs/free-roadmap.md): implemented `1.9.0` baseline, completed milestones, and planned Free work
- [Pro roadmap](docs/pro-roadmap.md): planned separate add-on catalog and client-reporting delivery sequence

Feature placement includes future work; it does not mean a feature has shipped. The Key Features list above describes current Free functionality. Custom dates, year-to-date, chart comparison overlays, settings transfer, and a basic Search Console overview remain planned. All Pro functionality is planned.

## External Services (Google APIs)

When enabled and connected, the plugin communicates with Google services:

- Google OAuth 2.0 endpoints (authorize/refresh): `accounts.google.com`, `oauth2.googleapis.com`
- Google Analytics Admin API (list properties): `analyticsadmin.googleapis.com`
- Google Analytics Data API (fetch reports): `analyticsdata.googleapis.com`

Data sent includes your OAuth client credentials (Client ID + Client Secret), authorization codes, refresh/access tokens, and API request parameters (selected property, date range, requested dimensions/metrics).

Requests are made when an administrator connects GA4, explicitly runs connection diagnostics, or when the dashboard loads or refreshes GA4 data, including WP-Cron refreshes of previously requested reports and fresh retrieval for opt-in digests or explicit test sends.

## Installation (from this repo)

1. Copy/clone this repo into `wp-content/plugins/cliredas-analytics-dashboard/`
2. Activate "Cliredas - Client Dashboard for Google Analytics (GA4)" in `wp-admin` -> Plugins
3. Go to `Settings` -> `Client Report` and open the GA4 Setup Assistant
4. Enter your Google OAuth Client ID and Client Secret
5. If the site is behind a public tunnel or reverse proxy (for example, ngrok), enter the public site URL in `Public OAuth base URL` and save settings
6. In Google Cloud Console, add the Redirect URI shown in Settings as an Authorized redirect URI
7. Click "Connect Google Analytics", complete the consent screen, then select a GA4 Property
8. Run diagnostics to verify token health and property access
9. Visit `Client Report` -> `Dashboard`

## Compatibility

- Requires WordPress 6.0 or later
- Tested up to WordPress 7.1
- Requires PHP 7.4 or later

## Local Development Notes

- Google OAuth redirect URIs must use a public top-level domain (e.g. `.com`, `.org`). For local development, use a public tunnel (e.g. ngrok) or a real domain.
- When using a tunnel, enter the tunnel's public site URL in `Settings` -> `Client Report` -> `Public OAuth base URL`, save settings, and then copy the generated `Redirect URI` into Google Cloud Console.
- Recommended in `wp-config.php`:
  - `define('WP_DEBUG', true);`
  - `define('WP_DEBUG_LOG', true);`

## Data Stored

- Plugin settings are stored in the WordPress options table under the `cliredas_settings` option.
- The plugin stores OAuth tokens/credentials there and does not display your saved client secret back in the UI.
- The latest diagnostic statuses, safe messages, timestamp, and non-sensitive configuration fingerprint are stored in `cliredas_setup_diagnostics` user meta for the administrator who ran them. Credentials, tokens, and raw Google responses are not stored there.

### OAuth Storage and Recovery

Client secrets, refresh tokens, and access tokens are encrypted with native Sodium secretbox, or OpenSSL AES-256-GCM when Sodium is unavailable. Separate field/site keys are derived from WordPress auth and secure-auth secret material. Existing plaintext credentials migrate automatically during an administrator's normal admin initialization. Hosts without either authenticated-encryption backend retain plaintext compatibility with a settings warning; enable a supported extension to migrate them.

Changing WordPress security salts or the site context can make stored credentials unreadable. Restore the original secret material and site context, or re-enter the Client Secret and reconnect. Disconnect clears tokens but preserves credentials. Explicit secret clearing removes all three sensitive values. Keep compatible secret material when restoring or moving a database.

Encryption protects database values, not a compromised WordPress installation, runtime memory, or backups containing both database values and encryption material. Keys and plaintext copies are not stored alongside ciphertext.

Local diagnostics show storage/backend state, token expiry, refresh-token presence, and the latest retained refresh outcome without contacting Google. The separate Run diagnostics action performs remote health checks.

The non-autoloaded `cliredas_audit_log` option retains the latest 100 connection, disconnect, secret-clearing, actual refresh, cache-clearing, CSV export, weekly digest, and digest test outcomes. Entries contain only allow-listed event/outcome codes, Unix timestamps, and actor IDs. No URLs, reports, recipients, credentials, tokens, or raw errors are retained, and no audit telemetry leaves the site. Administrators can inspect the history on the settings page; uninstall removes it per site.

## Changelog

Release announcements appear only on the plugin Dashboard and Settings pages after upgrades, not fresh installs. Dismissal is remembered per administrator and site in `cliredas_dismissed_release_notices` user meta. The non-autoloaded `cliredas_release_state` option tracks installed and announced releases. Local release notes remain available in Settings; dismissing announcements never hides operational warnings. Both records are removed on uninstall.

### 1.9.0

- Added a disabled-by-default plain-text weekly owner digest to one saved recipient.
- Added fresh-only reporting, Monday 08:00 scheduling, DST reconciliation and duplicate prevention.
- Added protected test/disable actions, next-run/outcome status and safe audit events.
- Added digest lifecycle cleanup and skipped missed Mondays without backlogs.

### 1.8.0

- Added bounded last-good GA4 caching and background refresh with duplicate-request protection.
- Added Fresh, Refreshing, and Stale status, site-timezone timestamps, and automatic dashboard updates.
- Isolated reports by connection and exact reporting dates; invalidated retained data on access changes.
- Added CSV freshness/date metadata and complete cache/background-work cleanup.

### 1.7.0

- Added authenticated OAuth credential encryption and automatic legacy migration.
- Added local storage/token diagnostics and bounded administrator-only audit history.
- Replaced raw upstream OAuth error feedback with predefined recovery messages.
- Added persistent, administrator-only release announcements and local release notes.

## License

GPLv2 or later.

## Report freshness (1.8.0)

Reports are fresh for 15 minutes
by default. An expired report can display its last successful real data for up
to 24 hours from retrieval, only for the same site, connection, property, timezone
and resolved dates, while one WP-Cron refresh is queued. Freshness measures
plugin retrieval time, not Google's processing completeness. A date rollover
requires a different report; yesterday's range is never relabeled as today's.

The dashboard shows Fresh, Refreshing or Stale and the last successful refresh
in the WordPress site timezone. While pending, it checks cached status every
five seconds for up to three minutes, pausing while hidden. Refresh execution
depends on WP-Cron; if it is disabled, configure a system cron runner or use
Clear Cache for a new synchronous load. Refresh failures retain eligible data
and impose a five-minute retry cooldown. Authorization loss hides old data.

The non-autoloaded `cliredas_report_cache` option stores at most 20 contexts,
with successful payloads/timestamps separate from attempt state and owner leases
inside one atomically updated record. Expired data is never served; daily and
on-demand cleanup remove inactive records. Clear Cache removes retained and
legacy reports and pending work. Connection/access changes and deactivation
invalidate reports; uninstall deletes owned storage and events per site.
CSV exports include freshness and resolved-date metadata; partial source
failures are labeled unavailable, and sample data remains explicitly labeled.

## Weekly owner digest (1.9.0)

Administrators opt in under Client Report Settings. Upgrades leave delivery off.
One recipient defaults initially to `admin_email` and is persisted when saved;
later site-admin email changes do not redirect it. A one-off test works while
weekly delivery is off and does not change the schedule. Save recipient changes
before testing. No branding, attachments, custom message, or tracking is included.

The email contains KPIs/deltas, top five pages, devices/sources, actual report
and comparison dates, retrieval time and a dashboard link. The existing Last
7 days period includes the current day; it is not a completed calendar week.
Only complete fresh real GA4 data is eligible. Expired reports are refreshed
synchronously; failed, stale, partial, mock or obsolete reports are skipped.

One WP-Cron event targets Monday at 08:00 in the site timezone. DST and timezone
changes are reconciled. Late runs are allowed only on that Monday; missed weeks
are skipped without backlogs. An atomic lease and pre-mail claim prevent duplicate
attempts. Unknown mail outcomes are not automatically retried. A successful
`wp_mail()` result means mail handoff, not confirmed inbox delivery.

Opt-in digest/test requests pass the summary and saved recipient through the
site's configured mail transport; no separate Cliredas delivery service is used.
`cliredas_digest_settings` stores consent/recipient; the non-autoloaded
`cliredas_digest_state` holds bounded scheduling, lease and outcome metadata.
Operational records exclude addresses, report/message bodies and raw errors.
Audit outcomes share the existing 100-event limit. Disable removes future events
and cancels preparing work; deactivation stops scheduling and uninstall removes
owned per-site data. Already handed-off mail cannot be recalled.
