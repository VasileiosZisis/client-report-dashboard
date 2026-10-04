<?php
/**
 * Isolated security regression tests. Run with: wp eval-file tests/security.php
 *
 * Requires the active plugin; fixture writes never reach the site database.
 *
 * @package ClientReportingDashboard
 */

(defined('WP_CLI') && WP_CLI) || exit;

/**
 * In-memory adapter for the plugin's conditional option writes.
 */
final class CLIREDAS_Security_Fixture_DB
{
    /**
     * Fictional options table name.
     *
     * @var string
     */
    public $options = 'cliredas_fixture_options';

    /**
     * Fixture state and fault controls.
     *
     * @var array
     */
    public $state = array('cliredas_settings' => array(), 'cliredas_audit_log' => array(), 'fail' => false, 'conflict' => null);

    /**
     * Capture parameters without building executable SQL.
     *
     * @param string $sql  Query template.
     * @param mixed  ...$args Query parameters.
     * @return array
     */
    public function prepare($sql, ...$args)
    {
        return $args;
    }

    /**
     * Simulate a conditional replacement, conflict, or persistence failure.
     *
     * @param array $args Captured parameters.
     * @return int
     */
    public function query($args)
    {
        if ($this->state['fail']) {
            return 0;
        }
        if (is_callable($this->state['conflict'])) {
            $conflict = $this->state['conflict'];
            $this->state['conflict'] = null;
            $conflict($this);
            return 0;
        }
        list($replacement, $key, $expected) = $args;
        if (! isset($this->state[$key]) || maybe_serialize($this->state[$key]) !== $expected) {
            return 0;
        }
        $this->state[$key] = maybe_unserialize($replacement);
        return 1;
    }
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Self-contained test runner and its fixture adapter.
/**
 * Exercise encryption, guarded writes, migration, token health, and audit safety.
 */
final class CLIREDAS_Security_Tests
{
    /**
     * Number of successful assertions.
     *
     * @var int
     */
    private static $assertions = 0;

    /**
     * Fail without printing fixture secrets or stored values.
     *
     * @param bool   $condition Expected condition.
     * @param string $label     Non-sensitive test description.
     * @throws RuntimeException If an assertion fails.
     * @return void
     */
    private static function check($condition, $label)
    {
        if (! $condition) {
            throw new RuntimeException(esc_html($label));
        }
        ++self::$assertions;
    }

    /**
     * Modify one ciphertext envelope for negative cases.
     *
     * @param string $value Stored envelope.
     * @param string $key   Envelope key.
     * @param string $replacement Altered value.
     * @return string
     */
    private static function alter($value, $key, $replacement)
    {
        // phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary ciphertext test fixtures.
        $prefix = CLIREDAS_Credential_Crypto::PREFIX . 'v1:';
        $data = json_decode(base64_decode(substr($value, strlen($prefix))), true);
        $data[$key] = $replacement;
        return $prefix . base64_encode(wp_json_encode($data));
        // phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
    }

