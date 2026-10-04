<?php

/**
 * GA4 OAuth scaffolding (connect/disconnect + auth URL).
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/**
 * Handle the GA4 OAuth connection flow.
 */
final class CLIREDAS_GA4_Auth
{

    /**
     * Google OAuth token endpoint.
     *
     * @var string
     */
    const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /**
     * Settings service.
     *
     * @var CLIREDAS_Settings
     */
    private $settings;

    /**
     * Whether the current callback passed OAuth state validation.
     *
     * @var bool
     */
    private $callback_verified = false;

    /**
     * Set up the GA4 auth controller.
     *
     * @param CLIREDAS_Settings $settings Settings service.
     */
    public function __construct(CLIREDAS_Settings $settings)
    {
        $this->settings = $settings;

        add_action('admin_post_cliredas_ga4_connect', array($this, 'handle_connect'));
        add_action('admin_post_cliredas_ga4_oauth_callback', array($this, 'handle_oauth_callback'));
        add_action('admin_post_cliredas_ga4_disconnect', array($this, 'handle_disconnect'));
        add_action('admin_post_cliredas_ga4_clear_secret', array($this, 'handle_clear_secret'));
    }

    /**
     * Redirect URI that must be whitelisted in Google Cloud Console.
     *
     * @return string
     */
    public function get_redirect_uri()
    {
        return $this->settings->get_ga4_redirect_uri();
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
        check_admin_referer('cliredas_ga4_connect');
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'cliredas-analytics-dashboard'));
        }

        $readable = CLIREDAS_Credential_Store::instance()->check_readable(array('ga4_client_secret'));
        if (is_wp_error($readable)) {
            CLIREDAS_Audit_Log::record('connect', 'storage_failed');
            $this->safe_redirect_with_flag(array('cliredas_ga4_error' => $readable->get_error_code()));
        }
        $auth_url = $this->get_authorization_url();
        if ('' === $auth_url) {
            CLIREDAS_Audit_Log::record('connect', 'configuration');
            $this->safe_redirect_with_flag(array('cliredas_ga4_error' => 'missing_client_id'));
        }

        // Allow safe redirect to Google's OAuth host for this request.
        add_filter(
            'allowed_redirect_hosts',
            static function (array $hosts) use ($auth_url) {
                $host = (string) wp_parse_url($auth_url, PHP_URL_HOST);
                if ('' !== $host && ! in_array($host, $hosts, true)) {
                    $hosts[] = $host;
                }
                return $hosts;
            }
        );

        wp_safe_redirect($auth_url);
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
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $state = trim($state);

        $stored_state = get_user_meta(get_current_user_id(), 'cliredas_ga4_oauth_state_token', true);
        $stored_state = is_string($stored_state) ? $stored_state : '';

        if ('' === $state || '' === $stored_state) {
            $this->safe_redirect_with_flag(
                array(
                    'cliredas_ga4_error' => 'missing_state',
                )
            );
        }

        $state_parts = explode('.', $state, 2);
        if (2 !== count($state_parts)) {
            $this->safe_redirect_with_flag(
                array(
                    'cliredas_ga4_error' => 'invalid_state',
                )
            );
        }

        $state_nonce = sanitize_text_field($state_parts[0]);
        $state_token = sanitize_text_field($state_parts[1]);

        if (! wp_verify_nonce($state_nonce, 'cliredas_ga4_oauth_state')) {
            $this->safe_redirect_with_flag(
                array(
                    'cliredas_ga4_error' => 'invalid_state',
                )
            );
        }

        if ('' === $state_token || ! hash_equals($stored_state, $state_token)) {
            $this->safe_redirect_with_flag(
                array(
                    'cliredas_ga4_error' => 'invalid_state',
                )
            );
        }

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'cliredas-analytics-dashboard'));
        }

        // State is valid; delete it to prevent replay. A new Connect flow will set a new state.
        delete_user_meta(get_current_user_id(), 'cliredas_ga4_oauth_state_token');
        $this->callback_verified = true;

        $error = isset($_GET['error']) ? sanitize_key(wp_unslash($_GET['error'])) : '';

        if ('' !== $error) {
            $args = array(
                'cliredas_ga4_error' => 'access_denied' === $error ? 'oauth_access_denied' : 'oauth_failed',
            );

            $this->safe_redirect_with_flag($args);
        }

        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $code = trim($code);

        if ('' === $code) {
            $this->safe_redirect_with_flag(
                array(
                    'cliredas_ga4_error' => 'missing_code',
                )
            );
        }

        if (! $this->is_valid_authorization_code($code)) {
            $this->safe_redirect_with_flag(
                array(
                    'cliredas_ga4_error' => 'invalid_code',
                )
            );
        }

        $result = $this->exchange_code_for_tokens($code);
        if (is_wp_error($result)) {
            $error_code = (string) $result->get_error_code();

            $args = array(
                'cliredas_ga4_error' => $error_code,
            );

            $this->safe_redirect_with_flag($args);
        }

        $current = $this->settings->get_settings();

        $access_token = isset($result['access_token']) ? (string) $result['access_token'] : '';
        $expires_at   = isset($result['expires_at']) ? (int) $result['expires_at'] : 0;
        $refresh_token = isset($result['refresh_token']) ? (string) $result['refresh_token'] : '';

        if ('' === $refresh_token && empty($current['ga4_refresh_token'])) {
            $this->safe_redirect_with_flag(
                array(
                    'cliredas_ga4_error' => 'missing_refresh_token',
                )
            );
        }

        $changes = array('ga4_access_token' => $access_token, 'ga4_token_expires' => $expires_at, 'ga4_connected' => 1);

        if ('' !== $refresh_token) {
            $changes['ga4_refresh_token'] = $refresh_token;
        }

        $saved = $this->settings->save_settings($changes);
        if (is_wp_error($saved)) {
            $this->safe_redirect_with_flag(array('cliredas_ga4_error' => $saved->get_error_code()));
        }

        $this->safe_redirect_with_flag(array('cliredas_ga4_notice' => 'connected'));
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @param string $code Authorization code from Google.
     * @return array|\WP_Error
     */
    private function exchange_code_for_tokens($code)
    {
        $readable = CLIREDAS_Credential_Store::instance()->check_readable(array('ga4_client_secret'));
        if (is_wp_error($readable)) {
            return $readable;
        }
        $code = trim((string) $code);
        if ('' === $code) {
            return new WP_Error('missing_code', __('Missing authorization code.', 'cliredas-analytics-dashboard'));
        }

        $settings = $this->settings->get_settings();

        $client_id = isset($settings['ga4_client_id']) ? trim((string) $settings['ga4_client_id']) : '';
        if ('' === $client_id) {
            return new WP_Error('missing_client_id', __('Missing OAuth Client ID.', 'cliredas-analytics-dashboard'));
        }

        $client_secret = isset($settings['ga4_client_secret']) ? trim((string) $settings['ga4_client_secret']) : '';
        if ('' === $client_secret) {
            return new WP_Error('missing_client_secret', __('Missing OAuth Client Secret.', 'cliredas-analytics-dashboard'));
        }

        $response = wp_remote_post(
            self::TOKEN_ENDPOINT,
            array(
                'timeout' => 20,
                'body'    => array(
                    'code'          => $code,
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'redirect_uri'  => $this->get_redirect_uri(),
                    'grant_type'    => 'authorization_code',
                ),
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error('token_exchange_network', __('Unable to contact Google to complete the connection. Check connectivity and try again.', 'cliredas-analytics-dashboard'));
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = (string) wp_remote_retrieve_body($response);

        $data = json_decode($body, true);
        if (! is_array($data)) {
            return new WP_Error('token_response_invalid', __('Invalid token response from Google.', 'cliredas-analytics-dashboard'));
        }

        if (200 !== $status) {
            return new WP_Error('token_exchange_failed', __('Google could not complete the connection. Verify your OAuth credentials and try connecting again.', 'cliredas-analytics-dashboard'));
        }

        $access_token = isset($data['access_token']) && is_string($data['access_token']) ? trim($data['access_token']) : '';
        if ('' === $access_token) {
            return new WP_Error('token_missing_access_token', __('Google token response is missing access_token.', 'cliredas-analytics-dashboard'));
        }

        $expires_in = isset($data['expires_in']) ? (int) $data['expires_in'] : 0;
        if ($expires_in <= 0) {
            $expires_in = 3600;
        }

        $refresh_token = isset($data['refresh_token']) && is_string($data['refresh_token']) ? trim($data['refresh_token']) : '';

        return array(
            'access_token'  => $access_token,
            'refresh_token' => $refresh_token,
            // Buffer a minute to avoid using an already-expired token.
            'expires_at'    => time() + max(60, $expires_in - 60),
        );
    }

    /**
     * Disconnect: clear connection flags/tokens.
     *
     * @return void
     */
    public function handle_disconnect()
    {
        check_admin_referer('cliredas_ga4_disconnect');
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'cliredas-analytics-dashboard'));
        }

        // Keep credentials; clear connection and token-ish fields.
        $saved = $this->settings->save_settings(array('ga4_connected' => 0, 'ga4_refresh_token' => '', 'ga4_access_token' => '', 'ga4_token_expires' => 0, 'ga4_property_id' => ''));
        CLIREDAS_Audit_Log::record('disconnect', is_wp_error($saved) ? CLIREDAS_Audit_Log::outcome($saved) : 'success');
        if (is_wp_error($saved)) {
            $this->safe_redirect_with_flag(array('cliredas_ga4_error' => $saved->get_error_code()));
        }

        $this->safe_redirect_with_flag(array('cliredas_ga4_notice' => 'disconnected'));
    }

    /**
     * Clear stored OAuth client secret and disconnect.
     *
     * @return void
     */
    public function handle_clear_secret()
    {
        check_admin_referer('cliredas_ga4_clear_secret');
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'cliredas-analytics-dashboard'));
        }

        // Clearing the secret effectively disables token refresh, so also disconnect and clear tokens.
        $saved = $this->settings->save_settings(array('ga4_client_secret' => '', 'ga4_connected' => 0, 'ga4_refresh_token' => '', 'ga4_access_token' => '', 'ga4_token_expires' => 0, 'ga4_property_id' => ''));
        CLIREDAS_Audit_Log::record('secret_clear', is_wp_error($saved) ? CLIREDAS_Audit_Log::outcome($saved) : 'success');
        if (is_wp_error($saved)) {
            $this->safe_redirect_with_flag(array('cliredas_ga4_error' => $saved->get_error_code()));
        }

        $this->safe_redirect_with_flag(array('cliredas_ga4_notice' => 'secret_cleared'));
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
        // State combines a WP nonce and a random token to protect this callback from CSRF/replay.
        $state_nonce = wp_create_nonce('cliredas_ga4_oauth_state');
        $state_token = wp_generate_password(32, false, false);

        update_user_meta(get_current_user_id(), 'cliredas_ga4_oauth_state_token', $state_token);

        return $state_nonce . '.' . $state_token;
    }

    /**
     * Validate OAuth authorization code format.
     *
     * @param string $code Authorization code from callback query.
     * @return bool
     */
    private function is_valid_authorization_code($code)
    {
        $code = trim((string) $code);
        if ('' === $code) {
            return false;
        }

        // OAuth codes are opaque; validate length and reject control chars only.
        if (strlen($code) > 4096) {
            return false;
        }

        return 1 !== preg_match('/[\\x00-\\x1F\\x7F]/', $code);
    }

    /**
     * Redirect back to settings with query flags.
     *
     * @param array $args Query args.
     * @return void
     */
    private function safe_redirect_with_flag(array $args)
    {
        unset($args['cliredas_ga4_error_desc']);
        if ($this->callback_verified) {
            $code = isset($args['cliredas_ga4_error']) ? $args['cliredas_ga4_error'] : '';
            $outcome = '' === $code ? 'success' : CLIREDAS_Audit_Log::outcome(new WP_Error($code));
            if (in_array($code, array('oauth_access_denied', 'missing_code', 'invalid_code'), true)) {
                $outcome = 'authentication';
            }
            CLIREDAS_Audit_Log::record('connect', $outcome);
        }
        $args['cliredas_ga4_notice_nonce'] = wp_create_nonce('cliredas_ga4_notice');
        $url = admin_url('options-general.php?page=' . CLIREDAS_Settings::SETTINGS_PAGE_SLUG);
        $url = add_query_arg($args, $url);
        wp_safe_redirect($url);
        exit;
    }
}
