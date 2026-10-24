<?php
/**
 * Opt-in weekly owner digest and atomic delivery coordination.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/** Schedule local-calendar events and hand complete real reports to wp_mail(). */
final class CLIREDAS_Weekly_Digest
{
    const SETTINGS = 'cliredas_digest_settings';
    const STATE = 'cliredas_digest_state';
    const HOOK = 'cliredas_send_weekly_digest';
    const LEASE = 600;

    /** Clock injection is used only by isolated fixtures.
     *
     * @var callable|null
     */
    private $clock;
    /** Report injection is used only by isolated fixtures.
     *
     * @var callable|null
     */
    private $loader;

    /** Construct without registering hooks or sending mail. */
    public function __construct($clock = null, $loader = null)
    {
        $this->clock = $clock;
        $this->loader = $loader;
    }

    /** Register shared runtime hooks, including cron-only requests. */
    public static function register()
    {
        $service = new self();
        add_action('init', array($service, 'reconcile'), 15);
        add_action(self::HOOK, array($service, 'run_weekly'), 10, 2);
        add_action('update_option_' . self::SETTINGS, array($service, 'reconcile'));
        add_action('update_option_timezone_string', array($service, 'reconcile'));
        add_action('update_option_gmt_offset', array($service, 'reconcile'));
    }

    /** Current epoch, replaceable without changing the system clock. */
    private function now()
    {
        return $this->clock ? (int) call_user_func($this->clock) : time();
    }

    /** Validate exactly one bare address, rejecting header injection. */
    public static function valid_recipient($value)
    {
        return is_string($value) && ! preg_match('/[\r\n,;]/', $value) && trim($value) === sanitize_email(trim($value)) && (bool) is_email(trim($value));
    }

    /** Read safe settings; first-install defaults never enable delivery. */
    public function settings()
    {
        $stored = get_option(self::SETTINGS, false);
        if (! is_array($stored)) {
            return array('enabled' => 0, 'recipient' => sanitize_email((string) get_option('admin_email', '')));
        }
        $recipient = isset($stored['recipient']) && self::valid_recipient($stored['recipient']) ? trim($stored['recipient']) : '';
        return array('enabled' => ! empty($stored['enabled']) && '' !== $recipient ? 1 : 0, 'recipient' => $recipient);
    }

    /** Settings API sanitizer: reject invalid submissions without partial changes. */
    public function sanitize_settings($input)
    {
        $old = $this->settings();
        if (! is_array($input) || (isset($input['enabled']) && ! in_array($input['enabled'], array(0, 1, '0', '1'), true))
            || ! isset($input['recipient']) || ! self::valid_recipient($input['recipient'])) {
            add_settings_error(self::SETTINGS, 'cliredas_digest_invalid', __('Enter one valid recipient email address. Existing digest settings were preserved.', 'cliredas-analytics-dashboard'));
            return $old;
        }
        return array('enabled' => ! empty($input['enabled']) ? 1 : 0, 'recipient' => trim($input['recipient']));
    }

    /** Read the latest non-autoloaded state. */
    public function state($create = false)
    {
        wp_cache_delete(self::STATE, 'options');
        wp_cache_delete('notoptions', 'options');
        $state = get_option(self::STATE, false);
        if (is_array($state) && isset($state['generation'], $state['fingerprint'], $state['next_due'], $state['owner'], $state['lease_until'])) {
            return $state;
        }
        if (! $create) {
            return false;
        }
        $state = array('generation' => wp_generate_uuid4(), 'fingerprint' => '', 'next_due' => 0,
            'owner' => '', 'lease_until' => 0, 'schedule_error' => false, 'retry_at' => 0,
            'last_weekly' => array(), 'last_test' => array(),
        );
        return add_option(self::STATE, $state, '', false) ? $state : $this->state(false);
    }

    /** Apply an atomic state transition with bounded conflict retries. */
    private function change($callback)
    {
        for ($attempt = 0; $attempt < 8; ++$attempt) {
            $old = $this->state(true);
            if (! $old) {
                return false;
            }
            $next = $callback($old);
            if (false === $next) {
                return false;
            }
            if (CLIREDAS_Local_Store::compare_and_swap(self::STATE, $old, $next)) {
                return true;
            }
        }
        return false;
    }

    /** Opaque consent/calendar fingerprint; no address is kept in run history. */
    private function fingerprint()
    {
        return hash_hmac('sha256', wp_json_encode(array($this->settings(), wp_timezone_string(), get_current_blog_id())), wp_salt('auth'));
    }

