<?php

/**
 * Plugin Name: Cliredas - Simple Google Analytics Dashboard
 * Description: Client-friendly Google Analytics 4 (GA4) dashboard inside wp-admin.
 * Version:     1.0.0
 * Author:      Vasileios Zisis
 * Author URI:  https://profiles.wordpress.org/vzisis/
 * Text Domain: cliredas-analytics-dashboard
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ClientReportingDashboard
 */

defined('ABSPATH') || exit;

define('CLIREDAS_VERSION', '1.0.0');
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
