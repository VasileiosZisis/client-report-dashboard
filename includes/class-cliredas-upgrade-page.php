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
            <h1><?php echo esc_html__('Pro (Coming Soon) - Features & Metrics', 'client-report-dashboard'); ?></h1>

            <p><?php echo esc_html__('Client Reporting Dashboard Pro includes everything in the Free version, plus:', 'client-report-dashboard'); ?></p>

            <ol>
                <li>
                    <h2><?php echo esc_html__('Advanced metrics & date ranges', 'client-report-dashboard'); ?></h2>

                    <p><strong><?php echo esc_html__('Additional date ranges:', 'client-report-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('This month', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Last month', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Last 90 days', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Year-to-date', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Custom date range picker', 'client-report-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('KPI cards with richer data:', 'client-report-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Conversion events (e.g. key form submissions, sign-ups, thank-you page views)', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Conversion rate for the selected period', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Flexible metric selection so you can choose which KPIs appear on the dashboard for each client.', 'client-report-dashboard'); ?></li>
                    </ul>
                </li>

                <li>
                    <h2><?php echo esc_html__('Comparison & trend insights', 'client-report-dashboard'); ?></h2>

                    <p><strong><?php echo esc_html__('Period comparison for all top-level KPIs:', 'client-report-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Compare current period vs previous period (e.g. last 30 days vs previous 30 days).', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Percentage change indicators with clear green/red highlighting for improvements or declines.', 'client-report-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('Line chart comparison:', 'client-report-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Optional second line showing the previous period, so clients can instantly see whether traffic is trending up or down.', 'client-report-dashboard'); ?></li>
                    </ul>
                </li>

                <li>
                    <h2><?php echo esc_html__('Search performance (Google Search Console)', 'client-report-dashboard'); ?></h2>

                    <p><strong><?php echo esc_html__('Integrated Search Console metrics, alongside GA4 data:', 'client-report-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Total clicks', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Impressions', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Average position', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Click-through rate (CTR)', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('“Top search queries” and “Top landing pages from search” tables for the selected date range.', 'client-report-dashboard'); ?></li>
                    </ul>

                    <p><?php echo esc_html__('Quick overview of how organic search is performing without leaving WordPress.', 'client-report-dashboard'); ?></p>
                </li>

                <li>
                    <h2><?php echo esc_html__('Automated client reporting (email & PDF)', 'client-report-dashboard'); ?></h2>

                    <p><strong><?php echo esc_html__('Scheduled email reports:', 'client-report-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Weekly or monthly email summaries sent directly to clients.', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Includes headline KPIs, key trends, top pages, traffic sources, and devices.', 'client-report-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('Customizable email content:', 'client-report-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Custom subject line and intro text per site.', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Optional “Highlights & next steps” note that appears at the top of the email.', 'client-report-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('PDF export:', 'client-report-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('One-click export of the dashboard view as a PDF for clients who prefer attachments.', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Ideal for attaching to proposals, monthly reports, or internal documentation.', 'client-report-dashboard'); ?></li>
                    </ul>
                </li>

                <li>
                    <h2><?php echo esc_html__('White-label & branding options', 'client-report-dashboard'); ?></h2>

                    <p><strong><?php echo esc_html__('Fully white-labelled experience:', 'client-report-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Change the dashboard menu label (e.g. “Performance Report”, “Marketing Report”).', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Add your own logo to the dashboard and email reports.', 'client-report-dashboard'); ?></li>
                        <li><?php echo esc_html__('Choose a brand accent color used in charts and headings.', 'client-report-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('Hide plugin branding:', 'client-report-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Option to remove “Powered by Client Reporting Dashboard” from the UI and emails, so everything appears under your agency brand.', 'client-report-dashboard'); ?></li>
                    </ul>
                </li>

                <li>
                    <h2><?php echo esc_html__('Agency workflow tools', 'client-report-dashboard'); ?></h2>

                    <p><strong><?php echo esc_html__('Configuration templates:', 'client-report-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Save a default configuration (date ranges, metrics, branding) and apply it to new client sites in a few clicks.', 'client-report-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('Import/export settings:', 'client-report-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Export plugin settings from one site and import them into another, for fast onboarding across multiple clients.', 'client-report-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('Client notes history:', 'client-report-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Keep a timeline of monthly notes (e.g. “New landing page launched”, “Campaign X started”), visible on the dashboard and in reports.', 'client-report-dashboard'); ?></li>
                    </ul>

                    <p><strong><?php echo esc_html__('Multisite-friendly:', 'client-report-dashboard'); ?></strong></p>
                    <ul>
                        <li><?php echo esc_html__('Designed to work smoothly in WordPress multisite environments for agencies managing many client sites.', 'client-report-dashboard'); ?></li>
                    </ul>
                </li>
            </ol>

        </div>
<?php
    }
}
