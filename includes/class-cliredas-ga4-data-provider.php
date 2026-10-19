<?php

/**
 * GA4 Data API provider (real report).
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/**
 * Provide dashboard data from the GA4 Data API.
 */
final class CLIREDAS_GA4_Data_Provider
{

    /**
     * Settings service.
     *
     * @var CLIREDAS_Settings
     */
    private $settings;

    /**
     * GA4 client.
     *
     * @var CLIREDAS_GA4_Client
     */
    private $client;

    /**
     * Set up the GA4 data provider.
     *
     * @param CLIREDAS_Settings $settings Settings service.
     */
    public function __construct(CLIREDAS_Settings $settings)
    {
        $this->settings = $settings;
        $this->client   = new CLIREDAS_GA4_Client($settings);
    }

    /**
     * Get report for a range key.
     *
     * @param string $range_key Range key.
     * @return array
     */
    public function get_report($range_key)
    {
        $context = $this->report_context($range_key);
        $range_key = $context['range'];
        if (! $this->settings->is_ga4_connected() || '' === $context['property']) {
            return $this->fallback_with_error($range_key, new WP_Error('missing_property', __('Connect Google Analytics and select a property in Settings.', 'cliredas-analytics-dashboard')));
        }
        $readable = CLIREDAS_Credential_Store::instance()->check_readable();
        if (is_wp_error($readable)) {
            CLIREDAS_Report_Cache::clear();
            return $this->fallback_with_error($range_key, $readable);
        }
        if (! $this->cache_enabled($range_key)) {
            $report = $this->fetch_report($context);
            return is_wp_error($report) ? $this->fallback_with_error($range_key, $report)
                : apply_filters('cliredas_report', $report, $range_key);
        }
        $entry = CLIREDAS_Report_Cache::entry($context);
        if ($this->usable($entry)) {
            if ($entry['good']['fresh_until'] <= time()) {
                CLIREDAS_Report_Cache::schedule($context);
            }
            return $this->cached_response($context);
        }
        if (! CLIREDAS_Report_Cache::ensure($context)) {
            return $this->unavailable($context, 'storage_failed');
        }
        return $this->refresh_context($context);
    }

    /** Resolve both periods once; cache and Google requests use identical dates. */
    public function report_context($range_key, $create = true)
    {
        $range_key = sanitize_key($range_key);
        if (! in_array($range_key, array('last_7_days', 'last_30_days', 'last_90_days', 'this_month', 'last_month'), true)) {
            $range_key = 'last_7_days';
        }
        $today = new DateTimeImmutable('today', wp_timezone());
        $dates = $this->get_date_range($range_key, $today);
        $comparison = $this->get_previous_date_range($range_key, $today);
        foreach (array('startDate', 'endDate') as $key) {
            $dates[$key] = $this->absolute_date($dates[$key], $today);
            $comparison[$key] = $this->absolute_date($comparison[$key], $today);
        }
        $settings = $this->settings->get_settings();
        $property = isset($settings['ga4_property_id']) ? trim((string) $settings['ga4_property_id']) : '';
        return CLIREDAS_Report_Cache::context($range_key, $property, $dates, $comparison,
            CLIREDAS_Report_Cache::connection_identity($settings), $create && $this->cache_enabled($range_key));
    }

    /** Cache configuration remains compatible with existing integrations. */
    private function cache_enabled($range_key)
    {
        return (bool) apply_filters('cliredas_enable_cache', true, $range_key);
    }

    /** Resolve GA4 relative dates in the site's calendar, including DST changes. */
    private function absolute_date($date, DateTimeImmutable $today)
    {
        if ('today' === $date) {
            return $today->format('Y-m-d');
        }
        if (preg_match('/^(\d+)daysAgo$/', $date, $matches)) {
            return $today->modify('-' . $matches[1] . ' days')->format('Y-m-d');
        }
        return $date;
    }

    /** A last-good report is never eligible after its retention deadline. */
    private function usable($entry)
    {
        return is_array($entry) && ! $entry['blocked'] && is_array($entry['good']) && $entry['good']['retained_until'] > time();
    }

