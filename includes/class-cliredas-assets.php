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
     * @param array $localized_data Data passed to CLIREDAS_DASHBOARD in JS.
     * @return void
     */
    public static function enqueue_dashboard_assets(array $localized_data)
    {
        // Provide i18n strings for JS (allow callers to override/extend).
        $i18n_defaults = array(
            'loading'                => __('Loading…', 'client-report-dashboard'),
            /* translators: %s: selected date range label (e.g. "Last 7 days"). */
            'showingTemplate'        => __('Showing: %s', 'client-report-dashboard'),
            'errorGeneric'           => __('An error occurred.', 'client-report-dashboard'),
            'failedToLoadReport'     => __('Failed to load report.', 'client-report-dashboard'),
            'errorLoadingReport'     => __('Error loading report.', 'client-report-dashboard'),
            'noData'                 => __('No data.', 'client-report-dashboard'),
            'sessions'               => __('Sessions', 'client-report-dashboard'),
            'totalUsers'             => __('Total users', 'client-report-dashboard'),
            /* translators: %s: chart metric label (e.g. "Sessions" or "Total users"). */
            'overTimeTemplate'       => __('%s over time', 'client-report-dashboard'),
            'trafficSources'         => array(
                'organic_search' => __('Organic Search', 'client-report-dashboard'),
                'direct'         => __('Direct', 'client-report-dashboard'),
                'referral'       => __('Referral', 'client-report-dashboard'),
                'social'         => __('Social', 'client-report-dashboard'),
                'other'          => __('Other', 'client-report-dashboard'),
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
            'CLIREDAS_DASHBOARD',
            $localized_data
        );
    }
}
