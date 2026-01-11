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
     * Dashboard screen id.
     *
     * @var string
     */
    private $screen_id = 'toplevel_page_cliredas-client-report';

    /**
     * @param CLIREDAS_Settings $settings Settings service.
     */
    public function __construct(CLIREDAS_Settings $settings)
    {
        $this->settings = $settings;

        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
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
                'selectedRange' => $this->get_current_range_key(),
                'ranges'        => $this->get_date_ranges(),
                'upgradeUrl'    => 'https://example.com/client-report-dashboard-pro',
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

        $ranges        = $this->get_date_ranges();
        $selected_key  = $this->get_current_range_key();
        $selected_label = isset($ranges[$selected_key]) ? $ranges[$selected_key] : reset($ranges);

        /**
         * Filters KPI definitions (Free provides the default 3).
         *
         * Later, Pro can add more KPIs here.
         *
         * Each KPI:
         * - label: string
         * - value: string (placeholder for now)
         */
        $kpis = apply_filters(
            'cliredas_kpis',
            array(
                'sessions' => array(
                    'label' => __('Sessions', 'client-report-dashboard'),
                    'value' => '—',
                ),
                'users'    => array(
                    'label' => __('Users', 'client-report-dashboard'),
                    'value' => '—',
                ),
                'engagement_time' => array(
                    'label' => __('Avg engagement time', 'client-report-dashboard'),
                    'value' => '—',
                ),
            )
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
                </div>
            </div>

            <?php do_action('cliredas_dashboard_before_kpis'); ?>

            <div class="cliredas-kpis">
                <?php foreach ($kpis as $kpi_key => $kpi) : ?>
                    <div class="cliredas-kpi" data-kpi="<?php echo esc_attr($kpi_key); ?>">
                        <div class="cliredas-kpi-label"><?php echo esc_html($kpi['label']); ?></div>
                        <div class="cliredas-kpi-value"><?php echo esc_html($kpi['value']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php do_action('cliredas_dashboard_after_kpis'); ?>

            <div class="cliredas-grid">
                <div class="cliredas-card cliredas-card-wide">
                    <h2 class="cliredas-card-title"><?php echo esc_html__('Sessions over time', 'client-report-dashboard'); ?></h2>
                    <div class="cliredas-chart-placeholder" id="cliredas-sessions-chart">
                        <?php echo esc_html__('Chart will render here (mock for now).', 'client-report-dashboard'); ?>
                    </div>
                </div>

                <div class="cliredas-card">
                    <h2 class="cliredas-card-title"><?php echo esc_html__('Device breakdown', 'client-report-dashboard'); ?></h2>

                    <table class="widefat striped cliredas-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Device', 'client-report-dashboard'); ?></th>
                                <th><?php echo esc_html__('Sessions', 'client-report-dashboard'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo esc_html__('Desktop', 'client-report-dashboard'); ?></td>
                                <td>—</td>
                            </tr>
                            <tr>
                                <td><?php echo esc_html__('Mobile', 'client-report-dashboard'); ?></td>
                                <td>—</td>
                            </tr>
                            <tr>
                                <td><?php echo esc_html__('Tablet', 'client-report-dashboard'); ?></td>
                                <td>—</td>
                            </tr>
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
                            <tr>
                                <td colspan="3"><?php echo esc_html__('Top pages will render here (mock for now).', 'client-report-dashboard'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php
            /**
             * Action for additional dashboard sections (Pro can add more here).
             */
            do_action('cliredas_dashboard_sections');
            ?>

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
     * @return array<string,string> key => label
     */
    private function get_date_ranges()
    {
        $ranges = array(
            'last_7_days'  => __('Last 7 days', 'client-report-dashboard'),
            'last_30_days' => __('Last 30 days', 'client-report-dashboard'),
        );

        /**
         * Filter available date ranges.
         *
         * Pro can add extra ranges here.
         *
         * @param array<string,string> $ranges Ranges.
         */
        $ranges = apply_filters('cliredas_date_ranges', $ranges);

        // Ensure it remains a non-empty associative array.
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
}
