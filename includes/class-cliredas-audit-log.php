<?php
/**
 * Bounded, local operation history.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/**
 * Record predefined outcomes without external or credential data.
 */
final class CLIREDAS_Audit_Log
{
    const OPTION_KEY = 'cliredas_audit_log';
    const LIMIT = 100;
    const EVENTS = array('connect', 'disconnect', 'secret_clear', 'refresh', 'cache_clear', 'csv_export');
    const OUTCOMES = array('success', 'sample_data', 'configuration', 'authentication', 'permission', 'quota', 'network', 'google_service', 'storage_failed', 'invalid_range', 'output_failed');

    /**
     * Convert errors to allow-listed outcome codes.
     *
     * @param WP_Error $error Operation error.
     * @return string
     */
    public static function outcome(WP_Error $error)
    {
        $code = $error->get_error_code();
        if (0 === strpos($code, 'credential_')) {
            return 'storage_failed';
        }
        $map = array(
            'missing_client_id' => 'configuration',
            'missing_client_secret' => 'configuration',
            'missing_refresh_token' => 'authentication',
            'token_revoked' => 'authentication',
            'token_credentials_invalid' => 'authentication',
            'token_refresh_network' => 'network',
            'token_exchange_network' => 'network',
            'api_network' => 'network',
            'ga4_permission_denied' => 'permission',
            'ga4_quota_exceeded' => 'quota',
        );
        return isset($map[$code]) ? $map[$code] : 'google_service';
    }

    /**
     * Append an event, retaining at most 100 entries even across concurrent writes.
     *
     * @param string $event   Event key.
     * @param string $outcome Safe outcome key.
     * @return void
     */
    public static function record($event, $outcome)
    {
        if (! in_array($event, self::EVENTS, true) || ! in_array($outcome, self::OUTCOMES, true)) {
            return;
        }
        try {
            $entry = array('event' => $event, 'timestamp' => time(), 'actor_id' => get_current_user_id(), 'outcome' => $outcome);
            for ($attempt = 0; $attempt < 3; ++$attempt) {
                $old = get_option(self::OPTION_KEY, false);
                $entries = self::normalize($old);
                $entries[] = $entry;
                $entries = array_slice($entries, -self::LIMIT);
                if (false === $old) {
                    if (add_option(self::OPTION_KEY, $entries, '', false)) {
                        return;
                    }
                    CLIREDAS_Local_Store::invalidate(self::OPTION_KEY);
                } elseif (CLIREDAS_Local_Store::compare_and_swap(self::OPTION_KEY, $old, $entries)) {
                    return;
                }
            }
        } catch (Throwable $exception) {
            // Audit availability must not prevent the requested operation.
            return;
        }
    }

    /**
     * Return sanitized entries, newest first.
     *
     * @return array
     */
    public static function get_entries()
    {
        return array_reverse(self::normalize(get_option(self::OPTION_KEY, array())));
    }

    /**
     * Discard unknown fields and malformed entries from stored data.
     *
     * @param mixed $entries Stored entries.
     * @return array
     */
    private static function normalize($entries)
    {
        $safe = array();
        foreach (is_array($entries) ? $entries : array() as $entry) {
            if (! is_array($entry) || ! isset($entry['event'], $entry['outcome'], $entry['timestamp'], $entry['actor_id'])) {
                continue;
            }
            if (! in_array($entry['event'], self::EVENTS, true) || ! in_array($entry['outcome'], self::OUTCOMES, true)
                || ! is_numeric($entry['timestamp']) || ! is_numeric($entry['actor_id'])) {
                continue;
            }
            $safe[] = array(
                'event' => $entry['event'],
                'timestamp' => absint($entry['timestamp']),
                'actor_id' => absint($entry['actor_id']),
                'outcome' => $entry['outcome'],
            );
        }
        return array_slice($safe, -self::LIMIT);
    }

    /**
     * Translate an event key.
     *
     * @param string $event Event key.
     * @return string
     */
    public static function event_label($event)
    {
        $labels = array(
            'connect' => __('Google connection', 'cliredas-analytics-dashboard'),
            'disconnect' => __('Disconnect', 'cliredas-analytics-dashboard'),
            'secret_clear' => __('Client Secret cleared', 'cliredas-analytics-dashboard'),
            'refresh' => __('Token refresh', 'cliredas-analytics-dashboard'),
            'cache_clear' => __('Cache cleared', 'cliredas-analytics-dashboard'),
            'csv_export' => __('CSV export', 'cliredas-analytics-dashboard'),
        );
        return isset($labels[$event]) ? $labels[$event] : '';
    }

    /**
     * Translate a safe outcome key.
     *
     * @param string $outcome Outcome key.
     * @return string
     */
    public static function outcome_label($outcome)
    {
        $labels = array(
            'success' => __('Successful', 'cliredas-analytics-dashboard'),
            'sample_data' => __('Sample data exported', 'cliredas-analytics-dashboard'),
            'configuration' => __('Configuration required', 'cliredas-analytics-dashboard'),
            'authentication' => __('Reconnect required', 'cliredas-analytics-dashboard'),
            'permission' => __('Access denied by Google', 'cliredas-analytics-dashboard'),
            'quota' => __('Google quota exhausted', 'cliredas-analytics-dashboard'),
            'network' => __('Network failure', 'cliredas-analytics-dashboard'),
            'google_service' => __('Google request failed', 'cliredas-analytics-dashboard'),
            'storage_failed' => __('Credential storage requires attention', 'cliredas-analytics-dashboard'),
            'invalid_range' => __('Invalid date range', 'cliredas-analytics-dashboard'),
            'output_failed' => __('Download could not be created', 'cliredas-analytics-dashboard'),
        );
        return isset($labels[$outcome]) ? $labels[$outcome] : '';
    }
}
