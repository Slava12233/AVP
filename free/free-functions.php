<?php
namespace AVP\Free;

use AVP\Helpers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize free version hooks
 */
function init() {
    // Debug log
    error_log('AVP: Free init called');
    
    // Add AJAX endpoints
    add_action('wp_ajax_avp_validate_email', __NAMESPACE__ . '\\validate_email_ajax');
    add_action('wp_ajax_nopriv_avp_validate_email', __NAMESPACE__ . '\\validate_email_ajax');
    add_action('wp_ajax_avp_validate_phone', __NAMESPACE__ . '\\validate_phone_ajax');
    add_action('wp_ajax_nopriv_avp_validate_phone', __NAMESPACE__ . '\\validate_phone_ajax');
    
    // Add form validation hooks - use only one hook per type
    add_action('elementor_pro/forms/validation', __NAMESPACE__ . '\\validate_elementor_form', 10, 2);
    
    // Add frontend scripts and styles
    add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_validation_scripts');
    
    // WooCommerce checkout validation
    add_action('woocommerce_checkout_process', __NAMESPACE__ . '\\validate_woo_checkout');
}
add_action('init', __NAMESPACE__ . '\\init');

/**
 * Enqueue frontend validation scripts and styles
 */
function enqueue_validation_scripts() {
    error_log('AVP: Enqueuing scripts and styles');
    
    // Enqueue CSS
    wp_enqueue_style(
        'avp-validation-style',
        plugin_dir_url(dirname(__FILE__)) . 'assets/css/style.css',
        array(),
        '1.0.0'
    );
    
    // Enqueue JavaScript
    wp_enqueue_script(
        'avp-validation',
        plugin_dir_url(dirname(__FILE__)) . 'assets/js/validation.js',
        array('jquery'),
        '1.0.0',
        true
    );
    
    // Add AJAX data
    wp_localize_script('avp-validation', 'avpAjax', array(
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('avp_validation_nonce')
    ));
    
    error_log('AVP: Scripts and styles enqueued');
}

/**
 * AJAX endpoint for email validation
 */
function validate_email_ajax() {
    check_ajax_referer('avp_validation_nonce', 'nonce');
    
    if (!isset($_POST['email'])) {
        wp_send_json_error(array('message' => 'לא סופקה כתובת אימייל'));
    }
    
    $email = sanitize_email($_POST['email']);
    
    // Don't validate empty emails in real-time
    if (empty($email)) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => ''
        ));
        return;
    }
    
    // Check basic structure first
    if (!strpos($email, '@')) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'פורמט לא תקין - חסר @ בכתובת האימייל'
        ));
        return;
    }
    
    // Split email into parts
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'פורמט לא תקין - כתובת האימייל חייבת להכיל חלק לפני ואחרי ה-@'
        ));
        return;
    }
    
    $local = $parts[0];
    $domain = $parts[1];
    
    // Check local part
    if (empty($local)) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'פורמט לא תקין - חסר שם משתמש לפני ה-@'
        ));
        return;
    }
    
    // Check domain structure
    if (empty($domain)) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'פורמט לא תקין - חסר שם דומיין אחרי ה-@'
        ));
        return;
    }
    
    if (!strpos($domain, '.')) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'פורמט לא תקין - חסרה סיומת בכתובת האימייל (לדוגמה: .com)'
        ));
        return;
    }
    
    // Check if domain ends with a dot
    if (substr($domain, -1) === '.') {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'פורמט לא תקין - כתובת האימייל לא יכולה להסתיים בנקודה'
        ));
        return;
    }
    
    // Check domain parts
    $domain_parts = explode('.', $domain);
    $tld = end($domain_parts);
    
    // Check if TLD is empty or too short
    if (empty($tld)) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'פורמט לא תקין - חסרה סיומת אחרי הנקודה (לדוגמה: com)'
        ));
        return;
    }
    
    if (strlen($tld) < 2) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'פורמט לא תקין - סיומת האימייל חייבת להכיל לפחות 2 תווים'
        ));
        return;
    }
    
    // Check for invalid characters in local part
    if (!preg_match('/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~.-]+$/', $local)) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'פורמט לא תקין - שם המשתמש מכיל תווים לא חוקיים'
        ));
        return;
    }
    
    // Final validation using WordPress function
    if (!Helpers\is_valid_email_format($email)) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'פורמט לא תקין - נא להזין כתובת אימייל חוקית'
        ));
        return;
    }
    
    wp_send_json_success(array(
        'valid' => true,
        'message' => 'פורמט האימייל תקין'
    ));
}

/**
 * AJAX endpoint for phone validation
 */
