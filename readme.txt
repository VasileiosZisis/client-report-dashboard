=== Cliredas – Client Dashboard for Google Analytics (GA4) ===
Contributors: vzisis
Tags: google analytics, google analytics dashboard, ga4, wordpress analytics, analytics
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GA4 dashboard for WordPress clients and agencies. View Google Analytics reports, trends, top pages, traffic sources, and more.

== Description ==

Cliredas adds a clean, client-friendly Google Analytics 4 (GA4) reporting dashboard directly inside the WordPress admin.

Give clients, editors, marketers, and site owners the GA4 metrics they need without requiring them to navigate the full Google Analytics interface.

Cliredas is designed for WordPress agencies, freelancers, and site owners who want simple Google Analytics reporting inside WordPress.

Connect an existing GA4 property through Google OAuth, choose the property you want to report on, and view important website performance metrics from wp-admin.

= A simple GA4 dashboard inside WordPress =

Cliredas turns key Google Analytics 4 data into a focused WordPress dashboard.

Instead of sending clients or content teams into the full GA4 interface, you can give them a simpler view of website performance directly inside WordPress.

The dashboard includes:

* Sessions.
* Total users.
* Pageviews.
* Average engagement time.
* Previous-period percentage changes.
* Sessions over time.
* Total users over time.
* Top-performing pages.
* Device breakdown.
* Traffic-source breakdown.

This makes Cliredas useful when users need to understand website performance without working through the full Google Analytics reporting interface.

= Built for client reporting =

Cliredas is especially useful for agencies and freelancers that manage WordPress websites for clients.

Keep reporting inside the same WordPress admin area clients already use to manage their website.

Use Cliredas to give clients a focused view of:

* Overall website traffic.
* Changes compared with the previous period.
* Most-visited pages.
* Traffic sources.
* Desktop, mobile, and tablet usage.
* Engagement trends.

Administrators can also optionally allow users with the Editor role to view the dashboard.

= Compare performance over time =

Dashboard KPI cards include previous-period percentage changes so users can quickly see whether important metrics increased or decreased.

Available date ranges include:

* Last 7 days.
* Last 30 days.
* This month.
* Last month.
* Last 90 days.

Calendar-based ranges such as This month and Last month use aligned comparison periods.

The main line chart can switch between Sessions over time and Total users over time.

= See your top-performing pages =

The Top Pages report shows up to 25 pages and includes:

* Sessions.
* Views.
* Average engagement time.

Columns can be sorted to make it easier to identify high-traffic and high-engagement content.

Top Pages sorting is accessible and persists across date-range changes and browser visits.

= Understand where visitors come from =

Cliredas includes a traffic-source breakdown that groups GA4 traffic into useful categories such as:

* Organic Search.
* Direct.
* Referral.
* Social.
* Other.

Use this alongside the device report to get a quick picture of how visitors discover and access the website.

= See desktop, mobile, and tablet traffic =

The device breakdown summarizes traffic across:

* Desktop.
* Mobile.
* Tablet.

This gives clients and site owners a simple view of the devices visitors use without requiring them to build reports in Google Analytics.

= Export GA4 reports to CSV =

Export the current dashboard date range as a CSV file.

The export includes the plugin's built-in report blocks so analytics data can be used for:

* Client reporting.
* Internal analysis.
* Spreadsheets.
* Reporting archives.
* Further data analysis.

CSV exports identify sample or fallback data when applicable and include protection against spreadsheet formula injection.

= Guided GA4 setup =

Cliredas includes a rerunnable GA4 Setup Assistant to help administrators configure and troubleshoot the Google Analytics connection.

The setup flow helps with:

* Public site URL configuration.
* Google OAuth configuration.
* Refresh-token health.
* GA4 property access.
* Property selection.

Protected diagnostics help identify common configuration problems without exposing OAuth credentials, access tokens, refresh tokens, or raw Google API responses.

= Connect Google Analytics 4 with OAuth =

Cliredas connects to Google Analytics 4 through Google OAuth.

No service account is required.

To connect:

1. Create or use Google OAuth credentials.
2. Add the Client ID and Client Secret in Settings > Client Report.
3. Add the Redirect URI shown by Cliredas to the Google Cloud Console.
4. Connect Google Analytics through the OAuth consent flow.
5. Select the GA4 property you want to display.
6. Run the built-in diagnostics to verify token and property access.

After the connection is configured, Cliredas uses the Google Analytics APIs to retrieve reporting data.

= Built-in caching for faster reports =

Google Analytics report data is cached for 15 minutes by default.

Caching helps:

* Keep dashboard loading fast.
* Reduce repeated Google API requests.
* Avoid unnecessary requests when multiple users view the same reports.

