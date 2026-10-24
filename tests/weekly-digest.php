<?php
/**
 * Isolated digest regressions. Run with: wp eval-file tests/weekly-digest.php
 *
 * @package ClientReportingDashboard
 */

(defined('WP_CLI') && WP_CLI) || exit;
require_once __DIR__ . '/fixtures/class-cliredas-options-fixture.php';

/** Exercise real WordPress settings, scheduling, and mail APIs with virtual state. */
final class CLIREDAS_Digest_Tests
{
    /** Passed assertions.
     *
     * @var int
     */
    private static $assertions = 0;

    /** Fail without exposing mail or credentials.
     *
     * @throws RuntimeException On failed assertion.
     */
    private static function check($condition, $label)
    {
        if (! $condition) {
            throw new RuntimeException(esc_html($label));
        }
        ++self::$assertions;
    }

    /** Complete synthetic report; production never uses this loader. */
    public static function report($now)
    {
        $today = (new DateTimeImmutable('@' . $now))->setTimezone(wp_timezone())->setTime(0, 0);
        $totals = array('sessions' => 100, 'users' => 75, 'pageviews' => 150, 'avg_engagement_seconds' => 120);
        $pages = array();
        for ($index = 0; $index < 7; ++$index) {
            $pages[] = array('title' => 'Fixture page ' . $index, 'url' => '/fixture-' . $index, 'sessions' => 20, 'views' => 30);
        }
        return array(
            'source' => 'ga4', 'range' => array('startDate' => $today->modify('-6 days')->format('Y-m-d'), 'endDate' => $today->format('Y-m-d')),
            'totals' => $totals, 'comparison' => array('totals' => $totals, 'range' => array('startDate' => $today->modify('-13 days')->format('Y-m-d'), 'endDate' => $today->modify('-7 days')->format('Y-m-d'))),
            'timeseries' => array(), 'top_pages' => $pages, 'devices' => array('desktop' => 60, 'mobile' => 40, 'tablet' => 0),
            'traffic_sources' => array('organic_search' => 80, 'direct' => 20),
            'freshness' => array('state' => 'fresh', 'last_success_at' => $now, 'fresh_until' => $now + 900),
        );
    }

