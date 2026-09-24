<?php

/**
 * GA4 setup assistant diagnostics.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/**
 * Run and store administrator-facing setup diagnostics.
 */
final class CLIREDAS_Setup_Diagnostics
{

    /**
     * Per-user diagnostic result key.
     */
    const USER_META_KEY = 'cliredas_setup_diagnostics';

    /**
     * Settings service.
     *
     * @var CLIREDAS_Settings
     */
    private $settings;

    /**
     * GA4 API client.
     *
     * @var CLIREDAS_GA4_Client
     */
    private $client;

    /**
     * Set up diagnostics and the protected admin action.
     *
     * @param CLIREDAS_Settings   $settings Settings service.
     * @param CLIREDAS_GA4_Client $client   GA4 API client.
     */
    public function __construct(CLIREDAS_Settings $settings, CLIREDAS_GA4_Client $client)
    {
        $this->settings = $settings;
        $this->client   = $client;

        add_action('admin_post_cliredas_run_diagnostics', array($this, 'handle_run'));
    }

    /**
     * Handle an explicit administrator diagnostic run.
     *
     * @return void
     */
    public function handle_run()
    {
        check_admin_referer('cliredas_run_diagnostics');

        if (! current_user_can('manage_options')) {
            wp_die(
                esc_html__('You do not have permission to run these diagnostics.', 'cliredas-analytics-dashboard'),
                esc_html__('Forbidden', 'cliredas-analytics-dashboard'),
                array('response' => 403)
            );
        }

        $result = $this->run();
        update_user_meta(get_current_user_id(), self::USER_META_KEY, $result);

        $url = add_query_arg(
            array(
                'page'                       => CLIREDAS_Settings::SETTINGS_PAGE_SLUG,
                'cliredas_diagnostics'       => 'complete',
                'cliredas_diagnostics_nonce' => wp_create_nonce('cliredas_diagnostics_notice'),
            ),
            admin_url('options-general.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    /**
     * Get the current assistant state, combining live local checks with the
     * latest compatible remote result.
     *
     * @return array{checks:array<string,array>,passed:int,total:int,ran_at:int}
     */
    public function get_state()
    {
        $settings = $this->settings->get_settings();
        $checks   = $this->get_local_checks($settings);
        $stored   = get_user_meta(get_current_user_id(), self::USER_META_KEY, true);
        $ran_at   = 0;

        if (
            is_array($stored)
            && isset($stored['fingerprint'], $stored['checks'])
            && is_array($stored['checks'])
            && hash_equals($this->get_fingerprint($settings), (string) $stored['fingerprint'])
        ) {
            foreach (array('token_health', 'property_availability', 'selected_property') as $key) {
                if (isset($stored['checks'][$key]) && is_array($stored['checks'][$key])) {
                    $checks[$key] = $this->sanitize_check($stored['checks'][$key], $checks[$key]);
                }
            }
            $ran_at = isset($stored['ran_at']) ? absint($stored['ran_at']) : 0;
        }

        $passed = 0;
        foreach ($checks as $check) {
            if ('pass' === $check['status']) {
                ++$passed;
            }
        }

        return array(
            'checks' => $checks,
            'passed' => $passed,
            'total'  => count($checks),
            'ran_at' => $ran_at,
        );
    }

    /**
     * Build deterministic local setup checks.
     *
     * @param array|null $settings Optional settings override for verification.
     * @return array<string,array>
     */
    public function get_local_checks($settings = null)
    {
        $has_settings_override = is_array($settings);
        if (! $has_settings_override) {
            $settings = $this->settings->get_settings();
        }

        if ($has_settings_override) {
            $base_url = isset($settings['ga4_redirect_base_url']) ? trim((string) $settings['ga4_redirect_base_url']) : '';
            $redirect_uri = '' !== $base_url
                ? trailingslashit($base_url) . 'wp-admin/admin-post.php?action=cliredas_ga4_oauth_callback'
                : admin_url('admin-post.php?action=cliredas_ga4_oauth_callback');
        } else {
            $redirect_uri = $this->settings->get_ga4_redirect_uri();
        }
        $url_check    = $this->validate_effective_url($redirect_uri);
        $redirect     = $this->validate_redirect_uri($redirect_uri);
        $client_id    = isset($settings['ga4_client_id']) ? trim((string) $settings['ga4_client_id']) : '';
        $has_secret   = ! empty($settings['ga4_client_secret']);
        $has_refresh  = ! empty($settings['ga4_refresh_token']);
        $connected    = ! empty($settings['ga4_connected']) || $has_refresh || (! empty($settings['ga4_access_token']) && ! empty($settings['ga4_token_expires']));
        $property_id  = isset($settings['ga4_property_id']) ? trim((string) $settings['ga4_property_id']) : '';

        if ('' === $client_id || ! $has_secret) {
            $credentials = $this->make_check(
                'fail',
                'configuration',
                __('OAuth credentials', 'cliredas-analytics-dashboard'),
                __('Save both an OAuth Client ID and Client Secret.', 'cliredas-analytics-dashboard'),
                'credentials'
            );
        } elseif (1 !== preg_match('/\.apps\.googleusercontent\.com$/i', $client_id)) {
            $credentials = $this->make_check(
                'warning',
                'configuration',
                __('OAuth credentials', 'cliredas-analytics-dashboard'),
                __('Credentials are saved, but the Client ID has an unexpected format.', 'cliredas-analytics-dashboard'),
                'credentials'
            );
        } else {
            $credentials = $this->make_check(
                'pass',
                'configuration',
                __('OAuth credentials', 'cliredas-analytics-dashboard'),
                __('OAuth Client ID and Client Secret are saved.', 'cliredas-analytics-dashboard'),
                'credentials'
            );
        }

        if (! $connected) {
            $connection = $this->make_check(
                'fail',
                'authentication',
                __('Google connection', 'cliredas-analytics-dashboard'),
                __('Google Analytics is not connected.', 'cliredas-analytics-dashboard'),
                'connection'
            );
        } elseif (! $has_refresh) {
            $connection = $this->make_check(
                'warning',
                'authentication',
                __('Google connection', 'cliredas-analytics-dashboard'),
                __('A connection is recorded, but no refresh token is available.', 'cliredas-analytics-dashboard'),
                'connection'
            );
        } else {
            $connection = $this->make_check(
                'pass',
                'authentication',
                __('Google connection', 'cliredas-analytics-dashboard'),
                __('Google Analytics is connected.', 'cliredas-analytics-dashboard'),
                'connection'
            );
        }

        $remote_status  = $connected ? 'not_run' : 'blocked';
        $remote_message = $connected
            ? __('Run diagnostics to verify the saved refresh token.', 'cliredas-analytics-dashboard')
            : __('Connect Google Analytics before checking token health.', 'cliredas-analytics-dashboard');
        $properties_message = $connected
            ? __('Run diagnostics to load and verify accessible GA4 properties.', 'cliredas-analytics-dashboard')
            : __('Connect Google Analytics before checking available properties.', 'cliredas-analytics-dashboard');

        if ('' === $property_id) {
            $selected_property = $this->make_check(
                'fail',
                'property_selection',
                __('Selected property', 'cliredas-analytics-dashboard'),
                __('Choose the GA4 property used by the dashboard.', 'cliredas-analytics-dashboard'),
                'property'
            );
        } else {
            $selected_property = $this->make_check(
                'not_run',
                'property_selection',
                __('Selected property', 'cliredas-analytics-dashboard'),
                __('A property is selected. Run diagnostics to verify access.', 'cliredas-analytics-dashboard'),
                'property'
            );
        }

        return array(
            'public_url' => $url_check,
            'redirect_uri' => $redirect,
            'credentials' => $credentials,
            'connection' => $connection,
            'token_health' => $this->make_check(
                $remote_status,
                'authentication',
                __('Refresh-token health', 'cliredas-analytics-dashboard'),
                $remote_message,
                'diagnostics'
            ),
            'property_availability' => $this->make_check(
                $remote_status,
                'property_selection',
                __('Available GA4 properties', 'cliredas-analytics-dashboard'),
                $properties_message,
                'diagnostics'
            ),
            'selected_property' => $selected_property,
        );
    }

    /**
     * Run remote diagnostics and return a safe per-user result.
     *
     * @return array{fingerprint:string,ran_at:int,checks:array<string,array>}
     */
    private function run()
    {
        $settings = $this->settings->get_settings();
        $checks   = $this->get_local_checks($settings);

        if ('fail' === $checks['credentials']['status'] || empty($settings['ga4_refresh_token'])) {
            $checks['token_health'] = $this->make_check(
                'blocked',
                'authentication',
                __('Refresh-token health', 'cliredas-analytics-dashboard'),
                __('Save credentials and connect Google Analytics before running this check.', 'cliredas-analytics-dashboard'),
                'connection'
            );
            $checks['property_availability'] = $this->make_check(
                'blocked',
                'property_selection',
                __('Available GA4 properties', 'cliredas-analytics-dashboard'),
                __('A healthy Google connection is required before properties can be checked.', 'cliredas-analytics-dashboard'),
                'connection'
            );
        } else {
            $token = $this->client->refresh_access_token();
            if (is_wp_error($token)) {
                $checks['token_health'] = $this->check_from_error(
                    $token,
                    __('Refresh-token health', 'cliredas-analytics-dashboard'),
                    'connection'
                );
                $checks['property_availability'] = $this->make_check(
                    'blocked',
                    'property_selection',
                    __('Available GA4 properties', 'cliredas-analytics-dashboard'),
                    __('Resolve the token problem before checking properties.', 'cliredas-analytics-dashboard'),
                    'connection'
                );
            } else {
                $checks['token_health'] = $this->make_check(
                    'pass',
                    'authentication',
                    __('Refresh-token health', 'cliredas-analytics-dashboard'),
                    __('Google accepted the refresh token and issued a new access token.', 'cliredas-analytics-dashboard'),
                    'diagnostics'
                );

                $properties = $this->client->list_properties();
                if (is_wp_error($properties)) {
                    $checks['property_availability'] = $this->check_from_error(
                        $properties,
                        __('Available GA4 properties', 'cliredas-analytics-dashboard'),
                        'diagnostics'
                    );
                } elseif (empty($properties)) {
                    $checks['property_availability'] = $this->make_check(
                        'fail',
                        'permission',
                        __('Available GA4 properties', 'cliredas-analytics-dashboard'),
                        __('No GA4 properties are available to the connected Google account.', 'cliredas-analytics-dashboard'),
                        'connection'
                    );
                } else {
                    $checks['property_availability'] = $this->make_check(
                        'pass',
                        'property_selection',
                        __('Available GA4 properties', 'cliredas-analytics-dashboard'),
                        sprintf(
                            /* translators: %d: number of accessible GA4 properties. */
                            _n('%d accessible GA4 property was found.', '%d accessible GA4 properties were found.', count($properties), 'cliredas-analytics-dashboard'),
                            count($properties)
                        ),
                        'property'
                    );

                    $property_id = isset($settings['ga4_property_id']) ? trim((string) $settings['ga4_property_id']) : '';
                    if ('' === $property_id) {
                        $checks['selected_property'] = $this->make_check(
                            'fail',
                            'property_selection',
                            __('Selected property', 'cliredas-analytics-dashboard'),
                            __('Choose the GA4 property used by the dashboard.', 'cliredas-analytics-dashboard'),
                            'property'
                        );
                    } elseif (isset($properties[$property_id])) {
                        $checks['selected_property'] = $this->make_check(
                            'pass',
                            'property_selection',
                            __('Selected property', 'cliredas-analytics-dashboard'),
                            __('The selected property is accessible to the connected account.', 'cliredas-analytics-dashboard'),
                            'property'
                        );
                    } else {
                        $checks['selected_property'] = $this->make_check(
                            'fail',
                            'property_selection',
                            __('Selected property', 'cliredas-analytics-dashboard'),
                            __('The selected property is no longer available to the connected account.', 'cliredas-analytics-dashboard'),
                            'property'
                        );
                    }
                }
            }
        }

        return array(
            'fingerprint' => $this->get_fingerprint($this->settings->get_settings()),
            'ran_at'      => time(),
            'checks'      => array(
                'token_health'         => $checks['token_health'],
                'property_availability' => $checks['property_availability'],
                'selected_property'    => $checks['selected_property'],
            ),
        );
    }

    /**
     * Validate the effective URL used for the OAuth callback.
     *
     * @param string $redirect_uri Computed redirect URI.
     * @return array
     */
    private function validate_effective_url($redirect_uri)
    {
        $parts  = wp_parse_url($redirect_uri);
        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
        $host   = isset($parts['host']) ? strtolower(trim((string) $parts['host'], '[]')) : '';

        if ('' === $scheme || '' === $host) {
            return $this->make_check(
                'fail',
                'configuration',
                __('Effective public OAuth URL', 'cliredas-analytics-dashboard'),
                __('The effective OAuth URL is invalid.', 'cliredas-analytics-dashboard'),
                'public_url'
            );
        }

        $is_loopback = $this->is_loopback_host($host);
        if ($is_loopback) {
            return $this->make_check(
                'warning',
                'configuration',
                __('Effective public OAuth URL', 'cliredas-analytics-dashboard'),
                __('Google permits localhost for development, but the callback must return to this same browser session.', 'cliredas-analytics-dashboard'),
                'public_url'
            );
        }

        if ('https' !== $scheme) {
            return $this->make_check(
                'fail',
                'configuration',
                __('Effective public OAuth URL', 'cliredas-analytics-dashboard'),
                __('Public OAuth URLs must use HTTPS.', 'cliredas-analytics-dashboard'),
                'public_url'
            );
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->make_check(
                'fail',
                'configuration',
                __('Effective public OAuth URL', 'cliredas-analytics-dashboard'),
                __('Google does not accept raw public IP addresses as OAuth redirect hosts.', 'cliredas-analytics-dashboard'),
                'public_url'
            );
        }

        if (
            false === strpos($host, '.')
            || preg_match('/\.(local|localhost|test|invalid|example)$/i', $host)
            || ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
        ) {
            return $this->make_check(
                'fail',
                'configuration',
                __('Effective public OAuth URL', 'cliredas-analytics-dashboard'),
                __('Use an HTTPS host with a public domain suffix, or configure the Public OAuth base URL.', 'cliredas-analytics-dashboard'),
                'public_url'
            );
        }

        return $this->make_check(
            'pass',
            'configuration',
            __('Effective public OAuth URL', 'cliredas-analytics-dashboard'),
            __('The effective OAuth URL uses a valid public HTTPS host.', 'cliredas-analytics-dashboard'),
            'public_url'
        );
    }

    /**
     * Validate the shape of the computed redirect URI.
     *
     * @param string $redirect_uri Computed redirect URI.
     * @return array
     */
    private function validate_redirect_uri($redirect_uri)
    {
        $parts  = wp_parse_url($redirect_uri);
        if (! is_array($parts)) {
            return $this->make_check(
                'fail',
                'configuration',
                __('OAuth redirect URI', 'cliredas-analytics-dashboard'),
                __('The computed OAuth redirect URI is invalid.', 'cliredas-analytics-dashboard'),
                'redirect_uri'
            );
        }

        $path   = isset($parts['path']) ? (string) $parts['path'] : '';
        $query  = isset($parts['query']) ? (string) $parts['query'] : '';
        $params = array();
        parse_str($query, $params);

        if (
            empty($parts['scheme'])
            || empty($parts['host'])
            || false === strpos($path, '/wp-admin/admin-post.php')
            || ! isset($params['action'])
            || 'cliredas_ga4_oauth_callback' !== $params['action']
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return $this->make_check(
                'fail',
                'configuration',
                __('OAuth redirect URI', 'cliredas-analytics-dashboard'),
                __('The computed OAuth redirect URI is invalid.', 'cliredas-analytics-dashboard'),
                'redirect_uri'
            );
        }

        return $this->make_check(
            'pass',
            'configuration',
            __('OAuth redirect URI', 'cliredas-analytics-dashboard'),
            __('The redirect URI is correctly formed. Confirm that this exact value is registered in Google Cloud.', 'cliredas-analytics-dashboard'),
            'redirect_uri'
        );
    }

    /**
     * Map a stable client error to a safe diagnostic result.
     *
     * @param WP_Error $error  Error to classify.
     * @param string   $label  Check label.
     * @param string   $action Corrective action key.
     * @return array
     */
    private function check_from_error(WP_Error $error, $label, $action)
    {
        $code = (string) $error->get_error_code();

        $map = array(
            'missing_client_id' => array('configuration', __('The OAuth Client ID is missing.', 'cliredas-analytics-dashboard')),
            'missing_client_secret' => array('configuration', __('The OAuth Client Secret is missing.', 'cliredas-analytics-dashboard')),
            'missing_refresh_token' => array('authentication', __('The refresh token is missing. Reconnect Google Analytics.', 'cliredas-analytics-dashboard')),
            'token_revoked' => array('authentication', __('Google rejected or revoked the refresh token. Reconnect Google Analytics.', 'cliredas-analytics-dashboard')),
            'token_credentials_invalid' => array('authentication', __('Google rejected the saved OAuth client credentials.', 'cliredas-analytics-dashboard')),
            'token_refresh_network' => array('network', __('The site could not contact Google. Check outbound HTTPS connectivity and try again.', 'cliredas-analytics-dashboard')),
            'token_refresh_invalid' => array('google_service', __('Google returned an invalid token response. Try again later.', 'cliredas-analytics-dashboard')),
            'token_refresh_missing_access_token' => array('google_service', __('Google did not return an access token. Reconnect and try again.', 'cliredas-analytics-dashboard')),
            'ga4_permission_denied' => array('permission', __('Google denied access. Confirm that this account can view the GA4 property.', 'cliredas-analytics-dashboard')),
            'ga4_quota_exceeded' => array('quota', __('Google API quota is currently exhausted. Try again later.', 'cliredas-analytics-dashboard')),
            'ga4_not_found' => array('property_selection', __('The selected GA4 property could not be found.', 'cliredas-analytics-dashboard')),
            'api_invalid' => array('google_service', __('Google returned an invalid API response. Try again later.', 'cliredas-analytics-dashboard')),
            'api_failed' => array('network', __('The Google Analytics API request failed. Check connectivity and try again.', 'cliredas-analytics-dashboard')),
        );

        $classified = isset($map[$code]) ? $map[$code] : array(
            'google_service',
            __('Google Analytics diagnostics could not be completed. Try again later.', 'cliredas-analytics-dashboard'),
        );

        return $this->make_check('fail', $classified[0], $label, $classified[1], $action);
    }

    /**
     * Build a normalized check result.
     *
     * @param string $status   Status.
     * @param string $category Failure category.
     * @param string $label    Label.
     * @param string $message  Safe message.
     * @param string $action   Corrective action key.
     * @return array
     */
    private function make_check($status, $category, $label, $message, $action)
    {
        return array(
            'status'   => sanitize_key($status),
            'category' => sanitize_key($category),
            'label'    => sanitize_text_field($label),
            'message'  => sanitize_text_field($message),
            'action'   => sanitize_key($action),
        );
    }

    /**
     * Sanitize a stored check before rendering it.
     *
     * @param array $check    Stored check.
     * @param array $fallback Fallback check.
     * @return array
     */
    private function sanitize_check(array $check, array $fallback)
    {
        $statuses   = array('pass', 'warning', 'fail', 'blocked', 'not_run');
        $status     = isset($check['status']) ? sanitize_key($check['status']) : '';
        $check_data = array(
            'status'   => in_array($status, $statuses, true) ? $status : $fallback['status'],
            'category' => isset($check['category']) ? sanitize_key($check['category']) : $fallback['category'],
            'label'    => isset($check['label']) ? sanitize_text_field($check['label']) : $fallback['label'],
            'message'  => isset($check['message']) ? sanitize_text_field($check['message']) : $fallback['message'],
            'action'   => isset($check['action']) ? sanitize_key($check['action']) : $fallback['action'],
        );

        return $check_data;
    }

    /**
     * Create a non-sensitive signature for settings relevant to diagnostics.
     *
     * @param array $settings Current settings.
     * @return string
     */
    private function get_fingerprint(array $settings)
    {
        $data = array(
            'client_id'    => isset($settings['ga4_client_id']) ? trim((string) $settings['ga4_client_id']) : '',
            'has_secret'   => ! empty($settings['ga4_client_secret']),
            'redirect_uri' => $this->settings->get_ga4_redirect_uri(),
            'connected'    => ! empty($settings['ga4_connected']),
            'has_refresh'  => ! empty($settings['ga4_refresh_token']),
            'property_id'  => isset($settings['ga4_property_id']) ? trim((string) $settings['ga4_property_id']) : '',
        );

        return wp_hash(wp_json_encode($data), 'auth');
    }

    /**
     * Determine whether a host is a Google-supported local loopback host.
     *
     * @param string $host Hostname or IP.
     * @return bool
     */
    private function is_loopback_host($host)
    {
        if ('localhost' === $host || '::1' === $host) {
            return true;
        }

        return 0 === strpos($host, '127.');
    }
}
