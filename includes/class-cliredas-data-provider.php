<?php

/**
 * Data provider (mock).
 *
 * Later, Pro or a future update can swap this with a GA4 provider that implements caching.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/**
 * Provide mock dashboard data for the free plugin experience.
 */
final class CLIREDAS_Data_Provider
{

    /**
     * Cache index option key (stores list of transient keys to clear).
     */
    const CACHE_INDEX_OPTION = 'cliredas_cache_keys';

    /**
     * Get a full report for a date range key.
     *
     * @param string $range_key Range key (e.g. last_7_days).
     * @return array
     */
    public function get_report($range_key)
    {
        $range_key = sanitize_key($range_key);

        // Future: return cached if available.
        $cached = $this->get_cached_report($range_key);
        if (
            false !== $cached
            && isset($cached['comparison']['totals'])
            && is_array($cached['comparison']['totals'])
        ) {
            return $cached;
        }

        $report = $this->build_mock_report($range_key);

        // Future: cache result.
        $this->set_cached_report($range_key, $report);

        /**
         * Filter the generated report.
         *
         * Pro can append extra sections/metrics here.
         *
         * @param array  $report    Report data.
         * @param string $range_key Range key.
         */
        return apply_filters('cliredas_report', $report, $range_key);
    }

    /**
     * Build mock report.
     *
     * @param string $range_key Range key.
     * @return array
     */
    private function build_mock_report($range_key)
    {
        $today = new DateTimeImmutable('today', wp_timezone());
        $days  = $this->get_mock_range_days($range_key, $today);
        $start = $this->get_mock_range_start($range_key, $today, $days);
        $current_period = $this->build_mock_period($start, $days);
        $comparison_range = $this->get_mock_comparison_range($range_key, $today, $start, $days);
        $comparison_period = $this->build_mock_period($comparison_range['start'], $comparison_range['days']);
        $total_sessions = $current_period['totals']['sessions'];

        $top_pages = $this->mock_top_pages($days);
        $devices   = $this->mock_devices($total_sessions);
        $traffic_sources = $this->mock_traffic_sources($total_sessions);

        return array(
            'range' => array(
                'key'  => $range_key,
                'days' => $days,
            ),
            'totals' => array(
                'sessions'               => $current_period['totals']['sessions'],
                'users'                  => $current_period['totals']['users'],
                'avg_engagement_seconds' => $current_period['totals']['avg_engagement_seconds'],
                'pageviews'              => $current_period['totals']['pageviews'],
            ),
            'comparison' => array(
                'range' => array(
                    'startDate' => $comparison_range['start']->format('Y-m-d'),
                    'endDate'   => $comparison_range['end']->format('Y-m-d'),
                ),
                'totals' => $comparison_period['totals'],
            ),
            'timeseries' => $current_period['timeseries'],
            'top_pages'  => $top_pages,
            'devices'    => $devices,
            'traffic_sources' => $traffic_sources,
            'generated_at' => time(),
        );
    }

    /**
     * Build deterministic mock totals and time-series data for a period.
     *
     * @param DateTimeImmutable $start Period start date.
     * @param int               $days Number of days.
     * @return array{totals:array,timeseries:array}
     */
    private function build_mock_period(DateTimeImmutable $start, $days)
    {
        $days = max(1, (int) $days);
        $timeseries = array();
        $total_sessions = 0;
        $total_users = 0;

        for ($i = 0; $i < $days; $i++) {
            $date = $start->modify('+' . $i . ' days');
            $day_number = ((int) $date->format('Y') * 366) + (int) $date->format('z');
            $base = ($days >= 30) ? 320 : 380;
            $wave = (int) (70 * sin($day_number / 3.0));
            $trend = (($day_number % 13) - 6) * 3;
            $sessions = max(40, $base + $wave + $trend);
            $users = (int) round($sessions * 0.72);

            $total_sessions += $sessions;
            $total_users += $users;

            $timeseries[] = array(
                'date'     => $date->format('Y-m-d'),
                'sessions' => $sessions,
                'users'    => $users,
            );
        }

        $start_day_number = ((int) $start->format('Y') * 366) + (int) $start->format('z');

        return array(
            'totals' => array(
                'sessions'               => $total_sessions,
                'users'                  => $total_users,
                'avg_engagement_seconds' => 90 + ($start_day_number % 21),
                'pageviews'              => (int) round($total_sessions * 1.35),
            ),
            'timeseries' => $timeseries,
        );
    }

