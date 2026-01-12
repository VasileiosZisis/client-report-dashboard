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
     * @return CLIREDAS_Data_Provider
     */
    public static function get_provider()
    {
        $provider = new CLIREDAS_Data_Provider();

        /**
         * Filter the data provider instance.
         *
         * Pro add-on can return a GA4 provider here.
         *
         * @param CLIREDAS_Data_Provider $provider Provider instance.
         */
        $provider = apply_filters('cliredas_data_provider', $provider);

        // Safety: ensure we always return an object with get_report().
        if (! is_object($provider) || ! method_exists($provider, 'get_report')) {
            $provider = new CLIREDAS_Data_Provider();
        }

        return $provider;
    }
}
