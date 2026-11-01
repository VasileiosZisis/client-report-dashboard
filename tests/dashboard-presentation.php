<?php
/**
 * Isolated display-only regressions: wp eval-file tests/dashboard-presentation.php
 *
 * @package ClientReportingDashboard
 */

(defined('WP_CLI') && WP_CLI) || exit;

/** Exercise real PHP rendering and export without Google or report storage. */
final class CLIREDAS_Presentation_Tests
{
    /** Successful checks.
     *
     * @var int
     */
    private static $count = 0;

    /** Fail on an observable rendering regression.
     *
     * @throws RuntimeException On assertion failure.
     */
    private static function check($value, $label)
    {
        if (! $value) {
            throw new RuntimeException(esc_html($label));
        }
        ++self::$count;
    }

    /** Fixture for both real zero and populated presentations. */
    private static function report()
    {
        return array('source' => 'ga4', 'range' => array('startDate' => '2026-10-25', 'endDate' => '2026-10-31'),
            'totals' => array('sessions' => 10, 'users' => 8, 'pageviews' => 15, 'avg_engagement_seconds' => 45),
            'comparison' => array('totals' => array('sessions' => 20, 'users' => 8, 'pageviews' => 15, 'avg_engagement_seconds' => 45), 'range' => array('startDate' => '2026-10-18', 'endDate' => '2026-10-24')),
            'timeseries' => array(array('date' => '2026-10-31', 'sessions' => 10, 'users' => 8)),
            'top_pages' => array(array('title' => '<script>fixture</script>', 'url' => '/fixture', 'sessions' => 10, 'views' => 15)),
            'devices' => array('desktop' => 10), 'traffic_sources' => array('direct' => 10),
            'freshness' => array('state' => 'fresh', 'label' => 'Fresh', 'last_success_label' => '2026-10-31 08:00'),
        );
    }

