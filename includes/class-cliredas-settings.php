<?php

/**
 * Settings handler.
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

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
        'ga4_client_id'      => '',
        'ga4_client_secret'  => '',
        'ga4_property_id'    => '',
        'ga4_refresh_token'  => '',
        'ga4_access_token'   => '',
        'ga4_token_expires'  => 0,
    );

    public function __construct()
    {
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
     * Add Settings â†’ Client Report page.
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
            $clear_secret = ! empty($input['_cliredas_clear_ga4_client_secret']);

            $sanitized['allow_editors'] = ! empty($input['allow_editors']) ? 1 : 0;

            if (isset($input['ga4_client_id'])) {
                $sanitized['ga4_client_id'] = sanitize_text_field(wp_unslash($input['ga4_client_id']));
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
	        <div class="wrap">
	            <h1><?php echo esc_html__('Client Report Settings', 'cliredas-analytics-dashboard'); ?></h1>

	            <?php
	            $ga4_notice_raw = filter_input(INPUT_GET, 'cliredas_ga4_notice', FILTER_UNSAFE_RAW);
	            $ga4_error_raw = filter_input(INPUT_GET, 'cliredas_ga4_error', FILTER_UNSAFE_RAW);
	            $ga4_error_desc_raw = filter_input(INPUT_GET, 'cliredas_ga4_error_desc', FILTER_UNSAFE_RAW);
	            $ga4_notice_nonce_raw = filter_input(INPUT_GET, 'cliredas_ga4_notice_nonce', FILTER_UNSAFE_RAW);

	            $ga4_notice_nonce_ok = is_string($ga4_notice_nonce_raw) && wp_verify_nonce(sanitize_text_field($ga4_notice_nonce_raw), 'cliredas_ga4_notice');

	            $ga4_notice = '';
	            $ga4_error = '';
	            $ga4_error_desc = '';
	            if ($ga4_notice_nonce_ok) {
	                $ga4_notice = is_string($ga4_notice_raw) ? sanitize_key($ga4_notice_raw) : '';
	                $ga4_error = is_string($ga4_error_raw) ? sanitize_key($ga4_error_raw) : '';
	                $ga4_error_desc = is_string($ga4_error_desc_raw) ? sanitize_text_field($ga4_error_desc_raw) : '';
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
	            $cache_cleared = filter_input(INPUT_GET, 'cliredas_cache_cleared', FILTER_UNSAFE_RAW);
	            $cache_cleared_nonce = filter_input(INPUT_GET, 'cliredas_cache_cleared_nonce', FILTER_UNSAFE_RAW);
	            $cache_cleared_ok = is_string($cache_cleared_nonce) && wp_verify_nonce(sanitize_text_field(wp_unslash($cache_cleared_nonce)), 'cliredas_cache_cleared');
	            ?>
	            <?php if (is_string($cache_cleared) && $cache_cleared_ok) : ?>
	                <div class="notice notice-success is-dismissible">
	                    <p>
	                        <?php
	                        echo esc_html(
                             sprintf(
                                 /* translators: %d: number of cache entries cleared */
                                 __('Cached reports cleared (%d).', 'cliredas-analytics-dashboard'),
                                 absint(wp_unslash($cache_cleared))
                             )
                         );
                         ?>
	                     </p>
	                 </div>
	             <?php endif; ?>

            <?php
            // Only show errors (not the success message).
            $settings_updated = filter_input(INPUT_GET, 'settings-updated', FILTER_UNSAFE_RAW);
            if (is_string($settings_updated) && '' !== $settings_updated) {
                // Do nothing; let core show the success notice (or your own).
            } else {
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
            $token = $this->get_valid_access_token();
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

    public function render_ga4_client_id_field()
    {
        $settings = $this->get_settings();
        $value    = isset($settings['ga4_client_id']) ? (string) $settings['ga4_client_id'] : '';
    ?>
        <input type="text"
            class="regular-text"
            name="<?php echo esc_attr(self::OPTION_KEY); ?>[ga4_client_id]"
            value="<?php echo esc_attr($value); ?>"
            placeholder="<?php echo esc_attr__('1234-abc.apps.googleusercontent.com', 'cliredas-analytics-dashboard'); ?>" />
        <p class="description">
            <?php echo esc_html__('From Google Cloud Console â†’ OAuth consent screen / Credentials.', 'cliredas-analytics-dashboard'); ?>
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
        $redirect_uri = admin_url('admin-post.php?action=cliredas_ga4_oauth_callback');
    ?>
        <input type="text" class="large-text code" readonly value="<?php echo esc_attr($redirect_uri); ?>" />
        <p class="description">
            <?php echo esc_html__('Add this exact URL as an Authorized redirect URI in your Google OAuth client.', 'cliredas-analytics-dashboard'); ?>
        </p>
<?php
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

        $properties = $this->get_ga4_properties();
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
        <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[ga4_property_id]" class="regular-text">
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

    /**
     * Get a valid access token, refreshing it when needed.
     *
     * @return string|\WP_Error
     */
    private function get_valid_access_token()
    {
        $settings = $this->get_settings();

        $access_token = isset($settings['ga4_access_token']) ? trim((string) $settings['ga4_access_token']) : '';
        $expires_at   = isset($settings['ga4_token_expires']) ? (int) $settings['ga4_token_expires'] : 0;

        if ('' !== $access_token && $expires_at > (time() + 60)) {
            return $access_token;
        }

        $refresh_token = isset($settings['ga4_refresh_token']) ? trim((string) $settings['ga4_refresh_token']) : '';
        if ('' === $refresh_token) {
            return new WP_Error('missing_refresh_token', __('Missing refresh token. Please reconnect Google Analytics.', 'cliredas-analytics-dashboard'));
        }

        $client_id = isset($settings['ga4_client_id']) ? trim((string) $settings['ga4_client_id']) : '';
        if ('' === $client_id) {
            return new WP_Error('missing_client_id', __('Missing OAuth Client ID.', 'cliredas-analytics-dashboard'));
        }

        $client_secret = isset($settings['ga4_client_secret']) ? trim((string) $settings['ga4_client_secret']) : '';
        if ('' === $client_secret) {
            return new WP_Error('missing_client_secret', __('Missing OAuth Client Secret.', 'cliredas-analytics-dashboard'));
        }

        $response = wp_remote_post(
            'https://oauth2.googleapis.com/token',
            array(
                'timeout' => 20,
                'body'    => array(
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'refresh_token' => $refresh_token,
                    'grant_type'    => 'refresh_token',
                ),
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error('token_refresh_failed', $response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body   = (string) wp_remote_retrieve_body($response);
        $data   = json_decode($body, true);

        if (! is_array($data)) {
            return new WP_Error('token_refresh_invalid', __('Invalid token refresh response from Google.', 'cliredas-analytics-dashboard'));
        }

        if (200 !== $status) {
            $remote_error = isset($data['error']) ? (string) $data['error'] : '';
            $remote_desc  = isset($data['error_description']) ? (string) $data['error_description'] : '';
            $msg = $remote_error ? $remote_error : __('Token refresh failed.', 'cliredas-analytics-dashboard');
            if ('' !== $remote_desc) {
                $msg .= ' - ' . $remote_desc;
            }
            return new WP_Error('token_refresh_failed', $msg);
        }

        $new_access_token = isset($data['access_token']) ? trim((string) $data['access_token']) : '';
        if ('' === $new_access_token) {
            return new WP_Error('token_refresh_missing_access_token', __('Token refresh response is missing access_token.', 'cliredas-analytics-dashboard'));
        }

        $expires_in = isset($data['expires_in']) ? (int) $data['expires_in'] : 0;
        if ($expires_in <= 0) {
            $expires_in = 3600;
        }

        $settings['ga4_access_token']  = $new_access_token;
        $settings['ga4_token_expires'] = time() + max(60, $expires_in - 60);
        $settings['ga4_connected']     = 1;

        update_option(self::OPTION_KEY, $settings);

        return $new_access_token;
    }

    /**
     * Fetch accessible GA4 properties via Google Analytics Admin API.
     *
     * @return array<string,string>|\WP_Error
     */
    private function get_ga4_properties()
    {
        $properties = array();

        $token = $this->get_valid_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $base_url = 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries';

        $page_token = '';
        $seen_tokens = array();
        $max_pages = 20;

        for ($page = 0; $page < $max_pages; $page++) {
            $args = array(
                'pageSize' => 200,
            );

            if ('' !== $page_token) {
                if (isset($seen_tokens[$page_token])) {
                    break;
                }
                $seen_tokens[$page_token] = true;
                $args['pageToken'] = $page_token;
            }

            $url = add_query_arg($args, $base_url);

            $response = wp_remote_get(
                $url,
                array(
                    'timeout' => 20,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                    ),
                )
            );

            // If the access token expired mid-loop, refresh once and retry this page.
            if (! is_wp_error($response) && 401 === (int) wp_remote_retrieve_response_code($response)) {
                $token = $this->get_valid_access_token();
                if (is_wp_error($token)) {
                    return $token;
                }

                $response = wp_remote_get(
                    $url,
                    array(
                        'timeout' => 20,
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $token,
                        ),
                    )
                );
            }

            if (is_wp_error($response)) {
                return new WP_Error('admin_api_failed', $response->get_error_message());
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            $body   = (string) wp_remote_retrieve_body($response);
            $data   = json_decode($body, true);

            if (! is_array($data)) {
                return new WP_Error('admin_api_invalid', __('Invalid response from Google Analytics Admin API.', 'cliredas-analytics-dashboard'));
            }

            if (200 !== $status) {
                $msg = __('Failed to load properties from Google Analytics Admin API.', 'cliredas-analytics-dashboard');
                if (isset($data['error']['message'])) {
                    $msg .= ' ' . sanitize_text_field((string) $data['error']['message']);
                }
                return new WP_Error('admin_api_failed', $msg);
            }

            $account_summaries = isset($data['accountSummaries']) && is_array($data['accountSummaries']) ? $data['accountSummaries'] : array();

            foreach ($account_summaries as $summary) {
                if (! is_array($summary)) {
                    continue;
                }

                $property_summaries = isset($summary['propertySummaries']) && is_array($summary['propertySummaries']) ? $summary['propertySummaries'] : array();
                foreach ($property_summaries as $property_summary) {
                    if (! is_array($property_summary)) {
                        continue;
                    }

                    $property_id = isset($property_summary['property']) ? (string) $property_summary['property'] : '';
                    $display_name = isset($property_summary['displayName']) ? (string) $property_summary['displayName'] : '';

                    $property_id = trim($property_id);
                    if ('' === $property_id) {
                        continue;
                    }

                    $label = $display_name ? $display_name : $property_id;
                    $properties[$property_id] = $label;
                }
            }

            $page_token = isset($data['nextPageToken']) ? trim((string) $data['nextPageToken']) : '';
            if ('' === $page_token) {
                break;
            }
        }

        asort($properties, SORT_NATURAL | SORT_FLAG_CASE);

        return $properties;
    }
}
