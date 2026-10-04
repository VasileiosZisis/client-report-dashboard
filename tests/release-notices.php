<?php
/**
 * Isolated release-notice regressions. Run with: wp eval-file tests/release-notices.php
 *
 * @package ClientReportingDashboard
 */

(defined('WP_CLI') && WP_CLI) || exit;

/**
 * In-memory option adapter; unexpected database operations fail the test.
 */
final class CLIREDAS_Release_Fixture_DB
{
    /**
     * Fictional options table.
     *
     * @var string
     */
    public $options = 'cliredas_fixture_options';
    /**
     * Fictional user metadata table, enabling the Metadata API filters.
     *
     * @var string
     */
    public $usermeta = 'cliredas_fixture_usermeta';
    /**
     * Preserve the fixture actor's capabilities while changing storage context.
     *
     * @var string
     */
    public $prefix = '';
    /**
     * Option fixtures.
     *
     * @var array
     */
    public $state = array('cliredas_release_state' => false, 'cliredas_settings' => array());
    /**
     * Simulated persistence failure.
     *
     * @var bool
     */
    public $fail = false;
    /**
     * Simulated concurrent update.
     *
     * @var callable|null
     */
    public $conflict = null;
    /**
     * Captured autoload flag.
     *
     * @var string|null
     */
    public $autoload = null;

    /** Capture query parameters without executing SQL. */
    public function prepare($sql, ...$args)
    {
        return array($sql, $args);
    }

    /** Preserve role fixtures across simulated blog IDs. */
    public function get_blog_prefix()
    {
        return $this->prefix;
    }

    /** Simulate missing/present option lookups. */
    public function get_row($query)
    {
        $key = $query[1][0];
        return false === $this->state[$key] ? null : (object) array('option_value' => maybe_serialize($this->state[$key]));
    }

    /** Simulate initial inserts and conditional updates. */
    public function query($query)
    {
        if ($this->fail) {
            return 0;
        }
        if (is_callable($this->conflict)) {
            $callback = $this->conflict;
            $this->conflict = null;
            $callback($this);
            return 0;
        }
        list($sql, $args) = $query;
        if (0 === strpos($sql, 'INSERT')) {
            list($key, $value, $this->autoload) = $args;
            $this->state[$key] = maybe_unserialize($value);
            return 1;
        }
        list($value, $key, $expected) = $args;
        if (maybe_serialize($this->state[$key]) !== $expected) {
            return 0;
        }
        $this->state[$key] = maybe_unserialize($value);
        return 1;
    }
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Self-contained runner and fixture adapter.
/**
 * Test real WordPress handlers with isolated options and user metadata.
 */
final class CLIREDAS_Release_Tests
{
    /**
     * Successful assertions.
     *
     * @var int
     */
    private static $assertions = 0;

    /**
     * Fail with a non-sensitive test label.
     *
     * @throws RuntimeException If an assertion fails.
     */
    private static function check($condition, $label)
    {
        if (! $condition) {
            throw new RuntimeException(esc_html($label));
        }
        ++self::$assertions;
    }