    /** Next future local Monday at 08:00, correctly accounting for DST. */
    public function next_due($now = null)
    {
        $now = null === $now ? $this->now() : (int) $now;
        $local = (new DateTimeImmutable('@' . $now))->setTimezone(wp_timezone());
        $target = $local->modify('monday this week')->setTime(8, 0, 0);
        return ($target->getTimestamp() <= $now ? $target->modify('+1 week') : $target)->getTimestamp();
    }

    /** True only on the nominal Monday, never before its due time. */
    private function eligible_day($due)
    {
        $scheduled = (new DateTimeImmutable('@' . $due))->setTimezone(wp_timezone());
        $current = (new DateTimeImmutable('@' . $this->now()))->setTimezone(wp_timezone());
        return $this->now() >= $due && '1' === $scheduled->format('N') && $scheduled->format('Y-m-d') === $current->format('Y-m-d');
    }

    /** Create disabled defaults and reconcile one event without fetching analytics. */
    public function reconcile()
    {
        if (false === get_option(self::SETTINGS, false)) {
            add_option(self::SETTINGS, $this->settings(), '', false);
        }
        $config = $this->settings();
        $fingerprint = $this->fingerprint();
        $now = $this->now();
        $this->change(function ($state) use ($config, $fingerprint, $now) {
            if ($fingerprint !== $state['fingerprint']) {
                foreach (array('last_weekly', 'last_test') as $key) {
                    if (isset($state[$key]['outcome']) && 'preparing' === $state[$key]['outcome']) {
                        $state[$key]['outcome'] = 'context_changed';
                    }
                }
                $state['fingerprint'] = $fingerprint;
                $state['generation'] = wp_generate_uuid4();
                $state['next_due'] = $config['enabled'] ? $this->next_due($now) : 0;
                $state['owner'] = '';
                $state['lease_until'] = 0;
                $state['schedule_error'] = false;
                $state['retry_at'] = 0;
            }
            if ($state['lease_until'] <= $now && '' !== $state['owner']) {
                foreach (array('last_weekly', 'last_test') as $key) {
                    if (isset($state[$key]['outcome']) && 'preparing' === $state[$key]['outcome']) {
                        $state[$key]['outcome'] = 'interrupted';
                    }
                }
                $state['owner'] = '';
                $state['lease_until'] = 0;
            }
            return $state;
        });
        $this->schedule();
    }

    /** Remove only obsolete event tuples; never erase another generation's event. */
    private function schedule()
    {
        $cron = get_option('cron', array());
        foreach (is_array($cron) ? $cron : array() as $timestamp => $hooks) {
            if (! is_array($hooks) || ! isset($hooks[self::HOOK])) {
                continue;
            }
            foreach ($hooks[self::HOOK] as $event) {
                $current = $this->state();
                $enabled = $this->settings()['enabled'];
                $args = isset($event['args']) && is_array($event['args']) ? $event['args'] : array();
                if (! $enabled || ! $current || $current['fingerprint'] !== $this->fingerprint()
                    || array($current['next_due'], $current['generation']) !== $args || (int) $timestamp !== $current['next_due']) {
                    wp_unschedule_event((int) $timestamp, self::HOOK, $args);
                }
            }
        }
        $state = $this->state();
        if (! $state || ! $this->settings()['enabled'] || $state['fingerprint'] !== $this->fingerprint() || ! $state['next_due'] || $state['retry_at'] > $this->now()) {
            return;
        }
        $args = array($state['next_due'], $state['generation']);
        $scheduled = wp_next_scheduled(self::HOOK, $args);
        if (! $scheduled) {
            $scheduled = wp_schedule_single_event($state['next_due'], self::HOOK, $args, true);
        }
        $failed = ! $scheduled || is_wp_error($scheduled);
        $this->change(function ($latest) use ($state, $failed) {
            if ($latest['generation'] !== $state['generation'] || $latest['next_due'] !== $state['next_due']) {
                return false;
            }
            $latest['schedule_error'] = $failed;
            $latest['retry_at'] = $failed ? $this->now() + 300 : 0;
            return $latest;
        });
        $latest = $this->state();
        if (! $latest || $latest['generation'] !== $state['generation'] || $latest['next_due'] !== $state['next_due'] || ! $this->settings()['enabled']) {
            wp_clear_scheduled_hook(self::HOOK, $args);
        }
    }

    /** Atomically disable delivery without overwriting a concurrently saved address. */
    public function disable()
    {
        for ($attempt = 0; $attempt < 8; ++$attempt) {
            $old = get_option(self::SETTINGS, false);
            if (! is_array($old)) {
                return new WP_Error('cliredas_digest_storage');
            }
            $updated = $old;
            $updated['enabled'] = 0;
            if (CLIREDAS_Local_Store::compare_and_swap(self::SETTINGS, $old, $updated)) {
                // Explicit disable also cancels an in-flight one-off test when already off.
                self::deactivate();
                $this->reconcile();
                return true;
            }
        }
        return new WP_Error('cliredas_digest_storage');
    }

