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

// Global initialization flag
global $avp_initialized;
if (isset($avp_initialized)) {
    return;
}
$avp_initialized = true;

// Define plugin constants
define('AVP_VERSION', '1.0');
define('AVP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AVP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader if exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load core files
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/license-check.php';

// Load version-specific files
require_once __DIR__ . '/free/free-functions.php';

// Load Pro files if license is active (only once)
global $avp_pro_loaded;
if (!isset($avp_pro_loaded) && \AVP\License\avp_is_pro_active()) {
    $avp_pro_loaded = true;
    require_once __DIR__ . '/pro/pro-functions.php';
}

// Initialize plugin
function avp_init() {
    static $done = false;
    if ($done) return;
    $done = true;
    
    // Load text domain for translations
    load_plugin_textdomain('advanced-validation', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Enqueue scripts only when needed
function avp_maybe_enqueue_scripts() {
    static $enqueued = false;
    if ($enqueued) return;
    $enqueued = true;
    
    // Only enqueue if we're on a page with a form
    if (!is_admin() && !has_shortcode(get_post()->post_content, 'contact-form-7') && !has_shortcode(get_post()->post_content, 'elementor-template')) {
        return;
    }
    
    wp_enqueue_style('avp-style', AVP_PLUGIN_URL . 'assets/css/style.css', [], AVP_VERSION);
}

// Hook initialization
add_action('plugins_loaded', 'avp_init', 5);
add_action('wp_enqueue_scripts', 'avp_maybe_enqueue_scripts', 10);

// Load dashboard files
require_once __DIR__ . '/free/dashboard-free.php';
if (\AVP\License\avp_is_pro_active()) {
    require_once __DIR__ . '/pro/dashboard-pro.php';
} 