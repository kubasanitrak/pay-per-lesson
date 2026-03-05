<?php
/**
 * Pay Per Lesson — Admin Settings Page
 * Full standalone view file (as mentioned in the original project structure)
 * Drop this into: admin/admin-settings-page.php
 *
 * How to use in class-settings.php (update the render method):
 *
 * public function render_settings_page() {
 *     if (!current_user_can('manage_options')) wp_die('Access denied');
 *     $settings = wp_parse_args(get_option('ppl_settings', []), $this->get_defaults());
 *     $active_tab = sanitize_key($_GET['tab'] ?? 'general');
 *     include PPL_PATH . 'admin/admin-settings-page.php';
 * }
 *
 * This keeps your class clean and the page editable separately.
 */

$tabs = [
    'general'   => 'General',
    'pricing'   => 'Pricing',
    'comgate'   => 'Comgate',
    'bank'      => 'Bank Transfer',
    'emails'    => 'Emails',
    'advanced'  => 'Advanced'
];

$active_tab = sanitize_key($_GET['tab'] ?? 'general');
?>
<div class="wrap">
    <h1>Pay Per Lesson — Settings</h1>

    <?php if (isset($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>Settings saved successfully.</strong></p>
        </div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab => $label) : 
            $class = ($active_tab === $tab) ? 'nav-tab-active' : '';
            $url = admin_url('admin.php?page=pay-per-lesson&tab=' . $tab);
            ?>
            <a href="<?php echo esc_url($url); ?>" class="nav-tab <?php echo esc_attr($class); ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <form method="post">
        <?php wp_nonce_field('ppl_settings_save'); ?>

        <?php
        switch ($active_tab) {
            case 'general':
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Currency Code</th>
                        <td><input type="text" name="ppl_settings[currency_code]" value="<?php echo esc_attr($settings['currency_code'] ?? 'CZK'); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Currency Symbol</th>
                        <td><input type="text" name="ppl_settings[currency_symbol]" value="<?php echo esc_attr($settings['currency_symbol'] ?? 'Kč'); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Currency Position</th>
                        <td>
                            <select name="ppl_settings[currency_position]">
                                <option value="before" <?php selected($settings['currency_position'] ?? 'after', 'before'); ?>>Before (e.g. $10)</option>
                                <option value="after" <?php selected($settings['currency_position'] ?? 'after', 'after'); ?>>After (e.g. 10 Kč)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Terms &amp; Conditions Page</th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'name'             => 'ppl_settings[terms_page]',
                                'selected'         => $settings['terms_page'] ?? 0,
                                'show_option_none' => '— No page —',
                                'option_none_value'=> '0',
                                'class'            => 'regular-text'
                            ]);
                            ?>
                        </td>
                    </tr>
                </table>
                <?php
                break;

            case 'pricing':
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Bundle Discount — 5+ lessons</th>
                        <td>
                            <input type="number" name="ppl_settings[pricing_discount_5]" value="<?php echo esc_attr($settings['pricing_discount_5'] ?? 15); ?>" min="0" max="100" step="1" style="width:80px"> %
                            <p class="description">Applied automatically at checkout</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Bundle Discount — 10+ lessons</th>
                        <td>
                            <input type="number" name="ppl_settings[pricing_discount_10]" value="<?php echo esc_attr($settings['pricing_discount_10'] ?? 30); ?>" min="0" max="100" step="1" style="width:80px"> %
                            <p class="description">Applied automatically at checkout (higher priority than 5+)</p>
                        </td>
                    </tr>
                </table>
                <?php
                break;

            case 'comgate':
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Comgate Card Payments</th>
                        <td><input type="checkbox" name="ppl_settings[comgate_enabled]" value="1" <?php checked($settings['comgate_enabled'] ?? true); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row">Merchant ID</th>
                        <td><input type="text" name="ppl_settings[comgate_merchant]" value="<?php echo esc_attr($settings['comgate_merchant'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Secret Key</th>
                        <td><input type="password" name="ppl_settings[comgate_secret]" value="<?php echo esc_attr($settings['comgate_secret'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Test Mode</th>
                        <td><input type="checkbox" name="ppl_settings[comgate_test]" value="1" <?php checked($settings['comgate_test'] ?? true); ?>> <span class="description">(uses Comgate sandbox)</span></td>
                    </tr>
                </table>
                <?php
                break;

            case 'bank':
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Bank Transfer + QR</th>
                        <td><input type="checkbox" name="ppl_settings[bank_enabled]" value="1" <?php checked($settings['bank_enabled'] ?? true); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row">Account Name</th>
                        <td><input type="text" name="ppl_settings[bank_name]" value="<?php echo esc_attr($settings['bank_name'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Account Number / Bank Code</th>
                        <td>
                            <input type="text" name="ppl_settings[bank_account]" value="<?php echo esc_attr($settings['bank_account'] ?? ''); ?>" class="regular-text"> / 
                            <input type="text" name="ppl_settings[bank_code]" value="<?php echo esc_attr($settings['bank_code'] ?? ''); ?>" style="width:80px">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">IBAN</th>
                        <td><input type="text" name="ppl_settings[bank_iban]" value="<?php echo esc_attr($settings['bank_iban'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">BIC / SWIFT</th>
                        <td><input type="text" name="ppl_settings[bank_bic]" value="<?php echo esc_attr($settings['bank_bic'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <?php
                break;

            case 'emails':
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">From Name</th>
                        <td><input type="text" name="ppl_settings[emails_from_name]" value="<?php echo esc_attr($settings['emails_from_name'] ?? get_bloginfo('name')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">From Email</th>
                        <td><input type="email" name="ppl_settings[emails_from_email]" value="<?php echo esc_attr($settings['emails_from_email'] ?? get_option('admin_email')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Admin Notification Email</th>
                        <td><input type="email" name="ppl_settings[emails_admin_email]" value="<?php echo esc_attr($settings['emails_admin_email'] ?? get_option('admin_email')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Send HTML Emails</th>
                        <td><input type="checkbox" name="ppl_settings[emails_html]" value="1" <?php checked($settings['emails_html'] ?? true); ?>></td>
                    </tr>
                    <tr>
                        <th scope="row">Notify Admin on Purchase</th>
                        <td><input type="checkbox" name="ppl_settings[emails_notify_admin]" value="1" <?php checked($settings['emails_notify_admin'] ?? true); ?>></td>
                    </tr>
                </table>

                <h3>Available Email Types</h3>
                <ul style="list-style:disc;padding-left:20px">
                    <li>Purchase Confirmation — Customer — Payment Completed</li>
                    <li>Payment Failed — Customer</li>
                    <li>Admin Notification — New Purchase</li>
                    <li>Order Expiry Warning — Customer</li>
                </ul>
                <p class="description">Templates are coded in <code>includes/class-emails.php</code></p>
                <?php
                break;

            case 'advanced':
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Lesson Access Expiry (days)</th>
                        <td>
                            <input type="number" name="ppl_settings[advanced_expiry_days]" value="<?php echo esc_attr($settings['advanced_expiry_days'] ?? 365); ?>" min="0" style="width:120px">
                            <p class="description">0 = never expires</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Send Expiry Notification (days before)</th>
                        <td><input type="number" name="ppl_settings[advanced_notify_days]" value="<?php echo esc_attr($settings['advanced_notify_days'] ?? 7); ?>" min="1" style="width:120px"></td>
                    </tr>
                    <tr>
                        <th scope="row">Pending Basket / Order Cleanup (days)</th>
                        <td><input type="number" name="ppl_settings[advanced_basket_cleanup]" value="<?php echo esc_attr($settings['advanced_basket_cleanup'] ?? 7); ?>" min="1" style="width:120px"></td>
                    </tr>
                    <tr>
                        <th scope="row">Delete All Data on Plugin Uninstall</th>
                        <td><input type="checkbox" name="ppl_settings[advanced_delete_data]" value="1" <?php checked($settings['advanced_delete_data'] ?? false); ?>></td>
                    </tr>
                </table>

                <h3>System Information</h3>
                <table class="widefat">
                    <tr><td><strong>Plugin Version</strong></td><td><?php echo PPL_VERSION; ?></td></tr>
                    <tr><td><strong>WordPress Version</strong></td><td><?php echo get_bloginfo('version'); ?></td></tr>
                    <tr><td><strong>PHP Version</strong></td><td><?php echo phpversion(); ?></td></tr>
                </table>
                <?php
                break;
        }
        ?>

        <p class="submit">
            <input type="submit" name="ppl_save_settings" class="button button-primary button-large" value="Save All Settings">
        </p>
    </form>
</div>