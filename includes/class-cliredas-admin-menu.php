<?php

/**
 * Admin menu registration.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

final class CLIREDAS_Admin_Menu
{

    /**
     * Top-level menu slug.
     *
     * @var string
     */
    const MENU_SLUG = 'cliredas-client-report';

    /**
     * Settings service.
     *
     * @var CLIREDAS_Settings
     */
    private $settings;

    /**
     * Dashboard page renderer.
     *
     * @var CLIREDAS_Dashboard_Page
     */
    private $dashboard_page;

    /**
     * Upgrade page renderer.
     *
     * @var CLIREDAS_Upgrade_Page
     */
    private $upgrade_page;

    /**
     * @param CLIREDAS_Settings       $settings       Settings service.
     * @param CLIREDAS_Dashboard_Page $dashboard_page Dashboard page renderer.
     * @param CLIREDAS_Upgrade_Page   $upgrade_page   Upgrade page renderer.
     */
    public function __construct(CLIREDAS_Settings $settings, CLIREDAS_Dashboard_Page $dashboard_page, CLIREDAS_Upgrade_Page $upgrade_page)
    {
        $this->settings       = $settings;
        $this->dashboard_page = $dashboard_page;
        $this->upgrade_page   = $upgrade_page;

        add_action('admin_menu', array($this, 'register_menus'));
    }

    /**
     * Register admin menus.
     *
     * @return void
     */
    public function register_menus()
    {
        $menu_title = (string) apply_filters('cliredas_menu_title', __('Client Report', 'client-report-dashboard'));
        $capability = $this->settings->get_required_capability('menu');

        /**
         * Action before menus are registered.
         */
        do_action('cliredas_before_register_menus');

        add_menu_page(
            __('Client Report', 'client-report-dashboard'),
            $menu_title,
            $capability,
            self::MENU_SLUG,
            array($this->dashboard_page, 'render'),
            'dashicons-chart-area'
        );

        // Dashboard (explicit submenu, points to the same callback/slug).
        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard', 'client-report-dashboard'),
            __('Dashboard', 'client-report-dashboard'),
            $capability,
            self::MENU_SLUG,
            array($this->dashboard_page, 'render')
        );

        // Upgrade page (admin only).
        add_submenu_page(
            self::MENU_SLUG,
            __('Pro (Coming Soon)', 'client-report-dashboard'),
            __('Pro (Coming Soon)', 'client-report-dashboard'),
            'manage_options',
            'cliredas-upgrade',
            array($this->upgrade_page, 'render')
        );

        // Settings page (admin only) - points to the same slug as Settings → Client Report.
        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'client-report-dashboard'),
            __('Settings', 'client-report-dashboard'),
            'manage_options',
            'options-general.php?page=' . CLIREDAS_Settings::SETTINGS_PAGE_SLUG
        );

        /**
         * Action after menus are registered.
         *
         * Pro add-on can hook here to add submenu pages.
         */
        do_action('cliredas_after_register_menus');
    }

    public function redirect_to_settings()
    {
        wp_safe_redirect(admin_url('options-general.php?page=' . CLIREDAS_Settings::SETTINGS_PAGE_SLUG));
        exit;
    }
}