    /** Invalidate active leases on deactivation; retain the administrator's opt-in. */
    public static function deactivate()
    {
        $service = new self();
        if ($service->state()) {
            $service->change(static function ($state) {
                foreach (array('last_weekly', 'last_test') as $key) {
                    if (isset($state[$key]['outcome']) && 'preparing' === $state[$key]['outcome']) {
                        $state[$key]['outcome'] = 'context_changed';
                    }
                }
                $state['generation'] = wp_generate_uuid4();
                $state['fingerprint'] = '';
                $state['next_due'] = 0;
                $state['owner'] = '';
                $state['lease_until'] = 0;
                return $state;
            });
        }
        wp_unschedule_hook(self::HOOK);
    }

    /** Refuse invalid, early, or duplicate callbacks before claiming any work. */
    public function run_weekly($due, $generation)
    {
        $due = is_numeric($due) ? (int) $due : 0;
        if ($due <= 0 || ! is_string($generation)) {
            return;
        }
        $this->reconcile();
        $state = $this->state();
        if (! $state || $due !== $state['next_due'] || $generation !== $state['generation'] || ! $this->settings()['enabled'] || $this->now() < $due) {
            return;
        }
        $this->deliver(false, $due, $generation);
    }

    /** Explicit one-off send; it never enables or advances the weekly schedule. */
    public function send_test()
    {
        if (! current_user_can('manage_options')) {
            return new WP_Error('cliredas_digest_permission');
        }
        $this->reconcile();
        $state = $this->state();
        return $state ? $this->deliver(true, 0, $state['generation']) : new WP_Error('cliredas_digest_storage');
    }

    /** Site/connection snapshot for a final pre-mail authorization recheck. */
    private function connection()
    {
        return hash('sha256', wp_json_encode(array(get_current_blog_id(), home_url('/'),
            CLIREDAS_Report_Cache::connection_identity(CLIREDAS_Credential_Store::instance()->read()),
        )));
    }

    /** Complete fresh data is required even for an explicitly requested test. */
    private function report_ready($report)
    {
        if (! is_array($report) || ! isset($report['source'], $report['freshness']['state'], $report['freshness']['fresh_until'], $report['freshness']['last_success_at'])
            || ! in_array($report['source'], array('ga4', 'ga4_cache'), true) || 'fresh' !== $report['freshness']['state']
            || $report['freshness']['fresh_until'] <= $this->now() || $report['freshness']['last_success_at'] > $this->now()
            || $report['freshness']['last_success_at'] <= 0 || ! empty($report['error_message']) || ! empty($report['traffic_sources_unavailable'])
            || ! empty($report['freshness']['error_code']) || ! isset($report['range']['startDate'], $report['range']['endDate'], $report['comparison']['range']['startDate'], $report['comparison']['range']['endDate'])) {
            return false;
        }
        foreach (array('totals', 'comparison', 'top_pages', 'devices', 'traffic_sources') as $key) {
            if (! isset($report[$key]) || ! is_array($report[$key])) {
                return false;
            }
        }
        foreach (array('sessions', 'users', 'pageviews', 'avg_engagement_seconds') as $key) {
            foreach (array($report['totals'], isset($report['comparison']['totals']) ? $report['comparison']['totals'] : array()) as $values) {
                if (! isset($values[$key]) || ! is_numeric($values[$key]) || ! is_finite((float) $values[$key]) || $values[$key] < 0) {
                    return false;
                }
            }
        }
        return true;
    }

