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
     * @var CLIREDAS_Data_Provider
     */
    private $provider;

    /**
     * Dashboard screen id.
     *
     * @var string
     */
    private $screen_id = 'toplevel_page_cliredas-client-report';

    /**
     * @param CLIREDAS_Settings      $settings  Settings service.
     * @param CLIREDAS_Data_Provider $provider  Data provider.
     */
    public function __construct(CLIREDAS_Settings $settings, CLIREDAS_Data_Provider $provider)
    {
        $this->settings = $settings;
        $this->provider = $provider;

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
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_id = ($screen && ! empty($screen->id)) ? $screen->id : '';

        if ($this->screen_id !== $screen_id) {
            return;
        }

        wp_enqueue_style(
            'cliredas-dashboard',
            CLIREDAS_PLUGIN_URL . 'assets/css/dashboard.css',
            array(),
            CLIREDAS_VERSION
        );

        wp_enqueue_script(
            'cliredas-dashboard',
            CLIREDAS_PLUGIN_URL . 'assets/js/dashboard.js',
            array(),
            CLIREDAS_VERSION,
            true
        );

        wp_localize_script(
            'cliredas-dashboard',
            'CLIREDAS_DASHBOARD',
            array(
                'ajaxUrl'      => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('cliredas_dashboard'),
                'selectedRange' => $this->get_current_range_key(),
                'ranges'       => $this->get_date_ranges(),
                'upgradeUrl'   => 'https://example.com/client-report-dashboard-pro',
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
        $capability = $this->settings->get_required_capability('dashboard');

        if (! current_user_can($capability)) {
            wp_send_json_error(
                array('message' => __('Permission denied.', 'client-report-dashboard')),
                403
            );
        }

        check_ajax_referer('cliredas_dashboard', 'nonce');

        $ranges = $this->get_date_ranges();
        $range  = isset($_POST['range']) ? sanitize_key(wp_unslash($_POST['range'])) : $this->get_current_range_key();

        if (! array_key_exists($range, $ranges)) {
            $range = $this->get_current_range_key();
        }

        $report = $this->provider->get_report($range);

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
            wp_die(esc_html__('You do not have permission to view this page.', 'client-report-dashboard'));
        }

        $ranges         = $this->get_date_ranges();
        $selected_key   = $this->get_current_range_key();
        $selected_label = isset($ranges[$selected_key]) ? $ranges[$selected_key] : reset($ranges);

        // Initial server-rendered report (so the page is usable without JS).
        $report = $this->provider->get_report($selected_key);

        $kpis = apply_filters(
            'cliredas_kpis',
            array(
                'sessions' => array(
                    'label' => __('Sessions', 'client-report-dashboard'),
                    'value' => number_format_i18n((int) $report['totals']['sessions']),
                ),
                'users'    => array(
                    'label' => __('Users', 'client-report-dashboard'),
                    'value' => number_format_i18n((int) $report['totals']['users']),
                ),
                'engagement_time' => array(
                    'label' => __('Avg engagement time', 'client-report-dashboard'),
                    'value' => $this->format_duration((int) $report['totals']['avg_engagement_seconds']),
                ),
            ),
            $report,
            $selected_key
        );

?>
        <div class="wrap cliredas-wrap">
            <div class="cliredas-header">
                <h1 class="cliredas-title"><?php echo esc_html__('Client Report', 'client-report-dashboard'); ?></h1>

                <div class="cliredas-controls">
                    <label for="cliredas-date-range" class="cliredas-control-label">
                        <?php echo esc_html__('Date range', 'client-report-dashboard'); ?>
                    </label>

                    <select id="cliredas-date-range" class="cliredas-select">
                        <?php foreach ($ranges as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_key, $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <span class="cliredas-range-hint">
                        <?php echo esc_html(sprintf(__('Showing: %s', 'client-report-dashboard'), $selected_label)); ?>
                    </span>

                    <span class="cliredas-status" id="cliredas-status" aria-live="polite"></span>
                </div>
            </div>

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
                    <h2 class="cliredas-card-title"><?php echo esc_html__('Sessions over time', 'client-report-dashboard'); ?></h2>

                    <div class="cliredas-chart-placeholder" id="cliredas-sessions-chart">
                        <?php echo esc_html__('Chart will render here.', 'client-report-dashboard'); ?>
                    </div>
                </div>

                <div class="cliredas-card">
                    <h2 class="cliredas-card-title"><?php echo esc_html__('Device breakdown', 'client-report-dashboard'); ?></h2>

                    <table class="widefat striped cliredas-table" id="cliredas-devices">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Device', 'client-report-dashboard'); ?></th>
                                <th><?php echo esc_html__('Sessions', 'client-report-dashboard'); ?></th>
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

                <div class="cliredas-card cliredas-card-wide">
                    <h2 class="cliredas-card-title"><?php echo esc_html__('Top pages', 'client-report-dashboard'); ?></h2>

                    <table class="widefat striped cliredas-table" id="cliredas-top-pages">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Page Title', 'client-report-dashboard'); ?></th>
                                <th><?php echo esc_html__('URL', 'client-report-dashboard'); ?></th>
                                <th><?php echo esc_html__('Sessions', 'client-report-dashboard'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ((array) $report['top_pages'] as $row) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) $row['title']); ?></td>
                                    <td><code><?php echo esc_html((string) $row['url']); ?></code></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $row['sessions'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php do_action('cliredas_dashboard_sections', $report, $selected_key); ?>

            <div class="cliredas-footer">
                <?php
                $show_powered_by = (bool) apply_filters('cliredas_show_powered_by', true);
                if ($show_powered_by) :
                ?>
                    <span class="cliredas-powered-by"><?php echo esc_html__('Powered by Client Reporting Dashboard', 'client-report-dashboard'); ?></span>
                <?php endif; ?>

                <a class="cliredas-upgrade-link" href="<?php echo esc_url('https://example.com/client-report-dashboard-pro'); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html__('Upgrade to Pro', 'client-report-dashboard'); ?>
                </a>
            </div>
        </div>
<?php
    }

    /**
     * Get date ranges (Free defaults). Pro can add more via filter.
     *
     * @return array<string,string>
     */
    private function get_date_ranges()
    {
        $ranges = array(
            'last_7_days'  => __('Last 7 days', 'client-report-dashboard'),
            'last_30_days' => __('Last 30 days', 'client-report-dashboard'),
        );

        $ranges = apply_filters('cliredas_date_ranges', $ranges);

        if (! is_array($ranges) || empty($ranges)) {
            $ranges = array(
                'last_7_days' => __('Last 7 days', 'client-report-dashboard'),
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

        $range = isset($_GET['range']) ? sanitize_key(wp_unslash($_GET['range'])) : $default;

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
            return sprintf(_n('%d second', '%d seconds', $remain, 'client-report-dashboard'), $remain);
        }

        return sprintf(
            /* translators: 1: minutes, 2: seconds */
            __('%1$dm %2$ds', 'client-report-dashboard'),
            $minutes,
            $remain
        );
    }
}
