<?php
/**
 * Isolated cache/provider regressions. Run with: wp eval-file tests/report-cache.php
 *
 * @package ClientReportingDashboard
 */

(defined('WP_CLI') && WP_CLI) || exit;

require_once __DIR__ . '/fixtures/class-cliredas-options-fixture.php';

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Self-contained test runner and adapter.
/** Exercise the real provider, Options API and WP-Cron API with isolated fixtures. */
final class CLIREDAS_Cache_Tests
{
    /** Passed assertions.
     *
     * @var int
     */
    private static $assertions = 0;

    /** Fail with a non-sensitive label.
     *
     * @throws RuntimeException On a failed assertion.
     */
    private static function check($condition, $label)
    {
        if (! $condition) {
            throw new RuntimeException(esc_html($label));
        }
        ++self::$assertions;
    }

    /** Set up fixtures; restore the database, cache, filters and actor in finally. */
    public static function run()
    {
        global $wpdb, $blog_id, $wp_object_cache;
        if (wp_using_ext_object_cache()) {
            WP_CLI::error('Use a disposable WordPress installation without a persistent object-cache drop-in.');
        }
        CLIREDAS_Plugin::instance()->bootstrap_admin();
        foreach (array('auth', 'secure_auth', 'logged_in', 'nonce') as $scheme) {
            wp_salt($scheme);
        }
        $original_db = $wpdb;
        $original_blog = $blog_id;
        $original_user = get_current_user_id();
        $admins = get_users(array('role' => 'administrator', 'fields' => 'ID', 'number' => 1));
        self::check(! empty($admins), 'An administrator fixture actor is required');
        wp_set_current_user((int) $admins[0]);
        $alloptions = wp_load_alloptions();
        $original_cache = $wp_object_cache;
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Keep fixture caches separate from the original request cache.
        $wp_object_cache = new WP_Object_Cache();
        $db = new CLIREDAS_Cache_Fixture_DB();
        $db->prefix = $original_db->get_blog_prefix();
        $settings = new CLIREDAS_Settings();
        $fixture = array('ga4_connected' => 1, 'ga4_client_id' => 'fixture-client', 'ga4_client_secret' => 'fixture-secret',
            'ga4_refresh_token' => 'fixture-refresh', 'ga4_access_token' => 'fixture-access', 'ga4_token_expires' => time() + 3600,
            'ga4_property_id' => 'properties/123', 'allow_editors' => 0,
        );
        $reset = static function () use ($db, $fixture) {
            $db->fail = false;
            $db->conflict = null;
            $db->state = array('cliredas_settings' => $fixture, 'cliredas_audit_log' => array(),
                'cliredas_report_cache' => array('generation' => wp_generate_uuid4(), 'entries' => array()),
                'cliredas_cache_keys' => array(), 'cron' => array('version' => 2),
                'timezone_string' => 'Europe/Chisinau', 'gmt_offset' => 3,
            );
            wp_cache_flush();
        };
        $option_filter = static function ($value, $key) use ($db) {
            return array_key_exists($key, $db->state) ? $db->state[$key] : $value;
        };
        $alloptions_filter = static function () use ($alloptions) {
            return $alloptions;
        };
        $calls = 0;
        $mode = 'success';
        $during_http = null;
        $http_filter = static function ($pre, $args) use (&$calls, &$mode, &$during_http) {
            ++$calls;
            if (is_callable($during_http)) {
                $callback = $during_http;
                $during_http = null;
                $callback();
            }
            $body = json_decode($args['body'], true);
            $dimension = isset($body['dimensions'][0]['name']) ? $body['dimensions'][0]['name'] : '';
            $status = 'forbidden' === $mode ? 403 : ('quota' === $mode ? 429 : 503);
            if (in_array($mode, array('failure', 'forbidden', 'quota'), true) || ('partial' === $mode && 'sessionDefaultChannelGroup' === $dimension)) {
                return array('response' => array('code' => $status), 'body' => wp_json_encode(array('error' => array('message' => 'fixture-sensitive-error'))));
            }
            $data = array('rows' => array());
            if ('' === $dimension && 'zero' !== $mode) {
                $data['rows'][] = array('metricValues' => array(array('value' => '123'), array('value' => '80'), array('value' => '420'), array('value' => '180')));
            }
            return array('response' => array('code' => 200), 'body' => wp_json_encode($data));
        };
        add_filter('pre_option', $option_filter, 999, 2);
        add_filter('pre_wp_load_alloptions', $alloptions_filter, 999);
        add_filter('pre_http_request', $http_filter, 999, 2);
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolated adapter restored in finally.
        $wpdb = $db;
        $reset();
        try {
            $provider = new CLIREDAS_GA4_Data_Provider($settings);
            $context = $provider->report_context('last_7_days');
            $report = $provider->get_report('last_7_days');
            self::check(5 === $calls && 'ga4' === $report['source'] && 123 === $report['totals']['sessions'], 'Cold load fetches all five real blocks');
            self::check('fresh' === $report['freshness']['state'], 'Cold success is fresh');
            self::check(array_intersect_key($report['range'], $context['dates']) === $context['dates'], 'Report uses resolved request dates');
            self::check('ga4_cache' === $provider->get_report('last_7_days')['source'] && 5 === $calls, 'Fresh hit makes no HTTP requests');
            self::check(! is_wp_error($provider->get_fresh_report('last_7_days')) && 5 === $calls, 'Fresh-only consumers reuse complete fresh reports');
            $obsolete = $report;
            $obsolete['freshness']['context_id'] = 'obsolete-context';
            self::check(! $provider->is_fresh_report($obsolete, 'last_7_days'), 'Fresh-only validation rejects an obsolete context');
            $obsolete = $report;
            $obsolete['range']['endDate'] = '2000-01-01';
            self::check(! $provider->is_fresh_report($obsolete, 'last_7_days'), 'Fresh-only validation rejects dates from before a calendar rollover');
            $id = $context['id'];
            $db->state['cliredas_report_cache']['entries'][$id]['good']['fresh_until'] = time() - 1;
            $report = $provider->get_report('last_7_days');
            self::check('refreshing' === $report['freshness']['state'] && 5 === $calls, 'Stale report returns immediately');
            self::check(false !== wp_next_scheduled(CLIREDAS_Report_Cache::REFRESH_HOOK, array($id)), 'One background refresh is scheduled');
            $cron = $db->state['cron'];
            $provider->get_report('last_7_days');
            self::check($db->state['cron'] === $cron, 'Repeated stale reads do not duplicate scheduled events');
            $provider->cached_response($context);
            self::check(5 === $calls && $cron === $db->state['cron'], 'Status reads make no requests or schedule changes');
            CLIREDAS_Report_Cache::refresh($id);
            self::check(10 === $calls && 'fresh' === $provider->cached_response($context)['freshness']['state'], 'Cron-only callback refreshes a stale report');
            CLIREDAS_Report_Cache::refresh($id);
            self::check(10 === $calls, 'Repeated completed callback does not refetch fresh data');

            $db->state['cliredas_report_cache']['entries'][$id]['good']['fresh_until'] = time() - 1;
            $good = $db->state['cliredas_report_cache']['entries'][$id]['good'];
            $mode = 'failure';
            $report = $provider->refresh_context($context);
            self::check($good === $db->state['cliredas_report_cache']['entries'][$id]['good'] && 'stale' === $report['freshness']['state'], 'Failure preserves payload and success timestamp');
            $before = $calls;
            $provider->get_report('last_7_days');
            self::check($before === $calls && $db->state['cliredas_report_cache']['entries'][$id]['retry_at'] > time(), 'Failure cooldown prevents repeated Google requests');
            self::check(false === strpos($report['error_message'], 'fixture-sensitive'), 'Failure feedback never exposes upstream contents');
            self::check(is_wp_error($provider->get_fresh_report('last_7_days')), 'Fresh-only consumers reject retained stale reports after refresh failure');
            $db->state['cliredas_report_cache']['entries'][$id]['retry_at'] = 0;
            $mode = 'quota';
            self::check('ga4_quota_exceeded' === $provider->refresh_context($context)['freshness']['error_code'], 'Quota failures retain last-good data');
            $db->state['cliredas_report_cache']['entries'][$id]['good']['retained_until'] = time() - 1;
            self::check('unavailable' === $provider->cached_response($context)['source'], 'Reports older than retention are never served');

            $reset();
            $context = $provider->report_context('last_7_days');
            CLIREDAS_Report_Cache::ensure($context);
            $owner = CLIREDAS_Report_Cache::lock($context);
            self::check(false === CLIREDAS_Report_Cache::lock($context), 'Concurrent worker cannot acquire a live lease');
            self::check(false === CLIREDAS_Report_Cache::finish($context, 'not-owner'), 'Non-owner cannot release a lease');
            $before = $calls;
            $pending = $provider->get_report('last_7_days');
            self::check('unavailable' === $pending['source'] && $pending['freshness']['pending'] && $before === $calls, 'Concurrent cold load returns unavailable without fabrication');
            $db->state['cliredas_report_cache']['entries'][$context['id']]['lease_until'] = time() - 1;
            $replacement = CLIREDAS_Report_Cache::lock($context);
            self::check($replacement && $replacement !== $owner, 'Expired leases are reclaimable');
            self::check(false === CLIREDAS_Report_Cache::lock($context, $owner), 'Old owner cannot renew a replacement lease');
            CLIREDAS_Report_Cache::clear();
            self::check(false === CLIREDAS_Report_Cache::finish($context, $replacement, $report), 'Clear invalidates in-flight publication');

            $reset();
            $mode = 'success';
            $during_http = static function () {
                CLIREDAS_Report_Cache::clear();
            };
            self::check('unavailable' === $provider->get_report('last_7_days')['source'], 'Clear during HTTP cannot restore old data');
            self::check(array() === CLIREDAS_Report_Cache::read()['entries'], 'Cleared cache stays empty after old fetch');

            $reset();
            $during_http = static function () use ($settings) {
                $settings->save_settings(array('ga4_connected' => 0, 'ga4_refresh_token' => '', 'ga4_access_token' => ''));
            };
            self::check('unavailable' === $provider->get_report('last_7_days')['source'], 'Disconnect during HTTP prevents publication');

            $reset();
            $during_http = static function () use ($db) {
                $db->fail = true;
            };
            $failed = $provider->get_report('last_7_days');
            $db->fail = false;
            self::check('unavailable' === $failed['source'], 'Publication failure cannot fabricate a cached success');

            $reset();
            $context = $provider->report_context('last_7_days');
            CLIREDAS_Report_Cache::ensure($context);
            $db->conflict = static function ($fixture_db) use ($context) {
                $entry = &$fixture_db->state['cliredas_report_cache']['entries'][$context['id']];
                $entry['owner'] = 'concurrent-owner';
                $entry['lease_until'] = time() + 120;
            };
            self::check(false === CLIREDAS_Report_Cache::lock($context), 'Conditional lock acquisition preserves a concurrent owner');

            $reset();
            $report = $provider->get_report('last_7_days');
            $context = $provider->report_context('last_7_days');
            $id = $context['id'];
            $db->state['cliredas_report_cache']['entries'][$id]['good']['fresh_until'] = time() - 1;
            $mode = 'forbidden';
            $provider->refresh_context($context);
            self::check('unavailable' === $provider->cached_response($context)['source'] && null === CLIREDAS_Report_Cache::entry($context)['good'], 'Confirmed revocation invalidates last-good data');

            $reset();
            $mode = 'partial';
            $report = $provider->get_report('last_7_days');
            self::check('ga4' === $report['source'] && ! empty($report['traffic_sources_unavailable']), 'Partial cold report labels missing traffic sources');
            self::check(null === CLIREDAS_Report_Cache::entry($provider->report_context('last_7_days'))['good'], 'Partial success is never last-good');
            self::check(is_wp_error($provider->get_fresh_report('last_7_days')), 'Fresh-only consumers reject partial reports');
            $reset();
            $mode = 'zero';
            $report = $provider->get_report('last_7_days');
            self::check(0 === $report['totals']['sessions'] && 'fresh' === $report['freshness']['state'], 'Real zero reports are valid successes');
            self::presentation($provider, $settings, $calls);
            $mode = 'forbidden';
            (new CLIREDAS_GA4_Client($settings))->run_report('properties/123', array('dateRanges' => array($provider->report_context('last_7_days')['dates'])));
            self::check('unavailable' === $provider->cached_response($provider->report_context('last_7_days'))['source'], 'Access loss detected outside report fetching also invalidates last-good');

            $reset();
            $mode = 'success';
            $before = $calls;
            add_filter('cliredas_enable_cache', '__return_false');
            self::check(! is_wp_error($provider->get_fresh_report('last_7_days')), 'Fresh-only retrieval also supports cache-disabled real reports');
            remove_filter('cliredas_enable_cache', '__return_false');
            self::check(5 === $calls - $before && array() === CLIREDAS_Report_Cache::read()['entries'] && array('version' => 2) === $db->state['cron'], 'Cache-disabled mode has no retained reports or schedules');

            $reset();
            $report = $provider->get_report('last_7_days');
            $context = $provider->report_context('last_7_days');
            $id = $context['id'];
            $db->state['cliredas_report_cache']['entries'][$id]['good']['fresh_until'] = time() - 1;
            $schedule_failure = static function ($pre, $event) {
                return CLIREDAS_Report_Cache::REFRESH_HOOK === $event->hook ? new WP_Error('fixture_schedule_failed') : $pre;
            };
            add_filter('pre_schedule_event', $schedule_failure, 10, 2);
            $report = $provider->get_report('last_7_days');
            remove_filter('pre_schedule_event', $schedule_failure, 10);
            self::check('stale' === $report['freshness']['state'] && 'schedule_failed' === $report['freshness']['error_code'], 'Scheduling failure is actionable and retains real data');
            $good = CLIREDAS_Report_Cache::entry($context)['good'];
            $db->fail = true;
            self::check(false === CLIREDAS_Report_Cache::clear(), 'Failed invalidation reports failure');
            self::check(CLIREDAS_Report_Cache::entry($context)['good'] === $good, 'Failed writes preserve last-good');
            $db->fail = false;

            $old = $settings->get_settings();
            $changed = $old;
            $changed['ga4_access_token'] = 'rotated-access';
            $generation = CLIREDAS_Report_Cache::read()['generation'];
            CLIREDAS_Report_Cache::settings_changed($old, $changed);
            self::check(CLIREDAS_Report_Cache::read()['generation'] === $generation, 'Access-token renewal preserves report generation');
            $crypto = new CLIREDAS_Credential_Crypto();
            $changed['ga4_client_secret'] = $crypto->encrypt($old['ga4_client_secret'], 'ga4_client_secret');
            self::check(CLIREDAS_Report_Cache::connection_identity($old) === CLIREDAS_Report_Cache::connection_identity($changed), 'Equivalent encryption migration preserves identity');
            foreach (array('ga4_property_id', 'ga4_client_secret', 'ga4_refresh_token', 'allow_editors', 'ga4_connected') as $key) {
                $changed = $old;
                $changed[$key] = 'allow_editors' === $key ? 1 : ('ga4_connected' === $key ? 0 : 'changed');
                $generation = CLIREDAS_Report_Cache::read()['generation'];
                CLIREDAS_Report_Cache::settings_changed($old, $changed);
                self::check(CLIREDAS_Report_Cache::read()['generation'] !== $generation, 'Connection/access change invalidates: ' . $key);
            }

            $reset();
            $base = $provider->report_context('last_7_days');
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulate site identity without switching the live database.
            $blog_id = 99;
            self::check($base['id'] !== $provider->report_context('last_7_days')['id'], 'Site identities isolate cache contexts');
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore fixture site.
            $blog_id = $original_blog;
            $db->state['timezone_string'] = 'UTC';
            self::check($base['id'] !== $provider->report_context('last_7_days')['id'], 'Timezone changes isolate reports');
            foreach (array('this_month', 'last_month', 'last_7_days', 'last_30_days', 'last_90_days') as $range) {
                $context = $provider->report_context($range);
                self::check((bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $context['dates']['startDate']), 'Preset resolves exact dates: ' . $range);
            }
            $date_method = new ReflectionMethod($provider, 'get_previous_date_range');
            $date_method->setAccessible(true);
            $dates = $date_method->invoke($provider, 'this_month', new DateTimeImmutable('2024-03-31', wp_timezone()));
            self::check('2024-02-29' === $dates['endDate'], 'Comparison clamps correctly in leap year');
            $absolute = new ReflectionMethod($provider, 'absolute_date');
            $absolute->setAccessible(true);
            self::check('2024-03-28' === $absolute->invoke($provider, '6daysAgo', new DateTimeImmutable('2024-04-03', new DateTimeZone('Europe/Chisinau'))), 'Relative days cross DST using calendar dates');

            for ($i = 0; $i < 25; ++$i) {
                $context = CLIREDAS_Report_Cache::context('last_7_days', 'properties/' . $i, $base['dates'], $base['comparison'], 'fixture');
                self::check(CLIREDAS_Report_Cache::ensure($context), 'Bounded context insertion');
            }
            self::check(20 === count(CLIREDAS_Report_Cache::read()['entries']), 'Retained contexts are bounded at twenty');
            foreach ($db->state['cliredas_report_cache']['entries'] as &$entry) {
                $entry['created_at'] = time() - DAY_IN_SECONDS - 1;
            }
            unset($entry);
            CLIREDAS_Report_Cache::cleanup();
            self::check(array() === CLIREDAS_Report_Cache::read()['entries'], 'Daily cleanup removes expired inactive contexts');
            CLIREDAS_Plugin::deactivate();
            self::check(array() === CLIREDAS_Report_Cache::read()['entries'] && ! wp_next_scheduled(CLIREDAS_Report_Cache::CLEANUP_HOOK), 'Deactivation clears reports and background work');
            WP_CLI::success('Report cache regressions passed (' . self::$assertions . ' assertions).');
        } finally {
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore actual database after fixtures.
            $wpdb = $original_db;
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore actual site after fixtures.
            $blog_id = $original_blog;
            remove_filter('pre_option', $option_filter, 999);
            remove_filter('pre_wp_load_alloptions', $alloptions_filter, 999);
            remove_filter('pre_http_request', $http_filter, 999);
            remove_filter('cliredas_enable_cache', '__return_false');
            wp_cache_flush();
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the original request cache after clearing only the fixture instance.
            $wp_object_cache = $original_cache;
            wp_set_current_user($original_user);
        }
    }