Administrators can use Clear Cache to force fresh data to be fetched on the next dashboard load.

= Optional Editor access =

By default, analytics configuration remains an administrative task.

Administrators can optionally allow users with the WordPress Editor role to view the Client Report dashboard.

This can be useful for:

* Marketing teams.
* Content editors.
* Client website managers.
* Internal reporting teams.

= Key features =

* Google Analytics 4 dashboard inside WordPress.
* Client-friendly GA4 reporting.
* Google OAuth connection.
* No service account required.
* Guided GA4 Setup Assistant.
* OAuth, token, and property diagnostics.
* GA4 property selection.
* Sessions KPI.
* Total users KPI.
* Pageviews KPI.
* Average engagement time KPI.
* Previous-period percentage comparisons.
* Last 7 days date range.
* Last 30 days date range.
* This month date range.
* Last month date range.
* Last 90 days date range.
* Sessions-over-time chart.
* Total-users-over-time chart.
* Top Pages report with up to 25 pages.
* Sortable Top Pages columns.
* Traffic-source reporting.
* Device reporting.
* CSV report export.
* Built-in report caching.
* Manual cache clearing.
* Optional Editor dashboard access.

= Who is Cliredas for? =

**WordPress agencies**

Give clients a simpler way to see website performance without sending them into the full GA4 reporting interface.

**Freelancers**

Keep basic client analytics reporting inside the WordPress websites you manage.

**Site owners**

Check important Google Analytics metrics without switching between WordPress and GA4 for routine reporting.

**Marketing and content teams**

Give eligible WordPress users access to traffic, content, source, device, and engagement information alongside the site they manage.

= Reporting dashboard, not a full analytics suite =

Cliredas focuses on presenting useful GA4 reporting data inside WordPress.

It is designed for users who want a straightforward client-facing or site-management dashboard rather than the full set of reports, explorations, configuration tools, and analysis features available directly in Google Analytics.

Cliredas connects to an existing Google Analytics 4 property to retrieve reporting data.

== External Services ==

Cliredas connects to Google services in order to authorize access, list available Google Analytics properties, and retrieve GA4 reporting data.

The plugin does not send analytics data anywhere except Google APIs and your WordPress site.

When enabled and connected, the plugin sends requests to the following Google services:

* Google OAuth 2.0 endpoints for authorization and token refresh:
  * https://accounts.google.com/
  * https://oauth2.googleapis.com/
* Google Analytics Admin API for listing available properties:
  * https://analyticsadmin.googleapis.com/
* Google Analytics Data API for retrieving reporting data:
  * https://analyticsdata.googleapis.com/

Data sent to Google can include:

* OAuth Client ID.
* OAuth Client Secret.
* Authorization codes.
* Refresh tokens.
* Access tokens.
* Selected GA4 property.
* Requested date ranges.
* Requested dimensions and metrics.

These requests are made when an authorized WordPress administrator connects Google Analytics, explicitly runs connection diagnostics, or when the dashboard needs to load or refresh analytics data.

Google Privacy Policy:
https://policies.google.com/privacy

== Screenshots ==

1. Client-friendly GA4 dashboard showing Sessions, Total users, Pageviews, Average engagement time, trends, and Top Pages.
2. Google Analytics traffic-source and device reports showing how visitors discover and access the website.
3. Client Report settings with the GA4 Setup Assistant, OAuth configuration, diagnostics, and property selection.
4. Guided GA4 Setup Assistant showing successful OAuth, token-health, and property-access diagnostics.

== Installation ==

1. Install Cliredas from Plugins > Add New, or upload the `cliredas-analytics-dashboard` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to Client Report in the WordPress admin menu.
4. Open Settings > Client Report and launch the GA4 Setup Assistant.
5. Add your Google OAuth Client ID and Client Secret.
6. If the site is behind a public tunnel or reverse proxy, enter the public site URL in Public OAuth base URL and save the settings.
7. In Google Cloud Console, add the Redirect URI shown by Cliredas as an Authorized redirect URI.
8. Click Connect Google Analytics and complete Google's OAuth consent flow.
9. Select the GA4 property you want to use.
10. Run the connection diagnostics to verify refresh-token health and property access.
11. Open Client Report to view your Google Analytics dashboard.

== Frequently Asked Questions ==

= What does Cliredas do? =

Cliredas displays key Google Analytics 4 reporting data inside the WordPress admin.

It provides a simplified GA4 dashboard with traffic KPIs, trends, top pages, traffic sources, device information, previous-period comparisons, and CSV export.

= Can I view Google Analytics 4 inside WordPress? =

Yes.

Cliredas connects an existing Google Analytics 4 property to WordPress and displays key reporting data directly in wp-admin.

= Is Cliredas suitable for client reporting? =

Yes.