    /** Claim, fetch, fence, and hand off at most once per claimed attempt. */
    private function deliver($test, $due, $generation)
    {
        $config = $this->settings();
        if (! self::valid_recipient($config['recipient'])) {
            return new WP_Error('cliredas_digest_configuration');
        }
        $key = $test ? 'last_test' : 'last_weekly';
        $owner = wp_generate_uuid4();
        $now = $this->now();
        $claim_error = 'cliredas_digest_storage';
        $claimed = $this->change(function ($state) use ($test, $due, $generation, $key, $owner, $now, &$claim_error) {
            $claim_error = 'cliredas_digest_storage';
            if ($state['generation'] !== $generation || $state['fingerprint'] !== $this->fingerprint() || $state['lease_until'] > $now
                || (! $test && ($state['next_due'] !== $due || ! $this->settings()['enabled']))
                || ($test && isset($state['last_test']['timestamp']) && $state['last_test']['timestamp'] > $now - 5)) {
                $claim_error = 'cliredas_digest_busy';
                return false;
            }
            $state['owner'] = $owner;
            $state['lease_until'] = $now + self::LEASE;
            $state[$key] = array('timestamp' => $now, 'due' => $due, 'outcome' => 'preparing');
            if (! $test) {
                $state['next_due'] = $this->next_due();
                $state['retry_at'] = 0;
            }
            return $state;
        });
        if (! $claimed) {
            return new WP_Error($claim_error);
        }
        if (! $test) {
            $this->schedule();
        }
        $outcome = 'report_unavailable';
        try {
            if (! $test && ! $this->eligible_day($due)) {
                $outcome = 'missed';
                return new WP_Error('cliredas_digest_missed');
            }
            $connection = $this->connection();
            $provider = null;
            if ($this->loader) {
                $report = call_user_func($this->loader);
            } else {
                $provider = new CLIREDAS_GA4_Data_Provider(CLIREDAS_Plugin::instance()->reporting_settings());
                $report = $provider->get_fresh_report('last_7_days');
            }
            if (! $this->report_ready($report)) {
                return new WP_Error('cliredas_digest_report_unavailable');
            }
            $message = CLIREDAS_Digest_Message::build($report, $test);
            if ($connection !== $this->connection() || ($provider && ! $provider->is_fresh_report($report, 'last_7_days'))
                || ! $this->report_ready($report) || (! $test && ! $this->eligible_day($due))) {
                $outcome = 'context_changed';
                return new WP_Error('cliredas_digest_context_changed');
            }
            // The durable unknown marker precedes the external side effect. Never retry it automatically.
            $ready = $this->change(function ($state) use ($owner, $generation, $key, $test, $config) {
                if ($state['generation'] !== $generation || $state['owner'] !== $owner || $state['lease_until'] <= $this->now()
                    || $state['fingerprint'] !== $this->fingerprint() || $this->settings() !== $config || (! $test && ! $config['enabled'])) {
                    return false;
                }
                $state[$key]['outcome'] = 'mail_unknown';
                return $state;
            });
            if (! $ready) {
                $outcome = 'storage_failed';
                return new WP_Error('cliredas_digest_storage');
            }
            $outcome = 'mail_unknown';
            $sent = wp_mail($config['recipient'], $message['subject'], $message['body'], array('Content-Type: text/plain; charset=UTF-8'));
            $outcome = $sent ? 'mail_handoff' : 'mail_failed';
            return $sent ? true : new WP_Error('cliredas_digest_mail_failed');
        } catch (Throwable $exception) {
            if ('mail_unknown' !== $outcome) {
                $outcome = 'interrupted';
            }
            return new WP_Error('mail_unknown' === $outcome ? 'cliredas_digest_mail_unknown' : 'cliredas_digest_interrupted');
        } finally {
            $this->change(function ($state) use ($owner, $generation, $key, $outcome) {
                if ($state['generation'] !== $generation || $state['owner'] !== $owner) {
                    return false;
                }
                $state[$key]['outcome'] = $outcome;
                $state['owner'] = '';
                $state['lease_until'] = 0;
                return $state;
            });
            $audit = in_array($outcome, array('mail_handoff', 'mail_failed', 'mail_unknown', 'missed'), true) ? 'digest_' . $outcome : 'digest_skipped';
            CLIREDAS_Audit_Log::record($test ? 'digest_test' : 'digest_weekly', $audit);
        }
    }

    /** Safe, translated status text; no raw upstream or transport error is shown. */
    public static function outcome_label($code)
    {
        $labels = array(
            'preparing' => __('Preparing a fresh report.', 'cliredas-analytics-dashboard'),
            'mail_handoff' => __('Handed to mail transport. Inbox delivery is not confirmed.', 'cliredas-analytics-dashboard'),
            'mail_failed' => __('Mail handoff failed. Check the site mail transport and try a test send.', 'cliredas-analytics-dashboard'),
            'mail_unknown' => __('Mail handoff outcome is unknown. Check the mailbox before requesting another test.', 'cliredas-analytics-dashboard'),
            'missed' => __('Scheduled Monday was missed. The next future Monday is scheduled.', 'cliredas-analytics-dashboard'),
            'report_unavailable' => __('No complete fresh GA4 report was available. Check connection diagnostics; no email was sent.', 'cliredas-analytics-dashboard'),
            'context_changed' => __('Reporting context changed while preparing the digest. No email was sent.', 'cliredas-analytics-dashboard'),
            'storage_failed' => __('Digest state could not be saved. Check database access; no mail handoff was started.', 'cliredas-analytics-dashboard'),
            'interrupted' => __('Preparation was interrupted. Check configuration and run a test.', 'cliredas-analytics-dashboard'),
        );
        return isset($labels[$code]) ? $labels[$code] : __('Not run yet.', 'cliredas-analytics-dashboard');
    }
}
