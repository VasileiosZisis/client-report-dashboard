=== Client Reporting Dashboard ===
Contributors: vzisis
Tags: analytics, dashboard, reporting, google-analytics, ga4
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Client-friendly Google Analytics 4 (GA4) dashboard inside wp-admin with real GA4 data, caching, and clear setup steps.

== Description ==
Client Reporting Dashboard adds a clean, client-friendly analytics dashboard inside WordPress admin.

Use it to show key GA4 performance metrics without sending clients to the GA4 interface.

= Key Features =
* Connect Google Analytics 4 via OAuth (no service account needed)
* Select your GA4 Property from a dropdown
* Dashboard KPIs: Sessions, Total users, Pageviews, Avg engagement time
* Toggle line chart between Sessions over time and Total users over time
* Top pages table with Sessions, Views, and Avg engagement time per page
* Device breakdown (desktop / mobile / tablet)
* Traffic sources breakdown (Organic Search / Direct / Referral / Social / Other)
* Built-in caching (default 15 minutes) for fast dashboards and fewer API calls
* Clear Cache button (forces a fresh fetch on the next load)
* Optional access control: allow Editors to view the dashboard

= External Services =
This plugin can connect to Google Analytics 4 and uses Google APIs to retrieve analytics data.

When enabled and connected, the plugin sends requests to:
* Google OAuth 2.0 endpoints (to authorize and refresh access): https://accounts.google.com/ and https://oauth2.googleapis.com/
* Google Analytics Admin API (to list properties): https://analyticsadmin.googleapis.com/
* Google Analytics Data API (to fetch reports): https://analyticsdata.googleapis.com/

Data sent includes your OAuth client credentials (Client ID + Client Secret), authorization codes, refresh/access tokens, and API request parameters (selected property, date range, requested dimensions/metrics).

These requests are only made when an authorized WordPress admin user connects GA4 and when the dashboard needs to load or refresh data.

Google privacy policy: https://policies.google.com/privacy

== Installation ==
1. Upload the plugin folder to /wp-content/plugins/ or install via Plugins > Add New (when published).
2. Activate the plugin.
3. Go to Client Report in the admin menu.
4. Go to Settings > Client Report and add your Google OAuth Client ID and Client Secret.
5. In Google Cloud Console, add the Redirect URI shown in Settings as an Authorized redirect URI.
6. Click Connect Google Analytics, complete the consent screen, then select a GA4 Property.

== Frequently Asked Questions ==
= Does this connect to Google Analytics 4? =
Yes. Use Settings > Client Report to connect GA4 via OAuth and select a property.

= Why does Google block my redirect URI on a local domain? =
Google OAuth redirect URIs must use a public top-level domain (e.g. .com, .org). For local development, use a public tunnel (e.g. ngrok) or a real domain.

= Does this plugin store tokens/secrets in the database? =
Yes. OAuth credentials and tokens are stored in the WordPress options table under the `cliredas_settings` option. The plugin never displays your saved client secret back in the UI.

= Can Editors see the dashboard? =
Yes. Enable the option in Settings > Client Report.

== Screenshots ==
1. Dashboard (KPIs, chart toggle, top pages, devices, traffic sources).
2. Settings page (OAuth credentials, connect/disconnect, property selection).
3. Clear cache action (forces fresh fetch and refreshes the dashboard).

== Changelog ==
= 1.0.0 =
* GA4 OAuth connect + disconnect
* GA4 property listing + selection
* Real GA4 dashboard data (Data API)
* Dashboard improvements: chart toggle, pageviews KPI, traffic sources, caching + clear cache

== Upgrade Notice ==
= 1.0.0 =
Initial stable release with GA4 connection and real GA4 reporting.
