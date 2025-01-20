<?php
namespace AVP\Pro;

use AVP\Helpers;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Initialize hooks immediately
function init_pro_validation() {
    error_log('AVP Pro: Initializing validation hooks');
    
    // Remove free version hooks first
    remove_action('elementor_pro/forms/validation', 'AVP\\Free\\validate_elementor_form', 20);
    
    // Add our pro hooks
    add_action('elementor_pro/forms/validation', __NAMESPACE__ . '\\validate_elementor_form', 10, 2);
    add_filter('wpcf7_validate_email', __NAMESPACE__ . '\\validate_cf7_email', 20, 2);
    add_filter('wpcf7_validate_email*', __NAMESPACE__ . '\\validate_cf7_email', 20, 2);
    add_filter('wpcf7_validate_tel', __NAMESPACE__ . '\\validate_cf7_phone', 20, 2);
    add_filter('wpcf7_validate_tel*', __NAMESPACE__ . '\\validate_cf7_phone', 20, 2);
    
    error_log('AVP Pro: Hooks initialized');
}

// Call initialization on plugins_loaded to ensure all plugins are loaded
add_action('plugins_loaded', __NAMESPACE__ . '\\init_pro_validation');

// Also call on init with high priority
add_action('init', __NAMESPACE__ . '\\init_pro_validation', 1);

// Static cache
$validation_cache = [];

/**
 * Advanced email validation with optimized SMTP check
 */
