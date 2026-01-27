<?php

/**
 * Provider factory.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

final class CLIREDAS_Provider_Factory
{

    /**
     * Get active data provider.
     *
     * @param CLIREDAS_Settings|null $settings Settings service (optional).
     * @return object Provider with get_report().
     */
    public static function get_provider($settings = null)
    {
        $provider = new CLIREDAS_Data_Provider();

        // Prefer GA4 provider when GA4 is connected (provider can fall back with a helpful message).
        if ($settings instanceof CLIREDAS_Settings && $settings->is_ga4_connected()) {
            if (class_exists('CLIREDAS_GA4_Data_Provider')) {
                $provider = new CLIREDAS_GA4_Data_Provider($settings);
            }
        }

        /**
         * Filter the data provider instance.
         *
         * Pro add-on can return a GA4 provider here.
         *
         * @param object                 $provider Provider instance.
         * @param CLIREDAS_Settings|null $settings Settings service.
         */
        $provider = apply_filters('cliredas_data_provider', $provider, $settings);

        // Safety: ensure we always return an object with get_report().
        if (! is_object($provider) || ! method_exists($provider, 'get_report')) {
            $provider = new CLIREDAS_Data_Provider();
        }

        return $provider;
    }
}
