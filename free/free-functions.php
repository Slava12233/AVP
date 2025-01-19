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
    
    // Check if email is empty
    if (empty($email)) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'נדרשת כתובת אימייל'
        ));
        return;
    }
    
    // Split email into parts
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'פורמט לא תקין - חסר @ בכתובת האימייל'
        ));
        return;
    }
    
    $domain = $parts[1];
    
    // Check if domain has a dot
    if (strpos($domain, '.') === false) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'פורמט לא תקין - חסרה נקודה וסיומת בכתובת האימייל (לדוגמה: .com)'
        ));
        return;
    }
    
    // Check if domain has a valid TLD
    $domain_parts = explode('.', $domain);
    $tld = end($domain_parts);
    
    if (empty($tld) || strlen($tld) < 2) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'פורמט לא תקין - חסרה סיומת בכתובת האימייל (לדוגמה: .com)'
        ));
        return;
    }
    
    // If all checks pass, validate using WordPress function
    if (!is_email($email)) {
        wp_send_json_success(array(
            'valid' => false,
            'message' => 'פורמט לא תקין - כתובת האימייל אינה תקינה'
        ));
        return;
    }
    
    wp_send_json_success(array(
        'valid' => true,
        'message' => 'כתובת האימייל תקינה'
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

        // Basic validation for free version
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