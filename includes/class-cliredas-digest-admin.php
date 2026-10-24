<?php
/**
 * Administrator-only digest settings and explicit one-off actions.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/** Render independent Settings API and admin-post forms on the plugin page. */
final class CLIREDAS_Digest_Admin
{
    /** Digest service.
     *
     * @var CLIREDAS_Weekly_Digest
     */
    private $digest;

    /** Register admin-only callbacks once via the admin bootstrap. */
    public function __construct(?CLIREDAS_Weekly_Digest $digest = null)
    {
        $this->digest = $digest ? $digest : new CLIREDAS_Weekly_Digest();
        add_action('admin_init', array($this, 'register_settings'));
        add_action('cliredas_after_settings_form', array($this, 'render'));
        add_action('admin_post_cliredas_digest_test', array($this, 'handle_test'));
        add_action('admin_post_cliredas_digest_disable', array($this, 'handle_disable'));
    }

    /** Register a separate option/group; digest consent never shares the OAuth form. */
    public function register_settings()
    {
        register_setting('cliredas_digest', CLIREDAS_Weekly_Digest::SETTINGS, array(
            'type' => 'array', 'sanitize_callback' => array($this->digest, 'sanitize_settings'),
        ));
        add_filter('pre_update_option_' . CLIREDAS_Weekly_Digest::SETTINGS, array($this, 'persist_settings'), 10, 2);
    }

    /** Keep Settings API validation while preventing an unconditional core write. */
    public function persist_settings($value, $old)
    {
        if ($value === $old) {
            return $old;
        }
        $saved = false === $old ? add_option(CLIREDAS_Weekly_Digest::SETTINGS, $value, '', false)
            : CLIREDAS_Local_Store::compare_and_swap(CLIREDAS_Weekly_Digest::SETTINGS, $old, $value);
        if ($saved) {
            $this->digest->reconcile();
        } else {
            add_settings_error(CLIREDAS_Weekly_Digest::SETTINGS, 'cliredas_digest_save_failed', __('Digest settings could not be saved or changed in another request. Reload this page and try again.', 'cliredas-analytics-dashboard'));
        }
        // The conditional write owns persistence; returning the old value stops update_option().
        return $old;
    }