    /**
     * Run tests against native crypto and isolated option/HTTP fixtures.
     *
     * @return void
     */
    public static function run()
    {
        global $wpdb;

        CLIREDAS_Plugin::instance()->bootstrap_admin();
        wp_salt('auth');
        wp_salt('secure_auth');
        $original_db = $wpdb;
        $alloptions = wp_load_alloptions();
        $original_user = get_current_user_id();
        $admin_ids = get_users(array('role' => 'administrator', 'fields' => 'ID', 'number' => 1));
        self::check(! empty($admin_ids), 'An administrator fixture actor is required');
        wp_set_current_user((int) $admin_ids[0]);
        $db = new CLIREDAS_Security_Fixture_DB();
        $option_filter = static function ($value, $option) use ($db) {
            return array_key_exists($option, $db->state) ? $db->state[$option] : $value;
        };
        $alloptions_filter = static function () use ($alloptions) {
            return $alloptions;
        };
        $http_calls = 0;
        $http_result = array('response' => array('code' => 200), 'body' => wp_json_encode(array('access_token' => 'fixture-access-rotated', 'expires_in' => 3600)));
        $http_filter = static function () use (&$http_calls, &$http_result) {
            ++$http_calls;
            return $http_result;
        };
        add_filter('pre_option', $option_filter, 999, 2);
        add_filter('pre_wp_load_alloptions', $alloptions_filter, 999);
        add_filter('pre_http_request', $http_filter, 999);
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Replace only inside isolated fixtures and restore in finally.
        $wpdb = $db;

        try {
            $available = array();
            foreach (array('sodium', 'aes-256-gcm') as $backend) {
                $crypto = new CLIREDAS_Credential_Crypto($backend, 'fixture-material', 17);
                $value = $crypto->encrypt('fixture-secret', 'ga4_client_secret');
                if (is_wp_error($value)) {
                    WP_CLI::log('Skipped unavailable backend: ' . $backend);
                    continue;
                }
                $available[] = $backend;
                self::check('fixture-secret' === $crypto->decrypt($value, 'ga4_client_secret'), $backend . ' round trip');
                self::check($value !== $crypto->encrypt('fixture-secret', 'ga4_client_secret'), $backend . ' randomized ciphertext');
                self::check(is_wp_error($crypto->decrypt($value, 'ga4_refresh_token')), $backend . ' field separation');
                self::check(is_wp_error((new CLIREDAS_Credential_Crypto($backend, 'changed-material', 17))->decrypt($value, 'ga4_client_secret')), $backend . ' changed salts');
                self::check(is_wp_error((new CLIREDAS_Credential_Crypto($backend, 'fixture-material', 18))->decrypt($value, 'ga4_client_secret')), $backend . ' site separation');
                self::check(is_wp_error((new CLIREDAS_Credential_Crypto('', 'fixture-material', 17))->decrypt($value, 'ga4_client_secret')), $backend . ' missing decryption support');
                self::check(is_wp_error($crypto->decrypt(self::alter($value, 'algorithm', 'unknown'), 'ga4_client_secret')), $backend . ' unknown algorithm');
                self::check(is_wp_error($crypto->decrypt(self::alter($value, 'nonce', 'AA=='), 'ga4_client_secret')), $backend . ' invalid nonce length');
                self::check(is_wp_error($crypto->decrypt(self::alter($value, 'ciphertext', '*invalid*'), 'ga4_client_secret')), $backend . ' invalid encoding');
                // phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary negative fixtures.
                self::check(is_wp_error($crypto->decrypt(self::alter($value, 'ciphertext', base64_encode(str_repeat('x', 40))), 'ga4_client_secret')), $backend . ' tampering');
                self::check(is_wp_error($crypto->decrypt(self::alter($value, 'tag', base64_encode(str_repeat('x', 15))), 'ga4_client_secret')), $backend . ' truncated or unexpected tag');
                // phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
                self::check(is_wp_error($crypto->decrypt(CLIREDAS_Credential_Crypto::PREFIX . 'v2:unknown', 'ga4_client_secret')), $backend . ' unknown version');
                self::check(is_wp_error($crypto->decrypt(CLIREDAS_Credential_Crypto::PREFIX . 'v1:!!!', 'ga4_client_secret')), $backend . ' malformed envelope');
                self::check(is_wp_error($crypto->decrypt(array(), 'ga4_client_secret')), $backend . ' invalid value type');
            }
            self::check(! empty($available), 'At least one encryption backend must be available');
            $store = CLIREDAS_Credential_Store::instance();
            $settings = new CLIREDAS_Settings();
            $client = new CLIREDAS_GA4_Client($settings);
            $diagnostics = new CLIREDAS_Setup_Diagnostics($settings, $client);
            $fingerprint = new ReflectionMethod($diagnostics, 'get_fingerprint');
            if (PHP_VERSION_ID < 80100) {
                $fingerprint->setAccessible(true);
            }
            $db->state['cliredas_settings'] = array('ga4_client_id' => 'fixture-client', 'ga4_client_secret' => 'fixture-secret', 'ga4_refresh_token' => 'fixture-refresh', 'ga4_access_token' => 'fixture-access', 'ga4_token_expires' => time() + 3600, 'ga4_connected' => 1, 'unrelated' => 'keep');
            $before = $settings->get_settings();
            $signature = $fingerprint->invoke($diagnostics, $before);
            $store->maybe_migrate();
            $migrated = $db->state['cliredas_settings'];
            foreach (CLIREDAS_Credential_Crypto::FIELDS as $field) {
                self::check(0 === strpos($migrated[$field], CLIREDAS_Credential_Crypto::PREFIX), 'Migration encrypts each field');
                self::check($before[$field] === $settings->get_settings()[$field], 'Migration preserves decoded values');
            }
            self::check($signature === $fingerprint->invoke($diagnostics, $settings->get_settings()), 'Migration preserves fingerprint');
            $store->maybe_migrate();
            self::check($migrated === $db->state['cliredas_settings'], 'Repeat migration is idempotent');
            $sanitized = $settings->sanitize_settings(array('ga4_client_secret' => '', 'allow_editors' => 1));
            self::check($migrated['ga4_client_secret'] === $store->prepare($sanitized, $migrated)['ga4_client_secret'], 'Blank secret preserves ciphertext');
            self::check($migrated['ga4_client_secret'] === $store->prepare($before, $migrated)['ga4_client_secret'], 'Unchanged plaintext reuses ciphertext');
            self::check(true === $store->save(array('allow_editors' => 1)), 'Patch save succeeds');
            self::check('keep' === $db->state['cliredas_settings']['unrelated'], 'Patch preserves unrelated values');
            self::check('fixture-access' === $client->get_valid_access_token() && 0 === $http_calls, 'Token reuse makes no request');
            self::check(array() === CLIREDAS_Audit_Log::get_entries(), 'Token reuse creates no audit event');
            self::check('fixture-access-rotated' === $client->refresh_access_token(), 'Explicit refresh succeeds');
            self::check(1 === $http_calls && 'success' === CLIREDAS_Audit_Log::get_entries()[0]['outcome'], 'Refresh success is recorded');
            self::check($signature === $fingerprint->invoke($diagnostics, $settings->get_settings()), 'Access rotation preserves fingerprint');
            $http_result = new WP_Error('fixture_network', 'fixture-sensitive-upstream-message');
            $failure = $client->refresh_access_token();
            self::check(is_wp_error($failure) && false === strpos($failure->get_error_message(), 'fixture-sensitive'), 'Network feedback is predefined');
            self::check('network' === CLIREDAS_Audit_Log::get_entries()[0]['outcome'], 'Refresh failure is recorded safely');
            $http_result = array('response' => array('code' => 400), 'body' => wp_json_encode(array('error' => 'unknown', 'error_description' => 'fixture-sensitive-upstream-message')));
            self::check(false === strpos($client->refresh_access_token()->get_error_message(), 'fixture-sensitive'), 'Upstream description is discarded');
            $db->state['fail'] = true;
            $snapshot = $db->state['cliredas_settings'];
            self::check(is_wp_error($store->save(array('ga4_client_secret' => 'replacement'))), 'Persistence failure is reported');
            self::check($snapshot === $db->state['cliredas_settings'], 'Persistence failure preserves old values');
            CLIREDAS_Audit_Log::record('csv_export', 'success');
            $db->state['fail'] = false;
            $db->state['conflict'] = static function ($fixture) {
                $fixture->state['cliredas_settings']['concurrent'] = 'preserve';
            };
            self::check(true === $store->save(array('allow_editors' => 0)), 'Concurrent patch retries');
            self::check('preserve' === $db->state['cliredas_settings']['concurrent'], 'Concurrent change survives');
            $signature = $fingerprint->invoke($diagnostics, $settings->get_settings());
            $store->save(array('ga4_client_secret' => 'replacement-secret'));
            self::check($signature !== $fingerprint->invoke($diagnostics, $settings->get_settings()), 'Secret replacement invalidates fingerprint');
            $signature = $fingerprint->invoke($diagnostics, $settings->get_settings());
            $store->save(array('ga4_refresh_token' => 'replacement-refresh'));
            self::check($signature !== $fingerprint->invoke($diagnostics, $settings->get_settings()), 'Refresh replacement invalidates fingerprint');
            $db->state['cliredas_settings']['ga4_client_secret'] = CLIREDAS_Credential_Crypto::PREFIX . 'v1:broken';
            $unreadable = $db->state['cliredas_settings']['ga4_client_secret'];
            self::check(true === $store->save(array('allow_editors' => 1)), 'Unrelated save with unreadable credentials succeeds');
            self::check($unreadable === $db->state['cliredas_settings']['ga4_client_secret'], 'Unreadable ciphertext is preserved');
            $db->state['cliredas_settings']['ga4_client_secret'] = array('invalid');
            self::check(true === $store->save(array('allow_editors' => 0)) && array('invalid') === $db->state['cliredas_settings']['ga4_client_secret'], 'Unrelated save preserves malformed stored types');
            $db->state['cliredas_settings']['ga4_client_secret'] = $unreadable;
            self::check('' === $settings->get_settings()['ga4_client_secret'], 'Unreadable ciphertext never becomes plaintext');
            $calls = $http_calls;
            $events = CLIREDAS_Audit_Log::get_entries();
            self::check(is_wp_error($client->get_valid_access_token()), 'Unreadable credential blocks Google requests');
            $store->get_status();
            self::check($calls === $http_calls && CLIREDAS_Audit_Log::get_entries() === $events, 'Local reads do not request or audit');
            $cleared = $settings->sanitize_settings(array('cliredas_clear_ga4_client_secret' => 1));
            foreach (CLIREDAS_Credential_Crypto::FIELDS as $field) {
                self::check('' === $cleared[$field], 'Explicit clearing removes each sensitive value');
            }
            $unsupported = new CLIREDAS_Credential_Store(new CLIREDAS_Credential_Crypto(''));
            self::check('legacy' === $unsupported->prepare(array('ga4_client_secret' => 'legacy'), array())['ga4_client_secret'], 'Unavailable backend retains plaintext compatibility');
            $failed_backend = new CLIREDAS_Credential_Store(new CLIREDAS_Credential_Crypto('unknown'));
            self::check(is_wp_error($failed_backend->prepare(array('ga4_client_secret' => 'legacy'), array())), 'Backend failure rejects writes');
            $db->state['cliredas_settings'] = array('ga4_client_secret' => 'legacy');
            $db->state['conflict'] = static function ($fixture) {
                $fixture->state['cliredas_settings']['new_setting'] = 'concurrent';
            };
            $store->maybe_migrate();
            self::check('legacy' === $db->state['cliredas_settings']['ga4_client_secret'] && 'concurrent' === $db->state['cliredas_settings']['new_setting'], 'Conflicting migration preserves newer settings');
            $store->maybe_migrate();
            self::check(0 === strpos($db->state['cliredas_settings']['ga4_client_secret'], CLIREDAS_Credential_Crypto::PREFIX), 'Migration recovers on next initialization');
            $snapshot = $db->state['cliredas_settings'];
            $rejected = $store->filter_storage(array_replace($snapshot, array('ga4_client_secret' => CLIREDAS_Credential_Crypto::PREFIX . 'v2:invalid')));
            self::check($snapshot === $rejected, 'Invalid encrypted write preserves the entire option');
            $db->state['cliredas_settings'] = array('ga4_client_secret' => 'legacy', 'unrelated' => 'keep');
            $db->state['fail'] = true;
            $store->maybe_migrate();
            self::check('legacy' === $db->state['cliredas_settings']['ga4_client_secret'], 'Migration persistence failure preserves plaintext');
            $db->state['fail'] = false;
            $db->state['cliredas_settings'] = $migrated;
            $auth = new CLIREDAS_GA4_Auth($settings);
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Save and restore fixture request state without processing it.
            $request = $_REQUEST;
            $redirect_filter = static function () {
                throw new RuntimeException('fixture-redirect');
            };
            add_filter('wp_redirect', $redirect_filter, -999);
            try {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Fixture input for the real nonce-protected handler.
                $_REQUEST['_wpnonce'] = wp_create_nonce('cliredas_ga4_disconnect');
                try {
                    $auth->handle_disconnect();
                } catch (RuntimeException $exception) {
                    self::check('fixture-redirect' === $exception->getMessage(), 'Disconnect ends in a safe redirect');
                }
                self::check($migrated['ga4_client_secret'] === $db->state['cliredas_settings']['ga4_client_secret'], 'Disconnect preserves credential ciphertext');
                self::check('' === $db->state['cliredas_settings']['ga4_refresh_token'] && '' === $db->state['cliredas_settings']['ga4_access_token'], 'Disconnect clears both tokens');
                self::check('disconnect' === CLIREDAS_Audit_Log::get_entries()[0]['event'], 'Authorized disconnect is audited');
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Fixture input for the real nonce-protected handler.
                $_REQUEST['_wpnonce'] = wp_create_nonce('cliredas_ga4_clear_secret');
                try {
                    $auth->handle_clear_secret();
                } catch (RuntimeException $exception) {
                    self::check('fixture-redirect' === $exception->getMessage(), 'Secret clearing ends in a safe redirect');
                }
                self::check('' === $db->state['cliredas_settings']['ga4_client_secret'], 'Secret-clearing handler removes the secret');
                self::check('secret_clear' === CLIREDAS_Audit_Log::get_entries()[0]['event'], 'Authorized secret clearing is audited');
            } finally {
                $_REQUEST = $request;
                remove_filter('wp_redirect', $redirect_filter, -999);
            }
            $deny_admin = static function ($caps) {
                $caps['manage_options'] = false;
                return $caps;
            };
            $local_render = new ReflectionMethod($settings, 'render_local_diagnostics');
            if (PHP_VERSION_ID < 80100) {
                $local_render->setAccessible(true);
            }
            add_filter('user_has_cap', $deny_admin, 999);
            $die_filter = static function () {
                return static function () {
                    throw new RuntimeException('fixture-denied');
                };
            };
            add_filter('wp_die_handler', $die_filter, 999);
            ob_start();
            try {
                $local_render->invoke($settings);
                self::check('' === ob_get_contents(), 'Non-administrators cannot view local diagnostics or history');
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Save and restore fixture request state.
                $request = $_REQUEST;
                $_REQUEST['_wpnonce'] = wp_create_nonce('cliredas_run_diagnostics');
                $events = CLIREDAS_Audit_Log::get_entries();
                $calls = $http_calls;
                try {
                    $diagnostics->handle_run();
                    self::check(false, 'Non-administrators must not run diagnostics');
                } catch (RuntimeException $exception) {
                    self::check('fixture-denied' === $exception->getMessage(), 'Diagnostics permission check fails closed');
                }
                self::check($calls === $http_calls && CLIREDAS_Audit_Log::get_entries() === $events, 'Unauthorized diagnostics have no remote or audit side effects');
                $nonce_result = null;
                $nonce_observer = static function ($action, $result) use (&$nonce_result) {
                    $nonce_result = $result;
                };
                add_action('check_admin_referer', $nonce_observer, 999, 2);
                try {
                    foreach (array('invalid', '') as $nonce) {
                        $_REQUEST['_wpnonce'] = $nonce;
                        try {
                            $diagnostics->handle_run();
                            self::check(false, 'Invalid nonce must stop the request');
                        } catch (RuntimeException $exception) {
                            self::check('fixture-denied' === $exception->getMessage() && false === $nonce_result, 'Invalid or missing nonce fails before permissions');
                        }
                    }
                } finally {
                    remove_action('check_admin_referer', $nonce_observer, 999);
                }
            } finally {
                $_REQUEST = $request;
                ob_end_clean();
                remove_filter('wp_die_handler', $die_filter, 999);
                remove_filter('user_has_cap', $deny_admin, 999);
            }
            $db->state['cliredas_audit_log'] = array();
            for ($i = 0; $i < 110; ++$i) {
                CLIREDAS_Audit_Log::record('cache_clear', 'success');
            }
            self::check(100 === count(CLIREDAS_Audit_Log::get_entries()), 'Audit retention is bounded');
            self::check(array('event', 'timestamp', 'actor_id', 'outcome') === array_keys(CLIREDAS_Audit_Log::get_entries()[0]), 'Audit stores only safe fields');
            $entries = CLIREDAS_Audit_Log::get_entries();
            CLIREDAS_Audit_Log::record('unknown', 'success');
            CLIREDAS_Audit_Log::record('connect', 'raw-error');
            self::check(CLIREDAS_Audit_Log::get_entries() === $entries, 'Unknown events and outcomes are rejected');
            $db->state['conflict'] = static function ($fixture) {
                $fixture->state['cliredas_audit_log'][] = array('event' => 'disconnect', 'timestamp' => time(), 'actor_id' => 0, 'outcome' => 'success');
            };
            CLIREDAS_Audit_Log::record('csv_export', 'sample_data');
            self::check('csv_export' === CLIREDAS_Audit_Log::get_entries()[0]['event'] && 'disconnect' === CLIREDAS_Audit_Log::get_entries()[1]['event'], 'Concurrent audit events survive');
            WP_CLI::success(self::$assertions . ' security assertions passed; no fixture writes or remote requests reached the live site.');
        } finally {
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the original database before removing fixture filters.
            $wpdb = $original_db;
            remove_filter('pre_option', $option_filter, 999);
            remove_filter('pre_wp_load_alloptions', $alloptions_filter, 999);
            remove_filter('pre_http_request', $http_filter, 999);
            CLIREDAS_Local_Store::invalidate('cliredas_settings');
            CLIREDAS_Local_Store::invalidate('cliredas_audit_log');
            wp_set_current_user($original_user);
        }
    }
}

CLIREDAS_Security_Tests::run();
