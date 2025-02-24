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
        
        // Add country code
        if (substr($phone, 0, 1) === '0') {
            $phone = '+972' . substr($phone, 1);
        }
        
        // Use libphonenumber for validation
        $phoneUtil = PhoneNumberUtil::getInstance();
        try {
            $numberProto = $phoneUtil->parse($phone, $region);
        } catch (NumberParseException $e) {
            $result = [
                'valid' => false,
                'message' => 'מספר הטלפון בפורמט לא תקין'
            ];
            $validation_cache[$cache_key] = $result;
            return $result;
        }

        // Validate number
        if (!$phoneUtil->isValidNumber($numberProto)) {
            $result = [
                'valid' => false,
                'message' => 'מספר הטלפון לא תקין'
            ];
            $validation_cache[$cache_key] = $result;
            return $result;
        }

        // Format number
        $formatted = $phoneUtil->format($numberProto, PhoneNumberFormat::INTERNATIONAL);
        
        // Get carrier info
        $prefix = substr($originalPhone, 0, 3);
        $carriers = [
            '050' => ['name' => 'פלאפון', 'region' => 'ארצי'],
            '051' => ['name' => 'וויקום', 'region' => 'ארצי'],
            '052' => ['name' => 'סלקום', 'region' => 'ארצי'],
            '053' => ['name' => 'הוט מובייל', 'region' => 'ארצי'],
            '054' => ['name' => 'פרטנר', 'region' => 'ארצי'],
            '055' => ['name' => 'רמי לוי', 'region' => 'ארצי'],
            '058' => ['name' => 'גולן טלקום', 'region' => 'ארצי'],
            '056' => ['name' => 'מפעיל פלשתינאי', 'region' => 'שטחי הרשות הפלסטינית'],
            '059' => ['name' => 'ג\'וואל', 'region' => 'שטחי הרשות הפלסטינית']
        ];
        
        $carrierInfo = $carriers[$prefix] ?? ['name' => 'לא ידוע', 'region' => 'לא ידוע'];
        
        $result = [
            'valid' => true,
            'message' => sprintf(
                'מספר הטלפון תקין (ספק: %s, אזור: %s)',
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
            'message' => 'שגיאה בבדיקת מספר הטלפון'
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