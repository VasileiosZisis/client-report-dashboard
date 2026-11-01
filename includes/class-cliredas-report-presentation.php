<?php
/**
 * Display-only report states. Analytics payloads are never changed here.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/** Keep server-rendered empty/unavailable states equivalent to the dashboard JS. */
final class CLIREDAS_Report_Presentation
{
    /** A missing or invalid metric must not be presented as zero. */
    public static function number($value)
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value >= 0;
    }

    /** The accessible table can show one valid metric even when another is missing. */
    public static function trend_available(array $report)
    {
        if ((isset($report['source']) && 'unavailable' === $report['source']) || ! isset($report['timeseries']) || ! is_array($report['timeseries'])) {
            return false;
        }
        foreach ($report['timeseries'] as $point) {
            if (! is_array($point) || ! isset($point['date']) || ! is_string($point['date'])) {
                return false;
            }
        }
        return true;
    }

    /** Resolve a built-in block to ready, empty or unavailable, without inference from other blocks. */
    public static function state(array $report, $block, $metric = 'sessions')
    {
        if ((isset($report['source']) && 'unavailable' === $report['source']) || ! isset($report[$block]) || ! is_array($report[$block])
            || ('traffic_sources' === $block && ! empty($report['traffic_sources_unavailable']))) {
            return 'unavailable';
        }
        $data = $report[$block];
        if ('top_pages' === $block) {
            foreach ($data as $row) {
                if (! is_array($row)) {
                    return 'unavailable';
                }
            }
            return $data ? 'ready' : 'empty';
        }
        if ('totals' === $block) {
            $data = array_map(static function ($key) use ($data) {
                return isset($data[$key]) ? $data[$key] : null;
            }, array('sessions', 'users', 'pageviews', 'avg_engagement_seconds'));
        } elseif ('timeseries' === $block) {
            $values = array();
            foreach ($data as $row) {
                if (! is_array($row) || ! isset($row['date']) || ! is_string($row['date'])) {
                    return 'unavailable';
                }
                $values[] = isset($row[$metric]) ? $row[$metric] : null;
            }
            $data = $values;
        }
        $positive = false;
        foreach ($data as $value) {
            if (! self::number($value)) {
                return 'unavailable';
            }
            $positive = $positive || (float) $value > 0;
        }
        return $positive ? 'ready' : 'empty';
    }

    /** Translated explanatory text shared by the PHP fallback and localized JS. */
    public static function message($block, $state)
    {
        if ('unavailable' === $state) {
            return __('Data unavailable. Review the reporting status above.', 'cliredas-analytics-dashboard');
        }
        $messages = array(
            'totals' => __('No activity recorded for this period. Zero values are shown below.', 'cliredas-analytics-dashboard'),
            'timeseries' => __('No activity in this chart for the selected metric and period.', 'cliredas-analytics-dashboard'),
            'top_pages' => __('No pages for this period.', 'cliredas-analytics-dashboard'),
            'devices' => __('No device activity for this period.', 'cliredas-analytics-dashboard'),
            'traffic_sources' => __('No traffic-source activity for this period.', 'cliredas-analytics-dashboard'),
        );
        return isset($messages[$block]) ? $messages[$block] : '';
    }
}
