<?php
namespace AVP\Free;

use AVP\Helpers;
use AVP\License;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add menu pages
 */
function add_menu_pages() {
    \add_menu_page(
        \__('Advanced Validation', 'advanced-validation'),
        \__('AVP Settings', 'advanced-validation'),
        'manage_options',
        'avp-settings',
        __NAMESPACE__ . '\\render_settings_page',
        'dashicons-shield'
    );
}
\add_action('admin_menu', __NAMESPACE__ . '\\add_menu_pages');

/**
 * Register settings
 */
function register_settings() {
    \register_setting('avp_free_settings', 'avp_free_settings');
    \register_setting('avp_license', 'avp_license_key');
}
\add_action('admin_init', __NAMESPACE__ . '\\register_settings');

/**
 * Render settings page
 */
function render_settings_page() {
    $settings = Helpers\get_plugin_settings('free');
    $license_key = \get_option('avp_license_key', '');
    $is_pro_active = License\avp_is_pro_active();
    ?>
    <div class="wrap">
        <h1><?php echo \esc_html__('Advanced Validation Settings', 'advanced-validation'); ?></h1>
        
        <form method="post" action="options.php">
            <?php \settings_fields('avp_free_settings'); ?>
            
            <h2><?php echo \esc_html__('Free Features', 'advanced-validation'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo \esc_html__('Validate Email Format', 'advanced-validation'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="avp_free_settings[validate_email]" 
                                value="1" <?php \checked($settings['validate_email']); ?>>
                            <?php echo \esc_html__('Enable basic email format validation', 'advanced-validation'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo \esc_html__('Validate Phone Format', 'advanced-validation'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="avp_free_settings[validate_phone]" 
                                value="1" <?php \checked($settings['validate_phone']); ?>>
                            <?php echo \esc_html__('Enable basic phone format validation', 'advanced-validation'); ?>
                        </label>
                    </td>
                </tr>

                <!-- Visual Settings -->
                <tr>
                    <th scope="row"><?php echo \esc_html__('Show Error Labels', 'advanced-validation'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="avp_free_settings[show_labels]" 
                                value="1" <?php \checked($settings['show_labels'] ?? true); ?>>
                            <?php echo \esc_html__('Display error messages below invalid fields', 'advanced-validation'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo \esc_html__('Highlight Invalid Fields', 'advanced-validation'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="avp_free_settings[highlight_fields]" 
                                value="1" <?php \checked($settings['highlight_fields'] ?? true); ?>>
                            <?php echo \esc_html__('Change field color when validation fails', 'advanced-validation'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo \esc_html__('Error Color', 'advanced-validation'); ?></th>
                    <td>
                        <input type="color" name="avp_free_settings[error_color]" 
                            value="<?php echo \esc_attr($settings['error_color']); ?>">
                        <p class="description">
                            <?php echo \esc_html__('Color used for error messages and field highlighting', 'advanced-validation'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo \esc_html__('Success Color', 'advanced-validation'); ?></th>
                    <td>
                        <input type="color" name="avp_free_settings[success_color]" 
                            value="<?php echo \esc_attr($settings['success_color']); ?>">
                        <p class="description">
                            <?php echo \esc_html__('Color used for valid fields', 'advanced-validation'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php \submit_button(); ?>
        </form>

        <hr>

        <h2><?php echo \esc_html__('License Management', 'advanced-validation'); ?></h2>
        <form method="post" action="options.php">
            <?php \settings_fields('avp_license'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo \esc_html__('License Key', 'advanced-validation'); ?></th>
                    <td>
                        <input type="text" name="avp_license_key" 
                            value="<?php echo \esc_attr($license_key); ?>" 
                            class="regular-text"
                            placeholder="<?php echo \esc_attr__('Enter your license key or DEVMOCK for testing', 'advanced-validation'); ?>">
                        <p class="description">
                            <?php if ($is_pro_active): ?>
                                <span style="color: green;">
                                    <?php echo \esc_html__('Pro features are active!', 'advanced-validation'); ?>
                                </span>
                            <?php else: ?>
                                <?php echo \esc_html__('Enter a valid license key to activate Pro features', 'advanced-validation'); ?>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php \submit_button(\__('Activate License', 'advanced-validation')); ?>
        </form>

        <?php if (!$is_pro_active): ?>
        <div class="avp-pro-features" style="background: #f8f9fa; padding: 20px; margin-top: 20px; border-radius: 5px;">
            <h3><?php echo \esc_html__('Pro Features (Locked)', 'advanced-validation'); ?></h3>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li><?php echo \esc_html__('Advanced Email Validation (MX, SPF, DKIM)', 'advanced-validation'); ?></li>
                <li><?php echo \esc_html__('SMTP Server Verification', 'advanced-validation'); ?></li>
                <li><?php echo \esc_html__('International Phone Number Validation', 'advanced-validation'); ?></li>
                <li><?php echo \esc_html__('Contact Form 7 Integration', 'advanced-validation'); ?></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php
} 