    /** Render plain, keyboard-accessible controls without nesting forms. */
    public function render()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $config = $this->digest->settings();
        $state = $this->digest->state();
        ?>
        <hr>
        <section aria-labelledby="cliredas-digest-heading" id="cliredas-digest-settings">
            <h2 id="cliredas-digest-heading"><?php echo esc_html__('Weekly owner digest', 'cliredas-analytics-dashboard'); ?></h2>
            <p><?php echo esc_html__('Opt in to one unbranded plain-text summary every Monday at 08:00 in the site timezone. No email is sent when enabling this setting.', 'cliredas-analytics-dashboard'); ?></p>
            <p><?php echo esc_html__('The recipient receives site analytics and a dashboard link. Only complete fresh GA4 data is emailed; current-day results may still be processing. Actual sending depends on WP-Cron and the site mail transport.', 'cliredas-analytics-dashboard'); ?></p>
            <?php settings_errors(CLIREDAS_Weekly_Digest::SETTINGS); ?>
            <?php $this->render_action_result(); ?>
            <form method="post" action="options.php">
                <?php settings_fields('cliredas_digest'); ?>
                <p><label><input type="checkbox" name="cliredas_digest_settings[enabled]" value="1" <?php checked(1, $config['enabled']); ?>> <?php echo esc_html__('Enable weekly owner digest', 'cliredas-analytics-dashboard'); ?></label></p>
                <p><label for="cliredas-digest-recipient"><?php echo esc_html__('Recipient email', 'cliredas-analytics-dashboard'); ?></label><br>
                    <input type="email" id="cliredas-digest-recipient" class="regular-text" name="cliredas_digest_settings[recipient]" value="<?php echo esc_attr($config['recipient']); ?>" required aria-describedby="cliredas-digest-recipient-help">
                </p>
                <p id="cliredas-digest-recipient-help"><?php echo esc_html__('Save recipient changes before sending a test. A test sends once to the saved address even when weekly delivery is disabled.', 'cliredas-analytics-dashboard'); ?></p>
                <?php submit_button(__('Save digest settings', 'cliredas-analytics-dashboard'), 'primary', 'cliredas_digest_save', false); ?>
            </form>
            <p><strong><?php echo esc_html__('Next scheduled run:', 'cliredas-analytics-dashboard'); ?></strong> <?php echo esc_html($config['enabled'] && $state && $state['next_due'] ? wp_date('Y-m-d H:i:s T', $state['next_due']) : __('Disabled', 'cliredas-analytics-dashboard')); ?></p>
            <?php if ($state && ! empty($state['schedule_error'])) : ?>
                <p role="alert"><?php echo esc_html__('The digest event could not be scheduled. Check WP-Cron configuration and database access. Scheduling will be retried after five minutes on a site request.', 'cliredas-analytics-dashboard'); ?></p>
            <?php endif; ?>
            <?php foreach (array('last_weekly' => __('Last weekly outcome:', 'cliredas-analytics-dashboard'), 'last_test' => __('Last test outcome:', 'cliredas-analytics-dashboard')) as $key => $label) : ?>
                <?php $result = $state && isset($state[$key]) ? $state[$key] : array(); ?>
                <p><strong><?php echo esc_html($label); ?></strong> <?php echo esc_html(CLIREDAS_Weekly_Digest::outcome_label(isset($result['outcome']) ? $result['outcome'] : '')); ?>
                    <?php if (! empty($result['timestamp'])) : ?>
                        <span><?php echo esc_html(wp_date('Y-m-d H:i:s T', $result['timestamp'])); ?></span>
                    <?php endif; ?>
                </p>
            <?php endforeach; ?>
            <p><?php echo esc_html__('A late run is allowed only on its scheduled Monday. Missed Mondays are skipped. Mail handoff is not confirmation of inbox delivery; uncertain attempts are not retried automatically.', 'cliredas-analytics-dashboard'); ?></p>
            <?php foreach (array('test' => __('Send test email', 'cliredas-analytics-dashboard'), 'disable' => __('Disable digest', 'cliredas-analytics-dashboard')) as $action => $label) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cliredas-digest-action">
                    <input type="hidden" name="action" value="<?php echo esc_attr('cliredas_digest_' . $action); ?>">
                    <?php wp_nonce_field('cliredas_digest_' . $action); ?>
                    <?php submit_button($label, 'secondary', 'cliredas_digest_' . $action, false); ?>
                </form>
            <?php endforeach; ?>
        </section>
        <?php
    }

    /** Safe nonce-gated feedback; arbitrary error strings are never rendered. */
    private function render_action_result()
    {
        if (! isset($_GET['cliredas_digest_nonce'], $_GET['cliredas_digest_result']) || ! is_string($_GET['cliredas_digest_nonce']) || ! is_string($_GET['cliredas_digest_result'])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['cliredas_digest_nonce'])), 'cliredas_digest_result')) {
            return;
        }
        $code = sanitize_key(wp_unslash($_GET['cliredas_digest_result']));
        $messages = array(
            'test_sent' => __('Test email handed to mail transport. Inbox delivery is not confirmed.', 'cliredas-analytics-dashboard'),
            'disabled' => __('Weekly digest disabled. Future digest events were removed.', 'cliredas-analytics-dashboard'),
            'cliredas_digest_busy' => __('A digest attempt is already running or was just requested. Check its outcome before trying again.', 'cliredas-analytics-dashboard'),
            'cliredas_digest_mail_failed' => __('Mail handoff failed. Check the site mail transport and try a test send.', 'cliredas-analytics-dashboard'),
            'cliredas_digest_mail_unknown' => CLIREDAS_Weekly_Digest::outcome_label('mail_unknown'),
            'cliredas_digest_storage' => __('Digest state could not be saved. Check database access and try again.', 'cliredas-analytics-dashboard'),
            'cliredas_digest_configuration' => __('Save one valid recipient and configure the GA4 connection before sending a test.', 'cliredas-analytics-dashboard'),
        );
        $message = isset($messages[$code]) ? $messages[$code] : __('The digest was not sent. Review the last outcome and GA4 connection diagnostics.', 'cliredas-analytics-dashboard');
        echo '<p role="status">' . esc_html($message) . '</p>';
    }

    /** Explicit test-send intent, then authorization, then work. */
    public function handle_test()
    {
        check_admin_referer('cliredas_digest_test');
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'cliredas-analytics-dashboard'), '', array('response' => 403));
        }
        $result = $this->digest->send_test();
        $this->redirect(is_wp_error($result) ? $result->get_error_code() : 'test_sent');
    }

    /** Explicit disable intent; administrators can revoke consent immediately. */
    public function handle_disable()
    {
        check_admin_referer('cliredas_digest_disable');
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'cliredas-analytics-dashboard'), '', array('response' => 403));
        }
        $result = $this->digest->disable();
        $this->redirect(is_wp_error($result) ? $result->get_error_code() : 'disabled');
    }

    /** Redirect only to the fixed plugin-owned settings destination. */
    private function redirect($code)
    {
        $url = add_query_arg(array('cliredas_digest_result' => sanitize_key($code), 'cliredas_digest_nonce' => wp_create_nonce('cliredas_digest_result')), admin_url('options-general.php?page=cliredas-settings'));
        wp_safe_redirect($url . '#cliredas-digest-settings');
        exit;
    }
}
