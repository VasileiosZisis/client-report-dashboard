<?php

/**
 * Settings handler.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

/**
 * Register and render the plugin settings.
 */
final class CLIREDAS_Settings
{

    /**
     * Option key.
     *
     * @var string
     */
    const OPTION_KEY = 'cliredas_settings';

    /**
     * Settings group (Settings API).
     *
     * @var string
     */
    const SETTINGS_GROUP = 'cliredas_settings_group';

    /**
     * Settings page slug.
     *
     * @var string
     */
    const SETTINGS_PAGE_SLUG = 'cliredas-settings';

    /**
     * Defaults.
     *
     * @var array
     */
    private $defaults = array(
        'allow_editors'      => 0,
        'ga4_connected'      => 0,
        'ga4_client_id'         => '',
        'ga4_client_secret'     => '',
        'ga4_redirect_base_url' => '',
        'ga4_property_id'       => '',
        'ga4_refresh_token'     => '',
        'ga4_access_token'      => '',
        'ga4_token_expires'     => 0,
    );

    /**
     * Shared GA4 API client.
     *
     * @var CLIREDAS_GA4_Client
     */
    private $ga4_client;

    /**
     * Setup diagnostics service.
     *
     * @var CLIREDAS_Setup_Diagnostics
     */
    private $setup_diagnostics;

    public function __construct()
    {
        $this->ga4_client        = new CLIREDAS_GA4_Client($this);
        $this->setup_diagnostics = new CLIREDAS_Setup_Diagnostics($this, $this->ga4_client);

        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_options_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue assets for plugin settings screens only.
     *
     * @param string $hook_suffix Current admin hook suffix.
     * @return void
     */
    public function enqueue_assets($hook_suffix)
    {
        if (! is_string($hook_suffix)) {
            return;
        }

        $is_core_settings_screen = ('settings_page_' . self::SETTINGS_PAGE_SLUG) === $hook_suffix;
        $is_client_report_settings_submenu = false !== strpos($hook_suffix, 'cliredas-settings-shortcut');

        if (! $is_core_settings_screen && ! $is_client_report_settings_submenu) {
            return;
        }

        CLIREDAS_Assets::enqueue_settings_assets();
    }

    /**
     * Register settings + fields.
     *
     * @return void
     */
    public function register_settings()
    {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default'           => $this->defaults,
            )
        );

        add_settings_section(
            'cliredas_section_connection',
            __('GA4 Connection', 'cliredas-analytics-dashboard'),
            array($this, 'render_connection_section'),
            self::SETTINGS_PAGE_SLUG
        );

        add_settings_field(
            'cliredas_ga4_client_id',
            __('OAuth Client ID', 'cliredas-analytics-dashboard'),
            array($this, 'render_ga4_client_id_field'),
            self::SETTINGS_PAGE_SLUG,
            'cliredas_section_connection'
        );

        add_settings_field(
            'cliredas_ga4_client_secret',
            __('OAuth Client Secret', 'cliredas-analytics-dashboard'),
            array($this, 'render_ga4_client_secret_field'),
            self::SETTINGS_PAGE_SLUG,
            'cliredas_section_connection'
        );

        add_settings_field(
            'cliredas_ga4_redirect_base_url',
            __('Public OAuth base URL', 'cliredas-analytics-dashboard'),
            array($this, 'render_ga4_redirect_base_url_field'),
            self::SETTINGS_PAGE_SLUG,
            'cliredas_section_connection'
        );

        add_settings_field(
            'cliredas_ga4_redirect_uri',
            __('Redirect URI', 'cliredas-analytics-dashboard'),
            array($this, 'render_ga4_redirect_uri_field'),
            self::SETTINGS_PAGE_SLUG,
            'cliredas_section_connection'
        );

        add_settings_field(
            'cliredas_ga4_property_id',
            __('GA4 Property', 'cliredas-analytics-dashboard'),
            array($this, 'render_ga4_property_field'),
            self::SETTINGS_PAGE_SLUG,
            'cliredas_section_connection'
        );

        add_settings_field(
            'cliredas_connection_status',
            __('Status', 'cliredas-analytics-dashboard'),
            array($this, 'render_connection_status_field'),
            self::SETTINGS_PAGE_SLUG,
            'cliredas_section_connection'
        );

        add_settings_section(
            'cliredas_section_access',
            __('Access Control', 'cliredas-analytics-dashboard'),
            array($this, 'render_access_section'),
            self::SETTINGS_PAGE_SLUG
        );

