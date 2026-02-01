<?php

/**
 * Cache manager (clear cache action).
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

final class CLIREDAS_Cache_Manager
{

    /**
     * Provider instance.
     *
     * @var object
     */
    private $provider;

    /**
     * @param object $provider Provider with clear_all_cache().
     */
    public function __construct($provider)
    {
        $this->provider = $provider;

        add_action('admin_post_cliredas_clear_cache', array($this, 'handle_clear_cache'));
    }

    /**
     * Handle cache clearing (admin-post).
     *
     * @return void
     */
    public function handle_clear_cache()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'client-report-dashboard'));
        }

        check_admin_referer('cliredas_clear_cache');

        $cleared = 0;

        if (is_object($this->provider) && method_exists($this->provider, 'clear_all_cache')) {
            $cleared = (int) $this->provider->clear_all_cache();
        }

        $redirect = wp_get_referer();
        if (! $redirect) {
            $redirect = admin_url('admin.php?page=' . CLIREDAS_Admin_Menu::MENU_SLUG);
        }

        $redirect = add_query_arg(
            array(
                'cliredas_cache_cleared' => $cleared,
                'cliredas_cache_cleared_nonce' => wp_create_nonce('cliredas_cache_cleared'),
            ),
            $redirect
        );

        wp_safe_redirect($redirect);
        exit;
    }
}
