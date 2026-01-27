<?php

/**
 * GA4 client helper (token refresh + API calls).
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

final class CLIREDAS_GA4_Client
{

    /**
     * Token endpoint.
     *
     * @var string
     */
    const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /**
     * Analytics Admin API base.
     *
     * @var string
     */
    const ADMIN_API_BASE = 'https://analyticsadmin.googleapis.com/v1beta';

    /**
     * Analytics Data API base.
     *
     * @var string
     */
    const DATA_API_BASE = 'https://analyticsdata.googleapis.com/v1beta';

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
    }

    /**
     * Get a valid access token, refreshing it when needed.
     *
     * @return string|\WP_Error
     */
    public function get_valid_access_token()
    {
        $settings = $this->settings->get_settings();

        $access_token = isset($settings['ga4_access_token']) ? trim((string) $settings['ga4_access_token']) : '';
        $expires_at   = isset($settings['ga4_token_expires']) ? (int) $settings['ga4_token_expires'] : 0;

        if ('' !== $access_token && $expires_at > (time() + 60)) {
            return $access_token;
        }

        $refresh_token = isset($settings['ga4_refresh_token']) ? trim((string) $settings['ga4_refresh_token']) : '';
        if ('' === $refresh_token) {
            return new WP_Error('missing_refresh_token', __('Missing refresh token. Please reconnect Google Analytics.', 'client-report-dashboard'));
        }

        $client_id = isset($settings['ga4_client_id']) ? trim((string) $settings['ga4_client_id']) : '';
        if ('' === $client_id) {
            return new WP_Error('missing_client_id', __('Missing OAuth Client ID.', 'client-report-dashboard'));
        }

        $client_secret = isset($settings['ga4_client_secret']) ? trim((string) $settings['ga4_client_secret']) : '';
        if ('' === $client_secret) {
            return new WP_Error('missing_client_secret', __('Missing OAuth Client Secret.', 'client-report-dashboard'));
        }

        $response = wp_remote_post(
            self::TOKEN_ENDPOINT,
            array(
                'timeout' => 20,
                'body'    => array(
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'refresh_token' => $refresh_token,
                    'grant_type'    => 'refresh_token',
                ),
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error('token_refresh_failed', $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = (string) wp_remote_retrieve_body($response);
        $data   = json_decode($body, true);

        if (! is_array($data)) {
            return new WP_Error('token_refresh_invalid', __('Invalid token refresh response from Google.', 'client-report-dashboard'));
        }

        if (200 !== $status) {
            $remote_error = isset($data['error']) ? (string) $data['error'] : '';
            $remote_desc  = isset($data['error_description']) ? (string) $data['error_description'] : '';

            if ('invalid_grant' === $remote_error) {
                return new WP_Error(
                    'token_revoked',
                    __('Google revoked the token (invalid_grant). Please reconnect Google Analytics.', 'client-report-dashboard')
                );
            }

            $msg = $remote_error ? $remote_error : __('Token refresh failed.', 'client-report-dashboard');
            if ('' !== $remote_desc) {
                $msg .= ' - ' . $remote_desc;
            }
            return new WP_Error('token_refresh_failed', $msg);
        }

        $new_access_token = isset($data['access_token']) ? trim((string) $data['access_token']) : '';
        if ('' === $new_access_token) {
            return new WP_Error('token_refresh_missing_access_token', __('Token refresh response is missing access_token.', 'client-report-dashboard'));
        }

        $expires_in = isset($data['expires_in']) ? (int) $data['expires_in'] : 0;
        if ($expires_in <= 0) {
            $expires_in = 3600;
        }

        $settings['ga4_access_token']  = $new_access_token;
        $settings['ga4_token_expires'] = time() + max(60, $expires_in - 60);
        $settings['ga4_connected']     = 1;

        update_option(CLIREDAS_Settings::OPTION_KEY, $settings);

        return $new_access_token;
    }

    /**
     * List accessible GA4 properties (via accountSummaries).
     *
     * @return array<string,string>|\WP_Error
     */
    public function list_properties()
    {
        $properties = array();

        $token = $this->get_valid_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $base_url = self::ADMIN_API_BASE . '/accountSummaries';

        $page_token  = '';
        $seen_tokens = array();
        $max_pages   = 20;

        for ($page = 0; $page < $max_pages; $page++) {
            $args = array(
                'pageSize' => 200,
            );

            if ('' !== $page_token) {
                if (isset($seen_tokens[$page_token])) {
                    break;
                }
                $seen_tokens[$page_token] = true;
                $args['pageToken'] = $page_token;
            }

            $url = add_query_arg($args, $base_url);

            $response = $this->authorized_get($url, $token);
            if (is_wp_error($response)) {
                return $response;
            }

            $data = $response['data'];
            $account_summaries = isset($data['accountSummaries']) && is_array($data['accountSummaries']) ? $data['accountSummaries'] : array();

            foreach ($account_summaries as $summary) {
                if (! is_array($summary)) {
                    continue;
                }

                $property_summaries = isset($summary['propertySummaries']) && is_array($summary['propertySummaries']) ? $summary['propertySummaries'] : array();
                foreach ($property_summaries as $property_summary) {
                    if (! is_array($property_summary)) {
                        continue;
                    }

                    $property_id = isset($property_summary['property']) ? (string) $property_summary['property'] : '';
                    $display_name = isset($property_summary['displayName']) ? (string) $property_summary['displayName'] : '';

                    $property_id = trim($property_id);
                    if ('' === $property_id) {
                        continue;
                    }

                    $label = $display_name ? $display_name : $property_id;
                    $properties[$property_id] = $label;
                }
            }

            $page_token = isset($data['nextPageToken']) ? trim((string) $data['nextPageToken']) : '';
            if ('' === $page_token) {
                break;
            }
        }

        asort($properties, SORT_NATURAL | SORT_FLAG_CASE);

        return $properties;
    }

    /**
     * Run a GA4 Data API report.
     *
     * @param string $property_id Property resource name (e.g. "properties/123").
     * @param array  $body Request body for :runReport.
     * @return array|\WP_Error
     */
    public function run_report($property_id, array $body)
    {
        $property_id = trim((string) $property_id);
        if ('' === $property_id) {
            return new WP_Error('missing_property_id', __('Missing GA4 property id.', 'client-report-dashboard'));
        }

        $token = $this->get_valid_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $url = self::DATA_API_BASE . '/' . ltrim($property_id, '/') . ':runReport';

        return $this->authorized_post_json($url, $body, $token);
    }

    /**
     * @param string $url URL.
     * @param string $token Access token.
     * @return array{data:array,status:int}|\WP_Error
     */
    private function authorized_get($url, $token)
    {
        $token = (string) $token;

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                ),
            )
        );

        if (! is_wp_error($response) && 401 === (int) wp_remote_retrieve_response_code($response)) {
            $token = $this->get_valid_access_token();
            if (is_wp_error($token)) {
                return $token;
            }

            $response = wp_remote_get(
                $url,
                array(
                    'timeout' => 20,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                    ),
                )
            );
        }

        if (is_wp_error($response)) {
            return new WP_Error('api_failed', $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = (string) wp_remote_retrieve_body($response);
        $data   = json_decode($body, true);

        if (! is_array($data)) {
            return new WP_Error('api_invalid', __('Invalid API response from Google.', 'client-report-dashboard'));
        }

        if (200 !== $status) {
            return $this->build_google_api_error($status, $data);
        }

        return array(
            'data'   => $data,
            'status' => $status,
        );
    }

    /**
     * @param string $url URL.
     * @param array  $body JSON body.
     * @param string $token Access token.
     * @return array|\WP_Error
     */
    private function authorized_post_json($url, array $body, $token)
    {
        $token = (string) $token;

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json; charset=utf-8',
                ),
                'body' => wp_json_encode($body),
            )
        );

        if (! is_wp_error($response) && 401 === (int) wp_remote_retrieve_response_code($response)) {
            $token = $this->get_valid_access_token();
            if (is_wp_error($token)) {
                return $token;
            }

            $response = wp_remote_post(
                $url,
                array(
                    'timeout' => 20,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json; charset=utf-8',
                    ),
                    'body' => wp_json_encode($body),
                )
            );
        }

        if (is_wp_error($response)) {
            return new WP_Error('api_failed', $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body_raw = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body_raw, true);

        if (! is_array($data)) {
            return new WP_Error('api_invalid', __('Invalid API response from Google.', 'client-report-dashboard'));
        }

        if (200 !== $status) {
            return $this->build_google_api_error($status, $data);
        }

        return $data;
    }

    /**
     * Convert Google error payloads into stable WP_Error messages.
     *
     * @param int   $http_status HTTP status code.
     * @param array $data        Decoded JSON.
     * @return \WP_Error
     */
    private function build_google_api_error($http_status, array $data)
    {
        $http_status = (int) $http_status;

        $err = isset($data['error']) && is_array($data['error']) ? $data['error'] : array();
        $status_text = isset($err['status']) ? strtoupper((string) $err['status']) : '';
        $message = isset($err['message']) ? sanitize_text_field((string) $err['message']) : '';
        $detail = ('' !== $message) ? sprintf(__(' (Google: %s)', 'client-report-dashboard'), $message) : '';

        if (403 === $http_status || 'PERMISSION_DENIED' === $status_text) {
            return new WP_Error(
                'ga4_permission_denied',
                __('Permission denied by Google Analytics API. Please reconnect and ensure your Google user has access to the selected property.', 'client-report-dashboard') . $detail
            );
        }

        if (404 === $http_status || 'NOT_FOUND' === $status_text) {
            return new WP_Error(
                'ga4_not_found',
                __('GA4 property not found. Select a valid property in Settings and try again.', 'client-report-dashboard') . $detail
            );
        }

        if (429 === $http_status || 'RESOURCE_EXHAUSTED' === $status_text) {
            return new WP_Error(
                'ga4_quota_exceeded',
                __('Google API quota exceeded. Please try again later.', 'client-report-dashboard') . $detail
            );
        }

        $msg = __('Google API request failed.', 'client-report-dashboard');
        $msg .= $detail;

        return new WP_Error('api_failed', $msg);
    }
}
