<?php
namespace AVP\Free;

use AVP\Helpers;
use AVP\License;

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
    add_action('wp_ajax_avp_validate_email', __NAMESPACE__ . '\\handle_email_validation');
    add_action('wp_ajax_nopriv_avp_validate_email', __NAMESPACE__ . '\\handle_email_validation');
    add_action('wp_ajax_avp_validate_phone', __NAMESPACE__ . '\\validate_phone_ajax');
    add_action('wp_ajax_nopriv_avp_validate_phone', __NAMESPACE__ . '\\validate_phone_ajax');
    
    // Add form validation hooks only if pro is not active
    if (!License\avp_is_pro_active()) {
        error_log('AVP: Adding free version Elementor validation');
        add_action('elementor_pro/forms/validation', __NAMESPACE__ . '\\validate_elementor_form', 20, 2);
    } else {
        error_log('AVP: Pro version active, skipping free validation hooks');
        // Remove our validation if it was added before
        remove_action('elementor_pro/forms/validation', __NAMESPACE__ . '\\validate_elementor_form', 20);
    }
    
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
function handle_email_validation() {
    check_ajax_referer('avp_validation_nonce', 'nonce');
    
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    if (empty($email)) {
        wp_send_json_success(['valid' => false, 'message' => 'נדרשת כתובת אימייל']);
        return;
    }
    
    // If Pro is active, use advanced validation
    if (License\avp_is_pro_active()) {
        error_log('AVP: Using advanced email validation');
        try {
            $result = \AVP\Pro\validate_email_advanced($email);
            error_log('AVP: Advanced email validation result: ' . print_r($result, true));
            wp_send_json_success($result);
            return;
        } catch (\Exception $e) {
            error_log('AVP: Advanced email validation error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'שגיאה בבדיקת כתובת האימייל'));
            return;
        }
    }
    
    // Basic validation for free version
    $result = validate_email($email);
    wp_send_json_success($result);
}
add_action('wp_ajax_avp_validate_email', __NAMESPACE__ . '\\handle_email_validation');
add_action('wp_ajax_nopriv_avp_validate_email', __NAMESPACE__ . '\\handle_email_validation');

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

        // If Pro is active, use advanced validation
        if (License\avp_is_pro_active()) {
            error_log('AVP: Using advanced phone validation');
            try {
                $settings = Helpers\get_plugin_settings('pro');
                $region = $settings['default_region'] ?? 'IL';
                $result = \AVP\Pro\validate_phone_advanced($phone, $region);
                error_log('AVP: Advanced validation result: ' . print_r($result, true));
                wp_send_json_success($result);
                return;
            } catch (\Exception $e) {
                error_log('AVP: Advanced validation error: ' . $e->getMessage());
                wp_send_json_error(array('message' => 'שגיאה בבדיקת מספר הטלפון'));
                return;
            }
        }

        // Get selected region from settings
        $settings = get_option('avp_free_settings', []);
        $region = isset($settings['default_region']) ? $settings['default_region'] : 'IL';
        error_log('AVP: Using region: ' . $region);
        
        // Clean phone number from any non-digit characters
        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        error_log('AVP: Clean phone number: ' . $clean_phone);
        
        $valid = false;
        $message = '';
        
        switch ($region) {
            case 'IL':
                // Israeli phone format (including mobile and landline)
                $valid = preg_match('/^(0[23489][0-9]{7}|0[57][0-9]{8})$/', $phone);
                $message = $valid ? 'מספר הטלפון תקין' : 'פורמט לא תקין - נדרש מספר טלפון ישראלי תקין';
                break;
                
            case 'US':
                // US phone format - must start with digit 2-9 and be exactly 10 digits
                $valid = strlen($clean_phone) === 10 && preg_match('/^[2-9]/', $clean_phone);
                $message = $valid ? 'Valid phone number' : 'Invalid format - must be a valid US phone number (10 digits, starting with 2-9)';
                break;
                
            case 'GB':
                // UK phone format - must start with 07 and be 11 digits, or start with 7 and be 10 digits
                $valid = (strlen($clean_phone) === 11 && substr($clean_phone, 0, 2) === '07') || 
                        (strlen($clean_phone) === 10 && substr($clean_phone, 0, 1) === '7');
                $message = $valid ? 'Valid phone number' : 'Invalid format - must be a valid UK mobile number';
                break;

            case 'CA':
                // Canadian phone format - same as US (NANP)
                $valid = strlen($clean_phone) === 10 && preg_match('/^[2-9]/', $clean_phone);
                $message = $valid ? 'Valid phone number' : 'Invalid format - must be a valid Canadian phone number (10 digits, starting with 2-9)';
                break;

            case 'AU':
                // Australian phone format
                // Mobile: starts with 04, length 10
                // Landline: starts with 02,03,07,08, length 10
                $valid = strlen($clean_phone) === 10 && 
                        (
                            (substr($clean_phone, 0, 2) === '04') || // Mobile
                            (in_array(substr($clean_phone, 0, 2), ['02', '03', '07', '08'])) // Landline
                        );
                $message = $valid ? 'Valid phone number' : 'Invalid format - must be a valid Australian phone number (10 digits, starting with 02/03/04/07/08)';
                break;
                
            default:
                $valid = false;
                $message = 'Unsupported region';
        }
        
        error_log('AVP: Validation result - Valid: ' . ($valid ? 'true' : 'false') . ', Message: ' . $message);
        wp_send_json_success(array(
            'valid' => $valid,
            'message' => $message
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
                    // If Pro is active, use advanced validation
                    if (License\avp_is_pro_active()) {
                        error_log('AVP: Using advanced email validation for Elementor');
                        try {
                            $validation = \AVP\Pro\validate_email_advanced($value);
                            if (!$validation['valid']) {
                                $ajax_handler->add_error($id, $validation['message']);
                            }
                            continue;
                        } catch (\Exception $e) {
                            error_log('AVP: Advanced email validation error: ' . $e->getMessage());
                            $ajax_handler->add_error($id, 'שגיאה בבדיקת כתובת האימייל');
                            continue;
                        }
                    }
                    
                    // Basic validation for free version
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
                    
                    // Check for dot in domain
                    if (strpos($domain, '.') === false) {
                        $ajax_handler->add_error($id, 'פורמט לא תקין - חסרה נקודה וסיומת בכתובת האימייל (לדוגמה: .com)');
                        continue;
                    }
                    
                    // Check if domain ends with a dot
                    if (substr($domain, -1) === '.') {
                        $ajax_handler->add_error($id, 'פורמט לא תקין - כתובת האימייל לא יכולה להסתיים בנקודה');
                        continue;
                    }
                    
                    // Split domain parts and check TLD
                    $domain_parts = explode('.', $domain);
                    
                    // Check if we have at least two parts (domain and TLD)
                    if (count($domain_parts) < 2 || empty($domain_parts[1])) {
                        $ajax_handler->add_error($id, 'פורמט לא תקין - חסרה סיומת בכתובת האימייל (לדוגמה: .com)');
                        continue;
                    }
                    
                    $tld = end($domain_parts);
                    
                    // Check if TLD is empty or too short
                    if (empty($tld) || strlen($tld) < 2) {
                        $ajax_handler->add_error($id, 'פורמט לא תקין - חסרה סיומת תקינה אחרי הנקודה (לדוגמה: com)');
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
                    // Get selected region from settings
                    $settings = get_option('avp_free_settings', []);
                    $region = isset($settings['default_region']) ? $settings['default_region'] : 'IL';
                    
                    // Clean phone number
                    $phone = preg_replace('/[^0-9]/', '', $value);
                    
                    $valid = false;
                    $message = '';
                    
                    switch ($region) {
                        case 'IL':
                            // Israeli phone format (including mobile and landline)
                            $valid = preg_match('/^(0[23489][0-9]{7}|0[57][0-9]{8})$/', $value);
                            $message = $valid ? 'מספר הטלפון תקין' : 'פורמט לא תקין - נדרש מספר טלפון ישראלי תקין';
                            break;
                            
                        case 'US':
                            // US phone format (XXX) XXX-XXXX or XXX-XXX-XXXX
                            $valid = strlen($phone) === 10;
                            $message = $valid ? 'Valid phone number' : 'Invalid format - must be a valid US phone number (10 digits)';
                            break;
                            
                        case 'GB':
                            // UK phone format
                            $valid = (strlen($phone) === 11 && substr($phone, 0, 2) === '07') || 
                                    (strlen($phone) === 10 && substr($phone, 0, 1) === '7');
                            $message = $valid ? 'Valid phone number' : 'Invalid format - must be a valid UK mobile number';
                            break;
                            
                        case 'CA':
                            // Canadian phone format - same as US (NANP)
                            $valid = strlen($phone) === 10 && preg_match('/^[2-9]/', $phone);
                            $message = $valid ? 'Valid phone number' : 'Invalid format - must be a valid Canadian phone number (10 digits, starting with 2-9)';
                            break;

                        case 'AU':
                            // Australian phone format
                            // Mobile: starts with 04, length 10
                            // Landline: starts with 02,03,07,08, length 10
                            $valid = strlen($phone) === 10 && 
                                    (
                                        (substr($phone, 0, 2) === '04') || // Mobile
                                        (in_array(substr($phone, 0, 2), ['02', '03', '07', '08'])) // Landline
                                    );
                            $message = $valid ? 'Valid phone number' : 'Invalid format - must be a valid Australian phone number (10 digits, starting with 02/03/04/07/08)';
                            break;
                            
                        default:
                            $valid = false;
                            $message = 'Unsupported region';
                    }
                    
                    if (!$valid) {
                        $ajax_handler->add_error($id, $message);
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

/**
 * Add WooCommerce checkout validation
 */
function avp_validate_wc_checkout_email($valid, $email) {
    if (empty($email)) {
        return $valid;
    }

    if (!is_email($email)) {
        wc_add_notice(__('Please enter a valid email address.', 'advanced-validation'), 'error');
        return false;
    }

    return $valid;
}
add_filter('woocommerce_validate_email', __NAMESPACE__ . '\\avp_validate_wc_checkout_email', 10, 2);

function avp_validate_wc_checkout_phone($valid, $phone) {
    if (empty($phone)) {
        return $valid;
    }

    // Basic phone format validation
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a valid Israeli phone number format
    if (!preg_match('/^0[0-9]{8,9}$/', $phone)) {
        wc_add_notice(__('Please enter a valid phone number.', 'advanced-validation'), 'error');
        return false;
    }

    return $valid;
}
add_filter('woocommerce_validate_billing_phone', __NAMESPACE__ . '\\avp_validate_wc_checkout_phone', 10, 2);

/**
 * Add custom validation classes to WooCommerce fields
 */
function avp_add_wc_field_validation_classes() {
    // Get plugin settings
    $settings = Helpers\get_plugin_settings('free');
    ?>
    <script type="text/javascript">
    // Add plugin settings to window object
    window.avpSettings = {
        showLabels: <?php echo isset($settings['show_labels']) && $settings['show_labels'] ? 'true' : 'false'; ?>,
        highlightFields: <?php echo isset($settings['highlight_fields']) && $settings['highlight_fields'] ? 'true' : 'false'; ?>,
        errorColor: '<?php echo esc_js($settings['error_color'] ?? '#f44336'); ?>',
        successColor: '<?php echo esc_js($settings['success_color'] ?? '#4CAF50'); ?>'
    };

    jQuery(document).ready(function($) {
        // Prevent multiple initializations
        if (window.avpWooInitialized) return;
        window.avpWooInitialized = true;

        // Add or remove highlight class based on settings
        if (window.avpSettings.highlightFields) {
            $('body').addClass('avp-highlight-enabled');
        } else {
            $('body').removeClass('avp-highlight-enabled');
        }

        // Set CSS variables for colors
        document.documentElement.style.setProperty('--avp-error-color', window.avpSettings.errorColor);
        document.documentElement.style.setProperty('--avp-success-color', window.avpSettings.successColor);

        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function validateField(field, type) {
            const $field = $(field);
            const $parent = $field.closest('.form-row');
            const value = $field.val();
            
            // Clear previous validation classes
            $parent.removeClass('avp-valid avp-invalid');
            $parent.find('.avp-validation-message, .woocommerce-error, .woocommerce-message').remove();
            
            if (!value) return;
            
            // Send validation request
            $.ajax({
                url: avpAjax.url,
                type: 'POST',
                data: {
                    action: type === 'email' ? 'avp_validate_email' : 'avp_validate_phone',
                    nonce: avpAjax.nonce,
                    [type]: value
                },
                success: function(response) {
                    if (response.success && response.data) {
                        const isValid = response.data.valid;
                        
                        // Remove any existing validation classes and messages
                        $parent.removeClass('avp-valid avp-invalid');
                        $parent.find('.avp-validation-message, .woocommerce-error, .woocommerce-message').remove();
                        
                        // Add new validation class
                        $parent.addClass(isValid ? 'avp-valid' : 'avp-invalid');
                        
                        // Always show message if we have one and labels are enabled
                        if (window.avpSettings.showLabels && response.data.message) {
                            // Create new message element with appropriate classes
                            const $message = $('<div>')
                                .addClass('avp-validation-message')
                                .addClass(isValid ? 'avp-valid woocommerce-message' : 'avp-invalid woocommerce-error')
                                .text(response.data.message);
                            
                            // Insert after the input field
                            $field.after($message);
                        }
                    }
                }
            });
        }

        // Debounced validation function
        const debouncedValidate = debounce(validateField, 500);
        
        // Add validation to email field
        const emailField = '#billing_email';
        $(document).on('input', emailField, function() {
            debouncedValidate(this, 'email');
        });
        $(document).on('blur', emailField, function() {
            validateField(this, 'email');
        });
        
        // Add validation to phone field
        const phoneField = '#billing_phone';
        $(document).on('input', phoneField, function() {
            debouncedValidate(this, 'tel');
        });
        $(document).on('blur', phoneField, function() {
            validateField(this, 'tel');
        });
        
        // Initial validation for filled fields
        if ($(emailField).val()) validateField($(emailField)[0], 'email');
        if ($(phoneField).val()) validateField($(phoneField)[0], 'tel');

        // Update validation on checkout update
        $(document.body).on('updated_checkout', function() {
            if ($(emailField).val()) validateField($(emailField)[0], 'email');
            if ($(phoneField).val()) validateField($(phoneField)[0], 'tel');
        });
    });
    </script>
    
    <style type="text/css">
    /* WooCommerce specific validation styles */
    .woocommerce-checkout .form-row.avp-valid input {
        border-color: var(--avp-success-color) !important;
        border-width: 2px !important;
    }
    .woocommerce-checkout .form-row.avp-invalid input {
        border-color: var(--avp-error-color) !important;
        border-width: 2px !important;
    }
    body.avp-highlight-enabled .woocommerce-checkout .form-row.avp-valid input {
        background: linear-gradient(to right, color-mix(in srgb, var(--avp-success-color) 15%, transparent), rgba(255, 255, 255, 0.05)) !important;
    }
    body.avp-highlight-enabled .woocommerce-checkout .form-row.avp-invalid input {
        background: linear-gradient(to right, color-mix(in srgb, var(--avp-error-color) 15%, transparent), rgba(255, 255, 255, 0.05)) !important;
    }
    .woocommerce-checkout .avp-validation-message {
        margin-top: 5px !important;
        font-size: 0.857em !important;
        direction: rtl !important;
        padding: 0.5em !important;
        margin-bottom: 0 !important;
        border-radius: 3px !important;
        display: block !important;
        width: 100% !important;
    }
    .woocommerce-checkout .avp-validation-message.woocommerce-error {
        background-color: transparent !important;
        color: var(--avp-error-color) !important;
        border: none !important;
        font-weight: bold !important;
    }
    .woocommerce-checkout .avp-validation-message.woocommerce-message {
        background-color: transparent !important;
        color: var(--avp-success-color) !important;
        border: none !important;
        font-weight: bold !important;
    }
    </style>
    <?php
}
add_action('woocommerce_after_checkout_form', __NAMESPACE__ . '\\avp_add_wc_field_validation_classes'); 

function validate_email($email) {
    // Check if pro version is active
    if (function_exists('\\AVP\\Pro\\validate_email_advanced')) {
        return \AVP\Pro\validate_email_advanced($email);
    }

    // Free version validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return array(
            'valid' => false,
            'message' => 'פורמט לא תקין - כתובת האימייל אינה תקינה'
        );
    }

    // Split email into parts
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return array(
            'valid' => false,
            'message' => 'פורמט לא תקין - חסר @ בכתובת האימייל'
        );
    }

    $domain = $parts[1];

    // Check if domain has a dot
    if (strpos($domain, '.') === false) {
        return array(
            'valid' => false,
            'message' => 'פורמט לא תקין - חסרה נקודה וסיומת בכתובת האימייל (לדוגמה: .com)'
        );
    }

    // Check if domain has a valid TLD
    $domain_parts = explode('.', $domain);
    $tld = end($domain_parts);

    if (empty($tld) || strlen($tld) < 2) {
        return array(
            'valid' => false,
            'message' => 'פורמט לא תקין - חסרה סיומת בכתובת האימייל (לדוגמה: .com)'
        );
    }

    // If all checks pass
    return array(
        'valid' => true,
        'message' => 'כתובת האימייל תקינה'
    );
} 