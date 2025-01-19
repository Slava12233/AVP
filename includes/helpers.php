<?php
namespace AVP\Helpers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validate email format using WordPress core function and additional checks
 * 
 * @param string $email Email address to validate
 * @return bool True if email format is valid
 */
function is_valid_email_format($email) {
    // Test for the minimum length the email can be
    if (strlen($email) < 6) {
        return false;
    }
    
    // Test for an @ character after the first position
    if (strpos($email, '@', 1) === false) {
        return false;
    }
    
    // Split out the local and domain parts
    list($local, $domain) = explode('@', $email, 2);
    
    // Test for invalid characters in local part
    if (!preg_match('/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~.-]+$/', $local)) {
        return false;
    }
    
    // Test for sequences of periods in domain
    if (preg_match('/\.{2,}/', $domain)) {
        return false;
    }
    
    // Test for leading and trailing periods and whitespace in domain
    if (trim($domain, " \t\n\r\0\x0B.") !== $domain) {
        return false;
    }
    
    // Split the domain into subs
    $subs = explode('.', $domain);
    
    // Domain must have at least two parts
    if (count($subs) < 2) {
        return false;
    }
    
    // Check each domain part
    foreach ($subs as $sub) {
        // Test for leading and trailing hyphens
        if (trim($sub, " \t\n\r\0\x0B-") !== $sub) {
            return false;
        }
        
        // Test for invalid characters
        if (!preg_match('/^[a-z0-9-]+$/i', $sub)) {
            return false;
        }
    }
    
    // Use WordPress's built-in email validation as final check
    return \is_email($email);
}

/**
 * Basic phone number format validation for Israeli numbers
 * 
 * @param string $phone Phone number to validate
 * @return bool True if phone format matches Israeli pattern
 */
function is_valid_phone_format($phone) {
    // Remove all non-digit characters (spaces, dashes, etc)
    $phone = preg_replace('/[^0-9]/', '', $phone);
    error_log("AVP: Validating phone number: $phone");
    
    // Check if it's an Israeli mobile number
    // Allow formats:
    // - 05XXXXXXXX (10 digits starting with 05)
    // - 5XXXXXXXX (9 digits starting with 5)
    if (preg_match('/^(0[5][0-9]{8}|[5][0-9]{8})$/', $phone)) {
        error_log("AVP: Phone number is valid");
        return true;
    }
    
    error_log("AVP: Phone number is invalid");
    return false;
}

/**
 * Get plugin settings with defaults
 * 
 * @param string $type Either 'free' or 'pro'
 * @return array Plugin settings
 */
function get_plugin_settings($type = 'free') {
    $defaults = [
        'free' => [
            'validate_email' => true,
            'validate_phone' => true,
            'error_color' => '#ff0000',
            'success_color' => '#00ff00'
        ],
        'pro' => [
            'check_mx' => true,
            'check_spf' => false,
            'check_dkim' => false,
            'verify_smtp' => false,
            'default_region' => 'IL'
        ]
    ];

    $settings = \get_option("avp_{$type}_settings", []);
    return \wp_parse_args($settings, $defaults[$type]);
}

/**
 * Format validation error message
 * 
 * @param string $message Error message
 * @param string $type Validation type (email/phone)
 * @return string Formatted error message
 */
function format_error_message($message, $type) {
    return sprintf(
        \__('[%s Validation] %s', 'advanced-validation'),
        ucfirst($type),
        $message
    );
} 