function validate_phone_ajax() {
    error_log('AVP: Phone validation started');
    
    try {
        check_ajax_referer('avp_validation_nonce', 'nonce');
        
        if (!isset($_POST['tel'])) {
            error_log('AVP: No phone number provided');
            wp_send_json_error(array('message' => 'לא סופק מספר טלפון'));
        }
        
        $phone = sanitize_text_field($_POST['tel']);
        error_log('AVP: Validating phone: ' . $phone);
        
        // Don't validate empty phone numbers
        if (empty($phone)) {
            wp_send_json_success(array(
                'valid' => true,
                'message' => ''
            ));
            return;
        }

        // Basic format validation
        if (!preg_match('/^(0[5][0-9]{8}|[5][0-9]{8})$/', $phone)) {
            error_log('AVP: Invalid phone format: ' . $phone);
            wp_send_json_success(array(
                'valid' => false,
                'message' => 'פורמט לא תקין - נדרש מספר נייד ישראלי תקין'
            ));
            return;
        }
        
        error_log('AVP: Valid phone format: ' . $phone);
        wp_send_json_success(array(
            'valid' => true,
            'message' => 'מספר הטלפון תקין'
        ));
        
    } catch (\Exception $e) {
        error_log('AVP: Error in phone validation: ' . $e->getMessage());
        wp_send_json_error(array(
            'message' => 'שגיאה בבדיקת מספר הטלפון'
        ));
    }
}

/**
 * Validate Elementor form fields
 */
function validate_elementor_form($record, $ajax_handler) {
    static $validated_fields = array();
    
    try {
        $settings = Helpers\get_plugin_settings('free');
        $raw_fields = $record->get_field(['id', 'value', 'type']);
        
        foreach ($raw_fields as $field) {
            if (!isset($field['id'], $field['value'], $field['type'])) {
                continue;
            }
            
            $id = $field['id'];
            $value = $field['value'];
            $type = $field['type'];
            
            // Skip if already validated
            if (isset($validated_fields[$id])) {
                continue;
            }
            
            // Mark as validated
            $validated_fields[$id] = true;
            
            // Remove any existing validation messages for this field
            $ajax_handler->remove_error($id);
            
            if ($type === 'email' && $settings['validate_email']) {
                if (!empty($value)) {
                    // Check basic structure first
                    if (!strpos($value, '@')) {
                        $ajax_handler->add_error($id, 'פורמט לא תקין - חסר @ בכתובת האימייל');
                        continue;
                    }
                    
                    // Split email into parts
                    $parts = explode('@', $value);
                    if (count($parts) !== 2) {
                        $ajax_handler->add_error($id, 'פורמט לא תקין - כתובת האימייל חייבת להכיל חלק לפני ואחרי ה-@');
                        continue;
                    }
                    
                    $local = $parts[0];
                    $domain = $parts[1];
                    
                    // Check local part
                    if (empty($local)) {
                        $ajax_handler->add_error($id, 'פורמט לא תקין - חסר שם משתמש לפני ה-@');
                        continue;
                    }
                    
                    // Check domain structure
                    if (empty($domain)) {
                        $ajax_handler->add_error($id, 'פורמט לא תקין - חסר שם דומיין אחרי ה-@');
                        continue;
                    }
                    
                    if (!strpos($domain, '.')) {
                        $ajax_handler->add_error($id, 'פורמט לא תקין - חסרה סיומת בכתובת האימייל (לדוגמה: .com)');
                        continue;
                    }
                    
                    // Check if domain ends with a dot
                    if (substr($domain, -1) === '.') {
                        $ajax_handler->add_error($id, 'פורמט לא תקין - כתובת האימייל לא יכולה להסתיים בנקודה');
                        continue;
                    }
                    
                    // Check domain parts
                    $domain_parts = explode('.', $domain);
                    $tld = end($domain_parts);
                    
                    // Check if TLD is empty or too short
                    if (empty($tld) || strlen($tld) < 2) {
                        $ajax_handler->add_error($id, 'פורמט לא תקין - סיומת האימייל חייבת להכיל לפחות 2 תווים');
                        continue;
                    }
                    
                    // Check for invalid characters in local part
                    if (!preg_match('/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~.-]+$/', $local)) {
                        $ajax_handler->add_error($id, 'פורמט לא תקין - שם המשתמש מכיל תווים לא חוקיים');
                        continue;
                    }
                }
            }
            
            if ($type === 'tel' && $settings['validate_phone']) {
                if (!empty($value)) {
                    // Basic format validation - same as AJAX endpoint
                    if (!preg_match('/^(0[5][0-9]{8}|[5][0-9]{8})$/', $value)) {
                        $ajax_handler->add_error($id, 'פורמט לא תקין - נדרש מספר נייד ישראלי תקין');
                    }
                }
            }
        }
    } catch (\Exception $e) {
        error_log('AVP: Error in validation: ' . $e->getMessage());
    }
}

/**
 * Validate WooCommerce checkout fields
 */
function validate_woo_checkout() {
    $settings = Helpers\get_plugin_settings('free');

    if ($settings['validate_email']) {
        $billing_email = \sanitize_text_field($_POST['billing_email'] ?? '');
        if (!empty($billing_email) && !Helpers\is_valid_email_format($billing_email)) {
            throw new \Exception(
                Helpers\format_error_message(
                    \__('Invalid billing email format', 'advanced-validation'),
                    'email'
                )
            );
        }
    }

    if ($settings['validate_phone']) {
        $billing_phone = \sanitize_text_field($_POST['billing_phone'] ?? '');
        if (!empty($billing_phone) && !Helpers\is_valid_phone_format($billing_phone)) {
            throw new \Exception(
                Helpers\format_error_message(
                    \__('Invalid billing phone format', 'advanced-validation'),
                    'phone'
                )
            );
        }
    }
} 