    /** Read-only status for polling: no fetching, scheduling or option creation. */
    public function cached_response($context, $source = 'ga4_cache')
    {
        $current = $this->report_context($context['range'], false);
        if ($current['id'] !== $context['id'] || ! $this->settings->is_ga4_connected()) {
            return $this->unavailable($context, 'context_changed');
        }
        $readable = CLIREDAS_Credential_Store::instance()->check_readable();
        if (is_wp_error($readable)) {
            return $this->unavailable($context, 'credential_decryption_failed');
        }
        $entry = CLIREDAS_Report_Cache::entry($context);
        if (! $this->usable($entry)) {
            return $this->unavailable($context, $entry ? $entry['error'] : 'unavailable');
        }
        $report = $entry['good']['report'];
        $report['source'] = $source;
        $report['freshness'] = $this->freshness($context, $entry);
        if ('' !== $entry['error']) {
            $report['error_message'] = $this->safe_refresh_message($entry['error']);
        }
        return apply_filters('cliredas_report', $report, $context['range']);
    }

    /** Refresh only the current connection and exact saved reporting period. */
    public function refresh_context($context)
    {
        $current = $this->report_context($context['range'], false);
        if ($current['id'] !== $context['id'] || ! $this->settings->is_ga4_connected() || ! $this->cache_enabled($context['range'])) {
            return $this->unavailable($context, 'context_changed');
        }
        $entry = CLIREDAS_Report_Cache::entry($context);
        if ($this->usable($entry) && $entry['good']['fresh_until'] > time()) {
            return $this->cached_response($context);
        }
        $owner = CLIREDAS_Report_Cache::lock($context);
        if (! $owner) {
            return $this->cached_response($context);
        }
        try {
            $report = $this->fetch_report($context, $owner);
            $current = $this->report_context($context['range'], false);
            if ($current['id'] !== $context['id']) {
                return $this->unavailable($context, 'context_changed');
            }
            $error = is_wp_error($report) ? $report->get_error_code() : (! empty($report['traffic_sources_unavailable']) ? 'traffic_sources_unavailable' : '');
            $blocked = in_array($error, array(
                'ga4_permission_denied', 'ga4_not_found', 'token_revoked', 'token_credentials_invalid',
                'credential_decryption_failed', 'credential_backend_unavailable', 'missing_client_secret', 'missing_client_id', 'missing_refresh_token',
            ), true);
            $ttl = max(60, (int) apply_filters('cliredas_cache_ttl', 15 * MINUTE_IN_SECONDS, $context['range'],
                array('provider' => 'ga4', 'property_id' => $context['property'])));
            $saved = CLIREDAS_Report_Cache::finish($context, $owner, '' === $error ? $report : null, $ttl, $error, $blocked);
            if (! $saved) {
                if ($blocked) {
                    return $this->fallback_with_error($context['range'], new WP_Error($error, $this->safe_refresh_message($error)));
                }
                $previous = $this->cached_response($context);
                $previous['error_message'] = $this->safe_refresh_message('storage_failed');
                $previous['freshness']['error_code'] = 'storage_failed';
                return $previous;
            }
            if ('' === $error) {
                // Recheck identity and generation after publication, before returning data.
                return $this->cached_response($context, 'ga4');
            }
            if ($this->usable(CLIREDAS_Report_Cache::entry($context))) {
                return $this->cached_response($context);
            }
            if (is_array($report) && ! $blocked) {
                $report['freshness'] = $this->freshness($context, CLIREDAS_Report_Cache::entry($context));
                return apply_filters('cliredas_report', $report, $context['range']);
            }
            return $this->fallback_with_error($context['range'], new WP_Error($error, $this->safe_refresh_message($error)));
        } catch (Throwable $exception) {
            CLIREDAS_Report_Cache::finish($context, $owner, null, 900, 'refresh_interrupted');
            return $this->cached_response($context);
        } finally {
            // Release only our own lease, including exceptions and failed publication.
            CLIREDAS_Report_Cache::finish($context, $owner, null, 900, 'refresh_interrupted');
        }
    }

    /** Safe operational guidance contains no raw upstream payload. */
    private function safe_refresh_message($code)
    {
        if ('context_changed' === $code) {
            return __('The report context changed. Reload the dashboard to request the current reporting period.', 'cliredas-analytics-dashboard');
        }
        if ('storage_failed' === $code) {
            return __('The report cache could not be saved. Check database access and reload the dashboard. Eligible last-good data has been preserved.', 'cliredas-analytics-dashboard');
        }
        if ('schedule_failed' === $code) {
            return __('The background refresh could not be scheduled. Ask an administrator to check WP-Cron, then reload after five minutes.', 'cliredas-analytics-dashboard');
        }
        if (in_array($code, array('ga4_permission_denied', 'ga4_not_found', 'token_revoked', 'token_credentials_invalid', 'credential_decryption_failed', 'credential_backend_unavailable'), true)) {
            return __('Google Analytics access is unavailable. Review credentials and property access in Settings, then reconnect.', 'cliredas-analytics-dashboard');
        }
        return __('The report could not be refreshed. Last-good data is shown only while eligible. Check the connection in Settings and try again after five minutes.', 'cliredas-analytics-dashboard');
    }

