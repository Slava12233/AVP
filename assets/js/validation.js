jQuery(document).ready(function($) {
    console.log('AVP: Validation script loaded');
    
    // Add or remove highlight class based on settings
    if (window.avpSettings) {
        if (window.avpSettings.highlightFields) {
            $('body').addClass('avp-highlight-enabled');
        } else {
            $('body').removeClass('avp-highlight-enabled');
        }
        
        // Set CSS variables for colors
        document.documentElement.style.setProperty('--avp-error-color', window.avpSettings.errorColor);
        document.documentElement.style.setProperty('--avp-success-color', window.avpSettings.successColor);
    }
    
    let validationInProgress = false;
    let validationTimeout = null;
    
    function validateField($field) {
        if (validationInProgress) return;
        
        const $form = $field.closest('form');
        const isWooCommerce = $form.hasClass('woocommerce-checkout') || $form.hasClass('checkout');
        const $container = isWooCommerce ? 
            $field.closest('.form-row') : 
            $field.closest('.elementor-field-group');
            
        // Remove existing messages
        $container.find('.avp-validation-message').remove();
        
        let type = '';
        // Check for email field
        if ($field.attr('type') === 'email' || 
            $field.closest('[class*="email"]').length || 
            $field.attr('name') === 'billing_email') {
            type = 'email';
        }
        // Check for phone field
        else if ($field.attr('type') === 'tel' || 
                 $field.closest('.elementor-field-type-tel').length ||
                 $field.closest('[class*="phone"]').length || 
                 $field.attr('name') === 'billing_phone') {
            type = 'tel';
        }
        
        if (!type) return;
        
        const value = $field.val().trim();
        if (!value) return; // Don't validate empty fields
        
        validationInProgress = true;
        
        $.ajax({
            url: avpAjax.url,
            type: 'POST',
            data: {
                action: type === 'email' ? 'avp_validate_email' : 'avp_validate_phone',
                [type]: value,
                nonce: avpAjax.nonce
            },
            success: function(response) {
                if (!response.data) return;
                
                const isValid = response.data.valid;
                const message = response.data.message;
                
                // Remove existing classes
                $container.removeClass('avp-valid avp-invalid');
                $container.find('.avp-validation-message').remove();
                
                // Add new validation message
                if (window.avpSettings && window.avpSettings.showLabels) {
                    const $message = $('<div>')
                        .addClass('avp-validation-message')
                        .addClass(isValid ? 'avp-valid' : 'avp-invalid')
                        .text(message);
                    
                    $field.after($message);
                }
                
                // Add validation class to container
                $container.addClass(isValid ? 'avp-valid' : 'avp-invalid');
            },
            complete: function() {
                validationInProgress = false;
            }
        });
    }
    
    // Handle input changes with debounce
    let timers = new Map();
    const selector = 
        'input[type="email"], input[type="tel"], ' +
        '.elementor-field-type-email input, .elementor-field-type-tel input, ' +
        '.elementor-field-textual[type="tel"], ' +
        '[name="billing_email"], [name="billing_phone"]';
        
    $(document).on('input change', selector, function() {
        const $field = $(this);
        const fieldId = $field.attr('name') || $field.attr('id') || $field.attr('class');
        
        clearTimeout(timers.get(fieldId));
        timers.set(fieldId, setTimeout(() => validateField($field), 500));
    });
    
    // Remove blur event to prevent double validation
    $(document).off('blur', selector);
}); 