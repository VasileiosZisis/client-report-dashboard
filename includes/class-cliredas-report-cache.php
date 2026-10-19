<?php
/**
 * Bounded last-good reports and atomic refresh coordination.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/**
 * Keep report publication, leases and invalidation in one conditional record.
 *
 * Successful payloads live in the good section; attempts never overwrite it.
 * The bounded option is not autoloaded and does not depend on transient survival.
 */
final class CLIREDAS_Report_Cache
{
    const OPTION_KEY = 'cliredas_report_cache';
    const REFRESH_HOOK = 'cliredas_refresh_report';
    const CLEANUP_HOOK = 'cliredas_cleanup_reports';
    const RETENTION = DAY_IN_SECONDS;
    const LEASE = 120;
    const COOLDOWN = 300;

    /** Register shared hooks without initializing settings or making requests. */
    public static function register()
    {
        add_action(self::REFRESH_HOOK, array(__CLASS__, 'refresh'), 10, 1);
        add_action(self::CLEANUP_HOOK, array(__CLASS__, 'cleanup'));
        add_action('update_option_cliredas_settings', array(__CLASS__, 'settings_changed'), 10, 2);
        add_action('cliredas_ga4_access_lost', array(__CLASS__, 'invalidate_access'));
    }

    /** Read the latest site-local record, optionally creating it. */
    public static function read($create = true)
    {
        // This record is never autoloaded; retain unrelated autoload caches on status reads.
        wp_cache_delete(self::OPTION_KEY, 'options');
        wp_cache_delete('notoptions', 'options');
        $state = get_option(self::OPTION_KEY, false);
        if (is_array($state) && isset($state['generation'], $state['entries']) && is_string($state['generation']) && is_array($state['entries'])) {
            return $state;
        }
        if (! $create) {
            return false;
        }
        $state = array('generation' => wp_generate_uuid4(), 'entries' => array());
        if (add_option(self::OPTION_KEY, $state, '', false)) {
            self::discard_legacy();
            return $state;
        }
        return self::read(false);
    }

    /** Old preset-only entries cannot establish exact dates or connection identity. */
    private static function discard_legacy()
    {
        $keys = get_option('cliredas_cache_keys', array());
        foreach (is_array($keys) ? $keys : array() as $key) {
            if (is_string($key) && 0 === strpos($key, 'cliredas_report_')) {
                delete_transient(sanitize_key($key));
            }
        }
        foreach (array('last_7_days', 'last_30_days', 'last_90_days', 'this_month', 'last_month') as $range) {
            delete_transient('cliredas_report_' . $range);
            delete_transient('cliredas_report_v2_' . $range);
        }
        delete_option('cliredas_cache_keys');
    }

    /** Apply a short conditional transaction; callbacks must have no side effects. */
    private static function change($callback)
    {
        for ($attempt = 0; $attempt < 8; ++$attempt) {
            $old = self::read();
            if (! is_array($old)) {
                return false;
            }
            $next = $callback($old);
            if (false === $next) {
                return false;
            }
            if (CLIREDAS_Local_Store::compare_and_swap(self::OPTION_KEY, $old, $next)) {
                return true;
            }
        }
        return false;
    }

    /** Build an opaque identity including exact dates and connection generation. */
    public static function context($range, $property, $dates, $comparison, $identity, $create = true)
    {
        $state = self::read($create);
        $context = array(
            'schema' => 3,
            'site' => get_current_blog_id(),
            'url' => home_url('/'),
            'timezone' => wp_timezone_string(),
            'property' => $property,
            'range' => $range,
            'dates' => $dates,
            'comparison' => $comparison,
            'identity' => $identity,
            'generation' => $state ? $state['generation'] : '',
        );
        $context['id'] = hash('sha256', wp_json_encode($context));
        return $context;
    }

    /** Read one context, rejecting data from another generation. */
    public static function entry($context)
    {
        $state = self::read(false);
        return $state && $state['generation'] === $context['generation'] && isset($state['entries'][$context['id']])
            ? $state['entries'][$context['id']] : false;
    }

    /** Remove expired records and bound storage, oldest successful report first. */
    private static function prune($state, $reserve = 0)
    {
        $now = time();
        foreach ($state['entries'] as $id => $entry) {
            $stamp = $entry['good'] ? $entry['good']['retained_until'] : $entry['created_at'] + self::RETENTION;
            if ($stamp <= $now && $entry['lease_until'] <= $now) {
                unset($state['entries'][$id]);
            }
        }
        uasort($state['entries'], static function ($a, $b) {
            $left = $a['good'] ? $a['good']['successful_at'] : $a['created_at'];
            $right = $b['good'] ? $b['good']['successful_at'] : $b['created_at'];
            return $left <=> $right;
        });
        foreach ($state['entries'] as $id => $entry) {
            if (count($state['entries']) <= 20 - $reserve) {
                break;
            }
            if ($entry['lease_until'] <= $now) {
                unset($state['entries'][$id]);
            }
        }
        return $state;
    }

