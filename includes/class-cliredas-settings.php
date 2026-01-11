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
        'allow_editors' => 0,
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
        $sanitized = $this->defaults;

        if (is_array($input)) {
            $sanitized['allow_editors'] = ! empty($input['allow_editors']) ? 1 : 0;
        }

        return $sanitized;
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

            <form method="post" action="options.php">
                <?php
                settings_fields(self::SETTINGS_GROUP);
                do_settings_sections(self::SETTINGS_PAGE_SLUG);
                submit_button();
                ?>
            </form>
        </div>
<?php
    }
}
