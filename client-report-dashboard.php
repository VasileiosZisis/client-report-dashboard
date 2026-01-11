<?php

/**
 * Plugin Name: Client Reporting Dashboard
 * Plugin URI:  https://example.com/client-report-dashboard
 * Description: Client-friendly analytics dashboard inside wp-admin. (GA4 integration will be added later.)
 * Version:     0.1.0
 * Author:      Vasilis Zisis
 * Author URI:  https://profiles.wordpress.org/vzisis/
 * Text Domain: client-report-dashboard
 * Domain Path: /languages
 * License:     GPLv2 or later
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

define('CLIREDAS_VERSION', '0.1.0');
define('CLIREDAS_PLUGIN_FILE', __FILE__);
define('CLIREDAS_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('CLIREDAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLIREDAS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once CLIREDAS_PLUGIN_DIR . 'includes/class-cliredas-plugin.php';

/**
 * Helper: Check if the Pro add-on is active.
 *
 * Pro add-on should define CLIREDAS_PRO_VERSION.
 *
 * @return bool
 */
function cliredas_pro_version()
{
    return defined('CLIREDAS_PRO_VERSION') ? constant('CLIREDAS_PRO_VERSION') : false;
}

register_activation_hook(__FILE__, array('CLIREDAS_Plugin', 'activate'));

/**
 * Boot the plugin.
 *
 * @return CLIREDAS_Plugin
 */
function cliredas()
{
    return CLIREDAS_Plugin::instance();
}

cliredas();