    /** Reserve a bounded slot before scheduling or fetching. */
    public static function ensure($context)
    {
        $removed = array();
        $ok = self::change(static function ($state) use ($context, &$removed) {
            $removed = array();
            if ($state['generation'] !== $context['generation']) {
                return false;
            }
            if (isset($state['entries'][$context['id']])) {
                return $state;
            }
            $before = array_keys($state['entries']);
            $state = self::prune($state, 1);
            $removed = array_diff($before, array_keys($state['entries']));
            if (count($state['entries']) >= 20) {
                return false;
            }
            $state['entries'][$context['id']] = array(
                'context' => $context, 'good' => null, 'created_at' => time(),
                'owner' => '', 'lease_until' => 0, 'pending_at' => 0,
                'retry_at' => 0, 'error' => '', 'blocked' => false,
            );
            return $state;
        });
        if ($ok) {
            foreach ($removed as $id) {
                wp_clear_scheduled_hook(self::REFRESH_HOOK, array($id));
            }
        }
        if ($ok && ! wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
        return $ok;
    }

    /** Acquire or renew a lease, including reclaiming expired owners. */
    public static function lock($context, $owner = '')
    {
        $renew = '' !== $owner;
        $owner = $renew ? $owner : wp_generate_uuid4();
        $ok = self::change(static function ($state) use ($context, $owner, $renew) {
            $id = $context['id'];
            if ($state['generation'] !== $context['generation'] || ! isset($state['entries'][$id])) {
                return false;
            }
            $entry = $state['entries'][$id];
            if ($entry['blocked'] || ($renew && ($entry['owner'] !== $owner || $entry['lease_until'] <= time()))
                || (! $renew && ($entry['lease_until'] > time() || $entry['retry_at'] > time()))) {
                return false;
            }
            $state['entries'][$id]['owner'] = $owner;
            $state['entries'][$id]['lease_until'] = time() + self::LEASE;
            $state['entries'][$id]['pending_at'] = 0;
            return $state;
        });
        return $ok ? $owner : false;
    }

    /** Atomically finish an owned attempt; failed attempts preserve last-good. */
    public static function finish($context, $owner, $report = null, $ttl = 900, $error = '', $blocked = false)
    {
        return self::change(static function ($state) use ($context, $owner, $report, $ttl, $error, $blocked) {
            $id = $context['id'];
            if ($state['generation'] !== $context['generation'] || ! isset($state['entries'][$id])
                || $state['entries'][$id]['owner'] !== $owner || $state['entries'][$id]['lease_until'] <= time()) {
                return false;
            }
            $entry = &$state['entries'][$id];
            if (is_array($report)) {
                $entry['good'] = array(
                    'report' => $report, 'successful_at' => time(),
                    'fresh_until' => time() + min(self::RETENTION, max(60, $ttl)), 'retained_until' => time() + self::RETENTION,
                );
            }
            $entry['owner'] = '';
            $entry['lease_until'] = 0;
            $entry['pending_at'] = 0;
            $entry['retry_at'] = '' !== $error ? time() + self::COOLDOWN : 0;
            $entry['error'] = sanitize_key($error);
            $entry['blocked'] = $blocked;
            // A confirmed revocation invalidates every report for this connection.
            if ($blocked) {
                foreach ($state['entries'] as &$item) {
                    $item['good'] = null;
                    $item['blocked'] = true;
                    $item['error'] = sanitize_key($error);
                    $item['owner'] = '';
                    $item['lease_until'] = 0;
                    $item['pending_at'] = 0;
                }
                unset($item);
            }
            return $state;
        });
    }

    /** Queue at most one refresh, recovering abandoned scheduling reservations. */
    public static function schedule($context)
    {
        $reserved = self::change(static function ($state) use ($context) {
            $id = $context['id'];
            if ($state['generation'] !== $context['generation'] || ! isset($state['entries'][$id])) {
                return false;
            }
            $entry = &$state['entries'][$id];
            if ($entry['blocked'] || $entry['retry_at'] > time() || $entry['lease_until'] > time()
                || $entry['pending_at'] > time() - self::LEASE) {
                return false;
            }
            $entry['pending_at'] = time();
            return $state;
        });
        if (! $reserved) {
            return;
        }
        $args = array($context['id']);
        $scheduled = wp_next_scheduled(self::REFRESH_HOOK, $args);
        if (! $scheduled) {
            $scheduled = wp_schedule_single_event(time(), self::REFRESH_HOOK, $args, true);
        }
        if (! $scheduled || is_wp_error($scheduled)) {
            self::change(static function ($state) use ($context) {
                if ($state['generation'] !== $context['generation'] || ! isset($state['entries'][$context['id']])) {
                    return false;
                }
                $entry = &$state['entries'][$context['id']];
                $entry['pending_at'] = 0;
                $entry['retry_at'] = time() + self::COOLDOWN;
                $entry['error'] = 'schedule_failed';
                return $state;
            });
        }
        // Clear can race scheduling; obsolete callbacks must not remain queued.
        if (! self::entry($context)) {
            wp_clear_scheduled_hook(self::REFRESH_HOOK, $args);
        }
    }

    /** Cron callback: use saved context only after recomputing current identity. */
    public static function refresh($id)
    {
        $state = self::read(false);
        if (! is_string($id) || ! $state || ! isset($state['entries'][$id])) {
            return;
        }
        $settings = CLIREDAS_Plugin::instance()->reporting_settings();
        $provider = new CLIREDAS_GA4_Data_Provider($settings);
        $provider->refresh_context($state['entries'][$id]['context']);
    }

    /** Daily cleanup also removes events for contexts no longer retained. */
    public static function cleanup()
    {
        $old = self::read(false);
        if (! $old) {
            return;
        }
        self::change(static function ($state) {
            return self::prune($state);
        });
        $state = self::read(false);
        foreach (array_diff(array_keys($old['entries']), array_keys($state ? $state['entries'] : array())) as $id) {
            wp_clear_scheduled_hook(self::REFRESH_HOOK, array($id));
        }
    }

    /** Rotate generation atomically, making in-flight publication impossible. */
    public static function clear()
    {
        $old = self::read(false);
        if (! $old) {
            wp_unschedule_hook(self::REFRESH_HOOK);
            wp_unschedule_hook(self::CLEANUP_HOOK);
            return 0;
        }
        $ok = self::change(static function ($state) {
            $state['generation'] = wp_generate_uuid4();
            $state['entries'] = array();
            return $state;
        });
        wp_unschedule_hook(self::REFRESH_HOOK);
        wp_unschedule_hook(self::CLEANUP_HOOK);
        return $ok ? ($old ? count($old['entries']) : 0) : false;
    }

    /** Compare semantic settings, ignoring access-token rotation and re-encryption. */
    public static function settings_changed($old, $updated)
    {
        if (self::connection_identity($old) !== self::connection_identity($updated)) {
            self::clear();
        }
    }

    /** Revoke retained reports when any shared client detects lost access. */
    public static function invalidate_access($code)
    {
        if (! in_array($code, array(
            'ga4_permission_denied', 'ga4_not_found', 'token_revoked', 'token_credentials_invalid',
            'credential_decryption_failed', 'credential_backend_unavailable', 'missing_client_id', 'missing_client_secret', 'missing_refresh_token',
        ), true)
            || ! self::read(false)) {
            return;
        }
        self::change(static function ($state) use ($code) {
            foreach ($state['entries'] as &$entry) {
                $entry['good'] = null;
                $entry['blocked'] = true;
                $entry['error'] = $code;
                $entry['owner'] = '';
                $entry['lease_until'] = 0;
                $entry['pending_at'] = 0;
            }
            unset($entry);
            return $state;
        });
        wp_unschedule_hook(self::REFRESH_HOOK);
    }

    /** Hash sensitive identity material; never store credentials in cache records. */
    public static function connection_identity($settings)
    {
        $settings = is_array($settings) ? $settings : array();
        $identity = array();
        $crypto = new CLIREDAS_Credential_Crypto();
        foreach (array('ga4_connected', 'ga4_property_id', 'ga4_client_id', 'ga4_client_secret', 'ga4_refresh_token', 'ga4_redirect_base_url', 'allow_editors') as $key) {
            $value = isset($settings[$key]) ? $settings[$key] : '';
            if (in_array($key, array('ga4_client_secret', 'ga4_refresh_token'), true)) {
                $value = $crypto->decrypt($value, $key);
                $value = is_wp_error($value) ? 'unreadable' : $value;
            }
            $identity[$key] = (string) $value;
        }
        return hash_hmac('sha256', wp_json_encode($identity), wp_salt('auth'));
    }
}