        add_settings_field(
            'cliredas_allow_editors',
            __('Dashboard visibility', 'cliredas-analytics-dashboard'),
            array($this, 'render_allow_editors_field'),
            self::SETTINGS_PAGE_SLUG,
            'cliredas_section_access'
        );

    }

    /**
     * Add Settings → Client Report page.
     *
     * @return void
     */
    public function add_options_page()
    {
        add_options_page(
            __('Client Report Settings', 'cliredas-analytics-dashboard'),
            __('Client Report', 'cliredas-analytics-dashboard'),
            'manage_options',
            self::SETTINGS_PAGE_SLUG,
            array($this, 'render_settings_page')
        );
    }

    /**
     * Get settings merged with defaults.
     *
     * @return array
     */
    public function get_settings()
    {
        $stored = get_option(self::OPTION_KEY, array());
        if (! is_array($stored)) {
            $stored = array();
        }

        return wp_parse_args($stored, $this->defaults);
    }

    /**
     * Determine required capability for viewing dashboard/menu.
     *
     * - Admins by default (manage_options)
     * - Optionally Editors too (edit_pages)
     *
     * @param string $context Context string (e.g. 'dashboard', 'menu').
     * @return string
     */
    public function get_required_capability($context = 'dashboard')
    {
        $settings = $this->get_settings();

        $capability = 'manage_options';

        if (! empty($settings['allow_editors'])) {
            $capability = 'edit_pages';
        }

        /**
         * Filter the capability required for a given context.
         *
         * @param string $capability Capability.
         * @param string $context    Context (dashboard/menu/etc).
         * @param array  $settings   Current settings.
         */
        return (string) apply_filters('cliredas_required_capability', $capability, $context, $settings);
    }

    /**
     * Sanitize settings input.
     *
     * @param mixed $input Input from Settings API.
     * @return array
     */
    public function sanitize_settings($input)
    {
        $existing  = $this->get_settings();
        $sanitized = $existing;

        if (is_array($input)) {
            $clear_secret = ! empty($input['cliredas_clear_ga4_client_secret']);

            $sanitized['allow_editors'] = ! empty($input['allow_editors']) ? 1 : 0;

            if (isset($input['ga4_client_id'])) {
                $sanitized['ga4_client_id'] = sanitize_text_field(wp_unslash($input['ga4_client_id']));
            }

            if (isset($input['ga4_redirect_base_url'])) {
                $sanitized['ga4_redirect_base_url'] = $this->sanitize_ga4_redirect_base_url(
                    wp_unslash($input['ga4_redirect_base_url']),
                    isset($existing['ga4_redirect_base_url']) ? (string) $existing['ga4_redirect_base_url'] : ''
                );
            }

            // Client secret: don't wipe on blank saves, but allow explicit clearing.
            if ($clear_secret) {
                $sanitized['ga4_client_secret'] = '';
            } elseif (isset($input['ga4_client_secret'])) {
                // Only update secret when user actually enters a new one.
                $new_secret = trim((string) wp_unslash($input['ga4_client_secret']));
                if ('' !== $new_secret) {
                    $sanitized['ga4_client_secret'] = sanitize_text_field($new_secret);
                }
            }

            // Internal GA4 auth fields (set by the OAuth flow / programmatic updates).
            if (isset($input['ga4_connected'])) {
                $sanitized['ga4_connected'] = ! empty($input['ga4_connected']) ? 1 : 0;
            }

            if (isset($input['ga4_property_id'])) {
                $property_id = sanitize_text_field(wp_unslash($input['ga4_property_id']));
                $property_id = trim($property_id);

                // Normalize to the GA4 Admin API/Data API format: properties/123456789.
                if ('' === $property_id) {
                    $sanitized['ga4_property_id'] = '';
                } elseif (preg_match('/^\\d+$/', $property_id)) {
                    $sanitized['ga4_property_id'] = 'properties/' . $property_id;
                } else {
                    $sanitized['ga4_property_id'] = $property_id;
                }
            }

            if (isset($input['ga4_refresh_token'])) {
                $sanitized['ga4_refresh_token'] = sanitize_text_field(wp_unslash($input['ga4_refresh_token']));
            }

            if (isset($input['ga4_access_token'])) {
                $sanitized['ga4_access_token'] = sanitize_text_field(wp_unslash($input['ga4_access_token']));
            }

            if (isset($input['ga4_token_expires'])) {
                $sanitized['ga4_token_expires'] = absint(wp_unslash($input['ga4_token_expires']));
            }
        }

        return wp_parse_args($sanitized, $this->defaults);
    }

    /**
     * Section description.
     *
     * @return void
     */
    public function render_access_section()
    {
        echo '<p>' . esc_html__('Control who can view the Client Report dashboard.', 'cliredas-analytics-dashboard') . '</p>';
    }

    /**
     * Checkbox field.
     *
     * @return void
     */
    public function render_allow_editors_field()
    {
        $settings     = $this->get_settings();
        $allow_editors = ! empty($settings['allow_editors']) ? 1 : 0;

?>
        <label for="cliredas_allow_editors">
            <input type="checkbox" id="cliredas_allow_editors" name="<?php echo esc_attr(self::OPTION_KEY); ?>[allow_editors]" value="1" <?php checked(1, $allow_editors); ?> />
            <?php echo esc_html__('Show dashboard to Editors as well as Administrators', 'cliredas-analytics-dashboard'); ?>
        </label>
    <?php
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'cliredas-analytics-dashboard'));
        }
    ?>
        <div class="wrap cliredas-settings-wrap">
            <h1><?php echo esc_html__('Client Report Settings', 'cliredas-analytics-dashboard'); ?></h1>

            <?php
            $ga4_notice = '';
            $ga4_error = '';
            $ga4_error_desc = '';

            if (
                isset($_GET['cliredas_ga4_notice_nonce']) &&
                wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['cliredas_ga4_notice_nonce'])), 'cliredas_ga4_notice')
            ) {
                $ga4_notice = isset($_GET['cliredas_ga4_notice']) ? sanitize_key(wp_unslash($_GET['cliredas_ga4_notice'])) : '';
                $ga4_error = isset($_GET['cliredas_ga4_error']) ? sanitize_key(wp_unslash($_GET['cliredas_ga4_error'])) : '';
                $ga4_error_desc = isset($_GET['cliredas_ga4_error_desc']) ? sanitize_text_field(wp_unslash($_GET['cliredas_ga4_error_desc'])) : '';
            }

	            $ga4_notice_message = '';
	            $ga4_notice_class   = '';

	            if ('' !== $ga4_error) {
	                $ga4_notice_class = 'notice notice-error is-dismissible';

	                if (0 === strpos($ga4_error, 'oauth_') && 'oauth_access_denied' !== $ga4_error) {
	                    $oauth_code = substr($ga4_error, strlen('oauth_'));
	                    $oauth_code = sanitize_key($oauth_code);

	                    $ga4_notice_message = sprintf(
	                        /* translators: %s: Google OAuth error code */
	                        __('Google OAuth error: %s', 'cliredas-analytics-dashboard'),
	                        $oauth_code ? $oauth_code : __('unknown', 'cliredas-analytics-dashboard')
	                    );
	                }

	                switch ($ga4_error) {
	                    case 'missing_client_id':
	                        $ga4_notice_message = __('Missing OAuth Client ID. Save your Client ID first, then click Connect again.', 'cliredas-analytics-dashboard');
	                        break;
	                    case 'missing_client_secret':
	                        $ga4_notice_message = __('Missing OAuth Client Secret. Save your Client Secret first, then click Connect again.', 'cliredas-analytics-dashboard');
	                        break;
                    case 'missing_code':
                        $ga4_notice_message = __('OAuth callback did not include an authorization code. Please try connecting again.', 'cliredas-analytics-dashboard');
                        break;
                    case 'invalid_code':
                        $ga4_notice_message = __('OAuth callback returned an invalid authorization code. Please try connecting again.', 'cliredas-analytics-dashboard');
                        break;
                    case 'missing_state':
                        $ga4_notice_message = __('OAuth callback is missing state verification. Please try connecting again.', 'cliredas-analytics-dashboard');
                        break;
	                    case 'invalid_state':
	                        $ga4_notice_message = __('OAuth state verification failed. Please try connecting again.', 'cliredas-analytics-dashboard');
	                        break;
	                    case 'missing_refresh_token':
	                        $ga4_notice_message = __('Connected, but Google did not return a refresh token. Please reconnect and approve access again.', 'cliredas-analytics-dashboard');
	                        break;
	                    case 'token_exchange_failed':
	                        $ga4_notice_message = __('Token exchange failed. Please try connecting again.', 'cliredas-analytics-dashboard');
	                        break;
	                    case 'token_response_invalid':
	                        $ga4_notice_message = __('Token exchange failed due to an invalid response. Please try again.', 'cliredas-analytics-dashboard');
	                        break;
	                    case 'token_missing_access_token':
	                        $ga4_notice_message = __('Token exchange failed (missing access token). Please try again.', 'cliredas-analytics-dashboard');
	                        break;
	                    case 'oauth_access_denied':
	                        $ga4_notice_message = __('You denied access on the Google consent screen.', 'cliredas-analytics-dashboard');
	                        break;
	                    default:
	                        if ('' === $ga4_notice_message) {
	                            $ga4_notice_message = __('GA4 connection failed. Please try again.', 'cliredas-analytics-dashboard');
	                        }
	                        break;
	                }
	            } elseif ('' !== $ga4_notice) {
	                $ga4_notice_class = 'notice notice-success is-dismissible';

	                switch ($ga4_notice) {
	                    case 'callback_reached':
	                        $ga4_notice_class   = 'notice notice-info is-dismissible';
	                        $ga4_notice_message = __('Google OAuth callback received. Token exchange will be implemented in the next milestone.', 'cliredas-analytics-dashboard');
	                        break;
	                    case 'connected':
	                        $ga4_notice_message = __('Connected to Google Analytics.', 'cliredas-analytics-dashboard');
	                        break;
	                    case 'secret_cleared':
	                        $ga4_notice_message = __('Client secret cleared. GA4 has been disconnected.', 'cliredas-analytics-dashboard');
	                        break;
	                    case 'disconnected':
	                        $ga4_notice_message = __('Disconnected from Google Analytics.', 'cliredas-analytics-dashboard');
	                        break;
	                    default:
	                        $ga4_notice_message = __('GA4 status updated.', 'cliredas-analytics-dashboard');
	                        break;
	                }
	            }
	            ?>

	            <?php if ('' !== $ga4_notice_message) : ?>
	                <div class="<?php echo esc_attr($ga4_notice_class); ?>">
	                    <p><?php echo esc_html($ga4_notice_message); ?></p>
	                    <?php if ('' !== $ga4_error_desc && '' !== $ga4_error) : ?>
	                        <p class="description"><?php echo esc_html($ga4_error_desc); ?></p>
	                    <?php endif; ?>
	                </div>
	            <?php endif; ?>

            <?php
            $cache_cleared = null;
            if (
                isset($_GET['cliredas_cache_cleared_nonce']) &&
                wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['cliredas_cache_cleared_nonce'])), 'cliredas_cache_cleared')
            ) {
                $cache_cleared = isset($_GET['cliredas_cache_cleared']) ? absint(wp_unslash($_GET['cliredas_cache_cleared'])) : null;
            }
            ?>
            <?php if (null !== $cache_cleared) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: number of cache entries cleared */
                                __('Cached reports cleared (%d).', 'cliredas-analytics-dashboard'),
                                $cache_cleared
                            )
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php
            $diagnostics_complete = false;
            if (
                isset($_GET['cliredas_diagnostics_nonce'])
                && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['cliredas_diagnostics_nonce'])), 'cliredas_diagnostics_notice')
            ) {
                $diagnostics_complete = isset($_GET['cliredas_diagnostics'])
                    && 'complete' === sanitize_key(wp_unslash($_GET['cliredas_diagnostics']));
            }

            $this->render_setup_assistant($diagnostics_complete);
            ?>

            <?php
            // Only show errors (not the success message).
            $settings_updated = filter_input(INPUT_GET, 'settings-updated', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if (! is_string($settings_updated) || '' === $settings_updated) {
                settings_errors();
            }
            ?>

            <form method="post" action="options.php">
                <?php
                settings_fields(self::SETTINGS_GROUP);          // <-- REQUIRED
                do_settings_sections(self::SETTINGS_PAGE_SLUG); // <-- REQUIRED
                submit_button();
                ?>
            </form>

            <hr />

            <h2><?php echo esc_html__('Tools', 'cliredas-analytics-dashboard'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="cliredas_clear_cache">
                <?php wp_nonce_field('cliredas_clear_cache'); ?>
                <?php submit_button(__('Clear cached reports', 'cliredas-analytics-dashboard'), 'secondary', 'submit', false); ?>
            </form>
        </div>
    <?php
    }

    /**
     * Render GA4 connection section description.
     *
     * @return void
     */
    public function render_connection_section()
    {
        echo '<p>' . esc_html__('Connect Google Analytics 4 to display real analytics data on the dashboard.', 'cliredas-analytics-dashboard') . '</p>';
    }

    /**
     * Render the inline GA4 setup assistant.
     *
     * @param bool $diagnostics_complete Whether this request follows a diagnostic run.
     * @return void
     */
    private function render_setup_assistant($diagnostics_complete)
    {
        $state        = $this->setup_diagnostics->get_state();
        $checks       = $state['checks'];
        $passed       = (int) $state['passed'];
        $total        = (int) $state['total'];
        $is_complete  = $passed === $total;
        $connect_url  = wp_nonce_url(
            admin_url('admin-post.php?action=cliredas_ga4_connect'),
            'cliredas_ga4_connect'
        );
        $redirect_uri = $this->get_ga4_redirect_uri();
        $status_labels = array(
            'pass'    => __('Passed', 'cliredas-analytics-dashboard'),
            'warning' => __('Warning', 'cliredas-analytics-dashboard'),
            'fail'    => __('Needs attention', 'cliredas-analytics-dashboard'),
            'blocked' => __('Blocked', 'cliredas-analytics-dashboard'),
            'not_run' => __('Not checked', 'cliredas-analytics-dashboard'),
        );
        $status_icons = array(
            'pass'    => 'dashicons-yes-alt',
            'warning' => 'dashicons-warning',
            'fail'    => 'dashicons-dismiss',
            'blocked' => 'dashicons-lock',
            'not_run' => 'dashicons-marker',
        );
        ?>
        <details class="cliredas-setup-assistant" <?php echo (! $is_complete || $diagnostics_complete) ? 'open' : ''; ?>>
            <summary class="cliredas-setup-summary">
                <span class="cliredas-setup-summary-title"><?php echo esc_html__('GA4 Setup Assistant', 'cliredas-analytics-dashboard'); ?></span>
                <span class="cliredas-setup-progress">
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: 1: passed setup checks, 2: total setup checks. */
                            __('%1$d of %2$d checks passed', 'cliredas-analytics-dashboard'),
                            $passed,
                            $total
                        )
                    );
                    ?>
                </span>
            </summary>

            <div class="cliredas-setup-content">
                <p><?php echo esc_html__('Review the connection steps below. Remote checks run only when you select Run diagnostics.', 'cliredas-analytics-dashboard'); ?></p>

                <?php if ($diagnostics_complete) : ?>
                    <div class="notice notice-info inline">
                        <p><?php echo esc_html__('GA4 diagnostics completed.', 'cliredas-analytics-dashboard'); ?></p>
                    </div>
                <?php endif; ?>

                <ol class="cliredas-setup-checks">
                    <?php foreach ($checks as $key => $check) : ?>
                        <?php
                        $status = isset($check['status'], $status_labels[$check['status']]) ? $check['status'] : 'not_run';
                        $category = isset($check['category']) ? sanitize_key($check['category']) : '';
                        ?>
                        <li class="cliredas-setup-check is-<?php echo esc_attr($status); ?>">
                            <span class="dashicons <?php echo esc_attr($status_icons[$status]); ?>" aria-hidden="true"></span>
                            <div class="cliredas-setup-check-body">
                                <div class="cliredas-setup-check-heading">
                                    <strong><?php echo esc_html($check['label']); ?></strong>
                                    <span class="cliredas-setup-status">
                                        <?php echo esc_html($status_labels[$status]); ?>
                                    </span>
                                    <?php if ('pass' !== $status && '' !== $category) : ?>
                                        <span class="cliredas-setup-category"><?php echo esc_html($this->get_diagnostic_category_label($category)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p><?php echo esc_html($check['message']); ?></p>
                                <?php $this->render_setup_action((string) $key, (string) $check['action'], $connect_url, $redirect_uri); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>

                <div class="cliredas-setup-footer">
                    <form class="cliredas-diagnostics-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="cliredas_run_diagnostics" />
                        <?php wp_nonce_field('cliredas_run_diagnostics'); ?>
                        <button type="submit" class="button button-secondary" data-running-label="<?php echo esc_attr__('Running diagnostics...', 'cliredas-analytics-dashboard'); ?>">
                            <?php echo esc_html__('Run diagnostics', 'cliredas-analytics-dashboard'); ?>
                        </button>
                    </form>

                    <?php if (! empty($state['ran_at'])) : ?>
                        <p class="description">
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %s: date and time of the last diagnostic run. */
                                    __('Last remote check: %s', 'cliredas-analytics-dashboard'),
                                    wp_date(
                                        get_option('date_format') . ' ' . get_option('time_format'),
                                        (int) $state['ran_at']
                                    )
                                )
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </details>
        <?php
    }

    /**
     * Render a corrective action for one setup check.
     *
     * @param string $check_key    Check key.
     * @param string $action       Action key.
     * @param string $connect_url  Protected connect URL.
     * @param string $redirect_uri Computed redirect URI.
     * @return void
     */
    private function render_setup_action($check_key, $action, $connect_url, $redirect_uri)
    {
        if ('public_url' === $action) {
            echo '<p class="cliredas-setup-action"><a href="#cliredas_ga4_redirect_base_url">' . esc_html__('Review Public OAuth base URL', 'cliredas-analytics-dashboard') . '</a></p>';
            return;
        }

        if ('credentials' === $action) {
            echo '<p class="cliredas-setup-action"><a href="#cliredas_ga4_client_id">' . esc_html__('Review OAuth credentials', 'cliredas-analytics-dashboard') . '</a></p>';
            return;
        }

        if ('connection' === $action) {
            echo '<p class="cliredas-setup-action"><a class="button button-small" href="' . esc_url($connect_url) . '">' . esc_html__('Connect or reconnect Google Analytics', 'cliredas-analytics-dashboard') . '</a></p>';
            return;
        }

        if ('property' === $action) {
            echo '<p class="cliredas-setup-action"><a href="#cliredas_ga4_property_id">' . esc_html__('Review GA4 property', 'cliredas-analytics-dashboard') . '</a></p>';
            return;
        }

        if ('redirect_uri' === $action && 'redirect_uri' === $check_key) {
            ?>
            <div class="cliredas-redirect-action">
                <code id="cliredas-setup-redirect-uri"><?php echo esc_html($redirect_uri); ?></code>
                <button type="button"
                    class="button button-small cliredas-copy-button"
                    data-copy-target="cliredas-setup-redirect-uri"
                    data-copied-label="<?php echo esc_attr__('Copied', 'cliredas-analytics-dashboard'); ?>">
                    <?php echo esc_html__('Copy URI', 'cliredas-analytics-dashboard'); ?>
                </button>
                <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html__('Open Google Cloud credentials', 'cliredas-analytics-dashboard'); ?>
                </a>
                <span class="screen-reader-text cliredas-copy-status" aria-live="polite"></span>
            </div>
            <?php
        }
    }

    /**
     * Get a user-facing diagnostic category label.
     *
     * @param string $category Diagnostic category.
     * @return string
     */
    private function get_diagnostic_category_label($category)
    {
        $labels = array(
            'configuration'      => __('Configuration', 'cliredas-analytics-dashboard'),
            'authentication'     => __('Authentication', 'cliredas-analytics-dashboard'),
            'permission'         => __('Permission', 'cliredas-analytics-dashboard'),
            'quota'              => __('Quota', 'cliredas-analytics-dashboard'),
            'property_selection' => __('Property selection', 'cliredas-analytics-dashboard'),
            'network'            => __('Network', 'cliredas-analytics-dashboard'),
            'google_service'     => __('Google service', 'cliredas-analytics-dashboard'),
        );

        return isset($labels[$category]) ? $labels[$category] : __('Setup', 'cliredas-analytics-dashboard');
    }

    /**
     * Render GA4 connection status field.
     *
     * @return void
     */
    public function render_connection_status_field()
    {
        $settings    = $this->get_settings();
        $connected   = $this->is_ga4_connected();

        $client_id   = isset($settings['ga4_client_id']) ? trim((string) $settings['ga4_client_id']) : '';
        $can_connect = ('' !== $client_id);

        $status_text = $connected
            ? __('Connected', 'cliredas-analytics-dashboard')
            : __('Not connected', 'cliredas-analytics-dashboard');

        $connect_url = wp_nonce_url(
            admin_url('admin-post.php?action=cliredas_ga4_connect'),
            'cliredas_ga4_connect'
        );

        $disconnect_url = wp_nonce_url(
            admin_url('admin-post.php?action=cliredas_ga4_disconnect'),
            'cliredas_ga4_disconnect'
        );

        $token_error_message = '';
        if ($connected) {
            $token = $this->ga4_client->get_valid_access_token();
            if (is_wp_error($token)) {
                $status_text = __('Connected (reconnect required)', 'cliredas-analytics-dashboard');
                $token_error_message = trim((string) $token->get_error_message());
                if ('' !== $token_error_message) {
                    $token_error_message = sanitize_text_field($token_error_message);
                    $token_error_message = substr($token_error_message, 0, 200);
                } else {
                    $token_error_message = __('Your saved connection is no longer valid. Please reconnect Google Analytics.', 'cliredas-analytics-dashboard');
                }
            }
        }

        $property_id = isset($settings['ga4_property_id']) ? trim((string) $settings['ga4_property_id']) : '';
        if ($connected && '' === $token_error_message && '' === $property_id) {
            $status_text = __('Connected (property not selected)', 'cliredas-analytics-dashboard');
        }

    ?>
        <p><strong><?php echo esc_html($status_text); ?></strong></p>

        <?php if ('' !== $token_error_message) : ?>
            <p class="description"><?php echo esc_html($token_error_message); ?></p>
        <?php endif; ?>

        <p>
            <?php if (! $connected) : ?>
                <?php if ($can_connect) : ?>
                    <a class="button button-primary" href="<?php echo esc_url($connect_url); ?>">
                        <?php echo esc_html__('Connect Google Analytics', 'cliredas-analytics-dashboard'); ?>
                    </a>
                <?php else : ?>
                    <a class="button button-primary disabled" href="#" aria-disabled="true" onclick="return false;">
                        <?php echo esc_html__('Connect Google Analytics', 'cliredas-analytics-dashboard'); ?>
                    </a>
                    <span class="description" style="margin-left:8px;">
                        <?php echo esc_html__('Save your Client ID first.', 'cliredas-analytics-dashboard'); ?>
                    </span>
                <?php endif; ?>
            <?php else : ?>
                <?php if ('' !== $token_error_message) : ?>
                    <a class="button button-primary" href="<?php echo esc_url($connect_url); ?>">
                        <?php echo esc_html__('Reconnect Google Analytics', 'cliredas-analytics-dashboard'); ?>
                    </a>
                <?php endif; ?>
                <a class="button" href="<?php echo esc_url($disconnect_url); ?>">
                    <?php echo esc_html__('Disconnect', 'cliredas-analytics-dashboard'); ?>
                </a>
            <?php endif; ?>
        </p>
    <?php
    }

    /**
     * Check if GA4 is connected (placeholder flag for now).
     *
     * @return bool
     */
    public function is_ga4_connected()
    {
        $settings = $this->get_settings();

        if (! empty($settings['ga4_connected'])) {
            return true;
        }

        // Fallback: treat as connected if token data exists (covers cases where the flag wasn't persisted).
        if (! empty($settings['ga4_refresh_token'])) {
            return true;
        }

        if (! empty($settings['ga4_access_token']) && ! empty($settings['ga4_token_expires'])) {
            return true;
        }

        return false;
    }

    /**
     * Build the GA4 OAuth redirect URI.
     *
     * @return string
     */
    public function get_ga4_redirect_uri()
    {
        $settings = $this->get_settings();
        $base_url = isset($settings['ga4_redirect_base_url']) ? trim((string) $settings['ga4_redirect_base_url']) : '';

        if ('' !== $base_url) {
            $redirect_uri = trailingslashit($base_url) . 'wp-admin/admin-post.php?action=cliredas_ga4_oauth_callback';
        } else {
            $redirect_uri = admin_url('admin-post.php?action=cliredas_ga4_oauth_callback');
        }

        /**
         * Filter the GA4 OAuth redirect URI.
         *
         * @param string $redirect_uri Redirect URI.
         * @param array  $settings     Current plugin settings.
         */
        return (string) apply_filters('cliredas_ga4_redirect_uri', $redirect_uri, $settings);
    }

    public function render_ga4_client_id_field()
    {
        $settings = $this->get_settings();
        $value    = isset($settings['ga4_client_id']) ? (string) $settings['ga4_client_id'] : '';
    ?>
        <input type="text"
            id="cliredas_ga4_client_id"
            class="regular-text"
            name="<?php echo esc_attr(self::OPTION_KEY); ?>[ga4_client_id]"
            value="<?php echo esc_attr($value); ?>"
            placeholder="<?php echo esc_attr__('1234-abc.apps.googleusercontent.com', 'cliredas-analytics-dashboard'); ?>" />
        <p class="description">
            <?php echo esc_html__('From Google Cloud Console → OAuth consent screen / Credentials.', 'cliredas-analytics-dashboard'); ?>
        </p>
    <?php
    }

	    public function render_ga4_client_secret_field()
	    {
	        $settings = $this->get_settings();
	        $has_secret = ! empty($settings['ga4_client_secret']);
	        $clear_url = wp_nonce_url(
	            admin_url('admin-post.php?action=cliredas_ga4_clear_secret'),
	            'cliredas_ga4_clear_secret'
	        );
	    ?>
	        <p style="margin-top:0;">
	            <strong><?php echo esc_html__('Client secret:', 'cliredas-analytics-dashboard'); ?></strong>
	            <?php echo esc_html($has_secret ? __('Saved', 'cliredas-analytics-dashboard') : __('Not set', 'cliredas-analytics-dashboard')); ?>
	        </p>

	        <input type="password"
	            id="cliredas_ga4_client_secret"
	            class="regular-text"
	            name="<?php echo esc_attr(self::OPTION_KEY); ?>[ga4_client_secret]"
	            value=""
	            autocomplete="new-password"
	            placeholder="<?php echo esc_attr($has_secret ? __('Enter to replace', 'cliredas-analytics-dashboard') : __('Enter client secret', 'cliredas-analytics-dashboard')); ?>" />
	        <p class="description">
	            <?php echo esc_html__('Leave blank to keep the currently saved secret.', 'cliredas-analytics-dashboard'); ?>
	        </p>

	        <?php if ($has_secret) : ?>
	            <p>
	                <a class="button button-secondary" href="<?php echo esc_url($clear_url); ?>"
	                    onclick="return confirm('<?php echo esc_js(__('This will clear the saved client secret and disconnect GA4. Continue?', 'cliredas-analytics-dashboard')); ?>');">
	                    <?php echo esc_html__('Clear secret', 'cliredas-analytics-dashboard'); ?>
	                </a>
	            </p>
	        <?php endif; ?>
	    <?php
	    }

    public function render_ga4_redirect_uri_field()
    {
        $redirect_uri = $this->get_ga4_redirect_uri();
    ?>
        <input type="text" id="cliredas_ga4_redirect_uri" class="large-text code" readonly value="<?php echo esc_attr($redirect_uri); ?>" />
        <p class="description">
            <?php echo esc_html__('Add this exact URL as an Authorized redirect URI in your Google OAuth client.', 'cliredas-analytics-dashboard'); ?>
        </p>
<?php
    }

    /**
     * Render optional public base URL for tunnels/reverse proxies.
     *
     * @return void
     */
    public function render_ga4_redirect_base_url_field()
    {
        $settings = $this->get_settings();
        $value    = isset($settings['ga4_redirect_base_url']) ? (string) $settings['ga4_redirect_base_url'] : '';
    ?>
        <input type="url"
            id="cliredas_ga4_redirect_base_url"
            class="regular-text"
            name="<?php echo esc_attr(self::OPTION_KEY); ?>[ga4_redirect_base_url]"
            value="<?php echo esc_attr($value); ?>"
            placeholder="<?php echo esc_attr(home_url()); ?>" />
        <p class="description">
            <?php echo esc_html__('Optional. Use this when WordPress is behind a public tunnel or reverse proxy. Enter only the public site URL, such as an HTTPS ngrok URL, without a path.', 'cliredas-analytics-dashboard'); ?>
        </p>
        <p class="description">
            <?php echo esc_html__('When this is set, open wp-admin through the same public URL before connecting so the OAuth callback uses the same login session.', 'cliredas-analytics-dashboard'); ?>
        </p>
    <?php
    }

    /**
     * Sanitize the optional OAuth redirect base URL.
     *
     * @param mixed  $value    Raw input value.
     * @param string $fallback Existing value to keep if input is invalid.
     * @return string
     */
    private function sanitize_ga4_redirect_base_url($value, $fallback)
    {
        $value = trim((string) $value);
        if ('' === $value) {
            return '';
        }

        $url = esc_url_raw($value);
        if ('' === $url) {
            add_settings_error(
                self::OPTION_KEY,
                'cliredas_ga4_redirect_base_url_invalid',
                __('Public OAuth base URL must be a valid URL.', 'cliredas-analytics-dashboard')
            );
            return $fallback;
        }

        $parts  = wp_parse_url($url);
        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
        $host   = isset($parts['host']) ? strtolower((string) $parts['host']) : '';

        if ('' === $scheme || '' === $host || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            add_settings_error(
                self::OPTION_KEY,
                'cliredas_ga4_redirect_base_url_invalid',
                __('Public OAuth base URL must include only a scheme and host, without a path query, fragment, or credentials.', 'cliredas-analytics-dashboard')
            );
            return $fallback;
        }

        $is_localhost = in_array($host, array('localhost', '127.0.0.1', '::1'), true);
        if ('https' !== $scheme && ! ($is_localhost && 'http' === $scheme)) {
            add_settings_error(
                self::OPTION_KEY,
                'cliredas_ga4_redirect_base_url_https',
                __('Public OAuth base URL must use HTTPS, except localhost URLs used for local testing.', 'cliredas-analytics-dashboard')
            );
            return $fallback;
        }

        $normalized = $scheme . '://' . $host;
        if (isset($parts['port'])) {
            $normalized .= ':' . absint($parts['port']);
        }

        return untrailingslashit($normalized);
    }

    /**
     * Render GA4 property selector.
     *
     * @return void
     */
    public function render_ga4_property_field()
    {
        $settings = $this->get_settings();

        if (! $this->is_ga4_connected()) {
            echo '<p class="description">' . esc_html__('Connect Google Analytics first to load available properties.', 'cliredas-analytics-dashboard') . '</p>';
            return;
        }

        $selected = isset($settings['ga4_property_id']) ? (string) $settings['ga4_property_id'] : '';

        $properties = $this->ga4_client->list_properties();
        if (is_wp_error($properties)) {
            $code = (string) $properties->get_error_code();
            $msg  = trim((string) $properties->get_error_message());
            $msg  = sanitize_text_field($msg);
            $msg  = substr($msg, 0, 200);

            $needs_reconnect = in_array(
                $code,
                array(
                    'missing_refresh_token',
                    'missing_client_id',
                    'missing_client_secret',
                    'token_revoked',
                    'token_credentials_invalid',
                    'token_refresh_failed',
                    'token_refresh_invalid',
                    'token_refresh_missing_access_token',
                ),
                true
            );

            echo '<p class="description">' . esc_html__('Unable to load GA4 properties right now.', 'cliredas-analytics-dashboard') . '</p>';
            if ('' !== $msg) {
                echo '<p class="description">' . esc_html($msg) . '</p>';
            }

            if ($needs_reconnect) {
                $connect_url = wp_nonce_url(
                    admin_url('admin-post.php?action=cliredas_ga4_connect'),
                    'cliredas_ga4_connect'
                );
                echo '<p><a class="button button-primary" href="' . esc_url($connect_url) . '">' . esc_html__('Reconnect Google Analytics', 'cliredas-analytics-dashboard') . '</a></p>';
            }

            return;
        }

        if (empty($properties)) {
            echo '<p class="description">' . esc_html__('No GA4 properties were found for this Google account.', 'cliredas-analytics-dashboard') . '</p>';
            return;
        }

    ?>
        <select id="cliredas_ga4_property_id" name="<?php echo esc_attr(self::OPTION_KEY); ?>[ga4_property_id]" class="regular-text">
            <option value=""><?php echo esc_html__('Select a property', 'cliredas-analytics-dashboard'); ?></option>
            <?php foreach ($properties as $property_id => $label) : ?>
                <option value="<?php echo esc_attr((string) $property_id); ?>" <?php selected($selected, (string) $property_id); ?>>
                    <?php echo esc_html((string) $label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php echo esc_html__('This selects which GA4 property the dashboard will report on.', 'cliredas-analytics-dashboard'); ?>
        </p>
        <?php if ('' === trim((string) $selected)) : ?>
            <p class="description">
                <?php echo esc_html__('No property selected yet. Choose one to enable GA4 reporting for the dashboard.', 'cliredas-analytics-dashboard'); ?>
            </p>
        <?php endif; ?>
    <?php
    }

}