function validate_email_advanced($email) {
    error_log('AVP Pro: Starting advanced email validation for: ' . $email);

    // Get settings using helper function
    $settings = Helpers\get_plugin_settings('pro');
    error_log('AVP Pro: Current settings: ' . print_r($settings, true));

    // First check basic format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log('AVP Pro: Basic format validation failed');
        return array(
            'valid' => false,
            'message' => 'פורמט לא תקין - כתובת האימייל אינה תקינה'
        );
    }

    // Split email into parts
    $parts = explode('@', $email);
    $domain = $parts[1];

    $valid = true;
    $messages = [];

    // Check MX records if enabled
    if ($settings['check_mx']) {
        error_log('AVP Pro: Checking MX records for domain: ' . $domain);
        if (!getmxrr($domain, $mx_records, $mx_weights)) {
            error_log('AVP Pro: No MX records found for domain: ' . $domain);
            $valid = false;
            $messages[] = 'לא נמצא שרת מייל תקין עבור הדומיין ' . $domain;
        } else {
            error_log('AVP Pro: MX records found: ' . print_r($mx_records, true));
        }
    }

    // Additional DNS checks if enabled
    if ($settings['check_spf']) {
        error_log('AVP Pro: Starting SPF check for domain: ' . $domain);
        $has_spf = checkdnsrr($domain, 'TXT');
        $has_a = checkdnsrr($domain, 'A');
        error_log('AVP Pro: SPF check results - SPF record: ' . ($has_spf ? 'Found' : 'Not found') . ', A record: ' . ($has_a ? 'Found' : 'Not found'));

        if (!$has_spf && !$has_a) {
            error_log('AVP Pro: No SPF or A records found for domain: ' . $domain);
            $valid = false;
            $messages[] = 'הדומיין ' . $domain . ' לא מוגדר כראוי לשליחת מיילים';
        } else {
            error_log('AVP Pro: Domain has valid SPF or A records');
        }
    }

    // Check DKIM if enabled
    if ($settings['check_dkim']) {
        error_log('AVP Pro: Starting DKIM check for domain: ' . $domain);
        
        // Check for DKIM selector records - including Gmail's selector
        $selectors = ['20161025', 'default', 'google', 'mail', 'key1', 'dkim'];
        $has_dkim = false;
        
        foreach ($selectors as $selector) {
            $dkim_domain = $selector . '._domainkey.' . $domain;
            error_log('AVP Pro: Checking DKIM record for selector: ' . $dkim_domain);
            
            if (checkdnsrr($dkim_domain, 'TXT')) {
                error_log('AVP Pro: Found DKIM record for selector: ' . $selector);
                $has_dkim = true;
                break;
            }
        }
        
        if (!$has_dkim) {
            // Try checking for any _domainkey record
            if (checkdnsrr('_domainkey.' . $domain, 'TXT')) {
                error_log('AVP Pro: Found generic _domainkey record');
                $has_dkim = true;
            }
        }
        
        if (!$has_dkim) {
            error_log('AVP Pro: No DKIM records found for domain: ' . $domain);
            $valid = false;
            $messages[] = 'הדומיין ' . $domain . ' לא מוגדר עם חתימה דיגיטלית (DKIM)';
        } else {
            error_log('AVP Pro: Domain has valid DKIM configuration');
        }
    }

    // SMTP verification if enabled
    if ($settings['verify_smtp']) {
        error_log('AVP Pro: Starting SMTP verification for: ' . $email);
        
        // Get domain's mail servers (required for SMTP check)
        if (!getmxrr($domain, $mx_records)) {
            error_log('AVP Pro: No mail servers found for domain');
            $result = ['valid' => false, 'message' => 'לא נמצאו שרתי דואר עבור הדומיין'];
            error_log('AVP Pro: Returning validation result: ' . print_r($result, true));
            return $result;
        }
        
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->SMTPAuth = false;
            $mail->Host = $mx_records[0];
            $mail->Port = 25;
            $mail->Timeout = 5;
            
            error_log('AVP Pro: Attempting SMTP connection to: ' . $mx_records[0]);
            
            // Connect to SMTP server
            if (!$mail->smtpConnect()) {
                error_log('AVP Pro: Failed to connect to SMTP server');
                $result = ['valid' => false, 'message' => 'לא ניתן להתחבר לשרת המייל'];
                error_log('AVP Pro: Returning validation result: ' . print_r($result, true));
                return $result;
            }
            
            // Try MAIL FROM command
            $smtp = $mail->getSMTPInstance();
            if (!$smtp->mail("test@example.com")) {
                error_log('AVP Pro: MAIL FROM command failed');
                $result = ['valid' => false, 'message' => 'בדיקת SMTP נכשלה'];
                error_log('AVP Pro: Returning validation result: ' . print_r($result, true));
                return $result;
            }
            
            // Try RCPT TO command to verify if email exists
            if (!$smtp->recipient($email)) {
                error_log('AVP Pro: RCPT TO command failed - email does not exist');
                $smtp->quit();
                $result = ['valid' => false, 'message' => 'כתובת האימייל אינה קיימת'];
                error_log('AVP Pro: Returning validation result: ' . print_r($result, true));
                return $result;
            }
            
            error_log('AVP Pro: SMTP verification successful');
            $smtp->quit();
            $result = ['valid' => true, 'message' => 'כתובת האימייל תקינה ומאומתת'];
            error_log('AVP Pro: Returning validation result: ' . print_r($result, true));
            return $result;
            
        } catch (Exception $e) {
            error_log('AVP Pro: SMTP verification error: ' . $e->getMessage());
            $result = ['valid' => false, 'message' => 'שגיאה באימות SMTP: ' . $e->getMessage()];
            error_log('AVP Pro: Returning validation result: ' . print_r($result, true));
            return $result;
        }
    }

    // Prepare final result
    if ($valid && empty($messages)) {
        $message = 'כתובת האימייל תקינה';
        if ($settings['verify_smtp']) {
            $message .= ' ומאומתת';
        }
    } else {
        $message = implode(', ', $messages);
    }

    $result = [
        'valid' => $valid === true ? true : false,
        'message' => $message
    ];

    error_log('AVP Pro: Final validation result: ' . print_r($result, true));
    return $result;
}

/**
 * Advanced phone validation with carrier detection
 */
