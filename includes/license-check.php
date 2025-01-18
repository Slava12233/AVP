<?php
namespace AVP\License;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if pro version is active based on license key
 * 
 * @return bool True if pro version is active, false otherwise
 */
function avp_is_pro_active() {
    $license = \get_option('avp_license_key', '');
    
    // Development mode check
    if ($license === 'DEVMOCK') {
        return true;
    }
    
    // TODO: Add real license validation in the future
    return false;
}

/**
 * Validate and save license key
 * 
 * @param string $license_key The license key to validate
 * @return array Response with status and message
 */
function avp_validate_license($license_key) {
    $license_key = \sanitize_text_field($license_key);
    
    // Development mode check
    if ($license_key === 'DEVMOCK') {
        \update_option('avp_license_key', $license_key);
        return [
            'success' => true,
            'message' => \__('Development mode activated', 'advanced-validation')
        ];
    }
    
    // TODO: Add real license validation in the future
    return [
        'success' => false,
        'message' => \__('Invalid license key', 'advanced-validation')
    ];
}

/**
 * Deactivate license
 * 
 * @return bool True if deactivation successful
 */
function avp_deactivate_license() {
    return \delete_option('avp_license_key');
} 