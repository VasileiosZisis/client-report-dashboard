<?php
/**
 * Shared reporting calendar. No request input or storage is read here.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/** Resolve and validate inclusive reporting periods. */
final class CLIREDAS_Report_Period
{
    /** Resolve a selection, optionally against an injected site-calendar day. */
    public static function resolve($key, $start = '', $end = '', DateTimeImmutable $today = null)
    {
        $today = $today ? $today : new DateTimeImmutable('today', wp_timezone());
        $today = $today->setTime(0, 0);
        $last = $today;
        switch ($key) {
            case 'custom':
                $first = self::date($start, $today->getTimezone());
                $last = self::date($end, $today->getTimezone());
                if (! $first || ! $last || $first > $last || $last > $today || $first->diff($last)->days >= 366) {
                    return self::error();
                }
                break;
            case 'year_to_date':
                $first = $today->setDate((int) $today->format('Y'), 1, 1);
                break;
            case 'this_month':
                $first = $today->modify('first day of this month');
                break;
            case 'last_month':
                $first = $today->modify('first day of last month');
                $last = $first->modify('last day of this month');
                break;
            case 'last_7_days':
            case 'last_30_days':
            case 'last_90_days':
                $days = array('last_7_days' => 7, 'last_30_days' => 30, 'last_90_days' => 90);
                $first = $today->modify('-' . ($days[$key] - 1) . ' days');
                break;
            default:
                return self::error();
        }
        $days = (int) $first->diff($last)->days + 1;
        if ('year_to_date' === $key) {
            $previous_first = $first->modify('-1 year');
            $month = $today->setDate((int) $today->format('Y') - 1, (int) $today->format('n'), 1);
            $previous_last = $month->setDate((int) $month->format('Y'), (int) $month->format('n'), min((int) $today->format('j'), (int) $month->format('t')));
        } elseif ('this_month' === $key) {
            $previous_first = $today->modify('first day of last month');
            $previous_last = $previous_first->setDate((int) $previous_first->format('Y'), (int) $previous_first->format('n'), min((int) $today->format('j'), (int) $previous_first->format('t')));
        } elseif ('last_month' === $key) {
            $previous_first = $first->modify('-1 month');
            $previous_last = $previous_first->modify('last day of this month');
        } else {
            $previous_last = $first->modify('-1 day');
            $previous_first = $first->modify('-' . $days . ' days');
        }
        foreach (array($first, $last, $previous_first, $previous_last) as $date) {
            if (! self::date($date->format('Y-m-d'), $today->getTimezone())) {
                return self::error();
            }
        }
        return array(
            'key' => $key,
            'dates' => array('startDate' => $first->format('Y-m-d'), 'endDate' => $last->format('Y-m-d')),
            'comparison' => array('startDate' => $previous_first->format('Y-m-d'), 'endDate' => $previous_last->format('Y-m-d')),
            'days' => $days,
            'timezone' => wp_timezone_string(),
        );
    }

    /** Accept only an exact descriptor for the current selection and timezone. */
    public static function validate($period)
    {
        if (! is_array($period) || ! isset($period['key'], $period['dates']['startDate'], $period['dates']['endDate'])) {
            return self::error();
        }
        $resolved = self::resolve($period['key'], $period['dates']['startDate'], $period['dates']['endDate']);
        return ! is_wp_error($resolved) && $resolved === $period ? $resolved : new WP_Error('cliredas_obsolete_period', __('The reporting dates changed. Reload the dashboard and select the period again.', 'cliredas-analytics-dashboard'));
    }

    /** Build a safe unavailable report without inventing zero activity. */
    public static function unavailable($period, $message)
    {
        return array(
            'source' => 'unavailable',
            'range' => is_array($period) ? array_merge(array('key' => $period['key']), $period['dates']) : array(),
            'comparison' => array('range' => is_array($period) ? $period['comparison'] : array()),
            'error_message' => $message,
        );
    }

    /** Strict Gregorian date; PHP's permissive normalization is not validation. */
    private static function date($value, DateTimeZone $timezone)
    {
        if (! is_string($value) || ! preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value) || '0000' === substr($value, 0, 4)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        return $date && $date->format('Y-m-d') === $value ? $date : false;
    }

    /** Safe selection error shared by controllers and provider consumers. */
    private static function error()
    {
        return new WP_Error('cliredas_invalid_period', __('Choose valid dates in YYYY-MM-DD format, in order, ending no later than today, and covering at most 366 days.', 'cliredas-analytics-dashboard'));
    }
}