    /** Run isolated provider rendering with a preloaded administrator and local settings. */
    public static function run()
    {
        CLIREDAS_Plugin::instance()->bootstrap_admin();
        $actor = get_current_user_id();
        $admins = get_users(array('role' => 'administrator', 'fields' => 'ID', 'number' => 1));
        self::check(! empty($admins), 'Administrator fixture actor exists');
        wp_set_current_user((int) $admins[0]);
        $settings_filter = static function () {
            return array('ga4_connected' => 1, 'ga4_property_id' => 'properties/123', 'allow_editors' => 0);
        };
        $http = static function () {
            throw new RuntimeException('Presentation must not contact Google');
        };
        add_filter('pre_option_cliredas_settings', $settings_filter, 999);
        add_filter('pre_http_request', $http, 999);
        $provider = new class() {
            /** Fixture payload.
             *
             * @var array
             */
            public $report;
            /** Fetch counter.
             *
             * @var int
             */
            public $calls = 0;
            public function get_report($range)
            {
                unset($range);
                ++$this->calls;
                return $this->report;
            }
        };
        $render = static function ($report) use ($provider) {
            $provider->report = $report;
            $page = new CLIREDAS_Dashboard_Page(new CLIREDAS_Settings(), $provider);
            ob_start();
            $page->render();
            return array(ob_get_clean(), $page);
        };
        try {
            $report = self::report();
            list($html, $page) = $render($report);
            self::check(false !== strpos($html, '&lt;script&gt;fixture&lt;/script&gt;') && false === strpos($html, '<script>fixture'), 'External page titles are escaped');
            self::check(false !== strpos($html, 'id="cliredas-top-pages-filter" hidden'), 'Local filter remains hidden without JavaScript');
            self::check(false !== strpos($html, '<details id="cliredas-trend-details">') && false !== strpos($html, '2026-10-31') && false !== strpos($html, 'Daily Sessions and Total users'), 'Native chart data table is server-rendered');
            self::check(false !== strpos($html, 'aria-sort="none"') && false !== strpos($html, 'tabindex="0" role="region"'), 'Semantic sorting and keyboard scroll regions exist');
            self::check(false !== strpos($html, 'id="cliredas-trend-wrap" hidden'), 'No-JavaScript fallback does not show a blank chart area');
            self::check(false !== strpos($html, '<td>—</td>'), 'Missing Top pages engagement is unavailable rather than zero');
            $before = $provider->calls;
            ob_start();
            $page->render();
            ob_end_clean();
            self::check($before === $provider->calls, 'PHP uses a request-local snapshot');

            $writer = new ReflectionMethod($page, 'write_csv_report');
            $writer->setAccessible(true);
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory fixture only.
            $stream = fopen('php://temp', 'w+');
            $writer->invoke($page, $stream, $report, 'Last 7 days');
            rewind($stream);
            $csv = stream_get_contents($stream);
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory fixture only.
            fclose($stream);
            self::check(false !== strpos($csv, '/fixture') && $provider->report === $report, 'Presentation leaves full export/report contents unchanged');

            foreach (array('totals', 'timeseries', 'top_pages', 'devices', 'traffic_sources') as $block) {
                self::check('ready' === CLIREDAS_Report_Presentation::state($report, $block), 'Populated block: ' . $block);
                $missing = $report;
                unset($missing[$block]);
                self::check('unavailable' === CLIREDAS_Report_Presentation::state($missing, $block), 'Missing block: ' . $block);
                $empty = $report;
                $empty[$block] = array();
                self::check(('totals' === $block ? 'unavailable' : 'empty') === CLIREDAS_Report_Presentation::state($empty, $block), 'Empty block: ' . $block);
            }
            $zero = $report;
            $zero['totals'] = array_fill_keys(array_keys($report['totals']), 0);
            $zero['timeseries'][0]['sessions'] = 0;
            $zero['timeseries'][0]['users'] = 0;
            $zero['devices'] = array('desktop' => 0);
            $zero['traffic_sources'] = array('direct' => 0);
            $zero['top_pages'] = array();
            list($html) = $render($zero);
            self::check(false !== strpos($html, '-100.0% vs previous period') && false !== strpos($html, 'No activity recorded'), 'Zero metrics retain valid comparisons and an explanation');
            self::check(false !== strpos($html, 'No pages for this period.') && false !== strpos($html, 'No device activity') && false !== strpos($html, 'No traffic-source activity'), 'Every empty block has an explanation');
            self::check(false !== strpos($html, '<td>0</td>'), 'Accessible trend data preserves real zeros');
            $partial = $report;
            $partial['traffic_sources_unavailable'] = true;
            unset($partial['totals']['users']);
            list($html) = $render($partial);
            self::check('unavailable' === CLIREDAS_Report_Presentation::state($partial, 'traffic_sources') && false !== strpos($html, 'Data unavailable.'), 'Partial source failure remains unavailable');
            self::check(false !== strpos($html, '<div class="cliredas-kpi-value">—</div>'), 'Missing individual KPI remains unavailable');
            unset($partial['timeseries'][0]['sessions']);
            list($html) = $render($partial);
            self::check(false !== strpos($html, '<td>8</td>') && 'unavailable' === CLIREDAS_Report_Presentation::state($partial, 'timeseries'), 'Accessible table retains a known metric beside an unknown one');
            $unavailable = $report;
            $unavailable['source'] = 'unavailable';
            list($html) = $render($unavailable);
            self::check(false === strpos($html, '/fixture') && false === strpos($html, '&lt;script&gt;fixture'), 'Unavailable reports never render retained block payloads');
            foreach (array('stale', 'refreshing', 'fresh') as $state) {
                $candidate = $report;
                $candidate['freshness']['state'] = $state;
                $candidate['freshness']['label'] = ucfirst($state);
                list($html) = $render($candidate);
                self::check(false !== strpos($html, ucfirst($state)), 'Freshness remains labeled: ' . $state);
            }
            $sample = $report;
            $sample['source'] = 'mock';
            list($html) = $render($sample);
            self::check(false !== strpos($html, 'Sample data'), 'Mock reports retain sample labeling');
            foreach (array(null, false, '', ' ', '0x10', -1, INF) as $value) {
                self::check(! CLIREDAS_Report_Presentation::number($value), 'Unknown/invalid metric is not zero');
            }
            WP_CLI::success('Dashboard PHP presentation regressions passed (' . self::$count . ' assertions).');
        } finally {
            remove_filter('pre_option_cliredas_settings', $settings_filter, 999);
            remove_filter('pre_http_request', $http, 999);
            wp_set_current_user($actor);
        }
    }
}

CLIREDAS_Presentation_Tests::run();