    /** Run fixtures without sending mail, contacting Google or writing the site. */
    public static function run()
    {
        global $wpdb, $wp_object_cache, $blog_id;
        if (wp_using_ext_object_cache()) {
            WP_CLI::error('Use a disposable installation without a persistent object-cache drop-in.');
        }
        CLIREDAS_Plugin::instance()->bootstrap_admin();
        foreach (array('auth', 'secure_auth', 'nonce', 'logged_in') as $scheme) {
            wp_salt($scheme);
        }
        $original_db = $wpdb;
        $original_cache = $wp_object_cache;
        $original_blog = $blog_id;
        $original_user = get_current_user_id();
        $admins = get_users(array('role' => 'administrator', 'fields' => 'ID', 'number' => 1));
        self::check(! empty($admins), 'An administrator fixture actor is required');
        wp_set_current_user((int) $admins[0]);
        $alloptions = wp_load_alloptions();
        $db = new CLIREDAS_Cache_Fixture_DB();
        $db->prefix = $original_db->get_blog_prefix();
        $now = (new DateTimeImmutable('2026-10-24 12:00:00', new DateTimeZone('Europe/Chisinau')))->getTimestamp();
        $mail = array();
        $mail_result = true;
        $during_mail = null;
        $during_report = null;
        $mode = 'fresh';
        $report_calls = 0;
        $clock = static function () use (&$now) {
            return $now;
        };
        $loader = static function () use (&$now, &$mode, &$during_report, &$report_calls) {
            ++$report_calls;
            if (is_callable($during_report)) {
                $callback = $during_report;
                $during_report = null;
                $callback();
            }
            if ('error' === $mode) {
                return new WP_Error('fixture_upstream', 'fixture-sensitive-response');
            }
            $report = self::report($now);
            if ('mock' === $mode) {
                $report['source'] = 'mock';
            } elseif ('partial' === $mode) {
                $report['traffic_sources_unavailable'] = true;
            } elseif ('stale' === $mode) {
                $report['freshness']['state'] = 'stale';
                $report['freshness']['fresh_until'] = $now - 1;
            } elseif ('malformed' === $mode) {
                unset($report['totals']['sessions']);
            }
            return $report;
        };
        $service = new CLIREDAS_Weekly_Digest($clock, $loader);
        $reset = static function () use ($db, &$now, &$mail, &$mode, &$during_report, &$during_mail, &$mail_result, &$report_calls) {
            $db->fail = false;
            $db->conflict = null;
            $db->state = array(
                'cliredas_digest_settings' => array('enabled' => 0, 'recipient' => 'digest-fixture@example.test'),
                'cliredas_digest_state' => array('generation' => 'fixture', 'fingerprint' => '', 'next_due' => 0, 'owner' => '', 'lease_until' => 0,
                    'schedule_error' => false, 'retry_at' => 0, 'last_weekly' => array(), 'last_test' => array(),),
                'cliredas_settings' => array('ga4_connected' => 1, 'ga4_property_id' => 'properties/123', 'ga4_client_id' => 'fixture-client', 'ga4_client_secret' => 'fixture-secret', 'ga4_refresh_token' => 'fixture-refresh'),
                'cliredas_audit_log' => array(), 'cron' => array('version' => 2), 'timezone_string' => 'Europe/Chisinau', 'gmt_offset' => 3,
            );
            $now = (new DateTimeImmutable('2026-10-24 12:00:00', new DateTimeZone('Europe/Chisinau')))->getTimestamp();
            $mail = array();
            $mail_result = true;
            $during_report = null;
            $during_mail = null;
            $report_calls = 0;
            $mode = 'fresh';
            wp_cache_flush();
        };
        $option_filter = static function ($pre, $key) use ($db) {
            return array_key_exists($key, $db->state) ? $db->state[$key] : $pre;
        };
        $alloptions_filter = static function () use ($alloptions) {
            return $alloptions;
        };
        $mail_filter = static function ($pre, $args) use (&$mail, &$mail_result, &$during_mail) {
            $mail[] = $args;
            if (is_callable($during_mail)) {
                $callback = $during_mail;
                $during_mail = null;
                $callback();
            }
            return $mail_result;
        };
        $http_filter = static function () {
            throw new RuntimeException('Digest fixtures must never contact Google');
        };
        add_filter('pre_option', $option_filter, 999, 2);
        add_filter('pre_wp_load_alloptions', $alloptions_filter, 999);
        add_filter('pre_wp_mail', $mail_filter, 999, 2);
        add_filter('pre_http_request', $http_filter, 999);
        // phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolated objects restored in finally.
        $wp_object_cache = new WP_Object_Cache();
        $wpdb = $db;
        // phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
        $reset();
        try {
            $service->reconcile();
            self::check(0 === $service->settings()['enabled'] && ! wp_next_scheduled(CLIREDAS_Weekly_Digest::HOOK), 'Upgrade defaults leave delivery off and unscheduled');
            self::check(empty($mail) && 0 === $report_calls, 'Reconciliation never sends mail or fetches reports');
            $credentials = $db->state['cliredas_settings'];
            unset($db->state[CLIREDAS_Weekly_Digest::SETTINGS], $db->state[CLIREDAS_Weekly_Digest::STATE]);
            wp_cache_flush();
            $service->reconcile();
            self::check(0 === $service->settings()['enabled'] && empty($mail) && $db->state['cliredas_settings'] === $credentials, 'Upgrade creates disabled defaults without changing credentials or sending email');
            foreach (array(CLIREDAS_Weekly_Digest::SETTINGS, CLIREDAS_Weekly_Digest::STATE) as $option) {
                self::check(in_array($db->autoload[$option], array('no', 'off'), true), 'Digest storage is not autoloaded');
            }
            $reset();
            $service->reconcile();
            foreach (array('one@example.test,two@example.test', "one@example.test\r\nBcc: two@example.test", 'Name <one@example.test>', '', array()) as $invalid) {
                self::check(! CLIREDAS_Weekly_Digest::valid_recipient($invalid), 'Invalid/multiple/header-injected recipient rejected');
            }
            $before = $service->settings();
            self::check($service->sanitize_settings(array('enabled' => 'yes', 'recipient' => 'one@example.test')) === $before, 'Invalid consent preserves settings');
            self::check($service->sanitize_settings(array('enabled' => '1', 'recipient' => 'invalid')) === $before, 'Invalid address preserves settings');
            self::check(array('enabled' => 1, 'recipient' => 'one@example.test') === $service->sanitize_settings(array('enabled' => '1', 'recipient' => 'one@example.test')), 'One valid address and explicit consent accepted');

            $controller = new CLIREDAS_Digest_Admin($service);
            $controller->register_settings();
            update_option(CLIREDAS_Weekly_Digest::SETTINGS, array('enabled' => 0, 'recipient' => 'saved@example.test'));
            self::check('saved@example.test' === $service->settings()['recipient'], 'Settings API uses conditional persistence');
            $db->fail = true;
            update_option(CLIREDAS_Weekly_Digest::SETTINGS, array('enabled' => 1, 'recipient' => 'failed@example.test'));
            self::check('saved@example.test' === $service->settings()['recipient'] && ! $service->settings()['enabled'], 'Failed settings write preserves consent and recipient');
            $db->fail = false;
            $db->conflict = static function ($fixture) {
                $fixture->state[CLIREDAS_Weekly_Digest::SETTINGS]['recipient'] = 'concurrent@example.test';
            };
            update_option(CLIREDAS_Weekly_Digest::SETTINGS, array('enabled' => 1, 'recipient' => 'obsolete@example.test'));
            self::check('concurrent@example.test' === $service->settings()['recipient'] && ! $service->settings()['enabled'], 'Concurrent settings change cannot be overwritten by the obsolete save');
            remove_filter('pre_update_option_' . CLIREDAS_Weekly_Digest::SETTINGS, array($controller, 'persist_settings'));
            $reset();
            $service->reconcile();

            $due = $service->next_due();
            self::check('2026-10-26 08:00 +02:00' === wp_date('Y-m-d H:i P', $due), 'Next Monday correctly crosses the Chisinau fall DST transition');
            $spring = (new DateTimeImmutable('2026-03-28 12:00', wp_timezone()))->getTimestamp();
            self::check('2026-03-30 08:00 +03:00' === wp_date('Y-m-d H:i P', $service->next_due($spring)), 'Spring DST retains 08:00 local time');
            $db->state[CLIREDAS_Weekly_Digest::SETTINGS]['enabled'] = 1;
            $service->reconcile();
            $state = $service->state();
            self::check($due === $state['next_due'] && ! empty(wp_next_scheduled(CLIREDAS_Weekly_Digest::HOOK, array($due, $state['generation']))), 'Opt-in schedules one future Monday');
            $cron = $db->state['cron'];
            $service->reconcile();
            self::check($db->state['cron'] === $cron && empty($mail), 'Reconciliation is idempotent and enabling sends nothing');
            wp_unschedule_event($due, CLIREDAS_Weekly_Digest::HOOK, array($due, $state['generation']));
            $service->reconcile();
            self::check(false !== wp_next_scheduled(CLIREDAS_Weekly_Digest::HOOK, array($due, $state['generation'])) && empty($mail), 'Missing-event detection restores the expected event without sending');
            $service->run_weekly($due, $state['generation']);
            self::check(empty($mail), 'Early execution cannot send a weekly digest');
            $now = $due + 300;
            $service->run_weekly($due, $state['generation']);
            self::check(1 === count($mail) && 'digest-fixture@example.test' === $mail[0]['to'], 'Due Monday hands one message to the saved recipient');
            self::check('mail_handoff' === $service->state()['last_weekly']['outcome'], 'Success means mail handoff rather than inbox confirmation');
            self::check($service->state()['next_due'] > $due, 'Next future Monday is queued');
            $service->run_weekly($due, $state['generation']);
            self::check(1 === count($mail), 'Repeated callback cannot send twice');
            self::check(false === strpos($mail[0]['subject'], '[Test]') && false !== strpos($mail[0]['message'], '2026-10-26'), 'Weekly message labels actual inclusive reporting dates');
            self::check(false !== strpos($mail[0]['message'], 'Fixture page 4') && false === strpos($mail[0]['message'], 'Fixture page 5'), 'Only the top five pages are included');
            foreach (array('Sessions', 'Total users', 'Pageviews', 'Average engagement time', 'Devices', 'Traffic sources', 'Open dashboard') as $section) {
                self::check(false !== strpos($mail[0]['message'], $section), 'Message contains required section: ' . $section);
            }
            self::check(empty($mail[0]['attachments']) && in_array('Content-Type: text/plain; charset=UTF-8', $mail[0]['headers'], true), 'Digest is plain text without attachments');

            $reset();
            $service->reconcile();
            $schedule_before = $db->state['cron'];
            self::check(true === $service->send_test(), 'Explicit test is allowed while weekly delivery is disabled');
            self::check(0 === $service->settings()['enabled'] && 0 === $service->state()['next_due'] && $db->state['cron'] === $schedule_before, 'Test never enables or advances scheduling');
            self::check(false !== strpos($mail[0]['subject'], '[Test]'), 'Test subject is explicit');
            self::check(is_wp_error($service->send_test()) && 1 === count($mail), 'Immediate repeated test does not duplicate mail');
            foreach (array('mock', 'partial', 'stale', 'error', 'malformed') as $failure) {
                $reset();
                $service->reconcile();
                $mode = $failure;
                self::check(is_wp_error($service->send_test()) && empty($mail), 'Unsafe report is never emailed: ' . $failure);
                self::check('report_unavailable' === $service->state()['last_test']['outcome'], 'Failure has a safe actionable outcome');
            }

            $reset();
            $service->reconcile();
            $mail_result = false;
            self::check(is_wp_error($service->send_test()) && 'mail_failed' === $service->state()['last_test']['outcome'], 'Transport rejection is not reported as successful delivery');
            $reset();
            $service->reconcile();
            $during_mail = static function () {
                throw new RuntimeException('fixture-sensitive-transport');
            };
            $unknown = $service->send_test();
            self::check(is_wp_error($unknown) && 'cliredas_digest_mail_unknown' === $unknown->get_error_code() && 'mail_unknown' === $service->state()['last_test']['outcome'], 'Interrupted mail handoff remains unknown');
            $service->send_test();
            self::check(1 === count($mail), 'Uncertain immediate test is not automatically repeated');

            $reset();
            $service->reconcile();
            $db->fail = true;
            self::check(is_wp_error($service->send_test()) && empty($mail), 'Storage failure prevents mail handoff');
            $db->fail = false;
            $reset();
            $service->reconcile();
            $during_mail = static function () use ($db) {
                $db->fail = true;
            };
            self::check(true === $service->send_test(), 'Actual mail handoff can succeed even if outcome persistence fails');
            $db->fail = false;
            self::check('mail_unknown' === $service->state()['last_test']['outcome'], 'Failed post-mail persistence preserves the durable unknown marker');
            $service->send_test();
            self::check(1 === count($mail), 'Durable unknown marker and lease prevent an immediate duplicate');

            $reset();
            $service->reconcile();
            $during_report = static function () use ($service) {
                $service->disable();
            };
            self::check(is_wp_error($service->send_test()) && empty($mail), 'Disabling during preparation fences an in-flight send');
            $reset();
            $service->reconcile();
            $during_report = static function () use ($db) {
                $db->state['cliredas_settings']['ga4_property_id'] = 'properties/456';
            };
            self::check(is_wp_error($service->send_test()) && empty($mail), 'Connection change during preparation prevents mail');

            $reset();
            $db->state[CLIREDAS_Weekly_Digest::SETTINGS]['enabled'] = 1;
            $service->reconcile();
            $state = $service->state();
            $now = $state['next_due'] + DAY_IN_SECONDS;
            $service->run_weekly($state['next_due'], $state['generation']);
            self::check(empty($mail) && 0 === $report_calls && 'missed' === $service->state()['last_weekly']['outcome'], 'Missed Monday is skipped without fetching or emailing');
            self::check($service->state()['next_due'] > $now, 'Missed run schedules the next future Monday');

            $reset();
            $db->state[CLIREDAS_Weekly_Digest::SETTINGS]['enabled'] = 1;
            $service->reconcile();
            $state = $service->state();
            $now = $state['next_due'] + 15 * HOUR_IN_SECONDS + 59 * MINUTE_IN_SECONDS;
            $during_report = static function () use (&$now) {
                $now += 120;
            };
            $service->run_weekly($state['next_due'], $state['generation']);
            self::check(empty($mail) && 'context_changed' === $service->state()['last_weekly']['outcome'], 'Preparation crossing Monday midnight cannot send a Tuesday digest');

            $reset();
            $service->reconcile();
            $during_report = static function () use ($db) {
                $db->state[CLIREDAS_Weekly_Digest::SETTINGS]['recipient'] = 'replacement@example.test';
            };
            self::check(is_wp_error($service->send_test()) && empty($mail), 'Recipient change during preparation prevents a handoff to either address');

            $reset();
            $service->reconcile();
            $db->state[CLIREDAS_Weekly_Digest::SETTINGS]['enabled'] = 1;
            $failure_filter = static function ($pre, $event) {
                return CLIREDAS_Weekly_Digest::HOOK === $event->hook ? new WP_Error('fixture_schedule_failure') : $pre;
            };
            add_filter('pre_schedule_event', $failure_filter, 999, 2);
            $service->reconcile();
            remove_filter('pre_schedule_event', $failure_filter, 999);
            self::check($service->state()['schedule_error'] && ! wp_next_scheduled(CLIREDAS_Weekly_Digest::HOOK), 'Scheduling failure is retained for administrator guidance');
            $now += 301;
            $service->reconcile();
            self::check(! $service->state()['schedule_error'] && false !== wp_next_scheduled(CLIREDAS_Weekly_Digest::HOOK, array($service->state()['next_due'], $service->state()['generation'])), 'Scheduling failure recovers after its bounded retry delay');
            $old = $service->state();
            $db->state['timezone_string'] = 'America/New_York';
            $service->reconcile();
            self::check($old['generation'] !== $service->state()['generation'] && '08:00' === wp_date('H:i', $service->state()['next_due']), 'Timezone change replaces generation and retains local 08:00');
            self::check(false === wp_next_scheduled(CLIREDAS_Weekly_Digest::HOOK, array($old['next_due'], $old['generation'])), 'Old timezone event is removed');
            $pending = $service->state();
            self::check(true === $service->disable() && false === wp_next_scheduled(CLIREDAS_Weekly_Digest::HOOK, array($pending['next_due'], $pending['generation'])), 'Disable immediately removes future work');

            $reset();
            $service->reconcile();
            $state = $service->state();
            $db->state[CLIREDAS_Weekly_Digest::STATE]['owner'] = 'dead-worker';
            $db->state[CLIREDAS_Weekly_Digest::STATE]['lease_until'] = $now - 1;
            $db->state[CLIREDAS_Weekly_Digest::STATE]['last_test'] = array('timestamp' => $now - 700, 'due' => 0, 'outcome' => 'preparing');
            $service->reconcile();
            self::check('' === $service->state()['owner'] && 'interrupted' === $service->state()['last_test']['outcome'], 'Expired preparation lease recovers without sending');
            $db->state[CLIREDAS_Weekly_Digest::STATE]['owner'] = 'concurrent-worker';
            $db->state[CLIREDAS_Weekly_Digest::STATE]['lease_until'] = $now + 120;
            self::check(is_wp_error($service->send_test()) && empty($mail), 'Concurrent active worker blocks another send');
            $db->state[CLIREDAS_Weekly_Digest::STATE]['lease_until'] = $now - 1;
            self::check(true === $service->send_test(), 'Explicit retry works after an abandoned preparation lease expires');

            $message = self::report($now);
            $message['top_pages'][0]['title'] = "<script>unsafe()</script>Safe title\r\nInjected line";
            $text = CLIREDAS_Digest_Message::build($message, true);
            self::check(false === strpos($text['subject'], "\n") && false === strpos($text['body'], '<script>') && false === strpos($text['body'], "\r\nInjected"), 'Message normalizes external values to safe plain-text lines');
            self::handlers($service, $mail);
            CLIREDAS_Weekly_Digest::deactivate();
            self::check(0 === $service->state()['next_due'] && ! wp_next_scheduled(CLIREDAS_Weekly_Digest::HOOK), 'Deactivation invalidates pending work');
            foreach (CLIREDAS_Audit_Log::get_entries() as $entry) {
                self::check(array('event', 'timestamp', 'actor_id', 'outcome') === array_keys($entry), 'Digest audit entries contain only bounded safe fields');
            }
            WP_CLI::success('Weekly digest regressions passed (' . self::$assertions . ' assertions).');
        } finally {
            // phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the actual request objects after fixtures.
            $wpdb = $original_db;
            $blog_id = $original_blog;
            $wp_object_cache = $original_cache;
            // phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
            remove_filter('pre_option', $option_filter, 999);
            remove_filter('pre_wp_load_alloptions', $alloptions_filter, 999);
            remove_filter('pre_wp_mail', $mail_filter, 999);
            remove_filter('pre_http_request', $http_filter, 999);
            wp_set_current_user($original_user);
        }
    }