    /** Check protected status polling, snapshot reuse and CSV metadata. */
    private static function presentation($provider, $settings, &$calls)
    {
        $page = new CLIREDAS_Dashboard_Page($settings, $provider);
        $context = $provider->report_context('last_7_days');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve request fixtures; the real handler performs nonce verification.
        $request = $_REQUEST;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserve request fixtures; the real handler performs nonce verification.
        $post = $_POST;
        $die = static function () {
            return static function ($message = '') {
                if (-1 === $message) {
                    echo '-1';
                }
                throw new RuntimeException('fixture-response');
            };
        };
        $deny = static function () {
            return array('do_not_allow');
        };
        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', $die);
        $capture = static function () use ($page) {
            ob_start();
            try {
                $page->ajax_report_status();
            } catch (RuntimeException $error) {
                if ('fixture-response' !== $error->getMessage()) {
                    ob_end_clean();
                    throw $error;
                }
            }
            return ob_get_clean();
        };
        try {
            $_POST = array('cliredas_range' => 'last_7_days', 'cliredas_context' => $context['id']);
            $_REQUEST = array('cliredas_nonce' => wp_create_nonce('cliredas_dashboard'));
            $before = $calls;
            $result = json_decode($capture(), true);
            self::check(! empty($result['success']) && $calls === $before, 'Protected status returns cached data without Google requests');
            add_filter('map_meta_cap', $deny, 999);
            $result = json_decode($capture(), true);
            remove_filter('map_meta_cap', $deny, 999);
            self::check(empty($result['success']) && false !== strpos($result['data']['message'], 'Permission'), 'Status rejects unauthorized viewers');
            $_REQUEST['cliredas_nonce'] = 'invalid';
            self::check('-1' === $capture() && $calls === $before, 'Status rejects invalid nonces before reading data');
            $_REQUEST['cliredas_nonce'] = wp_create_nonce('cliredas_dashboard');
            $_POST['cliredas_context'] = str_repeat('0', 64);
            self::check(empty(json_decode($capture(), true)['success']), 'Status rejects obsolete context identifiers');

            $initial = new ReflectionMethod($page, 'initial_report');
            $initial->setAccessible(true);
            $count = 0;
            $counter = static function ($report) use (&$count) {
                ++$count;
                return $report;
            };
            add_filter('cliredas_report', $counter);
            $first = $initial->invoke($page, 'last_7_days');
            $second = $initial->invoke($page, 'last_7_days');
            remove_filter('cliredas_report', $counter);
            self::check($first === $second && 1 === $count, 'Initial HTML and localized data reuse one snapshot');
            $writer = new ReflectionMethod($page, 'write_csv_report');
            $writer->setAccessible(true);
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory export fixture.
            $stream = fopen('php://temp', 'w+');
            $first['top_pages'] = array(array('title' => '=SUM(1,2)', 'url' => '/fixture', 'sessions' => 1));
            $writer->invoke($page, $stream, $first, 'Fixture range');
            rewind($stream);
            $csv = stream_get_contents($stream);
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory export fixture.
            fclose($stream);
            self::check(false !== strpos($csv, 'Data freshness') && false !== strpos($csv, $context['dates']['startDate']), 'CSV includes freshness and resolved dates');
            self::check(false !== strpos($csv, "'=SUM"), 'CSV still neutralizes spreadsheet formulas');
        } finally {
            $_REQUEST = $request;
            $_POST = $post;
            remove_filter('map_meta_cap', $deny, 999);
            remove_filter('wp_doing_ajax', '__return_true');
            remove_filter('wp_die_ajax_handler', $die);
        }
    }
}

CLIREDAS_Cache_Tests::run();
