<?php

/**
 * Uninstall cleanup for Client Reporting Dashboard.
 *
 * @package ClientReportingDashboard
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Cleanup plugin data on uninstall.
 *
 * Notes:
 * - Options are stored per-site; on multisite we attempt to delete per-blog options.
 * - The cache index tracks transient keys, so we clear transients from the index first.
 */

$settings_option_key   = 'cliredas_settings';
$cache_index_option_key = 'cliredas_cache_keys';
$oauth_state_meta_key  = 'cliredas_ga4_oauth_state';

/**
 * Cleanup for a single site/blog context.
 *
 * @return void
 */
$cleanup_site = static function () use ($settings_option_key, $cache_index_option_key) {
    // Delete cached transients tracked in the index (if any).
    $keys = get_option($cache_index_option_key, array());
    if (is_array($keys)) {
        foreach ($keys as $key) {
            $key = sanitize_key($key);
            if ('' === $key) {
                continue;
            }
            delete_transient($key);
        }
    }

    // Back-compat: clear known transients for default ranges.
    delete_transient('cliredas_report_last_7_days');
    delete_transient('cliredas_report_last_30_days');

    // Delete options.
    delete_option($cache_index_option_key);
    delete_option($settings_option_key);
};

if (is_multisite() && function_exists('get_sites') && function_exists('switch_to_blog') && function_exists('restore_current_blog')) {
    $site_ids = get_sites(array('fields' => 'ids'));

    if (is_array($site_ids) && ! empty($site_ids)) {
        foreach ($site_ids as $site_id) {
            switch_to_blog((int) $site_id);
            $cleanup_site();
            restore_current_blog();
        }
    } else {
        $cleanup_site();
    }

    // Clean up any accidental network-level storage.
    delete_site_option($cache_index_option_key);
    delete_site_option($settings_option_key);
} else {
    $cleanup_site();
}

// Remove stored OAuth state for all users (used during GA4 connect flow).
if (function_exists('delete_metadata')) {
    delete_metadata('user', 0, $oauth_state_meta_key, '', true);
}