    /** Metadata is additive; timestamps are UTC epochs with a site-time label. */
    private function freshness($context, $entry)
    {
        $good = $entry && $this->usable($entry) ? $entry['good'] : null;
        $pending = $entry && ! $entry['blocked'] && ($entry['lease_until'] > time() || $entry['pending_at'] > 0);
        $fresh = $good && $good['fresh_until'] > time();
        $state = $fresh ? 'fresh' : ($pending ? 'refreshing' : ($good ? 'stale' : 'unavailable'));
        $labels = array(
            'fresh' => __('Fresh', 'cliredas-analytics-dashboard'), 'refreshing' => __('Refreshing', 'cliredas-analytics-dashboard'),
            'stale' => __('Stale', 'cliredas-analytics-dashboard'), 'unavailable' => __('Unavailable', 'cliredas-analytics-dashboard'),
        );
        return array(
            'state' => $state, 'label' => $labels[$state], 'context_id' => $context['id'], 'pending' => (bool) $pending,
            'last_success_at' => $good ? $good['successful_at'] : 0,
            'last_success_label' => $good ? wp_date('Y-m-d H:i:s T', $good['successful_at']) : '',
            'fresh_until' => $good ? $good['fresh_until'] : 0, 'retained_until' => $good ? $good['retained_until'] : 0,
            'error_code' => $entry ? $entry['error'] : '',
        );
    }

    /** Empty real-data result for concurrent cold loads; never invented success. */
    private function unavailable($context, $error)
    {
        $report = array(
            'source' => 'unavailable', 'range' => array_merge(array('key' => $context['range']), $context['dates']),
            'totals' => $this->empty_totals(), 'comparison' => array('range' => $context['comparison'], 'totals' => $this->empty_totals()),
            'timeseries' => array(), 'top_pages' => array(), 'devices' => array(), 'traffic_sources' => array(), 'generated_at' => 0,
        );
        $report['freshness'] = $this->freshness($context, CLIREDAS_Report_Cache::entry($context));
        $report['freshness']['error_code'] = $error;
        $report['error_message'] = $report['freshness']['pending']
            ? __('A real report is being refreshed. Analytics values are unavailable until it completes.', 'cliredas-analytics-dashboard')
            : $this->safe_refresh_message($error);
        return $report;
    }

    /** Fetch real data without fallback or cache writes; renew before each call. */
    private function fetch_report($context, $owner = '')
    {
        $results = array();
        foreach (array('totals', 'timeseries', 'top_pages', 'devices', 'traffic_sources') as $part) {
            if ('' !== $owner && ! CLIREDAS_Report_Cache::lock($context, $owner)) {
                return new WP_Error('context_changed');
            }
            $method = 'fetch_' . $part;
            $results[$part] = 'totals' === $part
                ? $this->$method($context['property'], $context['dates'], $context['comparison'])
                : $this->$method($context['property'], $context['dates']);
            if (is_wp_error($results[$part]) && 'traffic_sources' !== $part) {
                return $results[$part];
            }
        }
        $report = array(
            'range' => array_merge(array('key' => $context['range']), $context['dates']),
            'totals' => $results['totals']['current'],
            'comparison' => array('range' => $context['comparison'], 'totals' => $results['totals']['previous']),
            'timeseries' => $results['timeseries'], 'top_pages' => $results['top_pages'], 'devices' => $results['devices'],
            'traffic_sources' => is_wp_error($results['traffic_sources']) ? array() : $results['traffic_sources'],
            'generated_at' => time(), 'source' => 'ga4',
        );
        if (is_wp_error($results['traffic_sources'])) {
            // Authorization loss is fatal even if only the last block detected it.
            if (in_array($results['traffic_sources']->get_error_code(), array('ga4_permission_denied', 'ga4_not_found', 'token_revoked', 'token_credentials_invalid'), true)) {
                return $results['traffic_sources'];
            }
            $report['traffic_sources_unavailable'] = true;
            $report['error_message'] = __('Traffic sources are unavailable. Other report blocks contain real GA4 data.', 'cliredas-analytics-dashboard');
        }
        return $report;
    }

