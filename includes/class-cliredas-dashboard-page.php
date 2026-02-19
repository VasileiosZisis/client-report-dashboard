<?php

/**
 * Dashboard page renderer.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

final class CLIREDAS_Dashboard_Page
{

    /**
     * Settings service.
     *
     * @var CLIREDAS_Settings
     */
    private $settings;

    /**
     * Data provider.
     *
     * @var object
     */
    private $provider;

    /**
     * @param CLIREDAS_Settings $settings Settings service.
     * @param object            $provider Provider with get_report().
     */
    public function __construct(CLIREDAS_Settings $settings, $provider)
    {
        $this->settings = $settings;
        $this->provider = $provider;

        // Safety: ensure we always have a provider with get_report().
        if (! is_object($this->provider) || ! method_exists($this->provider, 'get_report')) {
            $this->provider = new CLIREDAS_Data_Provider();
        }

        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_cliredas_get_report', array($this, 'ajax_get_report'));
    }

    /**
     * Enqueue dashboard assets only on our dashboard page.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     * @return void
     */
    public function enqueue_assets($hook_suffix)
    {
        if (! CLIREDAS_Admin_Screens::is_dashboard_screen()) {
            return;
        }

        $selected_range = $this->get_current_range_key();
        $initial_report = $this->provider->get_report($selected_range);
        $initial_report = $this->sanitize_report_for_current_user($initial_report);

        CLIREDAS_Assets::enqueue_dashboard_assets(
            array(
                'ajaxUrl'       => admin_url('admin-ajax.php'),
                'cliredasNonce' => wp_create_nonce('cliredas_dashboard'),
                'selectedRange' => $selected_range,
                'ranges'        => $this->get_date_ranges(),
                'upgradeUrl'    => admin_url('admin.php?page=cliredas-upgrade'),
                'initialReport' => $initial_report,
            )
        );
    }

    /**
     * AJAX: Get report data.
     *
     * @return void
     */
    public function ajax_get_report()
    {
        check_ajax_referer('cliredas_dashboard', 'cliredas_nonce');
        $capability = $this->settings->get_required_capability('dashboard');

        if (! current_user_can($capability)) {
            wp_send_json_error(
                array('message' => __('Permission denied.', 'cliredas-analytics-dashboard')),
                403
            );
        }

        $ranges = $this->get_date_ranges();
        $range_raw = filter_input(INPUT_POST, 'cliredas_range', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $range  = is_string($range_raw) ? sanitize_key($range_raw) : $this->get_current_range_key();

        if (! array_key_exists($range, $ranges)) {
            $range = $this->get_current_range_key();
        }

        $report = $this->provider->get_report($range);
        $report = $this->sanitize_report_for_current_user($report);

        wp_send_json_success(
            array(
                'report' => $report,
            )
        );
    }

    /**
     * Render the dashboard page.
     *
     * @return void
     */
    public function render()
    {
        $capability = $this->settings->get_required_capability('dashboard');

        if (! current_user_can($capability)) {
            wp_die(esc_html__('You do not have permission to view this page.', 'cliredas-analytics-dashboard'));
        }

        $ranges         = $this->get_date_ranges();
        $selected_key   = $this->get_current_range_key();
        $selected_label = isset($ranges[$selected_key]) ? $ranges[$selected_key] : reset($ranges);

        // Initial server-rendered report (so the page is usable without JS).
        $report = $this->provider->get_report($selected_key);
        $report = $this->sanitize_report_for_current_user($report);

        $kpis = apply_filters(
            'cliredas_kpis',
            array(
                'sessions' => array(
                    'label' => __('Sessions', 'cliredas-analytics-dashboard'),
                    'value' => number_format_i18n((int) $report['totals']['sessions']),
                ),
                'users'    => array(
                    'label' => __('Total users', 'cliredas-analytics-dashboard'),
                    'value' => number_format_i18n((int) $report['totals']['users']),
                ),
                'pageviews' => array(
                    'label' => __('Pageviews', 'cliredas-analytics-dashboard'),
                    'value' => number_format_i18n((int) ($report['totals']['pageviews'] ?? 0)),
                ),
                'engagement_time' => array(
                    'label' => __('Avg engagement time', 'cliredas-analytics-dashboard'),
                    'value' => $this->format_duration((int) $report['totals']['avg_engagement_seconds']),
                ),
            ),
            $report,
            $selected_key
        );

?>
        <div class="wrap cliredas-wrap">
            <div class="cliredas-header">
                <h1 class="cliredas-title"><?php echo esc_html__('Client Report', 'cliredas-analytics-dashboard'); ?></h1>

                <div class="cliredas-controls">
                    <label for="cliredas-date-range" class="cliredas-control-label">
                        <?php echo esc_html__('Date range', 'cliredas-analytics-dashboard'); ?>
                    </label>

                    <select id="cliredas-date-range" class="cliredas-select">
                        <?php foreach ($ranges as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_key, $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <span class="cliredas-range-hint" id="cliredas-range-hint">
                        <?php
                        /* translators: %s: selected date range label (e.g. "Last 7 days"). */
                        echo esc_html(sprintf(__('Showing: %s', 'cliredas-analytics-dashboard'), $selected_label));
                        ?>
                    </span>

                    <span class="cliredas-status" id="cliredas-status" aria-live="polite"></span>
                </div>
                <?php if (current_user_can('manage_options')) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cliredas-clear-cache-form">
                        <input type="hidden" name="action" value="cliredas_clear_cache">
                        <?php wp_nonce_field('cliredas_clear_cache'); ?>
                        <button type="submit" class="button">
                            <?php echo esc_html__('Clear cache', 'cliredas-analytics-dashboard'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div id="cliredas-notice" class="notice notice-error is-dismissible" style="display:none;">
                <p></p>
            </div>

            <?php $settings_url = admin_url('options-general.php?page=' . CLIREDAS_Settings::SETTINGS_PAGE_SLUG); ?>
            <?php if (! $this->settings->is_ga4_connected()) : ?>
                <div class="notice notice-info">
                    <p>
                        <?php echo esc_html__('GA4 is not connected yet, so you are seeing sample data.', 'cliredas-analytics-dashboard'); ?>
                        <?php if (current_user_can('manage_options')) : ?>
                            <a href="<?php echo esc_url($settings_url); ?>">
                                <?php echo esc_html__('Connect Google Analytics in Settings', 'cliredas-analytics-dashboard'); ?>
                            </a>
                        <?php else : ?>
                            <?php echo esc_html__('Please ask an administrator to connect Google Analytics in Settings.', 'cliredas-analytics-dashboard'); ?>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php
            $ga4_warning_message = ! empty($report['error_message']) ? trim((string) $report['error_message']) : '';
            ?>
            <div id="cliredas-ga4-warning" class="notice notice-warning is-dismissible" <?php echo ('' === $ga4_warning_message) ? 'style="display:none;"' : ''; ?>>
                <p>
                    <span class="cliredas-ga4-warning-text"><?php echo esc_html($ga4_warning_message); ?></span>
                    <?php if (current_user_can('manage_options')) : ?>
                        <a href="<?php echo esc_url($settings_url); ?>">
                            <?php echo esc_html__('Open Settings', 'cliredas-analytics-dashboard'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </div>

            <?php
            $cache_cleared = filter_input(INPUT_GET, 'cliredas_cache_cleared', FILTER_SANITIZE_NUMBER_INT);
            $cache_cleared_nonce = filter_input(INPUT_GET, 'cliredas_cache_cleared_nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $cache_cleared_ok = is_string($cache_cleared_nonce) && wp_verify_nonce(sanitize_text_field($cache_cleared_nonce), 'cliredas_cache_cleared');
            ?>
            <?php if (current_user_can('manage_options') && is_string($cache_cleared) && $cache_cleared_ok) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: number of cache entries cleared */
                                __('Cache cleared (%d entries).', 'cliredas-analytics-dashboard'),
                                absint($cache_cleared)
                            )
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php do_action('cliredas_dashboard_before_kpis', $report, $selected_key); ?>

            <div class="cliredas-kpis" id="cliredas-kpis">
                <?php foreach ($kpis as $kpi_key => $kpi) : ?>
                    <div class="cliredas-kpi" data-kpi="<?php echo esc_attr($kpi_key); ?>">
                        <div class="cliredas-kpi-label"><?php echo esc_html($kpi['label']); ?></div>
                        <div class="cliredas-kpi-value"><?php echo esc_html($kpi['value']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php do_action('cliredas_dashboard_after_kpis', $report, $selected_key); ?>

            <div class="cliredas-grid">
                <div class="cliredas-card cliredas-card-wide">
                    <div class="cliredas-card-header">
                        <h2 class="cliredas-card-title" id="cliredas-chart-title"><?php echo esc_html__('Sessions over time', 'cliredas-analytics-dashboard'); ?></h2>

                        <label class="cliredas-inline-control" for="cliredas-chart-metric">
                            <span class="cliredas-inline-control-label"><?php echo esc_html__('Chart', 'cliredas-analytics-dashboard'); ?></span>
                            <select id="cliredas-chart-metric" class="cliredas-select cliredas-select-compact">
                                <option value="sessions"><?php echo esc_html__('Sessions', 'cliredas-analytics-dashboard'); ?></option>
                                <option value="users"><?php echo esc_html__('Total users', 'cliredas-analytics-dashboard'); ?></option>
                            </select>
                        </label>
                    </div>

                    <div class="cliredas-chart-wrap">
                        <canvas id="cliredas-sessions-chart" height="90"></canvas>
                    </div>
                </div>

                <div class="cliredas-card cliredas-card-wide">
                    <h2 class="cliredas-card-title"><?php echo esc_html__('Top pages', 'cliredas-analytics-dashboard'); ?></h2>

                    <table class="widefat striped cliredas-table" id="cliredas-top-pages">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Page Title', 'cliredas-analytics-dashboard'); ?></th>
                                <th><?php echo esc_html__('URL', 'cliredas-analytics-dashboard'); ?></th>
                                <th><?php echo esc_html__('Sessions', 'cliredas-analytics-dashboard'); ?></th>
                                <th><?php echo esc_html__('Views', 'cliredas-analytics-dashboard'); ?></th>
                                <th><?php echo esc_html__('Avg engagement time', 'cliredas-analytics-dashboard'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ((array) $report['top_pages'] as $row) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) $row['title']); ?></td>
                                    <td><code><?php echo esc_html((string) $row['url']); ?></code></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $row['sessions'])); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) ($row['views'] ?? 0))); ?></td>
                                    <td><?php echo esc_html($this->format_duration((int) ($row['avg_engagement_seconds'] ?? 0))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="cliredas-card">
                    <h2 class="cliredas-card-title"><?php echo esc_html__('Device breakdown', 'cliredas-analytics-dashboard'); ?></h2>

                    <table class="widefat striped cliredas-table" id="cliredas-devices">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Device', 'cliredas-analytics-dashboard'); ?></th>
                                <th><?php echo esc_html__('Sessions', 'cliredas-analytics-dashboard'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ((array) $report['devices'] as $device => $count) : ?>
                                <tr>
                                    <td><?php echo esc_html(ucfirst((string) $device)); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $count)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="cliredas-card">
                    <h2 class="cliredas-card-title"><?php echo esc_html__('Traffic sources', 'cliredas-analytics-dashboard'); ?></h2>

                    <div class="cliredas-chart-wrap cliredas-chart-wrap-small">
                        <canvas id="cliredas-traffic-sources-chart" height="180"></canvas>
                    </div>

                    <table class="widefat striped cliredas-table" id="cliredas-traffic-sources">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Source', 'cliredas-analytics-dashboard'); ?></th>
                                <th><?php echo esc_html__('Sessions', 'cliredas-analytics-dashboard'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sources = isset($report['traffic_sources']) && is_array($report['traffic_sources']) ? $report['traffic_sources'] : array();
                            $labels = array(
                                'organic_search' => __('Organic Search', 'cliredas-analytics-dashboard'),
                                'direct'         => __('Direct', 'cliredas-analytics-dashboard'),
                                'referral'       => __('Referral', 'cliredas-analytics-dashboard'),
                                'social'         => __('Social', 'cliredas-analytics-dashboard'),
                                'other'          => __('Other', 'cliredas-analytics-dashboard'),
                            );
                            foreach ($labels as $key => $label) :
                                $count = isset($sources[$key]) ? (int) $sources[$key] : 0;
                            ?>
                                <tr>
                                    <td><?php echo esc_html($label); ?></td>
                                    <td><?php echo esc_html(number_format_i18n($count)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php do_action('cliredas_dashboard_sections', $report, $selected_key); ?>

        </div>
<?php
    }

    /**
     * Hide detailed Google error messages from non-admin users.
     *
     * @param array $report Report data.
     * @return array
     */
    private function sanitize_report_for_current_user(array $report)
    {
        if (current_user_can('manage_options')) {
            return $report;
        }

        if (! empty($report['error_message'])) {
            $report['error_message'] = __(
                'GA4 data is temporarily unavailable. Please ask an administrator to reconnect Google Analytics.',
                'cliredas-analytics-dashboard'
            );
        }

        return $report;
    }

    /**
     * Get date ranges (Free defaults). Pro can add more via filter.
     *
     * @return array<string,string>
     */
    private function get_date_ranges()
    {
        $ranges = array(
            'last_7_days'  => __('Last 7 days', 'cliredas-analytics-dashboard'),
            'last_30_days' => __('Last 30 days', 'cliredas-analytics-dashboard'),
        );

        $ranges = apply_filters('cliredas_date_ranges', $ranges);

        if (! is_array($ranges) || empty($ranges)) {
            $ranges = array(
                'last_7_days' => __('Last 7 days', 'cliredas-analytics-dashboard'),
            );
        }

        return $ranges;
    }

    /**
     * Get current range key from request, validated against allowed ranges.
     *
     * @return string
     */
    private function get_current_range_key()
    {
        $ranges = $this->get_date_ranges();
        $keys   = array_keys($ranges);

        $default = isset($keys[0]) ? (string) $keys[0] : 'last_7_days';

        $range_raw = filter_input(INPUT_GET, 'cliredas_range', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (! is_string($range_raw)) {
            // Backward compatibility for existing links using ?range=...
            $range_raw = filter_input(INPUT_GET, 'range', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $range = is_string($range_raw) ? sanitize_key($range_raw) : $default;

        if (! array_key_exists($range, $ranges)) {
            $range = $default;
        }

        return $range;
    }

    /**
     * Format seconds as Xm Ys.
     *
     * @param int $seconds Seconds.
     * @return string
     */
    private function format_duration($seconds)
    {
        $seconds = max(0, (int) $seconds);

        $minutes = (int) floor($seconds / 60);
        $remain  = (int) ($seconds % 60);

        if ($minutes <= 0) {
            return sprintf(
                /* translators: %d: number of seconds */
                _n('%d second', '%d seconds', $remain, 'cliredas-analytics-dashboard'),
                $remain
            );
        }

        return sprintf(
            /* translators: 1: minutes, 2: seconds */
            __('%1$dm %2$ds', 'cliredas-analytics-dashboard'),
            $minutes,
            $remain
        );
    }
}