    /** Exercise upgrade states, conditional writes, rendering, and authorization. */
    public static function run()
    {
        global $wpdb, $current_screen, $blog_id;

        CLIREDAS_Plugin::instance()->bootstrap_admin();
        require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
        wp_salt('auth');
        $original_db = $wpdb;
        $original_screen = $current_screen;
        $original_blog = $blog_id;
        $original_user = get_current_user_id();
        // phpcs:disable WordPress.Security.NonceVerification -- Save, simulate, and restore test requests.
        $original_request = $_REQUEST;
        $original_post = $_POST;
        // phpcs:enable WordPress.Security.NonceVerification
        $users = get_users(array('role' => 'administrator', 'fields' => 'ID', 'number' => 1));
        self::check(! empty($users), 'Administrator fixture available');
        wp_set_current_user((int) $users[0]);
        $user = get_current_user_id();
        $alloptions = wp_load_alloptions();
        unset($alloptions[CLIREDAS_Release_Notices::OPTION_KEY], $alloptions['cliredas_settings']);
        $db = new CLIREDAS_Release_Fixture_DB();
        $db->prefix = $original_db->get_blog_prefix();
        $actor_caps = wp_get_current_user()->caps;
        $records = array();
        $fail_meta = false;
        $meta_conflict = null;
        $option_filter = static function ($value, $key) use ($db) {
            return array_key_exists($key, $db->state) ? $db->state[$key] : $value;
        };
        $alloptions_filter = static function () use ($alloptions) {
            return $alloptions;
        };
        $meta_filter = static function ($value, $actor, $key) use (&$records, $actor_caps, $db) {
            if ($db->prefix . 'capabilities' === $key) {
                return array($actor_caps);
            }
            return CLIREDAS_Release_Notices::USER_META_KEY === $key ? (isset($records[$actor]) ? $records[$actor] : array()) : $value;
        };
        $meta_add = static function ($value, $actor, $key, $map) use (&$records, &$fail_meta, &$meta_conflict) {
            if (CLIREDAS_Release_Notices::USER_META_KEY !== $key) {
                return $value;
            }
            if (is_callable($meta_conflict)) {
                $callback = $meta_conflict;
                $meta_conflict = null;
                $callback();
                return false;
            }
            if ($fail_meta || ! empty($records[$actor])) {
                return false;
            }
            $records[$actor] = array($map);
            return 1;
        };
        $meta_update = static function ($value, $actor, $key, $map, $previous) use (&$records, &$fail_meta, &$meta_conflict) {
            if (CLIREDAS_Release_Notices::USER_META_KEY !== $key) {
                return $value;
            }
            if (is_callable($meta_conflict)) {
                $callback = $meta_conflict;
                $meta_conflict = null;
                $callback();
                return false;
            }
            if (! $fail_meta && ! empty($previous)) {
                foreach ($records[$actor] as $index => $record) {
                    if ($previous === $record) {
                        $records[$actor][$index] = $map;
                        return true;
                    }
                }
            }
            return false;
        };
        $http_filter = static function () {
            throw new RuntimeException('Release notices must not make HTTP requests');
        };
        add_filter('pre_option', $option_filter, 999, 2);
        add_filter('pre_wp_load_alloptions', $alloptions_filter, 999);
        add_filter('get_user_metadata', $meta_filter, 999, 3);
        add_filter('add_user_metadata', $meta_add, 999, 4);
        add_filter('update_user_metadata', $meta_update, 999, 5);
        add_filter('pre_http_request', $http_filter, 999);
        CLIREDAS_Local_Store::invalidate(CLIREDAS_Release_Notices::OPTION_KEY);
        // phpcs:disable WordPress.WP.GlobalVariablesOverride -- Isolated fixture globals restored in finally.
        $wpdb = $db;
        $current_screen = WP_Screen::get('settings_page_cliredas-settings');
        // phpcs:enable WordPress.WP.GlobalVariablesOverride
        $controller = new CLIREDAS_Release_Notices();

        try {
            $tracked = array('installed_version' => '1.5.0', 'pending_release' => '1.5.0');
            self::check('' === CLIREDAS_Release_Notices::next_state(false, false, '1.7.0', true)['pending_release'], 'Fresh install stays quiet');
            self::check('1.7.0' === CLIREDAS_Release_Notices::next_state(false, true, '1.7.0', true)['pending_release'], 'Legacy upgrade announces current release');
            self::check('1.7.0' === CLIREDAS_Release_Notices::next_state($tracked, true, '1.7.0', true)['pending_release'], 'Skipped versions announce only current release');
            self::check(CLIREDAS_Release_Notices::next_state($tracked, true, '1.4.0', true) === $tracked, 'Downgrades preserve tracking');
            self::check(CLIREDAS_Release_Notices::next_state($tracked, true, '1.5.0', true) === $tracked, 'Repeated initialization preserves state');
            self::check('' === CLIREDAS_Release_Notices::next_state($tracked, true, '1.8.0', false)['pending_release'], 'Uncurated release has no stale highlights');
            CLIREDAS_Release_Notices::initialize_install(true);
            self::check(! $controller->should_show(), 'Fresh activation has no announcement');
            self::check(in_array($db->autoload, array('no', 'off'), true), 'Release state is non-autoloaded');
            $db->state[CLIREDAS_Release_Notices::OPTION_KEY] = $tracked;
            CLIREDAS_Release_Notices::initialize_install(true);
            self::check($tracked === $db->state[CLIREDAS_Release_Notices::OPTION_KEY], 'Reactivation preserves release state');
            $controller->track_release();
            self::check($controller->should_show(), 'Tracked upgrade announces release');
            $current = $db->state[CLIREDAS_Release_Notices::OPTION_KEY];
            $db->state[CLIREDAS_Release_Notices::OPTION_KEY] = false;
            CLIREDAS_Local_Store::invalidate(CLIREDAS_Release_Notices::OPTION_KEY);
            $controller->track_release();
            self::check($current === $db->state[CLIREDAS_Release_Notices::OPTION_KEY], 'Legacy file replacement detected');
            $db->state[CLIREDAS_Release_Notices::OPTION_KEY] = $tracked;
            $db->fail = true;
            $controller->track_release();
            self::check($tracked === $db->state[CLIREDAS_Release_Notices::OPTION_KEY], 'Failed tracking preserves previous state');
            $db->fail = false;
            $db->conflict = static function ($fixture) use ($current) {
                $fixture->state[CLIREDAS_Release_Notices::OPTION_KEY] = $current;
            };
            $controller->track_release();
            self::check($current === $db->state[CLIREDAS_Release_Notices::OPTION_KEY], 'Concurrent version tracking converges');
            ob_start();
            $controller->render_notice();
            $html = ob_get_clean();
            self::check(false !== strpos($html, 'cliredas-release-notice') && false !== strpos($html, 'method="post"'), 'Settings notice includes POST fallback');
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulate a screen without invoking admin rendering.
            $current_screen->id = 'toplevel_page_cliredas-client-report';
            ob_start();
            $controller->render_notice();
            self::check(false !== strpos(ob_get_clean(), 'cliredas-release-notice'), 'Dashboard notice rendered');
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulate an unrelated admin screen.
            $current_screen->id = 'dashboard';
            ob_start();
            $controller->render_notice();
            self::check('' === ob_get_clean(), 'Unrelated admin pages have no release notice');
            self::check(is_wp_error($controller->dismiss('1.6.0')) && empty($records), 'Invalid release does not write metadata');
            $fail_meta = true;
            self::check(is_wp_error($controller->dismiss('1.7.0')) && $controller->should_show(), 'Persistence failure keeps notice visible');
            $fail_meta = false;
            $dismissed = $controller->dismiss('1.7.0');
            self::check(true === $dismissed && ! $controller->should_show(), 'Dismissal persists for administrator');
            self::check(true === $controller->dismiss('1.7.0'), 'Repeated dismissal is idempotent');
            $actor = wp_get_current_user();
            $actor->ID = $user + 100;
            try {
                self::check($controller->should_show(), 'Another administrator remains undismissed');
                self::check(true === $controller->dismiss('1.7.0'), 'Another administrator can dismiss independently');
                self::check(isset($records[$user], $records[$user + 100]), 'Administrator dismissal records remain independent');
            } finally {
                $actor->ID = $user;
            }
            // phpcs:disable WordPress.WP.GlobalVariablesOverride -- Simulate another site without database reads.
            $blog_id = $original_blog + 100;
            // phpcs:enable WordPress.WP.GlobalVariablesOverride
            self::check($controller->should_show(), 'Another site remains undismissed');
            $meta_conflict = static function () use (&$records, $user) {
                $records[$user][0][222] = '1.8.0';
            };
            self::check(true === $controller->dismiss('1.7.0'), 'Concurrent dismissal retries successfully');
            $map = CLIREDAS_Release_Notices::merge_dismissals($records[$user]);
            self::check(isset($map[$original_blog], $map[$blog_id], $map[222]), 'Concurrent updates preserve other sites');
            $records[$user] = array();
            $meta_conflict = static function () use (&$records, $user) {
                $records[$user] = array(array(222 => '1.7.0'));
            };
            self::check(true === $controller->dismiss('1.7.0') && isset($records[$user][0][222]), 'Concurrent initial dismissal preserves other sites');
            $merged = CLIREDAS_Release_Notices::merge_dismissals(array(array(1 => '1.6.0', 2 => '1.7.0'), array(1 => '1.8.0'), 'invalid', array(0 => '1.7.0', 'bad' => '1.7.0', 3 => array())));
            self::check(array(1 => '1.8.0', 2 => '1.7.0') === $merged, 'Duplicate records retain latest valid versions');
            $records[$user] = array(array());
            self::check(is_wp_error($controller->dismiss('1.7.0')), 'Malformed metadata never invokes an unguarded update');
            $records[$user] = array();
            self::handlers($controller, $records, $fail_meta);
            ob_start();
            CLIREDAS_Release_Notices::render_changelog();
            self::check(false !== strpos(ob_get_clean(), '<details id="cliredas-changelog"'), 'Permanent changelog remains after dismissal');
            WP_CLI::success(self::$assertions . ' release-notice assertions passed; all writes and HTTP requests isolated.');
        } finally {
            // phpcs:disable WordPress.WP.GlobalVariablesOverride -- Restore original fixture globals.
            $wpdb = $original_db;
            $current_screen = $original_screen;
            $blog_id = $original_blog;
            // phpcs:enable WordPress.WP.GlobalVariablesOverride
            $_REQUEST = $original_request;
            $_POST = $original_post;
            remove_filter('pre_option', $option_filter, 999);
            remove_filter('pre_wp_load_alloptions', $alloptions_filter, 999);
            remove_filter('get_user_metadata', $meta_filter, 999);
            remove_filter('add_user_metadata', $meta_add, 999);
            remove_filter('update_user_metadata', $meta_update, 999);
            remove_filter('pre_http_request', $http_filter, 999);
            CLIREDAS_Local_Store::invalidate(CLIREDAS_Release_Notices::OPTION_KEY);
            wp_cache_delete($user, 'user_meta');
            wp_set_current_user($original_user);
        }
    }