    /** Clear shared retained data and the legacy transient index. */
    public function clear_all_cache()
    {
        return (new CLIREDAS_Data_Provider())->clear_all_cache();
    }

    /**
     * Build a fallback report when a GA4 request fails.
     *
     * @param string $range_key Range key.
     * @param \WP_Error $error Error.
     * @return array
     */
    private function fallback_with_error($range_key, WP_Error $error)
    {
        $fallback = new CLIREDAS_Data_Provider();
        $report = $fallback->get_report($range_key);

        $message = trim((string) $error->get_error_message());
        $message = $message ? $message : __('GA4 report failed. Showing mock data.', 'cliredas-analytics-dashboard');

        $report['error_message'] = $message;
        $report['source'] = 'mock';
        return $report;
    }

    /**
     * Map plugin range key to GA4 dateRanges.
     *
     * @param string $range_key Range key.
     * @return array{startDate:string,endDate:string}
     */
    private function get_date_range($range_key, DateTimeImmutable $today = null)
    {
        $today = $today ? $today : new DateTimeImmutable('today', wp_timezone());

        switch ($range_key) {
            case 'last_90_days':
                return array('startDate' => '89daysAgo', 'endDate' => 'today');
            case 'last_month':
                $last_month = $today->modify('first day of last month');

                return array(
                    'startDate' => $last_month->format('Y-m-01'),
                    'endDate'   => $last_month->format('Y-m-t'),
                );
            case 'this_month':
                return array(
                    'startDate' => $today->format('Y-m-01'),
                    'endDate'   => $today->format('Y-m-d'),
                );
            case 'last_30_days':
                return array('startDate' => '29daysAgo', 'endDate' => 'today');
            case 'last_7_days':
            default:
                return array('startDate' => '6daysAgo', 'endDate' => 'today');
        }
    }

    /**
     * Map a plugin range key to its previous comparison period.
     *
     * @param string                 $range_key Range key.
     * @param DateTimeImmutable|null $today Today's date in site timezone.
     * @return array{startDate:string,endDate:string}
     */
    private function get_previous_date_range($range_key, DateTimeImmutable $today = null)
    {
        $today = $today ? $today : new DateTimeImmutable('today', wp_timezone());

        switch ($range_key) {
            case 'last_90_days':
                return array('startDate' => '179daysAgo', 'endDate' => '90daysAgo');
            case 'last_month':
                $previous_month = $today->modify('first day of last month')->modify('-1 month');

                return array(
                    'startDate' => $previous_month->format('Y-m-01'),
                    'endDate'   => $previous_month->format('Y-m-t'),
                );
            case 'this_month':
                $previous_month = $today->modify('first day of last month');
                $end_day        = min((int) $today->format('j'), (int) $previous_month->format('t'));
                $previous_end   = $previous_month->setDate(
                    (int) $previous_month->format('Y'),
                    (int) $previous_month->format('n'),
                    $end_day
                );

                return array(
                    'startDate' => $previous_month->format('Y-m-01'),
                    'endDate'   => $previous_end->format('Y-m-d'),
                );
            case 'last_30_days':
                return array('startDate' => '59daysAgo', 'endDate' => '30daysAgo');
            case 'last_7_days':
            default:
                return array('startDate' => '13daysAgo', 'endDate' => '7daysAgo');
        }
    }

    /**
     * Fetch aggregate GA4 totals for the selected and previous ranges.
     *
     * @param string $property_id Property id.
     * @param array{startDate:string,endDate:string} $dates Dates.
     * @param array{startDate:string,endDate:string} $comparison_dates Previous-period dates.
     * @return array{current:array,previous:array}|\WP_Error
     */
    private function fetch_totals($property_id, array $dates, array $comparison_dates)
    {
        $body = array(
            'dateRanges' => array(
                array(
                    'startDate' => $dates['startDate'],
                    'endDate'   => $dates['endDate'],
                ),
                array(
                    'startDate' => $comparison_dates['startDate'],
                    'endDate'   => $comparison_dates['endDate'],
                ),
            ),
            'metrics' => array(
                array('name' => 'sessions'),
                array('name' => 'totalUsers'),
                // Use total engagement duration and compute an average (per session) in PHP.
                // "averageEngagementTime" is not a valid Data API metric.
                array('name' => 'userEngagementDuration'),
                array('name' => 'screenPageViews'),
            ),
        );

        $data = $this->client->run_report($property_id, $body);
        if (is_wp_error($data)) {
            return $data;
        }

        $periods = array(
            0 => $this->empty_totals(),
            1 => $this->empty_totals(),
        );
        $rows = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : array();

        foreach ($rows as $row_index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $period_index = $this->get_date_range_index($row, $row_index);
            if (! array_key_exists($period_index, $periods)) {
                continue;
            }

            $metric_values = isset($row['metricValues']) && is_array($row['metricValues'])
                ? $row['metricValues']
                : array();
            $periods[$period_index] = $this->format_totals($metric_values);
        }

        return array(
            'current'  => $periods[0],
            'previous' => $periods[1],
        );
    }

