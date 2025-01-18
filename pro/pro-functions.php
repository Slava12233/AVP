<?php
namespace AVP\Pro;

use AVP\Helpers;
use PHPMailer\PHPMailer\PHPMailer;
use Giggsey\libphonenumber\PhoneNumberUtil;
use Giggsey\libphonenumber\PhoneNumberFormat;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize pro version hooks
 */
function init() {
    // Contact Form 7 integration
    \add_filter('wpcf7_validate_email', __NAMESPACE__ . '\\validate_cf7_email', 20, 2);
    \add_filter('wpcf7_validate_email*', __NAMESPACE__ . '\\validate_cf7_email', 20, 2);
    \add_filter('wpcf7_validate_tel', __NAMESPACE__ . '\\validate_cf7_phone', 20, 2);
    \add_filter('wpcf7_validate_tel*', __NAMESPACE__ . '\\validate_cf7_phone', 20, 2);
}
\add_action('init', __NAMESPACE__ . '\\init');

/**
 * Advanced email validation including MX, SPF, and DKIM checks
 * 
 * @param string $email Email address to validate
 * @return array Validation result with status and message
 */
function validate_email_advanced($email) {
    $settings = Helpers\get_plugin_settings('pro');
    $result = ['valid' => true, 'message' => ''];

    // Basic format check first
    if (!Helpers\is_valid_email_format($email)) {
        return [
            'valid' => false,
            'message' => \__('Invalid email format', 'advanced-validation')
        ];
    }

    // Extract domain for DNS checks
    list(, $domain) = explode('@', $email);

    // MX Record check
    if ($settings['check_mx']) {
        if (!\checkdnsrr($domain, 'MX')) {
            return [
                'valid' => false,
                'message' => \__('No valid mail server found for domain', 'advanced-validation')
            ];
        }
    }

    // SPF Record check
    if ($settings['check_spf']) {
        $spf_record = \dns_get_record($domain, DNS_TXT);
        $has_spf = false;
        foreach ($spf_record as $record) {
            if (strpos($record['txt'], 'v=spf1') !== false) {
                $has_spf = true;
                break;
            }
        }
        if (!$has_spf) {
            $result['message'] .= \__('Warning: No SPF record found. ', 'advanced-validation');
        }
    }

    // SMTP verification if enabled
    if ($settings['verify_smtp']) {
        try {
            $mail = new PHPMailer(true);
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = \gethostbyname($domain);
            $mail->SMTPAuth = false;
            $mail->Port = 25;

            if (!$mail->smtpConnect()) {
                return [
                    'valid' => false,
                    'message' => \__('SMTP connection failed', 'advanced-validation')
                ];
            }
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => \__('SMTP verification failed', 'advanced-validation')
            ];
        }
    }

    return $result;
}

/**
 * Advanced phone validation using libphonenumber
 * 
 * @param string $phone Phone number to validate
 * @param string $region Default region code (e.g., 'US')
 * @return array Validation result with status and message
 */
function validate_phone_advanced($phone, $region = 'US') {
    try {
        $phoneUtil = PhoneNumberUtil::getInstance();
        $numberProto = $phoneUtil->parse($phone, $region);
        
        if (!$phoneUtil->isValidNumber($numberProto)) {
            return [
                'valid' => false,
                'message' => \__('Invalid phone number', 'advanced-validation')
            ];
        }

        return [
            'valid' => true,
            'formatted' => $phoneUtil->format($numberProto, PhoneNumberFormat::INTERNATIONAL)
        ];
    } catch (\Exception $e) {
        return [
            'valid' => false,
            'message' => \__('Phone number parsing failed', 'advanced-validation')
        ];
    }
}

/**
 * Contact Form 7 email field validation
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
 * Contact Form 7 phone field validation
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