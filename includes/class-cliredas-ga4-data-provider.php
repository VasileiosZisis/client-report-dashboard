<?php

/**
 * GA4 Data API provider (real report).
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

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
        $range_key = sanitize_key($range_key);

        $settings = $this->settings->get_settings();
        $property_id = isset($settings['ga4_property_id']) ? trim((string) $settings['ga4_property_id']) : '';

        if ('' === $property_id) {
            $fallback = new CLIREDAS_Data_Provider();
            $report = $fallback->get_report($range_key);
            $report['error_message'] = __('GA4 is connected but no property is selected yet. Select a GA4 Property in Settings.', 'cliredas-analytics-dashboard');
            $report['source'] = 'mock';
            return $report;
        }

        // GA4 provider caching (enabled by default).
        $cache_enabled = (bool) apply_filters('cliredas_enable_cache', true, $range_key);
        $cache_key = $this->get_cache_key($range_key, $property_id);

        // After "Clear cache", force a fresh fetch for the next request (even if an object cache is in play).
        $cache_cleared_nonce = filter_input(INPUT_GET, 'cliredas_cache_cleared_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $force_refresh = is_string($cache_cleared_nonce) && wp_verify_nonce(sanitize_text_field($cache_cleared_nonce), 'cliredas_cache_cleared');

        if ($cache_enabled && ! $force_refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                // If this report was cached before we tracked keys, backfill the cache index on read.
                $this->record_cache_key($cache_key);
                $cached['source'] = 'ga4_cache';
                return apply_filters('cliredas_report', $cached, $range_key);
            }
        }

        $dates = $this->get_date_range($range_key);

        $totals = $this->fetch_totals($property_id, $dates);
        if (is_wp_error($totals)) {
            return $this->fallback_with_error($range_key, $totals);
        }

        $timeseries = $this->fetch_timeseries($property_id, $dates);
        if (is_wp_error($timeseries)) {
            return $this->fallback_with_error($range_key, $timeseries);
        }

        $top_pages = $this->fetch_top_pages($property_id, $dates);
        if (is_wp_error($top_pages)) {
            return $this->fallback_with_error($range_key, $top_pages);
        }

        $devices = $this->fetch_devices($property_id, $dates);
        if (is_wp_error($devices)) {
            return $this->fallback_with_error($range_key, $devices);
        }

        $traffic_sources_error = '';
        $traffic_sources = $this->fetch_traffic_sources($property_id, $dates);
        if (is_wp_error($traffic_sources)) {
            // Non-fatal: keep the report usable, but surface a warning.
            $traffic_sources_error = trim((string) $traffic_sources->get_error_message());
            $traffic_sources = $this->empty_traffic_sources();
        }

        $report = array(
            'range' => array(
                'key' => $range_key,
                'startDate' => $dates['startDate'],
                'endDate' => $dates['endDate'],
            ),
            'totals' => $totals,
            'timeseries' => $timeseries,
            'top_pages' => $top_pages,
            'devices' => $devices,
            'traffic_sources' => is_array($traffic_sources) ? $traffic_sources : $this->empty_traffic_sources(),
            'generated_at' => time(),
            'source' => 'ga4',
        );

        if ('' !== $traffic_sources_error && empty($report['error_message'])) {
            $report['error_message'] = sprintf(
                /* translators: %s: error message */
                __('Traffic sources unavailable. %s', 'cliredas-analytics-dashboard'),
                $traffic_sources_error
            );
        }

        if ($cache_enabled) {
            set_transient($cache_key, $report, $this->get_cache_ttl($range_key, $property_id));
            $this->record_cache_key($cache_key);
        }

        /**
         * Filter the generated report.
         *
         * @param array  $report    Report data.
         * @param string $range_key Range key.
         */
        return apply_filters('cliredas_report', $report, $range_key);
    }

    /**
     * Clear all known cached reports tracked in the cache index.
     *
     * @return int Number of keys cleared.
     */
    public function clear_all_cache()
    {
        if (! class_exists('CLIREDAS_Data_Provider')) {
            return 0;
        }

        $keys = get_option(CLIREDAS_Data_Provider::CACHE_INDEX_OPTION, array());
        if (! is_array($keys)) {
            $keys = array();
        }

        $cleared = 0;

        foreach ($keys as $key) {
            $key = sanitize_key((string) $key);
            if ('' === $key) {
                continue;
            }

            delete_transient($key);
            $cleared++;
        }

        delete_option(CLIREDAS_Data_Provider::CACHE_INDEX_OPTION);

        /**
         * Fires after cache is cleared.
         *
         * @param int $cleared Count.
         */
        do_action('cliredas_cache_cleared', $cleared);

        return $cleared;
    }

    /**
     * Record a transient key in the cache index option.
     *
     * @param string $transient_key Transient key.
     * @return void
     */
    private function record_cache_key($transient_key)
    {
        if (! class_exists('CLIREDAS_Data_Provider')) {
            return;
        }

        $transient_key = sanitize_key((string) $transient_key);
        if ('' === $transient_key) {
            return;
        }

        $keys = get_option(CLIREDAS_Data_Provider::CACHE_INDEX_OPTION, array());
        if (! is_array($keys)) {
            $keys = array();
        }

        if (! in_array($transient_key, $keys, true)) {
            $keys[] = $transient_key;
            update_option(CLIREDAS_Data_Provider::CACHE_INDEX_OPTION, $keys, false);
        }
    }

    /**
     * Build a stable GA4 cache key.
     *
     * Format: cliredas_report_{blogId?}_{propertyId}_{rangeKey}
     *
     * @param string $range_key Range key.
     * @param string $property_id GA4 property resource name (e.g. "properties/123").
     * @return string
     */
    private function get_cache_key($range_key, $property_id)
    {
        $range_key = sanitize_key($range_key);
        $property_id = strtolower(trim((string) $property_id));
        $property_id = str_replace('/', '_', $property_id);
        $property_id = sanitize_key($property_id);

        $parts = array('cliredas_report');

        // Include blog id for cache safety when a persistent object cache is shared (multisite or not).
        if (function_exists('get_current_blog_id')) {
            $parts[] = (string) (int) get_current_blog_id();
        }

        if ('' !== $property_id) {
            $parts[] = $property_id;
        }

        $parts[] = $range_key ? $range_key : 'last_7_days';

        return implode('_', $parts);
    }

    /**
     * Cache TTL (seconds) for GA4 reports.
     *
     * Uses the existing `cliredas_cache_ttl` filter (default 15 minutes).
     *
     * @param string $range_key Range key.
     * @param string $property_id GA4 property resource name.
     * @return int
     */
    private function get_cache_ttl($range_key, $property_id)
    {
        $ttl = (int) apply_filters(
            'cliredas_cache_ttl',
            15 * MINUTE_IN_SECONDS,
            sanitize_key($range_key),
            array(
                'provider'     => 'ga4',
                'property_id'  => (string) $property_id,
            )
        );

        return max(60, $ttl);
    }

    /**
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
    private function get_date_range($range_key)
    {
        switch ($range_key) {
            case 'last_30_days':
                return array('startDate' => '29daysAgo', 'endDate' => 'today');
            case 'last_7_days':
            default:
                return array('startDate' => '6daysAgo', 'endDate' => 'today');
        }
    }

    /**
     * @param string $property_id Property id.
     * @param array{startDate:string,endDate:string} $dates Dates.
     * @return array|\WP_Error
     */
    private function fetch_totals($property_id, array $dates)
    {
        $body = array(
            'dateRanges' => array(
                array(
                    'startDate' => $dates['startDate'],
                    'endDate'   => $dates['endDate'],
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
            // Ensure the API returns totals[] in the response.
            'metricAggregations' => array('TOTAL'),
        );

        $data = $this->client->run_report($property_id, $body);
        if (is_wp_error($data)) {
            return $data;
        }

        $totals = array();

        if (isset($data['totals'][0]['metricValues']) && is_array($data['totals'][0]['metricValues'])) {
            $totals = $data['totals'][0]['metricValues'];
        } elseif (isset($data['rows'][0]['metricValues']) && is_array($data['rows'][0]['metricValues'])) {
            // Some responses return a single row with metricValues (even without totals).
            $totals = $data['rows'][0]['metricValues'];
        }

        $sessions = isset($totals[0]['value']) ? (int) round((float) $totals[0]['value']) : 0;
        $users    = isset($totals[1]['value']) ? (int) round((float) $totals[1]['value']) : 0;
        $engagement_total = isset($totals[2]['value']) ? (float) $totals[2]['value'] : 0.0;
        $avg_engagement = ($sessions > 0) ? (int) round($engagement_total / $sessions) : 0;
        $pageviews = isset($totals[3]['value']) ? (int) round((float) $totals[3]['value']) : 0;

        return array(
            'sessions' => $sessions,
            'users' => $users,
            'avg_engagement_seconds' => $avg_engagement,
            'pageviews' => $pageviews,
        );
    }

    /**
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
            // Fetch more than we display so Pro can unlock higher counts later without changing queries.
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

        $out = array_slice($out, 0, 10);

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