    /**
     * Get the date-range index from a GA4 report row.
     *
     * @param array $row Report row.
     * @param int   $fallback_index Row index fallback.
     * @return int
     */
    private function get_date_range_index(array $row, $fallback_index)
    {
        $dimensions = isset($row['dimensionValues']) && is_array($row['dimensionValues'])
            ? $row['dimensionValues']
            : array();

        foreach ($dimensions as $dimension) {
            $value = isset($dimension['value']) ? (string) $dimension['value'] : '';
            if (preg_match('/(\d+)$/', $value, $matches)) {
                return (int) $matches[1];
            }
        }

        return (int) $fallback_index;
    }

    /**
     * Format GA4 metric values as dashboard totals.
     *
     * @param array $metric_values Metric values.
     * @return array{sessions:int,users:int,avg_engagement_seconds:int,pageviews:int}
     */
    private function format_totals(array $metric_values)
    {
        $sessions = isset($metric_values[0]['value']) ? (int) round((float) $metric_values[0]['value']) : 0;
        $users = isset($metric_values[1]['value']) ? (int) round((float) $metric_values[1]['value']) : 0;
        $engagement_total = isset($metric_values[2]['value']) ? (float) $metric_values[2]['value'] : 0.0;
        $pageviews = isset($metric_values[3]['value']) ? (int) round((float) $metric_values[3]['value']) : 0;

        return array(
            'sessions'               => $sessions,
            'users'                  => $users,
            'avg_engagement_seconds' => ($sessions > 0) ? (int) round($engagement_total / $sessions) : 0,
            'pageviews'              => $pageviews,
        );
    }

    /**
     * Get an empty totals structure.
     *
     * @return array{sessions:int,users:int,avg_engagement_seconds:int,pageviews:int}
     */
    private function empty_totals()
    {
        return array(
            'sessions'               => 0,
            'users'                  => 0,
            'avg_engagement_seconds' => 0,
            'pageviews'              => 0,
        );
    }

    /**
     * Fetch the GA4 time series for the selected range.
     *
     * @param string $property_id Property id.
     * @param array{startDate:string,endDate:string} $dates Dates.
     * @return array<int,array{date:string,sessions:int}>|\WP_Error
     */
    private function fetch_timeseries($property_id, array $dates)
    {
        $body = array(
            'dateRanges' => array(
                array(
                    'startDate' => $dates['startDate'],
                    'endDate'   => $dates['endDate'],
                ),
            ),
            'dimensions' => array(
                array('name' => 'date'),
            ),
            'metrics' => array(
                array('name' => 'sessions'),
                array('name' => 'totalUsers'),
            ),
            'orderBys' => array(
                array(
                    'dimension' => array(
                        'dimensionName' => 'date',
                    ),
                ),
            ),
        );

        $data = $this->client->run_report($property_id, $body);
        if (is_wp_error($data)) {
            return $data;
        }

        $rows = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : array();
        $series = array();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $date_raw = isset($row['dimensionValues'][0]['value']) ? (string) $row['dimensionValues'][0]['value'] : '';
            $sessions_raw = isset($row['metricValues'][0]['value']) ? (string) $row['metricValues'][0]['value'] : '0';
            $users_raw = isset($row['metricValues'][1]['value']) ? (string) $row['metricValues'][1]['value'] : '0';

            $date = $this->format_yyyymmdd($date_raw);
            if ('' === $date) {
                continue;
            }

            $series[] = array(
                'date' => $date,
                'sessions' => (int) round((float) $sessions_raw),
                'users' => (int) round((float) $users_raw),
            );
        }