    /** Test nonce-first and capability checks without letting redirects exit. */
    private static function handlers($service, &$mail)
    {
        // phpcs:disable WordPress.Security.NonceVerification -- Save and restore inputs for the real protected handlers.
        $original_request = $_REQUEST;
        $original_post = $_POST;
        // phpcs:enable WordPress.Security.NonceVerification
        $controller = new CLIREDAS_Digest_Admin($service);
        $die = static function () {
            return static function () {
                throw new RuntimeException('fixture-denied');
            };
        };
        $deny = static function () {
            return array('do_not_allow');
        };
        $redirect = static function () {
            throw new RuntimeException('fixture-redirect');
        };
        add_filter('wp_die_handler', $die);
        add_filter('wp_redirect', $redirect, -999);
        try {
            foreach (array('test', 'disable') as $action) {
                // phpcs:ignore WordPress.Security.NonceVerification -- Handler fixture; handler verifies intent first.
                $_REQUEST = array('_wpnonce' => 'invalid');
                $before = count($mail);
                try {
                    $method = 'handle_' . $action;
                    $controller->$method();
                    self::check(false, 'Invalid nonce must stop');
                } catch (RuntimeException $error) {
                    self::check('fixture-denied' === $error->getMessage() && count($mail) === $before, 'Invalid nonce prevents action: ' . $action);
                }
                // phpcs:ignore WordPress.Security.NonceVerification -- Handler fixture.
                $_REQUEST = array('_wpnonce' => wp_create_nonce('cliredas_digest_' . $action));
                add_filter('map_meta_cap', $deny, 999);
                try {
                    $controller->$method();
                    self::check(false, 'Missing capability must stop');
                } catch (RuntimeException $error) {
                    self::check('fixture-denied' === $error->getMessage() && count($mail) === $before, 'Capability is required independently of nonce: ' . $action);
                } finally {
                    remove_filter('map_meta_cap', $deny, 999);
                }
            }
        } finally {
            // phpcs:disable WordPress.Security.NonceVerification -- Restore the request fixture.
            $_REQUEST = $original_request;
            $_POST = $original_post;
            // phpcs:enable WordPress.Security.NonceVerification
            remove_filter('wp_die_handler', $die);
            remove_filter('wp_redirect', $redirect, -999);
            remove_filter('map_meta_cap', $deny, 999);
        }
    }
}

CLIREDAS_Digest_Tests::run();
