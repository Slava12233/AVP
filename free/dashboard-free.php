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
            <p class="description" style="margin-bottom: 20px;">
                <?php echo \esc_html__('The free version performs basic format validation for emails and phone numbers. No license required.', 'advanced-validation'); ?>
            </p>
            
            <table class="form-table">
                <!-- Visual Settings -->
                <tr>
                    <th scope="row"><?php echo \esc_html__('Show Error Labels', 'advanced-validation'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="avp_free_settings[show_labels]" 
                                value="1" <?php \checked(isset($settings['show_labels']) ? $settings['show_labels'] : false); ?>>
                            <?php echo \esc_html__('Display error messages below invalid fields', 'advanced-validation'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo \esc_html__('Highlight Invalid Fields', 'advanced-validation'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="avp_free_settings[highlight_fields]" 
                                value="1" <?php \checked(isset($settings['highlight_fields']) ? $settings['highlight_fields'] : false); ?>>
                            <?php echo \esc_html__('Change field color when validation fails', 'advanced-validation'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo \esc_html__('Default Phone Region', 'advanced-validation'); ?></th>
                    <td>
                        <select name="avp_free_settings[default_region]" id="avp_default_region">
                            <option value="IL" <?php selected($settings['default_region'] ?? 'IL', 'IL'); ?>>Israel (IL)</option>
                            <option value="US" <?php selected($settings['default_region'] ?? 'IL', 'US'); ?>>United States (US)</option>
                            <option value="GB" <?php selected($settings['default_region'] ?? 'IL', 'GB'); ?>>United Kingdom (GB)</option>
                            <option value="CA" <?php selected($settings['default_region'] ?? 'IL', 'CA'); ?>>Canada (CA)</option>
                            <option value="AU" <?php selected($settings['default_region'] ?? 'IL', 'AU'); ?>>Australia (AU)</option>
                        </select>
                        <p class="description">
                            <?php echo \esc_html__('Select the default region for phone number validation', 'advanced-validation'); ?>
                        </p>
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
                            value="<?php echo \esc_attr($settings['success_color'] ?? '#00ff00'); ?>">
                        <p class="description">
                            <?php echo \esc_html__('Color used for valid fields', 'advanced-validation'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php \submit_button(); ?>
        </form>

        <hr>

        <h2><?php echo \esc_html__('Pro License Management', 'advanced-validation'); ?></h2>
        <p class="description" style="margin-bottom: 20px;">
            <?php echo \esc_html__('A valid license key is required to activate the Pro features. The free version will continue to work without a license.', 'advanced-validation'); ?>
        </p>
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
        <div class="avp-pro-features-wrapper">
            <h2 class="avp-pro-title"><?php echo \esc_html__('Upgrade to Pro', 'advanced-validation'); ?></h2>
            <p class="avp-pro-description">
                <?php echo \esc_html__('While the free version checks basic format, Pro version provides advanced validation to ensure maximum data quality', 'advanced-validation'); ?>
            </p>
            
            <div class="avp-pro-grid">
                <div class="avp-pro-feature">
                    <h4>
                        <span class="dashicons dashicons-email-alt"></span>
                        <?php echo \esc_html__('Advanced Email Validation', 'advanced-validation'); ?>
                    </h4>
                    <ul>
                        <li>• <?php echo \esc_html__('Complete DNS verification (MX, SPF, DKIM records)', 'advanced-validation'); ?></li>
                        <li>• <?php echo \esc_html__('Real-time SMTP mailbox existence check', 'advanced-validation'); ?></li>
                        <li>• <?php echo \esc_html__('Detailed error reporting for each validation step', 'advanced-validation'); ?></li>
                        <li>• <?php echo \esc_html__('Protection against disposable email addresses', 'advanced-validation'); ?></li>
                    </ul>
                </div>

                <div class="avp-pro-feature">
                    <h4>
                        <span class="dashicons dashicons-smartphone"></span>
                        <?php echo \esc_html__('Smart Phone Validation', 'advanced-validation'); ?>
                    </h4>
                    <ul>
                        <li>• <?php echo \esc_html__('International number format validation', 'advanced-validation'); ?></li>
                        <li>• <?php echo \esc_html__('Israeli carrier detection (Partner, Cellcom, etc.)', 'advanced-validation'); ?></li>
                        <li>• <?php echo \esc_html__('Automatic region and provider display', 'advanced-validation'); ?></li>
                        <li>• <?php echo \esc_html__('Smart country code handling', 'advanced-validation'); ?></li>
                    </ul>
                </div>

                <div class="avp-pro-feature">
                    <h4>
                        <span class="dashicons dashicons-forms"></span>
                        <?php echo \esc_html__('Extended Forms Support', 'advanced-validation'); ?>
                    </h4>
                    <ul>
                        <li>• <?php echo \esc_html__('Contact Form 7 full integration', 'advanced-validation'); ?></li>
                        <li>• <?php echo \esc_html__('WPForms advanced validation', 'advanced-validation'); ?></li>
                        <li>• <?php echo \esc_html__('Gravity Forms support (coming soon)', 'advanced-validation'); ?></li>
                        <li>• <?php echo \esc_html__('Ninja Forms compatibility (coming soon)', 'advanced-validation'); ?></li>
                    </ul>
                </div>

                <div class="avp-pro-feature">
                    <h4>
                        <span class="dashicons dashicons-admin-customizer"></span>
                        <?php echo \esc_html__('Advanced Customization', 'advanced-validation'); ?>
                    </h4>
                    <ul>
                        <li>• <?php echo \esc_html__('Fully customizable validation messages', 'advanced-validation'); ?></li>
                        <li>• <?php echo \esc_html__('Advanced styling options', 'advanced-validation'); ?></li>
                        <li>• <?php echo \esc_html__('Individual feature controls', 'advanced-validation'); ?></li>
                        <li>• <?php echo \esc_html__('Custom validation rules', 'advanced-validation'); ?></li>
                    </ul>
                </div>
            </div>

            <div class="avp-pro-cta">
                <a href="https://yoursite.com/avp-pro" target="_blank" class="avp-pro-button">
                    <?php echo \esc_html__('Upgrade to Pro Now', 'advanced-validation'); ?>
                </a>
            </div>

            <style>
                .avp-pro-features-wrapper {
                    background: #fff;
                    padding: 30px;
                    margin: 20px 0;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                }
                
                .avp-pro-title {
                    text-align: center;
                    color: #1d2327;
                    font-size: 24px;
                    margin-bottom: 10px;
                }
                
                .avp-pro-description {
                    text-align: center;
                    color: #50575e;
                    font-size: 16px;
                    margin-bottom: 30px;
                }
                
                .avp-pro-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 25px;
                    margin-bottom: 40px;
                }
                
                .avp-pro-feature {
                    background: #f8f9fa;
                    padding: 25px;
                    border-radius: 6px;
                    border: 1px solid #e5e7eb;
                }
                
                .avp-pro-feature h4 {
                    color: #2271b1;
                    font-size: 18px;
                    margin: 0 0 15px 0;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .avp-pro-feature .dashicons {
                    color: #2271b1;
                    font-size: 22px;
                    width: 22px;
                    height: 22px;
                }
                
                .avp-pro-feature ul {
                    margin: 0;
                    padding: 0;
                    list-style: none;
                }
                
                .avp-pro-feature li {
                    color: #50575e;
                    margin-bottom: 10px;
                    font-size: 14px;
                    line-height: 1.4;
                }
                
                .avp-pro-cta {
                    text-align: center;
                    margin-top: 30px;
                }
                
                .avp-pro-button {
                    display: inline-block;
                    background: #2271b1;
                    color: #fff;
                    padding: 12px 24px;
                    border-radius: 4px;
                    text-decoration: none;
                    font-size: 16px;
                    font-weight: 500;
                    transition: all 0.3s ease;
                }
                
                .avp-pro-button:hover {
                    background: #135e96;
                    color: #fff;
                    transform: translateY(-1px);
                }
            </style>
        </div>
        <?php endif; ?>
    </div>
    <?php
} 