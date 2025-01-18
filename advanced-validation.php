<?php
/*
Plugin Name: Advanced Validation
Plugin URI:  https://yoursite.com/advanced-validation
Description: Validates emails & phones. Free (basic format checks) + Pro (MX, SPF, DKIM, SMTP, libphonenumber).
Version:     1.0
Author:      Your Name
Author URI:  https://yoursite.com
Text Domain: advanced-validation
Domain Path: /languages
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AVP_VERSION', '1.0');
define('AVP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AVP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader if exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load core files
require_once AVP_PLUGIN_DIR . 'includes/helpers.php';
require_once AVP_PLUGIN_DIR . 'includes/license-check.php';

// Load free version files
require_once AVP_PLUGIN_DIR . 'free/free-functions.php';
require_once AVP_PLUGIN_DIR . 'free/dashboard-free.php';

// Load pro version files if license is active
if (\AVP\License\avp_is_pro_active()) {
    require_once AVP_PLUGIN_DIR . 'pro/pro-functions.php';
    require_once AVP_PLUGIN_DIR . 'pro/dashboard-pro.php';
}

// Initialize plugin
function avp_init() {
    // Load text domain for translations
    load_plugin_textdomain('advanced-validation', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Enqueue admin assets
    add_action('admin_enqueue_scripts', 'avp_admin_enqueue_scripts');
}
add_action('plugins_loaded', 'avp_init');

// Enqueue admin scripts and styles
function avp_admin_enqueue_scripts() {
    wp_enqueue_style('avp-admin-style', AVP_PLUGIN_URL . 'assets/css/style.css', [], AVP_VERSION);
    wp_enqueue_script('avp-admin-script', AVP_PLUGIN_URL . 'assets/js/script.js', ['jquery'], AVP_VERSION, true);
} 