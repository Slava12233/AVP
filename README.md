# Advanced Validation Plugin

A WordPress plugin that provides advanced validation for email addresses and phone numbers in forms.

## Features

### Free Version
- Basic email format validation
- Basic phone number format validation
- Integration with Elementor forms
- Integration with WooCommerce checkout

### Pro Version
- Advanced email validation:
  - MX record verification
  - SPF record checking
  - DKIM verification
  - SMTP server validation
- International phone number validation using Google's libphonenumber
- Additional form integrations (Contact Form 7)

## Installation

1. Upload the plugin files to `/wp-content/plugins/advanced-validation`
2. Run `composer install` in the plugin directory to install dependencies
3. Activate the plugin through the WordPress plugins screen
4. Configure the plugin settings under "AVP Settings" in the admin menu

## Development Mode

To test pro features during development:
1. Go to "AVP Settings" in the WordPress admin
2. Enter `DEVMOCK` as the license key
3. Pro features will be activated for testing

## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher
- Composer (for installation)

## Dependencies

- PHPMailer (for SMTP verification)
- libphonenumber-for-php (for international phone validation)

## License

GPL v2 or later 