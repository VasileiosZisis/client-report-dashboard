<?php

/**
 * Dashboard page renderer (placeholder for now).
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
     * @param CLIREDAS_Settings $settings Settings service.
     */
    public function __construct(CLIREDAS_Settings $settings)
    {
        $this->settings = $settings;
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

?>
        <div class="wrap">
            <h1><?php echo esc_html__('Client Report', 'client-report-dashboard'); ?></h1>

            <p>
                <?php echo esc_html__('Dashboard UI will be added in the next milestone. This is a placeholder.', 'client-report-dashboard'); ?>
            </p>

            <hr />

            <p>
                <a href="<?php echo esc_url('https://example.com/client-report-dashboard-pro'); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html__('Upgrade to Pro', 'client-report-dashboard'); ?>
                </a>
            </p>
        </div>
<?php
    }
}
