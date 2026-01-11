# Client Reporting Dashboard (Free)

A freemium WordPress plugin that provides a client-friendly analytics dashboard inside wp-admin using Google Analytics 4 (GA4) data.

This repository is the **Free** version (WordPress.org). A separate paid add-on plugin will extend this plugin.

## Goals

- Simple analytics dashboard inside wp-admin
- Clean OOP architecture following WordPress standards
- GA4 integration is abstracted behind a data provider (mock now, real GA4 later)
- Built-in extension points for the future Pro add-on plugin

## Planned Free Features

- Admin dashboard page: KPI cards, sessions chart, top pages table, device breakdown
- Date ranges: Last 7 days, Last 30 days
- Role/access control setting: allow Editors in addition to Administrators
- Upgrade page + “Upgrade to Pro” link

## Pro Add-on (Later)

The Pro add-on will be a separate plugin that depends on this free plugin and will:

- Add extra date ranges, metrics, and sections
- Enable email reports
- Add white-label options

The Free plugin will expose actions/filters and a simple Pro-detection helper:

- Pro is considered active when `CLIREDAS_PRO_VERSION` is defined.

## Conventions

### Prefixes

- PHP classes/constants: `CLIREDAS_`
- Functions/hooks/options: `cliredas_`

### Code style

- WordPress Coding Standards
- Escape output (`esc_html`, `esc_attr`, `wp_kses_post`)
- Sanitize input (`sanitize_text_field`, `absint`, etc.)
- Capabilities checked for all admin screens and AJAX

## Development

### Local setup

1. Install WordPress locally.
2. Clone or copy this repo into:
   `wp-content/plugins/client-report-dashboard/`
3. Activate the plugin from wp-admin.

### Debugging

Recommended in `wp-config.php`:

- `define( 'WP_DEBUG', true );`
- `define( 'WP_DEBUG_LOG', true );`

## License

GPLv2 or later.
