# Client Reporting Dashboard (Free)

Client Reporting Dashboard adds a clean, client-friendly Google Analytics 4 (GA4) dashboard inside WordPress admin (`wp-admin`).

This repository is the Free version intended for WordPress.org distribution. A separate Pro add-on is planned (menu label: "Pro (Coming Soon)").

## Key Features (Free)

- Connect Google Analytics 4 via OAuth (no service account needed)
- Select a GA4 Property from a dropdown (one active property per WordPress site)
- Dashboard KPIs: Sessions, Total Users, Pageviews, Avg engagement time
- Toggle line chart between Sessions over time and Total Users over time
- Top pages table: Path, Title, Sessions, Views, Avg engagement time (per page)
- Device breakdown (desktop / mobile / tablet)
- Traffic sources breakdown (Organic Search / Direct / Referral / Social / Other)
- Built-in caching (default 15 minutes) for faster dashboards and fewer API calls
- Clear cache button (forces a fresh fetch on the next load)
- Optional access control: allow Editors to view the dashboard

## External Services (Google APIs)

When enabled and connected, the plugin communicates with Google services:

- Google OAuth 2.0 endpoints (authorize/refresh): `accounts.google.com`, `oauth2.googleapis.com`
- Google Analytics Admin API (list properties): `analyticsadmin.googleapis.com`
- Google Analytics Data API (fetch reports): `analyticsdata.googleapis.com`

Data sent includes your OAuth client credentials (Client ID + Client Secret), authorization codes, refresh/access tokens, and API request parameters (selected property, date range, requested dimensions/metrics).

## Installation (from this repo)

1. Copy/clone this repo into `wp-content/plugins/client-report-dashboard/`
2. Activate "Client Reporting Dashboard" in `wp-admin` -> Plugins
3. Go to `Settings` -> `Client Report` and enter your Google OAuth Client ID and Client Secret
4. In Google Cloud Console, add the Redirect URI shown in Settings as an Authorized redirect URI
5. Click "Connect Google Analytics", complete the consent screen, then select a GA4 Property
6. Visit `Client Report` -> `Dashboard`

## Local Development Notes

- Google OAuth redirect URIs must use a public top-level domain (e.g. `.com`, `.org`). For local development, use a public tunnel (e.g. ngrok) or a real domain.
- Recommended in `wp-config.php`:
  - `define('WP_DEBUG', true);`
  - `define('WP_DEBUG_LOG', true);`

## Data Stored

- Plugin settings are stored in the WordPress options table under the `cliredas_settings` option.
- The plugin stores OAuth tokens/credentials there and does not display your saved client secret back in the UI.

## License

GPLv2 or later.
