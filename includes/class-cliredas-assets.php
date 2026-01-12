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
