<?php

/**
 * Data provider (mock).
 *
 * Later, Pro or a future update can swap this with a GA4 provider that implements caching.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

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
        if (false !== $cached) {
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
        $days = ('last_30_days' === $range_key) ? 30 : 7;

        $today = new DateTimeImmutable('today', wp_timezone());
        $start = $today->modify('-' . ($days - 1) . ' days');

        $timeseries = array();
        $total_sessions = 0;

        for ($i = 0; $i < $days; $i++) {
            $date = $start->modify('+' . $i . ' days');
            $label = $date->format('Y-m-d');

            // Deterministic-ish mock value.
            // Base depends on range, with some weekly-ish wave.
            $base = (30 === $days) ? 320 : 380;
            $wave = (int) (70 * sin(($i / 3.0)));
            $value = max(40, $base + $wave + ($i * 3));

            $total_sessions += $value;

            $timeseries[] = array(
                'date'     => $label,
                'sessions' => $value,
            );
        }

        // Simple derivations.
        $total_users = (int) round($total_sessions * 0.72);
        $avg_engagement_seconds = (30 === $days) ? 102 : 95;

        $top_pages = $this->mock_top_pages($days);
        $devices   = $this->mock_devices($total_sessions);

        return array(
            'range' => array(
                'key'  => $range_key,
                'days' => $days,
            ),
            'totals' => array(
                'sessions'               => $total_sessions,
                'users'                  => $total_users,
                'avg_engagement_seconds' => $avg_engagement_seconds,
            ),
            'timeseries' => $timeseries,
            'top_pages'  => $top_pages,
            'devices'    => $devices,
            'generated_at' => time(),
        );
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
            $cleared++;
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
