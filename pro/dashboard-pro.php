<?php
namespace AVP\Pro;

use AVP\Helpers;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add submenu page for pro settings
 */
function add_submenu_page() {
    \add_submenu_page(
        'avp-settings',
        \__('Pro Settings', 'advanced-validation'),
        \__('Pro Settings', 'advanced-validation'),
        'manage_options',
        'avp-pro-settings',
        __NAMESPACE__ . '\\render_settings_page'
    );
}
\add_action('admin_menu', __NAMESPACE__ . '\\add_submenu_page');

/**
 * Register pro settings
 */
function register_settings() {
    \register_setting('avp_pro_settings', 'avp_pro_settings');
}
\add_action('admin_init', __NAMESPACE__ . '\\register_settings');

/**
 * Render pro settings page
 */
function render_settings_page() {
    $settings = Helpers\get_plugin_settings('pro');
    ?>
    <div class="wrap">
        <h1><?php echo \esc_html__('Advanced Validation Pro Settings', 'advanced-validation'); ?></h1>
        
        <form method="post" action="options.php">
            <?php \settings_fields('avp_pro_settings'); ?>
            
            <!-- Add hidden fields for all checkboxes to ensure they are saved when unchecked -->
            <input type="hidden" name="avp_pro_settings[check_mx]" value="0">
            <input type="hidden" name="avp_pro_settings[check_spf]" value="0">
            <input type="hidden" name="avp_pro_settings[check_dkim]" value="0">
            <input type="hidden" name="avp_pro_settings[verify_smtp]" value="0">
            
            <h2><?php echo \esc_html__('Email Validation Settings', 'advanced-validation'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo \esc_html__('MX Record Check', 'advanced-validation'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="avp_pro_settings[check_mx]" 
                                value="1" <?php \checked($settings['check_mx']); ?>>
                            <?php echo \esc_html__('Verify domain has valid mail server', 'advanced-validation'); ?>
                        </label>
                        <p class="description">
                            <?php echo \esc_html__('Checks if the email domain has valid mail servers configured to receive emails. This is the most basic check to ensure the domain can handle email.', 'advanced-validation'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo \esc_html__('SPF Record Check', 'advanced-validation'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="avp_pro_settings[check_spf]" 
                                value="1" <?php \checked($settings['check_spf']); ?>>
                            <?php echo \esc_html__('Check for SPF record existence', 'advanced-validation'); ?>
                        </label>
                        <p class="description">
                            <?php echo \esc_html__('Verifies if the domain has SPF (Sender Policy Framework) records configured. This helps identify if the domain is properly set up for sending emails and reduces the chance of spam.', 'advanced-validation'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo \esc_html__('DKIM Check', 'advanced-validation'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="avp_pro_settings[check_dkim]" 
                                value="1" <?php \checked($settings['check_dkim']); ?>>
                            <?php echo \esc_html__('Verify DKIM record (if available)', 'advanced-validation'); ?>
                        </label>
                        <p class="description">
                            <?php echo \esc_html__('Checks for DKIM (DomainKeys Identified Mail) records which provide a digital signature for emails. This verifies the authenticity of the domain and helps prevent email spoofing.', 'advanced-validation'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo \esc_html__('SMTP Verification', 'advanced-validation'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="avp_pro_settings[verify_smtp]" 
                                value="1" <?php \checked($settings['verify_smtp']); ?>>
                            <?php echo \esc_html__('Attempt SMTP connection test', 'advanced-validation'); ?>
                        </label>
                        <p class="description">
                            <?php echo \esc_html__('Attempts to connect to the mail server and verify if the email address actually exists. This is the most thorough check but may be slower and some servers might block SMTP connections.', 'advanced-validation'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2><?php echo \esc_html__('Phone Validation Settings', 'advanced-validation'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo \esc_html__('Default Region', 'advanced-validation'); ?></th>
                    <td>
                        <select name="avp_pro_settings[default_region]">
                            <!-- Free Version Countries -->
                            <option value="IL" <?php \selected($settings['default_region'] ?? 'IL', 'IL'); ?>>
                                <?php echo \esc_html__('Israel (IL)', 'advanced-validation'); ?>
                            </option>
                            <option value="US" <?php \selected($settings['default_region'] ?? 'IL', 'US'); ?>>
                                <?php echo \esc_html__('United States (US)', 'advanced-validation'); ?>
                            </option>
                            <option value="GB" <?php \selected($settings['default_region'] ?? 'IL', 'GB'); ?>>
                                <?php echo \esc_html__('United Kingdom (GB)', 'advanced-validation'); ?>
                            </option>
                            <option value="CA" <?php \selected($settings['default_region'] ?? 'IL', 'CA'); ?>>
                                <?php echo \esc_html__('Canada (CA)', 'advanced-validation'); ?>
                            </option>
                            <option value="AU" <?php \selected($settings['default_region'] ?? 'IL', 'AU'); ?>>
                                <?php echo \esc_html__('Australia (AU)', 'advanced-validation'); ?>
                            </option>

                            <!-- Pro Version Additional Countries -->
                            <option value="FR" <?php \selected($settings['default_region'] ?? 'IL', 'FR'); ?>>
                                <?php echo \esc_html__('France (FR)', 'advanced-validation'); ?>
                            </option>
                            <option value="DE" <?php \selected($settings['default_region'] ?? 'IL', 'DE'); ?>>
                                <?php echo \esc_html__('Germany (DE)', 'advanced-validation'); ?>
                            </option>
                            <option value="IT" <?php \selected($settings['default_region'] ?? 'IL', 'IT'); ?>>
                                <?php echo \esc_html__('Italy (IT)', 'advanced-validation'); ?>
                            </option>
                            <option value="ES" <?php \selected($settings['default_region'] ?? 'IL', 'ES'); ?>>
                                <?php echo \esc_html__('Spain (ES)', 'advanced-validation'); ?>
                            </option>
                            <option value="JP" <?php \selected($settings['default_region'] ?? 'IL', 'JP'); ?>>
                                <?php echo \esc_html__('Japan (JP)', 'advanced-validation'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php echo \esc_html__('Default region for phone number parsing when no country code is provided. Pro version includes additional countries.', 'advanced-validation'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo \esc_html__('Phone Validation Features', 'advanced-validation'); ?></th>
                    <td>
                        <p class="description" style="margin-bottom: 15px;">
                            <?php echo \esc_html__('The Pro version includes advanced phone validation features:', 'advanced-validation'); ?>
                        </p>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <li><?php echo \esc_html__('International number format validation using Google\'s libphonenumber library', 'advanced-validation'); ?></li>
                            <li><?php echo \esc_html__('Automatic detection of phone number type (mobile, landline, etc.)', 'advanced-validation'); ?></li>
                            <li>
                                <?php echo \esc_html__('Carrier detection for all major providers:', 'advanced-validation'); ?>
                                <ul style="list-style: circle; margin-left: 20px; margin-top: 5px;">
                                    <li><?php echo \esc_html__('Israel: Partner, Cellcom, Pelephone, Hot Mobile, etc.', 'advanced-validation'); ?></li>
                                    <li><?php echo \esc_html__('USA: AT&T, Verizon, T-Mobile, Sprint', 'advanced-validation'); ?></li>
                                    <li><?php echo \esc_html__('UK: EE, Vodafone, O2, Three', 'advanced-validation'); ?></li>
                                    <li><?php echo \esc_html__('Australia: Telstra, Optus, Vodafone', 'advanced-validation'); ?></li>
                                    <li><?php echo \esc_html__('France: Orange, SFR, Bouygues, Free Mobile', 'advanced-validation'); ?></li>
                                    <li><?php echo \esc_html__('Germany: Deutsche Telekom, O2, E-Plus', 'advanced-validation'); ?></li>
                                </ul>
                            </li>
                            <li><?php echo \esc_html__('Smart handling of country codes and local formats', 'advanced-validation'); ?></li>
                            <li><?php echo \esc_html__('Region-specific validation rules and area code detection', 'advanced-validation'); ?></li>
                        </ul>
                    </td>
                </tr>
            </table>

            <?php \submit_button(); ?>
        </form>
    </div>
    <?php
} 