    /** Exercise nonce ordering, capability checks, AJAX replies, and POST redirects. */
    private static function handlers($controller, &$records, &$fail_meta)
    {
        $records = array();
        $nonce_checked = false;
        $cap_checked = false;
        $deny = false;
        $status = 0;
        $redirect = '';
        $nonce_observer = static function ($action) use (&$nonce_checked) {
            if (CLIREDAS_Release_Notices::ACTION === $action) {
                $nonce_checked = true;
            }
        };
        $cap_filter = static function ($caps) use (&$deny, &$cap_checked, &$nonce_checked) {
            self::check($nonce_checked, 'Nonce verification precedes capability checks');
            $cap_checked = true;
            if ($deny) {
                $caps['manage_options'] = false;
            }
            return $caps;
        };
        $die_filter = static function () {
            return static function ($message, $title, $args) {
                throw new RuntimeException(esc_html('fixture-stop-' . (isset($args['response']) ? $args['response'] : 0)));
            };
        };
        $status_filter = static function ($header, $code) use (&$status) {
            $status = $code;
            return $header;
        };
        $redirect_filter = static function ($url) use (&$redirect) {
            $redirect = $url;
            throw new RuntimeException('fixture-redirect');
        };
        add_action('check_admin_referer', $nonce_observer, 999);
        add_action('check_ajax_referer', $nonce_observer, 999);
        add_filter('wp_die_handler', $die_filter, 999);
        add_filter('wp_die_ajax_handler', $die_filter, 999);
        add_filter('wp_doing_ajax', '__return_true', 999);
        add_filter('status_header', $status_filter, 999, 2);
        add_filter('wp_redirect', $redirect_filter, -999);
        try {
            foreach (array('post_dismiss', 'ajax_dismiss') as $method) {
                $records = array();
                foreach (array('invalid', '') as $nonce) {
                    // phpcs:disable WordPress.Security.NonceVerification -- Fixture inputs to nonce-protected handlers.
                    $_REQUEST = array('_wpnonce' => $nonce);
                    $_POST = array('cliredas_release' => '1.7.0');
                    // phpcs:enable WordPress.Security.NonceVerification
                    $nonce_checked = false;
                    $cap_checked = false;
                    add_filter('user_has_cap', $cap_filter, 999);
                    ob_start();
                    try {
                        $controller->$method();
                        self::check(false, 'Bad nonce must terminate');
                    } catch (RuntimeException $exception) {
                        self::check(0 === strpos($exception->getMessage(), 'fixture-stop') && ! $cap_checked && empty($records), 'Bad nonce fails without metadata writes');
                    } finally {
                        ob_end_clean();
                        remove_filter('user_has_cap', $cap_filter, 999);
                    }
                }
                foreach (array('denied', 'invalid', 'failed', 'success') as $case) {
                    $nonce = wp_create_nonce(CLIREDAS_Release_Notices::ACTION);
                    // phpcs:disable WordPress.Security.NonceVerification -- Fixture inputs to nonce-protected handlers.
                    $_REQUEST = array('_wpnonce' => $nonce);
                    $_POST = array('cliredas_release' => 'invalid' === $case ? '1.6.0' : '1.7.0', 'cliredas_return_page' => 'dashboard');
                    // phpcs:enable WordPress.Security.NonceVerification
                    $records = array();
                    $deny = 'denied' === $case;
                    $fail_meta = 'failed' === $case;
                    $nonce_checked = false;
                    $status = 0;
                    $redirect = '';
                    add_filter('user_has_cap', $cap_filter, 999);
                    ob_start();
                    try {
                        $controller->$method();
                        self::check(false, 'Handler must finish in a reply or redirect');
                    } catch (RuntimeException $exception) {
                        self::check($nonce_checked && 0 === strpos($exception->getMessage(), 'fixture-'), 'Authorized handler reaches safe termination');
                        if ('ajax_dismiss' === $method) {
                            $json = json_decode(ob_get_contents(), true);
                            self::check(is_array($json) && ('success' === $case) === $json['success'], 'AJAX reports persistence outcome');
                            if ('success' !== $case) {
                                self::check(('denied' === $case ? 403 : ('invalid' === $case ? 400 : 500)) === $status, 'AJAX failure has expected HTTP status');
                            }
                        } elseif (in_array($case, array('success', 'failed'), true)) {
                            self::check(false !== strpos($redirect, 'admin.php?page=cliredas-client-report'), 'POST redirects only to allowed plugin page');
                            self::check(('failed' === $case) === (false !== strpos($redirect, 'cliredas_release_dismiss_error=1')), 'POST persistence failure keeps safe inline feedback');
                        } else {
                            self::check('fixture-stop-' . ('denied' === $case ? '403' : '400') === $exception->getMessage(), 'POST authorization and validation fail closed');
                        }
                        self::check(('success' === $case) === ! empty($records), 'Only successful persistence changes dismissal state');
                    } finally {
                        ob_end_clean();
                        remove_filter('user_has_cap', $cap_filter, 999);
                    }
                }
            }
            $deny = true;
            $nonce_checked = true;
            add_filter('user_has_cap', $cap_filter, 999);
            ob_start();
            try {
                $controller->render_notice();
                CLIREDAS_Release_Notices::render_changelog();
                self::check('' === ob_get_contents() && ! $controller->should_show(), 'Editors cannot view release notices or notes');
            } finally {
                ob_end_clean();
                remove_filter('user_has_cap', $cap_filter, 999);
            }
        } finally {
            remove_action('check_admin_referer', $nonce_observer, 999);
            remove_action('check_ajax_referer', $nonce_observer, 999);
            remove_filter('wp_die_handler', $die_filter, 999);
            remove_filter('wp_die_ajax_handler', $die_filter, 999);
            remove_filter('wp_doing_ajax', '__return_true', 999);
            remove_filter('status_header', $status_filter, 999);
            remove_filter('wp_redirect', $redirect_filter, -999);
        }
    }
}

CLIREDAS_Release_Tests::run();
