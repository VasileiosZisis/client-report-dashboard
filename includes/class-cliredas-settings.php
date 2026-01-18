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
            __('GA4 Connection', 'client-report-dashboard'),
            array($this, 'render_connection_section'),
            self::SETTINGS_PAGE_SLUG
        );

        add_settings_field(
            'cliredas_ga4_client_id',
            __('OAuth Client ID', 'client-report-dashboard'),
            array($this, 'render_ga4_client_id_field'),
            self::SETTINGS_PAGE_SLUG,
            'cliredas_section_connection'
        );

        add_settings_field(
            'cliredas_ga4_client_secret',
            __('OAuth Client Secret', 'client-report-dashboard'),
            array($this, 'render_ga4_client_secret_field'),
            self::SETTINGS_PAGE_SLUG,
            'cliredas_section_connection'
        );

        add_settings_field(
            'cliredas_ga4_redirect_uri',
            __('Redirect URI', 'client-report-dashboard'),
            array($this, 'render_ga4_redirect_uri_field'),
            self::SETTINGS_PAGE_SLUG,
            'cliredas_section_connection'
        );

        add_settings_field(
            'cliredas_connection_status',
            __('Status', 'client-report-dashboard'),
            array($this, 'render_connection_status_field'),
            self::SETTINGS_PAGE_SLUG,
            'cliredas_section_connection'
        );

        add_settings_section(
            'cliredas_section_access',
            __('Access Control', 'client-report-dashboard'),
            array($this, 'render_access_section'),
            self::SETTINGS_PAGE_SLUG
        );

        add_settings_field(
            'cliredas_allow_editors',
            __('Dashboard visibility', 'client-report-dashboard'),
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
            __('Client Report Settings', 'client-report-dashboard'),
            __('Client Report', 'client-report-dashboard'),
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
            $sanitized['allow_editors'] = ! empty($input['allow_editors']) ? 1 : 0;

            if (isset($input['ga4_client_id'])) {
                $sanitized['ga4_client_id'] = sanitize_text_field(wp_unslash($input['ga4_client_id']));
            }

            // Only update secret when user actually enters a new one.
            if (isset($input['ga4_client_secret'])) {
                $new_secret = trim((string) wp_unslash($input['ga4_client_secret']));
                if ('' !== $new_secret) {
                    $sanitized['ga4_client_secret'] = sanitize_text_field($new_secret);
                }
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
        echo '<p>' . esc_html__('Control who can view the Client Report dashboard.', 'client-report-dashboard') . '</p>';
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
            <?php echo esc_html__('Show dashboard to Editors as well as Administrators', 'client-report-dashboard'); ?>
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
	            wp_die(esc_html__('You do not have permission to access this page.', 'client-report-dashboard'));
	        }
	    ?>
	        <div class="wrap">
	            <h1><?php echo esc_html__('Client Report Settings', 'client-report-dashboard'); ?></h1>

	            <?php
	            $ga4_notice = isset($_GET['cliredas_ga4_notice']) ? sanitize_key(wp_unslash($_GET['cliredas_ga4_notice'])) : '';
	            $ga4_error  = isset($_GET['cliredas_ga4_error']) ? sanitize_key(wp_unslash($_GET['cliredas_ga4_error'])) : '';

	            $ga4_notice_message = '';
	            $ga4_notice_class   = '';

	            if ('' !== $ga4_error) {
	                $ga4_notice_class = 'notice notice-error is-dismissible';

	                switch ($ga4_error) {
	                    case 'missing_client_id':
	                        $ga4_notice_message = __('Missing OAuth Client ID. Save your Client ID first, then click Connect again.', 'client-report-dashboard');
	                        break;
	                    default:
	                        $ga4_notice_message = __('GA4 connection failed. Please try again.', 'client-report-dashboard');
	                        break;
	                }
	            } elseif ('' !== $ga4_notice) {
	                $ga4_notice_class = 'notice notice-success is-dismissible';

	                switch ($ga4_notice) {
	                    case 'callback_reached':
	                        $ga4_notice_class   = 'notice notice-info is-dismissible';
	                        $ga4_notice_message = __('Google OAuth callback received. Token exchange will be implemented in the next milestone.', 'client-report-dashboard');
	                        break;
	                    case 'disconnected':
	                        $ga4_notice_message = __('Disconnected from Google Analytics.', 'client-report-dashboard');
	                        break;
	                    default:
	                        $ga4_notice_message = __('GA4 status updated.', 'client-report-dashboard');
	                        break;
	                }
	            }
	            ?>

	            <?php if ('' !== $ga4_notice_message) : ?>
	                <div class="<?php echo esc_attr($ga4_notice_class); ?>">
	                    <p><?php echo esc_html($ga4_notice_message); ?></p>
	                </div>

	                <script>
	                    (function() {
	                        try {
	                            var url = new URL(window.location.href);
	                            url.searchParams.delete('cliredas_ga4_notice');
	                            url.searchParams.delete('cliredas_ga4_error');
	                            window.history.replaceState({}, document.title, url.toString());
	                        } catch (e) {}
	                    })();
	                </script>
	            <?php endif; ?>

	            <?php if (isset($_GET['cliredas_cache_cleared'])) : ?>
	                <div class="notice notice-success is-dismissible">
	                    <p>
	                        <?php
	                        echo esc_html(
                            sprintf(
                                /* translators: %d: number of cache entries cleared */
                                __('Cached reports cleared (%d).', 'client-report-dashboard'),
                                absint(wp_unslash($_GET['cliredas_cache_cleared']))
                            )
                        );
                        ?>
                    </p>
                </div>

                <script>
                    (function() {
                        try {
                            var url = new URL(window.location.href);
                            url.searchParams.delete('cliredas_cache_cleared');
                            window.history.replaceState({}, document.title, url.toString());
                        } catch (e) {}
                    })();
                </script>
            <?php endif; ?>

            <?php
            // Only show errors (not the success message).
            if (isset($_GET['settings-updated'])) {
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

            <h2><?php echo esc_html__('Tools', 'client-report-dashboard'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="cliredas_clear_cache">
                <?php wp_nonce_field('cliredas_clear_cache'); ?>
                <?php submit_button(__('Clear cached reports', 'client-report-dashboard'), 'secondary', 'submit', false); ?>
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
        echo '<p>' . esc_html__('Connect Google Analytics 4 to display real analytics data. (Connection UI will be enabled in a future update.)', 'client-report-dashboard') . '</p>';
    }

    /**
     * Render GA4 connection status field.
     *
     * @return void
     */
    public function render_connection_status_field()
    {
        $settings    = $this->get_settings();
        $connected   = ! empty($settings['ga4_connected']);

        $client_id   = isset($settings['ga4_client_id']) ? trim((string) $settings['ga4_client_id']) : '';
        $can_connect = ('' !== $client_id);

        $status_text = $connected
            ? __('Connected', 'client-report-dashboard')
            : __('Not connected', 'client-report-dashboard');

        $connect_url = wp_nonce_url(
            admin_url('admin-post.php?action=cliredas_ga4_connect'),
            'cliredas_ga4_connect'
        );

        $disconnect_url = wp_nonce_url(
            admin_url('admin-post.php?action=cliredas_ga4_disconnect'),
            'cliredas_ga4_disconnect'
        );

    ?>
        <p><strong><?php echo esc_html($status_text); ?></strong></p>

        <p>
            <?php if (! $connected) : ?>
                <?php if ($can_connect) : ?>
                    <a class="button button-primary" href="<?php echo esc_url($connect_url); ?>">
                        <?php echo esc_html__('Connect Google Analytics', 'client-report-dashboard'); ?>
                    </a>
                <?php else : ?>
                    <a class="button button-primary disabled" href="#" aria-disabled="true" onclick="return false;">
                        <?php echo esc_html__('Connect Google Analytics', 'client-report-dashboard'); ?>
                    </a>
                    <span class="description" style="margin-left:8px;">
                        <?php echo esc_html__('Save your Client ID first.', 'client-report-dashboard'); ?>
                    </span>
                <?php endif; ?>
            <?php else : ?>
                <a class="button" href="<?php echo esc_url($disconnect_url); ?>">
                    <?php echo esc_html__('Disconnect', 'client-report-dashboard'); ?>
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
        return ! empty($settings['ga4_connected']);
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
            placeholder="<?php echo esc_attr__('1234-abc.apps.googleusercontent.com', 'client-report-dashboard'); ?>" />
        <p class="description">
            <?php echo esc_html__('From Google Cloud Console → OAuth consent screen / Credentials.', 'client-report-dashboard'); ?>
        </p>
    <?php
    }

    public function render_ga4_client_secret_field()
    {
        $settings = $this->get_settings();
        $has_secret = ! empty($settings['ga4_client_secret']);
    ?>
        <input type="password"
            class="regular-text"
            name="<?php echo esc_attr(self::OPTION_KEY); ?>[ga4_client_secret]"
            value=""
            autocomplete="new-password"
            placeholder="<?php echo esc_attr($has_secret ? __('Saved (enter to replace)', 'client-report-dashboard') : __('Enter client secret', 'client-report-dashboard')); ?>" />
        <p class="description">
            <?php echo esc_html__('Leave blank to keep the currently saved secret.', 'client-report-dashboard'); ?>
        </p>
    <?php
    }

    public function render_ga4_redirect_uri_field()
    {
        $redirect_uri = admin_url('admin-post.php?action=cliredas_ga4_oauth_callback');
    ?>
        <input type="text" class="large-text code" readonly value="<?php echo esc_attr($redirect_uri); ?>" />
        <p class="description">
            <?php echo esc_html__('Add this exact URL as an Authorized redirect URI in your Google OAuth client.', 'client-report-dashboard'); ?>
        </p>
<?php
    }
}
