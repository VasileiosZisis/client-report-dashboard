<?php
/**
 * Shared calendar regressions: wp eval-file tests/report-period.php.
 *
 * @package ClientReportingDashboard
 */

(defined('WP_CLI') && WP_CLI) || exit;

/** Calendar tests do not write options, fetch reports, or contact Google. */
final class CLIREDAS_Period_Tests
{
    /** Run deterministic calendar boundaries using an explicit site-calendar day. */
    public static function run()
    {
        $count = 0;
        $check = static function ($value, $label) use (&$count) {
            if (! $value) {
                throw new RuntimeException(esc_html($label));
            }
            ++$count;
        };
        $timezone = new DateTimeZone('Europe/Chisinau');
        $resolve = static function ($key, $today, $start = '', $end = '') use ($timezone) {
            return CLIREDAS_Report_Period::resolve($key, $start, $end, new DateTimeImmutable($today, $timezone));
        };
        foreach (array(
            array('last_7_days', '2024-03-31', '2024-03-25', '2024-03-31', '2024-03-18', '2024-03-24'),
            array('last_30_days', '2024-03-31', '2024-03-02', '2024-03-31', '2024-02-01', '2024-03-01'),
            array('last_90_days', '2024-03-31', '2024-01-02', '2024-03-31', '2023-10-04', '2024-01-01'),
            array('this_month', '2024-03-31', '2024-03-01', '2024-03-31', '2024-02-01', '2024-02-29'),
            array('this_month', '2025-03-31', '2025-03-01', '2025-03-31', '2025-02-01', '2025-02-28'),
            array('last_month', '2024-03-31', '2024-02-01', '2024-02-29', '2024-01-01', '2024-01-31'),
            array('year_to_date', '2024-01-01', '2024-01-01', '2024-01-01', '2023-01-01', '2023-01-01'),
            array('year_to_date', '2024-02-29', '2024-01-01', '2024-02-29', '2023-01-01', '2023-02-28'),
            array('year_to_date', '2024-03-01', '2024-01-01', '2024-03-01', '2023-01-01', '2023-03-01'),
            array('year_to_date', '2025-03-01', '2025-01-01', '2025-03-01', '2024-01-01', '2024-03-01'),
            array('year_to_date', '2024-12-31', '2024-01-01', '2024-12-31', '2023-01-01', '2023-12-31'),
        ) as $case) {
            $period = $resolve($case[0], $case[1]);
            $check(array($case[2], $case[3]) === array_values($period['dates']), 'Current period: ' . $case[0] . ' ' . $case[1]);
            $check(array($case[4], $case[5]) === array_values($period['comparison']), 'Comparison period: ' . $case[0] . ' ' . $case[1]);
        }
        foreach (array(array('2024-03-31', '2024-04-01'), array('2024-10-26', '2024-10-28'), array('2024-02-29', '2024-02-29'), array('2024-01-01', '2024-12-31')) as $dates) {
            $period = $resolve('custom', '2025-01-01', $dates[0], $dates[1]);
            $check(! is_wp_error($period) && array_values($period['dates']) === $dates, 'Inclusive custom dates survive DST/leap boundaries');
            $first = new DateTimeImmutable($dates[0], $timezone);
            $previous = new DateTimeImmutable($period['comparison']['startDate'], $timezone);
            $check($period['days'] === (int) $previous->diff($first)->days && $first->modify('-1 day')->format('Y-m-d') === $period['comparison']['endDate'], 'Custom comparison immediately precedes and has equal length');
        }
        foreach (array(
            array('', ''), array('2024-2-01', '2024-02-29'), array('2023-02-29', '2023-03-01'),
            array('2024-04-31', '2024-05-01'), array('2024-01-02', '2024-01-01'),
            array('2025-01-02', '2025-01-02'), array('2024-01-01', '2025-01-01'),
            array('0000-01-01', '0000-01-01'), array('0001-01-01', '0001-01-01'),
            array('2024-01-01 junk', '2024-01-02'), array(array(), '2024-01-02'),
        ) as $dates) {
            $check(is_wp_error($resolve('custom', '2025-01-01', $dates[0], $dates[1])), 'Invalid custom selection fails closed');
        }
        $check(is_wp_error($resolve('unknown', '2025-01-01')), 'Unknown built-in range is rejected');
        $before = $resolve('year_to_date', '2024-12-31');
        $after = $resolve('year_to_date', '2025-01-01');
        $check($before['dates'] !== $after['dates'] && 1 === $after['days'], 'YTD changes at local New Year');
        $utc = new DateTimeImmutable('2024-03-30 22:30:00', new DateTimeZone('UTC'));
        $local = CLIREDAS_Report_Period::resolve('last_7_days', '', '', $utc->setTimezone($timezone));
        $check('2024-03-31' === $local['dates']['endDate'], 'Site calendar can differ from UTC day');
        WP_CLI::success('Report period regressions passed (' . $count . ' assertions).');
    }
}

CLIREDAS_Period_Tests::run();
