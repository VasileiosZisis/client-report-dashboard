<?php

/**
 * Assets loader.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

final class CLIREDAS_Assets
{

    /**
     * Enqueue dashboard assets (Chart.js + dashboard JS/CSS) and localize data.
     *
     * @param array $localized_data Data passed to cliredasDashboard in JS.
     * @return void
     */
    public static function enqueue_dashboard_assets(array $localized_data)
    {
        // Provide i18n strings for JS (allow callers to override/extend).
        $i18n_defaults = array(
            'loading'                => __('Loading', 'cliredas-analytics-dashboard'),
            /* translators: %s: selected date range label (e.g. "Last 7 days"). */
            'showingTemplate'        => __('Showing: %s', 'cliredas-analytics-dashboard'),
            'errorGeneric'           => __('An error occurred.', 'cliredas-analytics-dashboard'),
            'failedToLoadReport'     => __('Failed to load report.', 'cliredas-analytics-dashboard'),
            'errorLoadingReport'     => __('Error loading report.', 'cliredas-analytics-dashboard'),
            'noData'                 => __('No data.', 'cliredas-analytics-dashboard'),
            'sessions'               => __('Sessions', 'cliredas-analytics-dashboard'),
            'totalUsers'             => __('Total users', 'cliredas-analytics-dashboard'),
            /* translators: %s: chart metric label (e.g. "Sessions" or "Total users"). */
            'overTimeTemplate'       => __('%s over time', 'cliredas-analytics-dashboard'),
            'trafficSources'         => array(
                'organic_search' => __('Organic Search', 'cliredas-analytics-dashboard'),
                'direct'         => __('Direct', 'cliredas-analytics-dashboard'),
                'referral'       => __('Referral', 'cliredas-analytics-dashboard'),
                'social'         => __('Social', 'cliredas-analytics-dashboard'),
                'other'          => __('Other', 'cliredas-analytics-dashboard'),
            ),
        );

        $localized_data['i18n'] = wp_parse_args(
            isset($localized_data['i18n']) && is_array($localized_data['i18n']) ? $localized_data['i18n'] : array(),
            $i18n_defaults
        );

        wp_enqueue_style(
            'cliredas-dashboard',
            CLIREDAS_PLUGIN_URL . 'assets/css/cliredas-dashboard.css',
            array(),
            CLIREDAS_VERSION
        );

        wp_register_script(
            'cliredas-chartjs',
            CLIREDAS_PLUGIN_URL . 'assets/vendor/chartjs/chart.umd.min.js',
            array(),
            '4.5.1',
            true
        );

        wp_enqueue_script(
            'cliredas-dashboard',
            CLIREDAS_PLUGIN_URL . 'assets/js/cliredas-dashboard.js',
            array('cliredas-chartjs'),
            CLIREDAS_VERSION,
            true
        );

        wp_localize_script(
            'cliredas-dashboard',
            'cliredasDashboard',
            $localized_data
        );
    }

    /**
     * Enqueue settings-page assets.
     *
     * @return void
     */
    public static function enqueue_settings_assets()
    {
        wp_enqueue_script(
            'cliredas-settings',
            CLIREDAS_PLUGIN_URL . 'assets/js/cliredas-settings.js',
            array(),
            CLIREDAS_VERSION,
            true
        );
    }
}
