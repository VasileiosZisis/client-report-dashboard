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
            wp_die(esc_html__('You do not have permission to access this page.', 'cliredas-analytics-dashboard'));
        }

?>
        <div class="wrap">
            <h1><?php echo esc_html__('Pro (Coming Soon) - Features & Metrics', 'cliredas-analytics-dashboard'); ?></h1>

            <p><?php echo esc_html__('Cliredas - Simple Google Analytics Dashboard Pro includes everything in the Free version, plus:', 'cliredas-analytics-dashboard'); ?></p>

            <ol>
                <li>
                    <h2><?php echo esc_html__('Advanced metrics & date ranges', 'cliredas-analytics-dashboard'); ?></h2>

                    <p><strong><?php echo esc_html__('Additional date ranges:', 'cliredas-analytics-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('This month', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Last month', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Last 90 days', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Year-to-date', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Custom date range picker', 'cliredas-analytics-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('KPI cards with richer data:', 'cliredas-analytics-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Conversion events (e.g. key form submissions, sign-ups, thank-you page views)', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Conversion rate for the selected period', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Flexible metric selection so you can choose which KPIs appear on the dashboard for each client.', 'cliredas-analytics-dashboard'); ?></li>
                    </ul>
                </li>

                <li>
                    <h2><?php echo esc_html__('Comparison & trend insights', 'cliredas-analytics-dashboard'); ?></h2>

                    <p><strong><?php echo esc_html__('Period comparison for all top-level KPIs:', 'cliredas-analytics-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Compare current period vs previous period (e.g. last 30 days vs previous 30 days).', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Percentage change indicators with clear green/red highlighting for improvements or declines.', 'cliredas-analytics-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('Line chart comparison:', 'cliredas-analytics-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Optional second line showing the previous period, so clients can instantly see whether traffic is trending up or down.', 'cliredas-analytics-dashboard'); ?></li>
                    </ul>
                </li>

                <li>
                    <h2><?php echo esc_html__('Search performance (Google Search Console)', 'cliredas-analytics-dashboard'); ?></h2>

                    <p><strong><?php echo esc_html__('Integrated Search Console metrics, alongside GA4 data:', 'cliredas-analytics-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Total clicks', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Impressions', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Average position', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Click-through rate (CTR)', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('â€œTop search queriesâ€ and â€œTop landing pages from searchâ€ tables for the selected date range.', 'cliredas-analytics-dashboard'); ?></li>
                    </ul>

                    <p><?php echo esc_html__('Quick overview of how organic search is performing without leaving WordPress.', 'cliredas-analytics-dashboard'); ?></p>
                </li>

                <li>
                    <h2><?php echo esc_html__('Automated client reporting (email & PDF)', 'cliredas-analytics-dashboard'); ?></h2>

                    <p><strong><?php echo esc_html__('Scheduled email reports:', 'cliredas-analytics-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Weekly or monthly email summaries sent directly to clients.', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Includes headline KPIs, key trends, top pages, traffic sources, and devices.', 'cliredas-analytics-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('Customizable email content:', 'cliredas-analytics-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Custom subject line and intro text per site.', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Optional â€œHighlights & next stepsâ€ note that appears at the top of the email.', 'cliredas-analytics-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('PDF export:', 'cliredas-analytics-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('One-click export of the dashboard view as a PDF for clients who prefer attachments.', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Ideal for attaching to proposals, monthly reports, or internal documentation.', 'cliredas-analytics-dashboard'); ?></li>
                    </ul>
                </li>

                <li>
                    <h2><?php echo esc_html__('White-label & branding options', 'cliredas-analytics-dashboard'); ?></h2>

                    <p><strong><?php echo esc_html__('Fully white-labelled experience:', 'cliredas-analytics-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Change the dashboard menu label (e.g. â€œPerformance Reportâ€, â€œMarketing Reportâ€).', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Add your own logo to the dashboard and email reports.', 'cliredas-analytics-dashboard'); ?></li>
                        <li><?php echo esc_html__('Choose a brand accent color used in charts and headings.', 'cliredas-analytics-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('Hide plugin branding:', 'cliredas-analytics-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Option to remove â€œPowered by Cliredas - Simple Google Analytics Dashboardâ€ from the UI and emails, so everything appears under your agency brand.', 'cliredas-analytics-dashboard'); ?></li>
                    </ul>
                </li>

                <li>
                    <h2><?php echo esc_html__('Agency workflow tools', 'cliredas-analytics-dashboard'); ?></h2>

                    <p><strong><?php echo esc_html__('Configuration templates:', 'cliredas-analytics-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Save a default configuration (date ranges, metrics, branding) and apply it to new client sites in a few clicks.', 'cliredas-analytics-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('Import/export settings:', 'cliredas-analytics-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Export plugin settings from one site and import them into another, for fast onboarding across multiple clients.', 'cliredas-analytics-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('Client notes history:', 'cliredas-analytics-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Keep a timeline of monthly notes (e.g. â€œNew landing page launchedâ€, â€œCampaign X startedâ€), visible on the dashboard and in reports.', 'cliredas-analytics-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('Multisite-friendly:', 'cliredas-analytics-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Designed to work smoothly in WordPress multisite environments for agencies managing many client sites.', 'cliredas-analytics-dashboard'); ?></li>
                    </ul>
                </li>
            </ol>

        </div>
<?php
    }
}