    /**
     * Get the previous comparison period for mock data.
     *
     * @param string            $range_key Range key.
     * @param DateTimeImmutable $today Today's date in site timezone.
     * @param DateTimeImmutable $current_start Current period start date.
     * @param int               $current_days Current period day count.
     * @return array{start:DateTimeImmutable,end:DateTimeImmutable,days:int}
     */
    private function get_mock_comparison_range($range_key, DateTimeImmutable $today, DateTimeImmutable $current_start, $current_days)
    {
        if ('this_month' === $range_key) {
            $start = $today->modify('first day of last month');
            $end_day = min((int) $today->format('j'), (int) $start->format('t'));
            $end = $start->setDate((int) $start->format('Y'), (int) $start->format('n'), $end_day);

            return array(
                'start' => $start,
                'end'   => $end,
                'days'  => $end_day,
            );
        }

        if ('last_month' === $range_key) {
            $end = $current_start->modify('-1 day');
            $start = $end->modify('first day of this month');

            return array(
                'start' => $start,
                'end'   => $end,
                'days'  => (int) $end->format('t'),
            );
        }

        $end = $current_start->modify('-1 day');
        $days = max(1, (int) $current_days);

        return array(
            'start' => $end->modify('-' . ($days - 1) . ' days'),
            'end'   => $end,
            'days'  => $days,
        );
    }

    /**
     * Get mock report day count for a range key.
     *
     * @param string            $range_key Range key.
     * @param DateTimeImmutable $today Today's date in site timezone.
     * @return int
     */
    private function get_mock_range_days($range_key, DateTimeImmutable $today)
    {
        switch ($range_key) {
            case 'last_90_days':
                return 90;
            case 'last_month':
                return (int) $today->modify('first day of last month')->format('t');
            case 'this_month':
                return (int) $today->format('j');
            case 'last_30_days':
                return 30;
            case 'last_7_days':
            default:
                return 7;
        }
    }

    /**
     * Get mock report start date for a range key.
     *
     * @param string            $range_key Range key.
     * @param DateTimeImmutable $today Today's date in site timezone.
     * @param int               $days Number of days in the range.
     * @return DateTimeImmutable
     */
    private function get_mock_range_start($range_key, DateTimeImmutable $today, $days)
    {
        switch ($range_key) {
            case 'last_month':
                return $today->modify('first day of last month');
            case 'this_month':
                return $today->modify('first day of this month');
            case 'last_90_days':
            case 'last_30_days':
            case 'last_7_days':
            default:
                return $today->modify('-' . ((int) $days - 1) . ' days');
        }
    }

    /**
     * Mock top pages.
     *
     * @param int $days Days.
     * @return array<int,array<string,mixed>>
     */
    private function mock_top_pages($days)
    {
        $mult = (30 === (int) $days) ? 1.0 : 0.35;

        $pages = array(
            array('title' => 'Home', 'url' => '/', 'sessions' => (int) round(8200 * $mult)),
            array('title' => 'Services', 'url' => '/services/', 'sessions' => (int) round(5300 * $mult)),
            array('title' => 'About', 'url' => '/about/', 'sessions' => (int) round(4100 * $mult)),
            array('title' => 'Contact', 'url' => '/contact/', 'sessions' => (int) round(2800 * $mult)),
            array('title' => 'Blog', 'url' => '/blog/', 'sessions' => (int) round(2600 * $mult)),
            array('title' => 'Pricing', 'url' => '/pricing/', 'sessions' => (int) round(2100 * $mult)),
            array('title' => 'Case Study: Alpha', 'url' => '/case-studies/alpha/', 'sessions' => (int) round(1700 * $mult)),
            array('title' => 'Case Study: Beta', 'url' => '/case-studies/beta/', 'sessions' => (int) round(1400 * $mult)),
            array('title' => 'FAQ', 'url' => '/faq/', 'sessions' => (int) round(1200 * $mult)),
            array('title' => 'Privacy Policy', 'url' => '/privacy-policy/', 'sessions' => (int) round(900 * $mult)),
        );

        // Add a views column for UI parity with GA4 provider.
        foreach ($pages as $i => $row) {
            $sessions = isset($row['sessions']) ? (int) $row['sessions'] : 0;
            $pages[$i]['views'] = (int) round($sessions * 1.25);
            // Fake "avg engagement time" (seconds) for UI parity.
            $pages[$i]['avg_engagement_seconds'] = 40 + ((int) $days * 2) + (($i * 7) % 55);
        }

        usort(
            $pages,
            static function ($a, $b) {
                return (int) $b['sessions'] <=> (int) $a['sessions'];
            }
        );

        return array_slice($pages, 0, 10);
    }