Cliredas is designed to provide a simpler, client-friendly view of GA4 data inside WordPress.

It can be useful for agencies and freelancers that want clients to see important website metrics without navigating the full Google Analytics interface.

= Is Cliredas useful for WordPress agencies and freelancers? =

Yes.

Agencies and freelancers can use Cliredas to keep basic GA4 reporting inside the WordPress websites they manage for clients.

Administrators can also optionally allow WordPress Editors to view the reporting dashboard.

= Does Cliredas connect to Google Analytics 4? =

Yes.

Use Settings > Client Report to connect GA4 through Google OAuth and select the Google Analytics property you want to display.

= Do I need a Google Analytics service account? =

No.

Cliredas uses Google OAuth rather than requiring a Google Analytics service account.

= Do I need an existing GA4 property? =

Yes.

Cliredas retrieves reporting data from a Google Analytics 4 property. You connect the plugin to Google through OAuth and then select an available GA4 property.

= Does Cliredas install Google Analytics tracking on my website? =

Cliredas is focused on reporting from an existing Google Analytics 4 property.

You should already have GA4 measurement configured for the website whose analytics you want to display.

= What Google Analytics metrics can I see in WordPress? =

The dashboard includes:

* Sessions.
* Total users.
* Pageviews.
* Average engagement time.
* Previous-period percentage changes.
* Sessions or users over time.
* Top pages.
* Traffic sources.
* Device categories.

= Can I compare the current period with the previous period? =

Yes.

The main KPI cards display previous-period percentage changes.

Calendar-based ranges such as This month and Last month use aligned comparison periods.

= What date ranges are available? =

Cliredas currently includes:

* Last 7 days.
* Last 30 days.
* This month.
* Last month.
* Last 90 days.

= Can I see which pages receive the most traffic? =

Yes.

The Top Pages table displays up to 25 pages and includes Sessions, Views, and Average engagement time.

The table can be sorted by its report columns.

= Can I see where website traffic comes from? =

Yes.

Cliredas includes a traffic-source report with categories such as Organic Search, Direct, Referral, Social, and Other.

= Can I see mobile and desktop traffic? =

Yes.

The device report shows a breakdown for desktop, mobile, and tablet traffic.

= Can I export GA4 data? =

Yes.

Cliredas can export the current dashboard range to CSV, including the plugin's built-in reporting blocks.

= Can Editors see the Google Analytics dashboard? =

Yes, if an administrator enables Editor access under Settings > Client Report.

= How often does Cliredas fetch Google Analytics data? =

Report data is cached for 15 minutes by default.

This reduces unnecessary API requests and improves dashboard loading performance.

Use the Clear Cache action when you want the next dashboard load to request fresh data.

= What is the GA4 Setup Assistant? =

The Setup Assistant provides a guided workflow for connecting Cliredas to Google Analytics.

It includes checks for public URL configuration, OAuth configuration, refresh-token health, and GA4 property access.

The assistant can be run again later if you need to troubleshoot the connection.

= Why does Google block my redirect URI on a local domain? =

Google OAuth redirect URIs must use an acceptable public domain.

For local development, use a public tunnel such as ngrok or a real public domain.

When using a tunnel, enter the tunnel's public site URL in Public OAuth base URL, save the settings, and copy the generated Redirect URI into Google Cloud Console.

= What if my WordPress site is behind a reverse proxy or tunnel? =

Use the Public OAuth base URL setting to provide the public-facing site URL.

Cliredas uses that URL when generating the OAuth Redirect URI.

= Does Cliredas store Google OAuth credentials or tokens in WordPress? =

Yes.

OAuth credentials and tokens are stored in the WordPress options table under the `cliredas_settings` option.

The plugin does not display the saved Client Secret back in the settings interface.

The latest connection diagnostic result is stored in user meta for the administrator who ran it. The diagnostic record contains statuses, safe messages, a timestamp, and a non-sensitive configuration fingerprint.

It does not contain credentials, tokens, or raw Google API responses.

= Does Cliredas send my analytics data to another service? =

Cliredas communicates with Google APIs and your WordPress site as described in the External Services section.

It does not send analytics reporting data to a separate Cliredas analytics service.

== Changelog ==

= 1.6.0 =

* Added a rerunnable GA4 Setup Assistant to the plugin settings page.
* Added protected diagnostics for OAuth URL configuration, token health, and GA4 property access.
* Consolidated settings-page token and property requests through the shared GA4 API client.

= 1.5.0 =

* Added accessible sorting for every Top Pages column.
* Top Pages sorting now persists across date-range changes and browser visits.

= 1.4.0 =

* Added protected CSV export for all built-in dashboard report blocks.
* CSV exports identify sample or fallback data and protect against spreadsheet formula injection.