        return $series;
    }

    /**
     * Fetch the top pages report from GA4.
     *
     * @param string $property_id Property id.
     * @param array{startDate:string,endDate:string} $dates Dates.
     * @return array<int,array{title:string,url:string,sessions:int}>|\WP_Error
     */
    private function fetch_top_pages($property_id, array $dates)
    {
        $body = array(
            'dateRanges' => array(
                array(
                    'startDate' => $dates['startDate'],
                    'endDate'   => $dates['endDate'],
                ),
            ),
            'dimensions' => array(
                array('name' => 'pageTitle'),
                array('name' => 'pagePath'),
            ),
            'metrics' => array(
                array('name' => 'sessions'),
                array('name' => 'screenPageViews'),
                array('name' => 'userEngagementDuration'),
            ),
            'orderBys' => array(
                array(
                    'metric' => array(
                        'metricName' => 'screenPageViews',
                    ),
                    'desc' => true,
                ),
            ),
            // Fetch the full Free table limit.
            'limit' => 25,
        );

        $data = $this->client->run_report($property_id, $body);
        if (is_wp_error($data)) {
            return $data;
        }

        $rows = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : array();

        // Aggregate by canonical path to avoid duplicates like "/page" vs "/page/" only.
        $by_path = array();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = isset($row['dimensionValues'][0]['value']) ? (string) $row['dimensionValues'][0]['value'] : '';
            $path  = isset($row['dimensionValues'][1]['value']) ? (string) $row['dimensionValues'][1]['value'] : '';
            $sessions_raw = isset($row['metricValues'][0]['value']) ? (string) $row['metricValues'][0]['value'] : '0';
            $views_raw    = isset($row['metricValues'][1]['value']) ? (string) $row['metricValues'][1]['value'] : '0';
            $engagement_raw = isset($row['metricValues'][2]['value']) ? (string) $row['metricValues'][2]['value'] : '0';

            $path = $this->canonicalize_page_path($path);

            $sessions = (int) round((float) $sessions_raw);
            $views    = (int) round((float) $views_raw);
            $engagement_total = (float) $engagement_raw;

            if (! isset($by_path[$path])) {
                $by_path[$path] = array(
                    'title'    => $title,
                    'url'      => $path,
                    'sessions' => 0,
                    'views'    => 0,
                    'engagement_total' => 0.0,
                );
            }

            $by_path[$path]['sessions'] += $sessions;
            $by_path[$path]['views']    += $views;
            $by_path[$path]['engagement_total'] += $engagement_total;

            // Prefer a non-empty, non-(not set) title if available.
            $current_title = (string) $by_path[$path]['title'];
            $new_title = trim((string) $title);
            $current_title_trim = trim($current_title);
            $is_good_title = ('' !== $new_title && '(not set)' !== strtolower($new_title));
            $current_is_bad = ('' === $current_title_trim || '(not set)' === strtolower($current_title_trim));

            if ($is_good_title && $current_is_bad) {
                $by_path[$path]['title'] = $title;
            }
        }

        $out = array_values($by_path);

        // Compute average engagement time per page view.
        foreach ($out as $i => $row) {
            $views = isset($row['views']) ? (int) $row['views'] : 0;
            $total = isset($row['engagement_total']) ? (float) $row['engagement_total'] : 0.0;
            $out[$i]['avg_engagement_seconds'] = ($views > 0) ? (int) round($total / $views) : 0;
            unset($out[$i]['engagement_total']);
        }

        usort(
            $out,
            static function ($a, $b) {
                return (int) $b['views'] <=> (int) $a['views'];
            }
        );

        $out = array_slice($out, 0, 25);

        // Make "/" clearer in the UI (titles can be misleading/non-unique in GA4).
        foreach ($out as $i => $row) {
            if (isset($row['url']) && '/' === (string) $row['url']) {
                $out[$i]['title'] = __('Landing Page', 'cliredas-analytics-dashboard');
            }
        }

        return $out;
    }

    /**
     * Normalize GA4 pagePath strings so variants dedupe cleanly.
     *
     * @param string $path Path from GA4.
     * @return string
     */
    private function canonicalize_page_path($path)
    {
        $path = trim((string) $path);
        if ('' === $path) {
            return '/';
        }

        if ('/' !== $path) {
            $path = '/' . ltrim($path, '/');
            $path = untrailingslashit($path);
            if ('' === $path) {
                $path = '/';
            }
        }

        return $path;
    }

    /**
     * Fetch device category totals from GA4.
     *
     * @param string $property_id Property id.
     * @param array{startDate:string,endDate:string} $dates Dates.
     * @return array<string,int>|\WP_Error
     */
    private function fetch_devices($property_id, array $dates)
    {
        $body = array(
            'dateRanges' => array(
                array(
                    'startDate' => $dates['startDate'],
                    'endDate'   => $dates['endDate'],
                ),
            ),
            'dimensions' => array(
                array('name' => 'deviceCategory'),
            ),
            'metrics' => array(
                array('name' => 'sessions'),
            ),
            'orderBys' => array(
                array(
                    'metric' => array(
                        'metricName' => 'sessions',
                    ),
                    'desc' => true,
                ),
            ),
        );

        $data = $this->client->run_report($property_id, $body);
        if (is_wp_error($data)) {
            return $data;
        }

        $rows = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : array();
        $devices = array();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $device = isset($row['dimensionValues'][0]['value']) ? strtolower((string) $row['dimensionValues'][0]['value']) : '';
            $sessions_raw = isset($row['metricValues'][0]['value']) ? (string) $row['metricValues'][0]['value'] : '0';

            $device = sanitize_key($device);
            if ('' === $device) {
                continue;
            }

            $devices[$device] = (int) round((float) $sessions_raw);
        }

        return $devices;
    }

    /**
     * Traffic sources (session default channel group) buckets.
     *
     * @param string $property_id Property id.
     * @param array{startDate:string,endDate:string} $dates Dates.
     * @return array<string,int>|\WP_Error
     */
    private function fetch_traffic_sources($property_id, array $dates)
    {
        $body = array(
            'dateRanges' => array(
                array(
                    'startDate' => $dates['startDate'],
                    'endDate'   => $dates['endDate'],
                ),
            ),
            'dimensions' => array(
                array('name' => 'sessionDefaultChannelGroup'),
            ),
            'metrics' => array(
                array('name' => 'sessions'),
            ),
            'orderBys' => array(
                array(
                    'metric' => array(
                        'metricName' => 'sessions',
                    ),
                    'desc' => true,
                ),
            ),
            'limit' => 50,
        );

        $data = $this->client->run_report($property_id, $body);
        if (is_wp_error($data)) {
            return $data;
        }

        $rows = isset($data['rows']) && is_array($data['rows']) ? $data['rows'] : array();
        $buckets = $this->empty_traffic_sources();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $group = isset($row['dimensionValues'][0]['value']) ? strtolower(trim((string) $row['dimensionValues'][0]['value'])) : '';
            $sessions_raw = isset($row['metricValues'][0]['value']) ? (string) $row['metricValues'][0]['value'] : '0';
            $sessions = (int) round((float) $sessions_raw);

            if ($sessions <= 0) {
                continue;
            }

            // Normalize into the five UI buckets requested.
            if (false !== strpos($group, 'organic search')) {
                $buckets['organic_search'] += $sessions;
            } elseif ('direct' === $group) {
                $buckets['direct'] += $sessions;
            } elseif (false !== strpos($group, 'referral')) {
                $buckets['referral'] += $sessions;
            } elseif (false !== strpos($group, 'social')) {
                $buckets['social'] += $sessions;
            } else {
                $buckets['other'] += $sessions;
            }
        }

        return $buckets;
    }

    /**
     * Build the empty traffic sources bucket structure.
     *
     * @return array<string,int>
     */
    private function empty_traffic_sources()
    {
        return array(
            'organic_search' => 0,
            'direct'         => 0,
            'referral'       => 0,
            'social'         => 0,
            'other'          => 0,
        );
    }

    /**
     * Format a GA4 date string for dashboard output.
     *
     * Convert GA4 "YYYYMMDD" into "YYYY-MM-DD".
     *
     * @param string $yyyymmdd Date string.
     * @return string
     */
    private function format_yyyymmdd($yyyymmdd)
    {
        $yyyymmdd = preg_replace('/[^0-9]/', '', (string) $yyyymmdd);
        if (8 !== strlen($yyyymmdd)) {
            return '';
        }

        $year = substr($yyyymmdd, 0, 4);
        $month = substr($yyyymmdd, 4, 2);
        $day = substr($yyyymmdd, 6, 2);

        return $year . '-' . $month . '-' . $day;
    }
}
