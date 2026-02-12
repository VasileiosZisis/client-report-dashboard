<?php

/**
 * Main plugin class.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/**
 * Main plugin orchestrator.
 */
final class CLIREDAS_Plugin
{

    /**
     * Plugin instance.
     *
     * @var CLIREDAS_Plugin|null
     */
    private static $instance = null;

    /**
     * Settings option name.
     *
     * @var string
     */
    const OPTION_KEY = 'cliredas_settings';

    /**
     * Settings instance.
     *
     * @var CLIREDAS_Settings|null
     */
    private $settings = null;

    /**
     * Get singleton instance.
     *
     * @return CLIREDAS_Plugin
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->setup_hooks();
        }

        return self::$instance;
    }

    /**
     * Activation hook callback.
     *
     * Creates default settings so first install behaves as expected.
     *
     * @return void
     */
    public static function activate()
    {
        $defaults = array(
            'allow_editors' => 0,
        );

        $existing = get_option(self::OPTION_KEY, null);

        // First install: add defaults.
        if (null === $existing) {
            add_option(self::OPTION_KEY, $defaults);
            return;
        }

        // If option exists, ensure required keys exist (non-destructive).
        if (is_array($existing)) {
            $merged = wp_parse_args($existing, $defaults);
            if ($merged !== $existing) {
                update_option(self::OPTION_KEY, $merged);
            }
            return;
        }

        // If something unexpected is stored, reset to defaults (safest baseline).
        update_option(self::OPTION_KEY, $defaults);
    }

    /**
     * Register core hooks.
     *
     * @return void
     */
    private function setup_hooks()
    {
        add_action('plugins_loaded', array($this, 'on_plugins_loaded'), 10);
        add_action('init', array($this, 'on_init'), 10);

        if (is_admin()) {
            /**
             * Bootstrap admin components early enough so their admin_menu hooks
             * register before WordPress fires admin_menu.
             */
            add_action('init', array($this, 'bootstrap_admin'), 9);

            /**
             * Keep admin_init for admin-init specific behavior and add-on extension.
             */
            add_action('admin_init', array($this, 'on_admin_init'), 10);
        }
    }

    /**
     * Fires on plugins_loaded.
     *
     * @return void
     */
    public function on_plugins_loaded()
    {
        /**
         * Fires when Cliredas - Simple Google Analytics Dashboard has loaded.
         *
         * Useful for add-ons (Pro) to bootstrap after the free plugin is available.
         */
        do_action('cliredas_loaded');
    }

    /**
     * Fires on init.
     *
     * @return void
     */
    public function on_init()
    {
        /**
         * Fires on WP init when the plugin is initialized.
         *
         * Add-ons can register filters, post types, cron hooks, etc.
         */
        do_action('cliredas_init');
    }

    /**
     * Bootstrap admin components early (before admin_menu runs).
     *
     * Uses __DIR__ so loading remains stable even when the plugin folder is a symlink.
     *
     * @return void
     */
    public function bootstrap_admin()
    {
        static $bootstrapped = false;

        if ($bootstrapped) {
            return;
        }
        $bootstrapped = true;

        require_once __DIR__ . '/class-cliredas-settings.php';
        require_once __DIR__ . '/class-cliredas-data-provider.php';
        require_once __DIR__ . '/class-cliredas-ga4-client.php';
        require_once __DIR__ . '/class-cliredas-ga4-data-provider.php';
        require_once __DIR__ . '/class-cliredas-provider-factory.php';
        require_once __DIR__ . '/class-cliredas-dashboard-page.php';
        require_once __DIR__ . '/class-cliredas-upgrade-page.php';
        require_once __DIR__ . '/class-cliredas-admin-menu.php';
        require_once __DIR__ . '/class-cliredas-admin-screens.php';
        require_once __DIR__ . '/class-cliredas-assets.php';
        require_once __DIR__ . '/class-cliredas-cache-manager.php';
        require_once __DIR__ . '/class-cliredas-ga4-auth.php';

        $this->settings = new CLIREDAS_Settings();

        new CLIREDAS_GA4_Auth($this->settings);
        $data_provider  = CLIREDAS_Provider_Factory::get_provider($this->settings);
        new CLIREDAS_Cache_Manager($data_provider);
        $dashboard_page = new CLIREDAS_Dashboard_Page($this->settings, $data_provider);
        $upgrade_page   = new CLIREDAS_Upgrade_Page();

        new CLIREDAS_Admin_Menu(
            $this->settings,
            $dashboard_page,
            $upgrade_page
        );
    }

    /**
     * Fires on admin_init.
     *
     * Keep this method focused on admin_init-specific tasks and as an extension
     * point for add-ons (Pro).
     *
     * @return void
     */
    public function on_admin_init()
    {
        /**
         * Fires during admin_init for plugin admin functionality.
         *
         * Add-ons can register settings, admin notices, etc.
         */
        do_action('cliredas_admin_init');
    }

    /**
     * Prevent direct construction.
     */
    private function __construct() {}

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserializing.
     */
    public function __wakeup()
    {
        wp_die(esc_html__('Unserializing is not allowed.', 'cliredas-analytics-dashboard'));
    }
}
