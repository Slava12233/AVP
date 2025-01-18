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
        
        const styles = `
            .avp-validation-message { display: ${window.avpSettings.showLabels ? 'block' : 'none'} !important; }
        `;
        
        $('<style>')
            .prop('type', 'text/css')
            .html(styles)
            .appendTo('head');
    }
    
    // Track validation state for all fields
    const validationState = {
        email: false,
        tel: false
    };
    
    // Add form submit handler
    $(document).on('submit', 'form', function(e) {
        const $form = $(this);
        const hasEmail = $form.find('input[type="email"], .elementor-field-type-email input').length > 0;
        const hasPhone = $form.find('input[type="tel"], .elementor-field-type-tel input').length > 0;
        
        let isValid = true;
        
        if (hasEmail) {
            const $email = $form.find('input[type="email"], .elementor-field-type-email input');
            if ($email.val() && !validationState.email) {
                isValid = false;
                e.preventDefault();
                $email.focus();
            }
        }
        
        if (hasPhone) {
            const $phone = $form.find('input[type="tel"], .elementor-field-type-tel input');
            if ($phone.val() && !validationState.tel) {
                isValid = false;
                e.preventDefault();
                $phone.focus();
            }
        }
        
        if (!isValid) {
            const $submitButton = $form.find('.elementor-button[type="submit"]');
            $submitButton.prop('disabled', true).css('opacity', '0.5');
            
            // Show message to user only if labels are enabled
            if (window.avpSettings && window.avpSettings.showLabels) {
                const $message = $('<div>')
                    .addClass('avp-form-error')
                    .css({
                        'color': window.avpSettings.errorColor || '#f44336',
                        'margin-top': '10px',
                        'text-align': 'center',
                        'font-weight': 'bold'
                    })
                    .text('נא לתקן את השדות המסומנים באדום לפני שליחת הטופס');
                
                $submitButton.after($message);
                setTimeout(() => $message.fadeOut(500, function() { $(this).remove(); }), 3000);
            }
        }
        
        return isValid;
    });
    
    function validateField(field) {
        const $field = $(field);
        const $group = $field.closest('.elementor-field-group');
        const value = $field.val();
        let type = $field.attr('type');
        
        // Fix for fields without explicit type attribute
        if (!type) {
            if ($field.closest('.elementor-field-type-email').length) {
                type = 'email';
            } else if ($field.closest('.elementor-field-type-tel').length) {
                type = 'tel';
            }
        }
        
        console.log('AVP: Validating field', { value, type });
        
        // Clear previous validation
        $group.removeClass('avp-valid avp-invalid');
        if (window.avpSettings && window.avpSettings.showLabels) {
            $group.find('.avp-validation-message').remove();
        }
        
        // Skip if empty and not focused
        if (!value && !$field.is(':focus')) {
            validationState[type] = false;
            updateSubmitButton($field);
            return;
        }
        
        // Determine endpoint and data
        const endpoint = type === 'email' ? 'avp_validate_email' : 'avp_validate_phone';
        const data = {
            action: endpoint,
            nonce: avpAjax.nonce
        };
        
        // Add the value with the correct parameter name
        if (type === 'email') {
            data.email = value;
        } else {
            data.tel = value;
        }
        
        console.log('AVP: Sending request', { endpoint, data });
        
        // Send validation request
        $.ajax({
            url: avpAjax.url,
            type: 'POST',
            data: data,
            timeout: 5000, // 5 second timeout
            success: function(response) {
                console.log('AVP: Validation response', response);
                
                // Always add validation class based on response
                const isValid = response.success && response.data && response.data.valid;
                const messageClass = isValid ? 'avp-valid' : 'avp-invalid';
                $group.addClass(messageClass);
                
                // Update validation state
                validationState[type] = isValid;
                
                // Always create message element
                let message = '';
                if (response.success && response.data && response.data.message) {
                    message = response.data.message;
                } else {
                    message = isValid ? 'תקין' : 'פורמט לא תקין';
                }
                
                const $message = $('<div>')
                    .addClass('avp-validation-message')
                    .addClass(messageClass)
                    .text(message);
                
                // Remove any existing message
                $group.find('.avp-validation-message').remove();
                
                // Add message after the input field
                $field.after($message);
                
                // Update submit button state
                updateSubmitButton($field);
                
                // Log for debugging
                console.log('AVP: Message added', {
                    message: message,
                    valid: isValid,
                    messageElement: $message[0]
                });
            },
            error: function(xhr, status, error) {
                console.error('AVP: Validation error', { xhr, status, error });
                
                // Update validation state
                validationState[type] = false;
                
                // Check if it's a timeout
                const errorMessage = status === 'timeout' ? 
                    'זמן התגובה ארוך מדי, נסה שוב' : 
                    'בעיה בתקשורת עם השרת, נסה שוב';
                
                $group.addClass('avp-invalid');
                
                const $message = $('<div>')
                    .addClass('avp-validation-message avp-invalid')
                    .text(errorMessage);
                
                // Remove any existing message
                $group.find('.avp-validation-message').remove();
                
                // Add message after the input field
                $field.after($message);
                
                // Update submit button state
                updateSubmitButton($field);
            }
        });
    }
    
    function updateSubmitButton($field) {
        const $form = $field.closest('form');
        const $submitButton = $form.find('.elementor-button[type="submit"]');
        
        // Check if form has both email and phone fields
        const hasEmail = $form.find('input[type="email"], .elementor-field-type-email input').length > 0;
        const hasPhone = $form.find('input[type="tel"], .elementor-field-type-tel input').length > 0;
        
        let shouldEnable = true;
        
        if (hasEmail) {
            const emailValue = $form.find('input[type="email"], .elementor-field-type-email input').val();
            if (emailValue && !validationState.email) {
                shouldEnable = false;
            }
        }
        
        if (hasPhone) {
            const phoneValue = $form.find('input[type="tel"], .elementor-field-type-tel input').val();
            if (phoneValue && !validationState.tel) {
                shouldEnable = false;
            }
        }
        
        if (shouldEnable) {
            $submitButton.prop('disabled', false).css('opacity', '1');
        } else {
            $submitButton.prop('disabled', true).css('opacity', '0.5');
        }
        
        console.log('AVP: Submit button updated', { 
            shouldEnable, 
            validationState, 
            hasEmail, 
            hasPhone 
        });
    }
    
    function initValidation() {
        console.log('AVP: Initializing validation');
        
        // Reset validation state
        validationState.email = false;
        validationState.tel = false;
        
        // Handle input changes with debounce
        let timer;
        $(document).on('input change blur', 
            '.elementor-field-group input[type="email"], ' + 
            '.elementor-field-group input[type="tel"], ' + 
            '.elementor-field-type-email input, ' + 
            '.elementor-field-type-tel input, ' +
            '.elementor-field-group .elementor-field[type="email"], ' +
            '.elementor-field-group .elementor-field[type="tel"]',
            function() {
                const field = this;
                clearTimeout(timer);
                timer = setTimeout(function() {
                    validateField(field);
                }, 300); // Wait 300ms after typing stops
        });
        
        // Immediate validation for existing fields with values
        $('.elementor-field-group input[type="email"], ' +
          '.elementor-field-group input[type="tel"], ' +
          '.elementor-field-type-email input, ' +
          '.elementor-field-type-tel input, ' +
          '.elementor-field-group .elementor-field[type="email"], ' +
          '.elementor-field-group .elementor-field[type="tel"]').each(function() {
            if ($(this).val()) {
                validateField(this);
            }
        });
        
        // Update submit buttons initially
        $('form').each(function() {
            updateSubmitButton($(this).find('input').first());
        });
    }
    
    // Initialize validation
    initValidation();
    
    // Watch for popup forms and dynamic content
    $(document).on('elementor/popup/show', function() {
        console.log('AVP: Popup shown, reinitializing validation');
        initValidation();
    });
}); 