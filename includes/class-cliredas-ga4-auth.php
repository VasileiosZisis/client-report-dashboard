<?php

/**
 * GA4 OAuth scaffolding (connect/disconnect + auth URL).
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

final class CLIREDAS_GA4_Auth
{

    /**
     * Settings service.
     *
     * @var CLIREDAS_Settings
     */
    private $settings;

    /**
     * @param CLIREDAS_Settings $settings Settings service.
     */
    public function __construct(CLIREDAS_Settings $settings)
    {
        $this->settings = $settings;

        add_action('admin_post_cliredas_ga4_connect', array($this, 'handle_connect'));
        add_action('admin_post_cliredas_ga4_oauth_callback', array($this, 'handle_oauth_callback'));
        add_action('admin_post_cliredas_ga4_disconnect', array($this, 'handle_disconnect'));
    }

    /**
     * Redirect URI that must be whitelisted in Google Cloud Console.
     *
     * @return string
     */
    public function get_redirect_uri()
    {
        return admin_url('admin-post.php?action=cliredas_ga4_oauth_callback');
    }

    /**
     * Build Google OAuth authorization URL (Authorization Code flow).
     *
     * @return string
     */
    public function get_authorization_url()
    {
        $settings = $this->settings->get_settings();

        $client_id = isset($settings['ga4_client_id']) ? (string) $settings['ga4_client_id'] : '';
        $client_id = trim($client_id);

        if ('' === $client_id) {
            return '';
        }

        $state = $this->generate_and_store_state();

        $query = array(
            'client_id'     => $client_id,
            'redirect_uri'  => $this->get_redirect_uri(),
            'response_type' => 'code',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'scope'         => $this->get_scope_string(),
            'state'         => $state,
        );

        return add_query_arg($query, 'https://accounts.google.com/o/oauth2/v2/auth');
    }

    /**
     * Handle connect: redirect to Google's consent screen.
     *
     * @return void
     */
    public function handle_connect()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'client-report-dashboard'));
        }

        check_admin_referer('cliredas_ga4_connect');

        $auth_url = $this->get_authorization_url();
        if ('' === $auth_url) {
            $this->safe_redirect_with_flag(array('cliredas_ga4_error' => 'missing_client_id'));
        }

        wp_redirect($auth_url);
        exit;
    }

    /**
     * OAuth callback handler (stub for Milestone 10).
     * Milestone 11 will exchange the code for tokens.
     *
     * @return void
     */
    public function handle_oauth_callback()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'client-report-dashboard'));
        }

        // For Milestone 10 we only validate that we reached the callback.
        $this->safe_redirect_with_flag(array('cliredas_ga4_notice' => 'callback_reached'));
    }

    /**
     * Disconnect: clear connection flags/tokens.
     *
     * @return void
     */
    public function handle_disconnect()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'client-report-dashboard'));
        }

        check_admin_referer('cliredas_ga4_disconnect');

        $current = $this->settings->get_settings();

        // Keep credentials; clear connection and token-ish fields.
        $current['ga4_connected']     = 0;
        $current['ga4_refresh_token'] = '';
        $current['ga4_access_token']  = '';
        $current['ga4_token_expires'] = 0;
        $current['ga4_property_id']   = '';

        update_option(CLIREDAS_Plugin::OPTION_KEY, $current);

        $this->safe_redirect_with_flag(array('cliredas_ga4_notice' => 'disconnected'));
    }

    /**
     * Scopes for GA4 read-only reporting.
     *
     * @return string
     */
    private function get_scope_string()
    {
        $scopes = array(
            'https://www.googleapis.com/auth/analytics.readonly',
        );

        /**
         * Filter OAuth scopes (Pro or future can add).
         *
         * @param string[] $scopes Scopes.
         */
        $scopes = apply_filters('cliredas_ga4_oauth_scopes', $scopes);

        $scopes = array_values(array_filter(array_map('trim', (array) $scopes)));

        return implode(' ', $scopes);
    }

    /**
     * Generate a state value and store it for later verification.
     *
     * @return string
     */
    private function generate_and_store_state()
    {
        $payload = array(
            'u' => get_current_user_id(),
            't' => time(),
            'n' => wp_generate_password(12, false, false),
        );

        $state = wp_json_encode($payload);
        $state = $state ? base64_encode($state) : wp_generate_password(24, false, false);

        update_user_meta(get_current_user_id(), 'cliredas_ga4_oauth_state', $state);

        return $state;
    }

    /**
     * Redirect back to settings with query flags.
     *
     * @param array $args Query args.
     * @return void
     */
    private function safe_redirect_with_flag(array $args)
    {
        $url = admin_url('options-general.php?page=' . CLIREDAS_Settings::SETTINGS_PAGE_SLUG);
        $url = add_query_arg($args, $url);
        wp_safe_redirect($url);
        exit;
    }
}
