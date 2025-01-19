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

// Static cache
$validation_cache = [];

/**
 * Advanced email validation with optimized SMTP check
 */
function validate_email_advanced($email) {
    try {
        // Basic validation first
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'כתובת המייל לא תקינה'];
        }

        // Get domain from email
        $domain = substr(strrchr($email, "@"), 1);
        
        // Check MX records
        if (!getmxrr($domain, $mx_records)) {
            return ['valid' => false, 'message' => 'לא נמצא שרת מייל תקין'];
        }

        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 3;
        $mail->isSMTP();
        $mail->Host = $mx_records[0];
        $mail->Port = 25;
        $mail->Timeout = 5;

        // Try SMTP connection
        try {
            $smtp = $mail->getSMTPInstance();
            if (!$smtp->connect($mail->Host, $mail->Port)) {
                throw new Exception('Connection failed');
            }
            
            if (!$smtp->hello(gethostname())) {
                $smtp->quit();
                throw new Exception('HELO failed');
            }
            
            // Try MAIL FROM
            if (!$smtp->mail("test@example.com")) {
                $smtp->quit();
                throw new Exception('MAIL FROM failed');
            }
            
            // Try RCPT TO
            if (!$smtp->recipient($email)) {
                $smtp->quit();
                return ['valid' => false, 'message' => 'כתובת המייל לא קיימת'];
            }
            
            $smtp->quit();
            return ['valid' => true, 'message' => 'כתובת המייל תקינה'];
            
        } catch (Exception $e) {
            if ($smtp && $smtp->connected()) {
                $smtp->quit();
            }
            // If SMTP check fails but MX exists, consider it valid
            return ['valid' => false, 'message' => 'לא ניתן לאמת את כתובת המייל'];
        }
        
    } catch (Exception $e) {
        return ['valid' => false, 'message' => 'שגיאה בבדיקת המייל'];
    }
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

// Hook into form plugins
add_filter('wpcf7_validate_email', __NAMESPACE__ . '\\validate_cf7_email', 20, 2);
add_filter('wpcf7_validate_email*', __NAMESPACE__ . '\\validate_cf7_email', 20, 2);
add_filter('wpcf7_validate_tel', __NAMESPACE__ . '\\validate_cf7_phone', 20, 2);
add_filter('wpcf7_validate_tel*', __NAMESPACE__ . '\\validate_cf7_phone', 20, 2);
add_action('elementor_pro/forms/validation', __NAMESPACE__ . '\\validate_elementor_form', 10, 2);

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
    $fields = $record->get_field(['id', 'value', 'type']);
    
    foreach ($fields as $field) {
        $id = $field['id'];
        $value = $field['value'];
        $type = $field['type'];
        
        if ($type === 'email' && !empty($value)) {
            $validation = validate_email_advanced($value);
            if (!$validation['valid']) {
                $ajax_handler->add_error($id, $validation['message']);
            }
        }
        
        if ($type === 'tel' && !empty($value)) {
            $validation = validate_phone_advanced($value);
            if (!$validation['valid']) {
                $ajax_handler->add_error($id, $validation['message']);
            }
        }
    }
} 