    /**
     * Mock device breakdown.
     *
     * @param int $total_sessions Total sessions.
     * @return array<string,int>
     */
    private function mock_devices($total_sessions)
    {
        $desktop = (int) round($total_sessions * 0.52);
        $mobile  = (int) round($total_sessions * 0.43);
        $tablet  = max(0, $total_sessions - $desktop - $mobile);

        return array(
            'desktop' => $desktop,
            'mobile'  => $mobile,
            'tablet'  => $tablet,
        );
    }

    /**
     * Mock traffic sources breakdown.
     *
     * @param int $total_sessions Total sessions.
     * @return array<string,int>
     */
    private function mock_traffic_sources($total_sessions)
    {
        $organic = (int) round($total_sessions * 0.46);
        $direct  = (int) round($total_sessions * 0.28);
        $ref     = (int) round($total_sessions * 0.14);
        $social  = (int) round($total_sessions * 0.08);
        $other   = max(0, $total_sessions - $organic - $direct - $ref - $social);

        return array(
            'organic_search' => $organic,
            'direct'         => $direct,
            'referral'       => $ref,
            'social'         => $social,
            'other'          => $other,
        );
    }

    /**
     * Cache key helper.
     *
     * @param string $range_key Range key.
     * @return string
     */
    private function get_cache_key($range_key)
    {
        return 'cliredas_report_' . sanitize_key($range_key);
    }

    /**
     * Get cached report (transient).
     *
     * @param string $range_key Range key.
     * @return array|false
     */
    private function get_cached_report($range_key)
    {
        // Free mock provider: keep disabled by default (easy toggle later).
        $enabled = (bool) apply_filters('cliredas_enable_cache', false, $range_key);
        if (! $enabled) {
            return false;
        }

        $cached = get_transient($this->get_cache_key($range_key));
        return is_array($cached) ? $cached : false;
    }

    /**
     * Record a transient key in the cache index option.
     *
     * @param string $transient_key Transient key.
     * @return void
     */
    private function record_cache_key($transient_key)
    {
        $transient_key = sanitize_key($transient_key);

        $keys = get_option(self::CACHE_INDEX_OPTION, array());
        if (! is_array($keys)) {
            $keys = array();
        }

        if (! in_array($transient_key, $keys, true)) {
            $keys[] = $transient_key;
            update_option(self::CACHE_INDEX_OPTION, $keys, false);
        }
    }

    /**
     * Clear all known cached reports tracked in the cache index.
     *
     * @return int Number of keys cleared.
     */
    public function clear_all_cache()
    {
        $keys = get_option(self::CACHE_INDEX_OPTION, array());
        if (! is_array($keys)) {
            $keys = array();
        }

        $cleared = 0;

        foreach ($keys as $key) {
            $key = sanitize_key($key);
            if ('' === $key) {
                continue;
            }

            delete_transient($key);
            ++$cleared;
        }

        delete_option(self::CACHE_INDEX_OPTION);

        /**
         * Fires after cache is cleared.
         *
         * @param int $cleared Count.
         */
        do_action('cliredas_cache_cleared', $cleared);

        return $cleared;
    }

    /**
     * Set cached report (transient).
     *
     * @param string $range_key Range key.
     * @param array  $report Report.
     * @return void
     */
    private function set_cached_report($range_key, array $report)
    {
        $enabled = (bool) apply_filters('cliredas_enable_cache', false, $range_key);
        if (! $enabled) {
            return;
        }

        $ttl = (int) apply_filters('cliredas_cache_ttl', 15 * MINUTE_IN_SECONDS, $range_key, $report);
        $key = $this->get_cache_key($range_key);

        set_transient($key, $report, $ttl);

        // Record key in an index so we can clear everything later (even with many variants).
        $this->record_cache_key($key);
    }
}
