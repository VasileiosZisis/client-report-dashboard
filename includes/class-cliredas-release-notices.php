<?php
/**
 * Contextual release announcements and administrator dismissal preferences.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/**
 * Keep release news local to the plugin and independent of operational warnings.
 */
final class CLIREDAS_Release_Notices
{
    const OPTION_KEY = 'cliredas_release_state';
    const USER_META_KEY = 'cliredas_dismissed_release_notices';
    const ACTION = 'cliredas_dismiss_release_notice';

    /**
     * Register admin-only release hooks.
     */
    public function __construct()
    {
        add_action('admin_init', array($this, 'track_release'), 5);
        add_action('admin_notices', array($this, 'render_notice'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_' . self::ACTION, array($this, 'ajax_dismiss'));
        add_action('admin_post_' . self::ACTION, array($this, 'post_dismiss'));
    }

    /**
     * Get curated, localized content for the currently installed release.
     *
     * @return array
     */
    public static function get_release_notes()
    {
        if ('1.7.0' !== CLIREDAS_VERSION) {
            return array();
        }
        return array(
            'highlights' => array(
                __('OAuth secrets and tokens now use encrypted storage on supported hosts.', 'cliredas-analytics-dashboard'),
                __('Settings now includes local credential and token diagnostics.', 'cliredas-analytics-dashboard'),
                __('Administrator-only audit history records the latest 100 connection, refresh, cache, and export operations.', 'cliredas-analytics-dashboard'),
            ),
            'detail' => __('Existing plaintext credentials migrate automatically on supported hosts. Preserve your WordPress security salts when restoring or moving a database; changed salts or site context may require re-entering the Client Secret and reconnecting.', 'cliredas-analytics-dashboard'),
        );
    }

    /**
     * Compute the next state without resetting announcements on a downgrade.
     *
     * @param mixed  $old          Stored state.
     * @param bool   $has_settings Whether an installation already has settings.
     * @param string $version      Installed version.
     * @param bool   $has_notes    Whether that version has curated notes.
     * @return array
     */
    public static function next_state($old, $has_settings, $version, $has_notes)
    {
        if (is_array($old) && isset($old['installed_version']) && self::valid_version($old['installed_version'])) {
            if (version_compare($version, $old['installed_version'], '<=')) {
                return $old;
            }
            $has_settings = true;
        }
        return array('installed_version' => $version, 'pending_release' => $has_settings && $has_notes ? $version : '');
    }

    /**
     * Initialize activation state only when it has not been recorded before.
     *
     * @param bool $fresh_install Whether settings were absent before activation.
     * @return void
     */
    public static function initialize_install($fresh_install)
    {
        add_option(self::OPTION_KEY, self::next_state(false, ! $fresh_install, CLIREDAS_VERSION, ! empty(self::get_release_notes())), '', false);
    }

    /**
     * Detect upgrades on an administrator request, including file replacements.
     *
     * @return void
     */
    public function track_release()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $old = get_option(self::OPTION_KEY, false);
            $settings = get_option('cliredas_settings', false);
            $next = self::next_state($old, false !== $settings, CLIREDAS_VERSION, ! empty(self::get_release_notes()));
            if ($old === $next) {
                return;
            }
            if (false === $old) {
                if (add_option(self::OPTION_KEY, $next, '', false)) {
                    return;
                }
                CLIREDAS_Local_Store::invalidate(self::OPTION_KEY);
            } elseif (CLIREDAS_Local_Store::compare_and_swap(self::OPTION_KEY, $old, $next)) {
                return;
            }
        }
    }

