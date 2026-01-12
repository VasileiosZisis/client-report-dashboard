<?php

/**
 * Admin screen helpers.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

final class CLIREDAS_Admin_Screens
{

    /**
     * Get dashboard screen id.
     *
     * @return string
     */
    public static function get_dashboard_screen_id()
    {
        // e.g. "toplevel_page_cliredas-client-report".
        return 'toplevel_page_' . CLIREDAS_Admin_Menu::MENU_SLUG;
    }

    /**
     * Check if the current admin screen is the plugin dashboard.
     *
     * @return bool
     */
    public static function is_dashboard_screen()
    {
        if (! function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (! $screen || empty($screen->id)) {
            return false;
        }

        return self::get_dashboard_screen_id() === (string) $screen->id;
    }
}
