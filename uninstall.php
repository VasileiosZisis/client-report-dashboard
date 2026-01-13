<?php

/**
 * Uninstall cleanup for Client Reporting Dashboard.
 *
 * @package ClientReportingDashboard
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

$option_key = 'cliredas_settings';

// Delete options (site + multisite).
delete_option('cliredas_cache_keys');
if (is_multisite()) {
    delete_site_option('cliredas_cache_keys');
}

// Delete known transients (range-based).
delete_transient('cliredas_report_last_7_days');
delete_transient('cliredas_report_last_30_days');

// If you later add property-based cache keys, also clear those via a known index option.