    /**
     * Check whether a stored version is safe to compare.
     *
     * @param mixed $version Version value.
     * @return bool
     */
    private static function valid_version($version)
    {
        return is_string($version) && strlen($version) <= 32 && 1 === preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version);
    }

    /**
     * Merge maps defensively, including duplicate rows from concurrent first saves.
     *
     * @param array $records Raw user-meta records.
     * @return array
     */
    public static function merge_dismissals(array $records)
    {
        $map = array();
        foreach ($records as $record) {
            foreach (is_array($record) ? $record : array() as $site => $version) {
                if (! ctype_digit((string) $site) || (int) $site < 1 || ! self::valid_version($version)) {
                    continue;
                }
                if (! isset($map[$site]) || version_compare($version, $map[$site], '>')) {
                    $map[$site] = $version;
                }
            }
        }
        return $map;
    }

    /**
     * Check eligibility without changing preferences or contacting Google.
     *
     * @return bool
     */
    public function should_show()
    {
        if (! current_user_can('manage_options') || empty(self::get_release_notes())) {
            return false;
        }
        $state = get_option(self::OPTION_KEY, array());
        if (! is_array($state) || ! isset($state['pending_release']) || CLIREDAS_VERSION !== $state['pending_release']) {
            return false;
        }
        $dismissals = self::merge_dismissals(get_user_meta(get_current_user_id(), self::USER_META_KEY, false));
        $site = get_current_blog_id();
        return ! isset($dismissals[$site]) || version_compare($dismissals[$site], CLIREDAS_VERSION, '<');
    }

    /**
     * Persist the current user's dismissal with conditional update retries.
     *
     * @param string $version Submitted release.
     * @return true|WP_Error
     */
    public function dismiss($version)
    {
        $state = get_option(self::OPTION_KEY, array());
        if (CLIREDAS_VERSION !== $version || empty(self::get_release_notes()) || ! is_array($state)
            || ! isset($state['pending_release']) || $version !== $state['pending_release']) {
            return new WP_Error('cliredas_invalid_release', __('Invalid release announcement.', 'cliredas-analytics-dashboard'));
        }
        $user = get_current_user_id();
        $site = get_current_blog_id();
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $records = get_user_meta($user, self::USER_META_KEY, false);
            $map = self::merge_dismissals($records);
            if (isset($map[$site]) && version_compare($map[$site], $version, '>=')) {
                return true;
            }
            $map[$site] = $version;
            if (empty($records)) {
                if (add_user_meta($user, self::USER_META_KEY, $map, true)) {
                    return true;
                }
            } else {
                // Feature-written maps are nonempty, so the Metadata API enforces its previous-value condition.
                foreach ($records as $previous) {
                    if (is_array($previous) && ! empty($previous) && update_user_meta($user, self::USER_META_KEY, $map, $previous)) {
                        return true;
                    }
                }
            }
            wp_cache_delete($user, 'user_meta');
        }
        return new WP_Error('cliredas_release_save_failed', __('Could not dismiss this notice. Please try again.', 'cliredas-analytics-dashboard'));
    }

    /**
     * Get the current plugin page, with no request-derived redirects.
     *
     * @return string
     */
    private function current_page()
    {
        $screen = get_current_screen();
        if (! $screen) {
            return '';
        }
        if ('toplevel_page_cliredas-client-report' === $screen->id) {
            return 'dashboard';
        }
        return 'settings_page_cliredas-settings' === $screen->id ? 'settings' : '';
    }

    /**
     * Load release assets only on plugin-owned screens.
     *
     * @return void
     */
    public function enqueue_assets()
    {
        $page = $this->current_page();
        if (! current_user_can('manage_options') || '' === $page || ('dashboard' === $page && ! $this->should_show())) {
            return;
        }
        wp_enqueue_style('cliredas-release-notices', CLIREDAS_PLUGIN_URL . 'assets/css/cliredas-release-notices.css', array(), CLIREDAS_VERSION);
        wp_enqueue_script('cliredas-release-notices', CLIREDAS_PLUGIN_URL . 'assets/js/cliredas-release-notices.js', array('common'), CLIREDAS_VERSION, true);
        wp_localize_script('cliredas-release-notices', 'cliredasReleaseNotice', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'errorMessage' => __('Could not dismiss this notice. Please try again.', 'cliredas-analytics-dashboard'),
        ));
    }

    /**
     * Render release news separately from existing warning notices.
     *
     * @return void
     */
    public function render_notice()
    {
        $page = $this->current_page();
        if ('' === $page || ! $this->should_show()) {
            return;
        }
        $notes = self::get_release_notes();
        $failed = isset($_GET['cliredas_release_dismiss_error'], $_GET['cliredas_release_error_nonce'])
            && '1' === sanitize_text_field(wp_unslash($_GET['cliredas_release_dismiss_error']))
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['cliredas_release_error_nonce'])), 'cliredas_release_error');
        ?>
        <div id="cliredas-release-notice" class="notice notice-info is-dismissible cliredas-release-notice">
            <p><strong><?php echo esc_html(self::title()); ?></strong></p>
            <ul><?php foreach ($notes['highlights'] as $highlight) : ?>
                <li><?php echo esc_html($highlight); ?></li>
            <?php endforeach; ?></ul>
            <div class="cliredas-release-actions">
                <a href="<?php echo esc_url(admin_url('options-general.php?page=cliredas-settings#cliredas-changelog')); ?>"><?php echo esc_html__('View changelog', 'cliredas-analytics-dashboard'); ?></a>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cliredas-release-dismiss-form">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                    <input type="hidden" name="cliredas_release" value="<?php echo esc_attr(CLIREDAS_VERSION); ?>">
                    <input type="hidden" name="cliredas_return_page" value="<?php echo esc_attr($page); ?>">
                    <?php wp_nonce_field(self::ACTION, '_wpnonce', false); ?>
                    <button type="submit" class="button button-secondary"><?php echo esc_html__('Dismiss release notice', 'cliredas-analytics-dashboard'); ?></button>
                </form>
            </div>
            <p class="cliredas-release-error" role="alert" <?php echo ! $failed ? 'hidden' : ''; ?>><?php echo $failed ? esc_html__('Could not dismiss this notice. Please try again.', 'cliredas-analytics-dashboard') : ''; ?></p>
        </div>
        <?php
    }

    /**
     * Get a translated release heading.
     *
     * @return string
     */
    private static function title()
    {
        /* translators: %s: Cliredas release version. */
        return sprintf(__("What's new in Cliredas %s", 'cliredas-analytics-dashboard'), CLIREDAS_VERSION);
    }

    /**
     * Render permanent local notes, also usable without JavaScript.
     *
     * @return void
     */
    public static function render_changelog()
    {
        $notes = self::get_release_notes();
        if (! current_user_can('manage_options') || empty($notes)) {
            return;
        }
        ?>
        <details id="cliredas-changelog" class="cliredas-release-changelog">
            <summary><?php echo esc_html__('Release notes', 'cliredas-analytics-dashboard'); ?></summary>
            <h3><?php echo esc_html(self::title()); ?></h3>
            <ul><?php foreach ($notes['highlights'] as $highlight) : ?>
                <li><?php echo esc_html($highlight); ?></li>
            <?php endforeach; ?></ul>
            <p><?php echo esc_html($notes['detail']); ?></p>
        </details>
        <?php
    }

    /**
     * Dismiss through the authenticated AJAX action.
     *
     * @return void
     */
    public function ajax_dismiss()
    {
        check_ajax_referer(self::ACTION, '_wpnonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'cliredas-analytics-dashboard')), 403);
        }
        $version = isset($_POST['cliredas_release']) ? sanitize_text_field(wp_unslash($_POST['cliredas_release'])) : '';
        $result = $this->dismiss($version);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()), 'cliredas_invalid_release' === $result->get_error_code() ? 400 : 500);
        }
        wp_send_json_success();
    }

    /**
     * Dismiss through a POST form when JavaScript is unavailable.
     *
     * @return void
     */
    public function post_dismiss()
    {
        check_admin_referer(self::ACTION);
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'cliredas-analytics-dashboard'), '', array('response' => 403));
        }
        $version = isset($_POST['cliredas_release']) ? sanitize_text_field(wp_unslash($_POST['cliredas_release'])) : '';
        $result = $this->dismiss($version);
        if (is_wp_error($result) && 'cliredas_invalid_release' === $result->get_error_code()) {
            wp_die(esc_html($result->get_error_message()), '', array('response' => 400));
        }
        $page = isset($_POST['cliredas_return_page']) ? sanitize_key(wp_unslash($_POST['cliredas_return_page'])) : '';
        $url = 'dashboard' === $page ? admin_url('admin.php?page=cliredas-client-report') : admin_url('options-general.php?page=cliredas-settings');
        if (is_wp_error($result)) {
            $url = add_query_arg(array('cliredas_release_dismiss_error' => '1', 'cliredas_release_error_nonce' => wp_create_nonce('cliredas_release_error')), $url);
        }
        wp_safe_redirect($url);
        exit;
    }
}
