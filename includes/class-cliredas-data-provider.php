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
     * Report cache schema version.
     */
    private const CACHE_SCHEMA_VERSION = 3;

    /**
     * Get a full report for a date range key.
     *
     * @param string $range_key Range key (e.g. last_7_days).
     * @return array
     */
    public function get_report($range_key)
    {
        $period = CLIREDAS_Report_Period::resolve($range_key);
        return is_wp_error($period) ? CLIREDAS_Report_Period::unavailable(null, $period->get_error_message()) : $this->get_report_for_period($period);
    }

    /** Generate sample data for the exact requested dates; never retain it as analytics. */
    public function get_report_for_period($period)
    {
        $valid = CLIREDAS_Report_Period::validate($period);
        if (is_wp_error($valid)) {
            return CLIREDAS_Report_Period::unavailable(null, $valid->get_error_message());
        }
        $report = $this->get_cached_report($period);
        if (false === $report) {
            $report = $this->build_mock_report($period);
            $this->set_cached_report($period, $report);
        }
        return apply_filters('cliredas_report', $report, $period['key'], $period);
    }

    /** Build a clearly labeled sample with the shared reporting calendar. */
    private function build_mock_report($period)
    {
        $range_key = $period['key'];
        $days = $period['days'];
        $start = new DateTimeImmutable($period['dates']['startDate'], wp_timezone());
        $previous_start = new DateTimeImmutable($period['comparison']['startDate'], wp_timezone());
        $previous_end = new DateTimeImmutable($period['comparison']['endDate'], wp_timezone());
        $new_range = in_array($range_key, array('custom', 'year_to_date'), true);
        $current_period = $this->build_mock_period($start, $days, $new_range);
        $comparison_period = $this->build_mock_period($previous_start, (int) $previous_start->diff($previous_end)->days + 1, $new_range);
        $total_sessions = $current_period['totals']['sessions'];

        $top_pages = $this->mock_top_pages($days, $new_range ? $total_sessions : null);
        $devices   = $this->mock_devices($total_sessions);
        $traffic_sources = $this->mock_traffic_sources($total_sessions);

        return array(
            'range' => array(
                'key'  => $range_key,
                'days' => $days,
                'startDate' => $start->format('Y-m-d'),
                'endDate' => $start->modify('+' . ($days - 1) . ' days')->format('Y-m-d'),
            ),
            'source' => 'mock',
            'totals' => array(
                'sessions'               => $current_period['totals']['sessions'],
                'users'                  => $current_period['totals']['users'],
                'avg_engagement_seconds' => $current_period['totals']['avg_engagement_seconds'],
                'pageviews'              => $current_period['totals']['pageviews'],
            ),
            'comparison' => array(
                'range' => array(
                    'startDate' => $period['comparison']['startDate'],
                    'endDate'   => $period['comparison']['endDate'],
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
     * @param bool              $stable Whether daily samples are independent of selection length.
     * @return array{totals:array,timeseries:array}
     */
    private function build_mock_period(DateTimeImmutable $start, $days, $stable = false)
    {
        $days = max(1, (int) $days);
        $timeseries = array();
        $total_sessions = 0;
        $total_users = 0;

        for ($i = 0; $i < $days; $i++) {
            $date = $start->modify('+' . $i . ' days');
            $day_number = ((int) $date->format('Y') * 366) + (int) $date->format('z');
            $base = ($stable || $days >= 30) ? 320 : 380;
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
     * Mock top pages.
     *
     * @param int      $days Days.
     * @param int|null $total_sessions Optional total for period-aware sample scaling.
     * @return array<int,array<string,mixed>>
     */
    private function mock_top_pages($days, $total_sessions = null)
    {
        $mult = (null !== $total_sessions || 30 === (int) $days) ? 1.0 : 0.35;

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
            array('title' => 'Resources', 'url' => '/resources/', 'sessions' => (int) round(840 * $mult)),
            array('title' => 'Portfolio', 'url' => '/portfolio/', 'sessions' => (int) round(790 * $mult)),
            array('title' => 'Team', 'url' => '/team/', 'sessions' => (int) round(740 * $mult)),
            array('title' => 'Support', 'url' => '/support/', 'sessions' => (int) round(690 * $mult)),
            array('title' => 'Careers', 'url' => '/careers/', 'sessions' => (int) round(640 * $mult)),
            array('title' => 'Newsletter', 'url' => '/newsletter/', 'sessions' => (int) round(590 * $mult)),
            array('title' => 'Analytics Guide', 'url' => '/guides/analytics/', 'sessions' => (int) round(540 * $mult)),
            array('title' => 'GA4 Reporting Guide', 'url' => '/guides/ga4-reporting/', 'sessions' => (int) round(500 * $mult)),
            array('title' => 'SEO Services', 'url' => '/services/seo/', 'sessions' => (int) round(460 * $mult)),
            array('title' => 'Web Design Services', 'url' => '/services/web-design/', 'sessions' => (int) round(420 * $mult)),
            array('title' => 'Case Study: Gamma', 'url' => '/case-studies/gamma/', 'sessions' => (int) round(380 * $mult)),
            array('title' => 'Product A', 'url' => '/products/product-a/', 'sessions' => (int) round(340 * $mult)),
            array('title' => 'Product B', 'url' => '/products/product-b/', 'sessions' => (int) round(300 * $mult)),
            array('title' => 'Terms of Service', 'url' => '/terms/', 'sessions' => (int) round(260 * $mult)),
            array('title' => 'Accessibility', 'url' => '/accessibility/', 'sessions' => (int) round(220 * $mult)),
        );

        $weight = array_sum(array_column($pages, 'sessions'));
        // Add a views column for UI parity with GA4 provider.
        foreach ($pages as $i => $row) {
            $sessions = isset($row['sessions']) ? (int) $row['sessions'] : 0;
            if (null !== $total_sessions) {
                $sessions = (int) floor($sessions * $total_sessions / max(1, $weight));
                $pages[$i]['sessions'] = $sessions;
            }
            $pages[$i]['views'] = (int) round($sessions * 1.25);
            // Fake "avg engagement time" (seconds) for UI parity.
            $pages[$i]['avg_engagement_seconds'] = 40 + (null === $total_sessions ? (int) $days * 2 : 0) + (($i * 7) % 55);
        }

        usort(
            $pages,
            static function ($a, $b) {
                return (int) $b['sessions'] <=> (int) $a['sessions'];
            }
        );

        return array_slice($pages, 0, 25);
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
    private function get_cache_key($period)
    {
        return 'cliredas_report_v' . self::CACHE_SCHEMA_VERSION . '_' . hash('sha256', wp_json_encode(array(get_current_blog_id(), home_url('/'), $period)));
    }

    /**
     * Get cached report (transient).
     *
     * @param string $range_key Range key.
     * @return array|false
     */
    private function get_cached_report($period)
    {
        // Free mock provider: keep disabled by default (easy toggle later).
        $enabled = (bool) apply_filters('cliredas_enable_cache', false, $period['key']);
        if (! $enabled) {
            return false;
        }

        $cached = get_transient($this->get_cache_key($period));
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
            $excess = count($keys) - 20;
            for ($i = 0; $i < $excess; ++$i) {
                delete_transient(array_shift($keys));
            }
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
        $retained = CLIREDAS_Report_Cache::clear();
        if (false === $retained) {
            return false;
        }
        $keys = get_option(self::CACHE_INDEX_OPTION, array());
        if (! is_array($keys)) {
            $keys = array();
        }

        $cleared = $retained;

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
    private function set_cached_report($period, array $report)
    {
        $enabled = (bool) apply_filters('cliredas_enable_cache', false, $period['key']);
        if (! $enabled) {
            return;
        }

        $ttl = (int) apply_filters('cliredas_cache_ttl', 15 * MINUTE_IN_SECONDS, $period['key'], $report);
        $key = $this->get_cache_key($period);

        set_transient($key, $report, $ttl);

        // Record key in an index so we can clear everything later (even with many variants).
        $this->record_cache_key($key);
    }
}
