<?php
/**
 * Fixed, unbranded plain-text owner summary.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/** Build mail content without HTML, remote assets, or custom mail transport. */
final class CLIREDAS_Digest_Message
{
    /** Normalize untrusted report/site values to a single plain-text line. */
    public static function line($value)
    {
        return sanitize_text_field(html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8'));
    }

    /** Format a numeric metric in the site's locale. */
    private static function metric($value, $duration = false)
    {
        return $duration ? sprintf(
            /* translators: %s: average engagement time in seconds. */
            __('%s seconds', 'cliredas-analytics-dashboard'), number_format_i18n((float) $value, 1)
        ) : number_format_i18n((float) $value);
    }

    /** Build the fixed message; the service validates the complete report first. */
    public static function build(array $report, $test = false)
    {
        $site = self::line(get_bloginfo('name'));
        $dates = $report['range'];
        $previous = $report['comparison']['range'];
        $period = self::line($dates['startDate'] . ' / ' . $dates['endDate']);
        $subject = sprintf(
            /* translators: 1: site name, 2: inclusive report dates. */
            __('Weekly website summary: %1$s (%2$s)', 'cliredas-analytics-dashboard'), $site, $period
        );
        if ($test) {
            /* translators: %s: weekly summary subject. */
            $subject = sprintf(__('[Test] %s', 'cliredas-analytics-dashboard'), $subject);
        }
        $lines = array($subject, '', __('Reporting dates (inclusive, including the current day):', 'cliredas-analytics-dashboard') . ' ' . $period,
            __('Comparison dates:', 'cliredas-analytics-dashboard') . ' ' . self::line($previous['startDate'] . ' / ' . $previous['endDate']),
            __('Last successful retrieval:', 'cliredas-analytics-dashboard') . ' ' . wp_date('Y-m-d H:i:s T', $report['freshness']['last_success_at']), '',
            __('Key metrics', 'cliredas-analytics-dashboard'),
        );
        $labels = array('sessions' => __('Sessions', 'cliredas-analytics-dashboard'), 'users' => __('Total users', 'cliredas-analytics-dashboard'),
            'pageviews' => __('Pageviews', 'cliredas-analytics-dashboard'), 'avg_engagement_seconds' => __('Average engagement time', 'cliredas-analytics-dashboard'),
        );
        foreach ($labels as $key => $label) {
            $value = (float) $report['totals'][$key];
            $before = (float) $report['comparison']['totals'][$key];
            $change = $before > 0 ? (($value - $before >= 0 ? '+' : '') . number_format_i18n(100 * ($value - $before) / $before, 1) . '%')
                : ($value > 0 ? __('New', 'cliredas-analytics-dashboard') : '0%');
            $lines[] = $label . ': ' . self::metric($value, 'avg_engagement_seconds' === $key) . ' (' . $change . ')';
        }
        $lines[] = '';
        $lines[] = __('Top five pages', 'cliredas-analytics-dashboard');
        if (empty($report['top_pages'])) {
            $lines[] = __('No page activity for this period.', 'cliredas-analytics-dashboard');
        }
        foreach (array_slice($report['top_pages'], 0, 5) as $page) {
            $lines[] = self::line(isset($page['title']) ? $page['title'] : '') . ' | ' . self::line(isset($page['url']) ? $page['url'] : '')
                . ' | ' . __('Sessions:', 'cliredas-analytics-dashboard') . ' ' . self::metric(isset($page['sessions']) ? $page['sessions'] : 0)
                . ' | ' . __('Views:', 'cliredas-analytics-dashboard') . ' ' . self::metric(isset($page['views']) ? $page['views'] : 0);
        }
        $groups = array(
            'devices' => array(__('Devices (sessions)', 'cliredas-analytics-dashboard'), array('desktop' => __('Desktop', 'cliredas-analytics-dashboard'), 'mobile' => __('Mobile', 'cliredas-analytics-dashboard'), 'tablet' => __('Tablet', 'cliredas-analytics-dashboard'))),
            'traffic_sources' => array(__('Traffic sources (sessions)', 'cliredas-analytics-dashboard'), array('organic_search' => __('Organic Search', 'cliredas-analytics-dashboard'), 'direct' => __('Direct', 'cliredas-analytics-dashboard'), 'referral' => __('Referral', 'cliredas-analytics-dashboard'), 'social' => __('Social', 'cliredas-analytics-dashboard'), 'other' => __('Other', 'cliredas-analytics-dashboard'))),
        );
        foreach ($groups as $key => $group) {
            $lines[] = '';
            $lines[] = $group[0];
            foreach ($group[1] as $code => $label) {
                $lines[] = $label . ': ' . self::metric(isset($report[$key][$code]) ? $report[$key][$code] : 0);
            }
        }
        $lines[] = '';
        $lines[] = __('Open dashboard:', 'cliredas-analytics-dashboard') . ' ' . esc_url_raw(admin_url('admin.php?page=cliredas-client-report'));
        $lines[] = __('Retrieval freshness does not mean Google has finished processing the current day.', 'cliredas-analytics-dashboard');
        return array('subject' => self::line($subject), 'body' => implode("\n", $lines));
    }
}