function validate_phone_advanced($phone, $region = 'IL') {
    global $validation_cache;
    
    // Check cache
    $cache_key = md5($phone . $region);
    if (isset($validation_cache[$cache_key])) {
        return $validation_cache[$cache_key];
    }
    
    try {
        // Clean number
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Store original
        $originalPhone = $phone;
        
        // Add country code based on region if number starts with 0
        if (substr($phone, 0, 1) === '0') {
            switch ($region) {
                case 'IL':
                    $phone = '+972' . substr($phone, 1);
                    break;
                case 'GB':
                    $phone = '+44' . substr($phone, 1);
                    break;
                case 'AU':
                    $phone = '+61' . substr($phone, 1);
                    break;
                // US and CA don't typically start with 0
            }
        }
        
        // Use libphonenumber for validation
        $phoneUtil = PhoneNumberUtil::getInstance();
        try {
            $numberProto = $phoneUtil->parse($phone, $region);
        } catch (NumberParseException $e) {
            $result = [
                'valid' => false,
                'message' => 'Invalid phone number format'
            ];
            $validation_cache[$cache_key] = $result;
            return $result;
        }

        // Validate number
        if (!$phoneUtil->isValidNumber($numberProto)) {
            $result = [
                'valid' => false,
                'message' => 'Invalid phone number'
            ];
            $validation_cache[$cache_key] = $result;
            return $result;
        }

        // Format number
        $formatted = $phoneUtil->format($numberProto, PhoneNumberFormat::INTERNATIONAL);
        
        // Get carrier info based on region
        $carrierInfo = ['name' => 'Unknown', 'region' => $region];
        
        // Carrier detection based on region and prefix
        switch ($region) {
            case 'IL':
                $prefix = substr($originalPhone, 0, 3);
                $carriers = [
                    '050' => ['name' => 'Pelephone', 'region' => 'Israel'],
                    '051' => ['name' => 'Weicom', 'region' => 'Israel'],
                    '052' => ['name' => 'Cellcom', 'region' => 'Israel'],
                    '053' => ['name' => 'Hot Mobile', 'region' => 'Israel'],
                    '054' => ['name' => 'Partner', 'region' => 'Israel'],
                    '055' => ['name' => 'Rami Levy', 'region' => 'Israel'],
                    '058' => ['name' => 'Golan Telecom', 'region' => 'Israel'],
                    '056' => ['name' => 'Palestinian Provider', 'region' => 'Palestinian Authority'],
                    '059' => ['name' => 'Jawwal', 'region' => 'Palestinian Authority']
                ];
                $carrierInfo = $carriers[$prefix] ?? ['name' => 'Unknown', 'region' => 'Israel'];
                break;

            case 'US':
                $prefix = substr($originalPhone, 0, 3);
                // Major US carriers based on area codes
                $carriers = [
                    // AT&T common area codes
                    '212' => ['name' => 'AT&T', 'region' => 'New York'],
                    '213' => ['name' => 'AT&T', 'region' => 'Los Angeles'],
                    '310' => ['name' => 'AT&T', 'region' => 'Los Angeles'],
                    // Verizon common area codes
                    '347' => ['name' => 'Verizon', 'region' => 'New York'],
                    '415' => ['name' => 'Verizon', 'region' => 'San Francisco'],
                    '917' => ['name' => 'Verizon', 'region' => 'New York'],
                    // T-Mobile common area codes
                    '332' => ['name' => 'T-Mobile', 'region' => 'New York'],
                    '424' => ['name' => 'T-Mobile', 'region' => 'Los Angeles'],
                    // Sprint common area codes
                    '929' => ['name' => 'Sprint', 'region' => 'New York'],
                    '657' => ['name' => 'Sprint', 'region' => 'California']
                ];
                $carrierInfo = $carriers[$prefix] ?? ['name' => 'US Carrier', 'region' => 'United States'];
                break;

            case 'GB':
                $prefix = substr($originalPhone, 0, 4);
                $carriers = [
                    '7400' => ['name' => 'EE', 'region' => 'UK'],
                    '7401' => ['name' => 'EE', 'region' => 'UK'],
                    '7402' => ['name' => 'EE', 'region' => 'UK'],
                    '7500' => ['name' => 'Vodafone', 'region' => 'UK'],
                    '7501' => ['name' => 'Vodafone', 'region' => 'UK'],
                    '7502' => ['name' => 'Vodafone', 'region' => 'UK'],
                    '7700' => ['name' => 'O2', 'region' => 'UK'],
                    '7701' => ['name' => 'O2', 'region' => 'UK'],
                    '7702' => ['name' => 'O2', 'region' => 'UK'],
                    '7300' => ['name' => 'Three', 'region' => 'UK'],
                    '7301' => ['name' => 'Three', 'region' => 'UK'],
                    '7302' => ['name' => 'Three', 'region' => 'UK']
                ];
                $carrierInfo = $carriers[$prefix] ?? ['name' => 'UK Carrier', 'region' => 'United Kingdom'];
                break;

            case 'AU':
                $prefix = substr($originalPhone, 0, 4);
                $carriers = [
                    '0402' => ['name' => 'Optus', 'region' => 'Australia'],
                    '0403' => ['name' => 'Optus', 'region' => 'Australia'],
                    '0404' => ['name' => 'Vodafone', 'region' => 'Australia'],
                    '0405' => ['name' => 'Vodafone', 'region' => 'Australia'],
                    '0407' => ['name' => 'Telstra', 'region' => 'Australia'],
                    '0408' => ['name' => 'Telstra', 'region' => 'Australia'],
                    '0412' => ['name' => 'Telstra', 'region' => 'Australia'],
                    '0413' => ['name' => 'Optus', 'region' => 'Australia'],
                    '0414' => ['name' => 'Vodafone', 'region' => 'Australia'],
                    '0419' => ['name' => 'Telstra', 'region' => 'Australia']
                ];
                $carrierInfo = $carriers[$prefix] ?? ['name' => 'AU Carrier', 'region' => 'Australia'];
                break;

            case 'FR':
                $prefix = substr($originalPhone, 0, 4);
                $carriers = [
                    '0601' => ['name' => 'Orange', 'region' => 'France'],
                    '0607' => ['name' => 'Orange', 'region' => 'France'],
                    '0620' => ['name' => 'Bouygues', 'region' => 'France'],
                    '0630' => ['name' => 'SFR', 'region' => 'France'],
                    '0640' => ['name' => 'Free Mobile', 'region' => 'France'],
                    '0650' => ['name' => 'Orange', 'region' => 'France'],
                    '0660' => ['name' => 'Bouygues', 'region' => 'France'],
                    '0670' => ['name' => 'SFR', 'region' => 'France'],
                    '0680' => ['name' => 'Free Mobile', 'region' => 'France']
                ];
                $carrierInfo = $carriers[$prefix] ?? ['name' => 'FR Carrier', 'region' => 'France'];
                break;

            case 'DE':
                $prefix = substr($originalPhone, 0, 4);
                $carriers = [
                    '0151' => ['name' => 'Deutsche Telekom', 'region' => 'Germany'],
                    '0152' => ['name' => 'Deutsche Telekom', 'region' => 'Germany'],
                    '0157' => ['name' => 'E-Plus', 'region' => 'Germany'],
                    '0159' => ['name' => 'O2', 'region' => 'Germany'],
                    '0160' => ['name' => 'Deutsche Telekom', 'region' => 'Germany'],
                    '0170' => ['name' => 'Deutsche Telekom', 'region' => 'Germany'],
                    '0176' => ['name' => 'O2', 'region' => 'Germany'],
                    '0177' => ['name' => 'E-Plus', 'region' => 'Germany'],
                    '0179' => ['name' => 'O2', 'region' => 'Germany']
                ];
                $carrierInfo = $carriers[$prefix] ?? ['name' => 'DE Carrier', 'region' => 'Germany'];
                break;
        }
        
        $result = [
            'valid' => true,
            'message' => sprintf(
                'Valid phone number (%s) - Carrier: %s, Region: %s',
                $formatted,
                $carrierInfo['name'],
                $carrierInfo['region']
            ),
            'formatted' => $formatted,
            'carrier' => $carrierInfo['name'],
            'region' => $carrierInfo['region']
        ];
        
        $validation_cache[$cache_key] = $result;
        return $result;
        
    } catch (\Exception $e) {
        $result = [
            'valid' => false,
            'message' => 'Error validating phone number'
        ];
        $validation_cache[$cache_key] = $result;
        return $result;
    }
}

