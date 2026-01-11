<?php

/**
 * Upgrade page renderer (placeholder for now).
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

final class CLIREDAS_Upgrade_Page
{

    /**
     * Render the upgrade page.
     *
     * @return void
     */
    public function render()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'client-report-dashboard'));
        }

?>
        <div class="wrap">
            <h1><?php echo esc_html__('Upgrade to Client Reporting Dashboard Pro', 'client-report-dashboard'); ?></h1>

            <p><?php echo esc_html__('This page will contain a Free vs Pro comparison. (Dummy content for now.)', 'client-report-dashboard'); ?></p>

            <ul>
                <li><?php echo esc_html__('Free: Basic dashboard, 7/30 day ranges, core metrics', 'client-report-dashboard'); ?></li>
                <li><?php echo esc_html__('Pro: More ranges, more metrics, email reports, white-labeling', 'client-report-dashboard'); ?></li>
            </ul>

            <p>
                <a class="button button-primary" href="<?php echo esc_url('https://example.com/client-report-dashboard-pro'); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html__('View Pro', 'client-report-dashboard'); ?>
                </a>
            </p>
        </div>
<?php
    }
}