/**
 * Contact Form 7 email validation
 */
function validate_cf7_email($result, $tag) {
    $email = isset($_POST[$tag->name]) ? \sanitize_email($_POST[$tag->name]) : '';
    
    if ($email) {
        $validation = validate_email_advanced($email);
        if (!$validation['valid']) {
            $result->invalidate($tag, $validation['message']);
        }
    }
    
    return $result;
}

/**
 * Contact Form 7 phone validation
 */
function validate_cf7_phone($result, $tag) {
    $phone = isset($_POST[$tag->name]) ? \sanitize_text_field($_POST[$tag->name]) : '';
    
    if ($phone) {
        $validation = validate_phone_advanced($phone);
        if (!$validation['valid']) {
            $result->invalidate($tag, $validation['message']);
        }
    }
    
    return $result;
}

/**
 * Elementor form validation
 */
function validate_elementor_form($record, $ajax_handler) {
    error_log('AVP Pro: Elementor validation started');
    
    try {
        $fields = $record->get_field(['id', 'value', 'type']);
        
        foreach ($fields as $field) {
            if (!isset($field['id'], $field['value'], $field['type'])) {
                error_log('AVP Pro: Invalid field structure: ' . print_r($field, true));
                continue;
            }

            $id = $field['id'];
            $value = $field['value'];
            $type = $field['type'];
            
            error_log("AVP Pro: Processing field - ID: $id, Type: $type, Value: $value");
            
            if ($type === 'email' && !empty($value)) {
                error_log('AVP Pro: Validating email field: ' . $value);
                try {
                    $validation = validate_email_advanced($value);
                    error_log('AVP Pro: Email validation result: ' . print_r($validation, true));
                    
                    // Always remove any existing errors first
                    $ajax_handler->remove_error($id);
                    
                    // Check if validation failed
                    if (!$validation['valid']) {
                        error_log('AVP Pro: Adding error for field ' . $id . ': ' . $validation['message']);
                        $ajax_handler->add_error($id, $validation['message']);
                        $record->set_status('invalid', 1);
                        error_log('AVP Pro: Set form status to invalid');
                    }
                } catch (\Exception $e) {
                    error_log('AVP Pro: Email validation error: ' . $e->getMessage());
                    $ajax_handler->add_error($id, 'שגיאה בבדיקת כתובת האימייל');
                    $record->set_status('invalid', 1);
                    error_log('AVP Pro: Set form status to invalid due to error');
                }
            }
            
            if ($type === 'tel' && !empty($value)) {
                error_log('AVP Pro: Validating phone field: ' . $value);
                try {
                    $validation = validate_phone_advanced($value);
                    error_log('AVP Pro: Phone validation result: ' . print_r($validation, true));
                    
                    // Clear any existing errors first
                    $ajax_handler->remove_error($id);
                    
                    // Only add error if validation failed
                    if (!$validation['valid']) {
                        $ajax_handler->add_error($id, $validation['message']);
                        error_log('AVP Pro: Added phone error: ' . $validation['message']);
                    } else {
                        // If validation passed, add success message
                        $ajax_handler->add_success_message($validation['message']);
                        error_log('AVP Pro: Added success message: ' . $validation['message']);
                    }
                } catch (\Exception $e) {
                    error_log('AVP Pro: Phone validation error: ' . $e->getMessage());
                    $ajax_handler->add_error($id, 'שגיאה בבדיקת מספר הטלפון');
                }
            }
        }
    } catch (\Exception $e) {
        error_log('AVP Pro: General validation error: ' . $e->getMessage());
    }
} 