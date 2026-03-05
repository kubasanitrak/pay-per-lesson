<?php
/**
 * Pay Per Lesson - Activator
 * Creates database table, auto-creates required pages, sets default options.
 * Fixed activation hook path (works reliably even if file structure changes).
 */

if (!defined('ABSPATH')) exit;

class PPL_Activator {

    public function __construct() {
        // Corrected hook path (bonus fix)
        $plugin_file = dirname(plugin_dir_path(__FILE__)) . '/pay-per-lesson.php';
        register_activation_hook($plugin_file, [$this, 'activate']);
    }

    public function activate() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppl_orders';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            lesson_ids TEXT NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(20) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            trans_id VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Auto-create required pages
        $pages = [
            ['slug' => 'checkout',          'title' => 'Checkout'],
            ['slug' => 'dashboard',         'title' => 'My Lessons'],
            ['slug' => 'payment-success',   'title' => 'Payment Success'],
            ['slug' => 'payment-failed',    'title' => 'Payment Failed'],
        ];

        foreach ($pages as $p) {
            if (!get_page_by_path($p['slug'])) {
                wp_insert_post([
                    'post_title'   => $p['title'],
                    'post_name'    => $p['slug'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_content' => '',
                ]);
            }
        }

        // Default settings (if none exist)
        if (!get_option('ppl_settings')) {
            update_option('ppl_settings', [
                'currency_code'          => 'CZK',
                'currency_symbol'        => 'Kč',
                'currency_position'      => 'after',
                'terms_page'             => 0,

                'pricing_discount_5'     => 15,
                'pricing_discount_10'    => 30,

                'comgate_enabled'        => true,
                'comgate_merchant'       => '',
                'comgate_secret'         => '',
                'comgate_test'           => true,

                'bank_enabled'           => true,
                'bank_name'              => 'Your Company s.r.o.',
                'bank_account'           => '1234567890/0100',
                'bank_code'              => '0100',
                'bank_iban'              => 'CZ1234567890123456789012',
                'bank_bic'               => 'KOMBCZPP',

                'emails_from_name'       => get_bloginfo('name'),
                'emails_from_email'      => get_option('admin_email'),
                'emails_admin_email'     => get_option('admin_email'),
                'emails_html'            => true,
                'emails_notify_admin'    => true,

                'advanced_expiry_days'   => 365,
                'advanced_notify_days'   => 7,
                'advanced_basket_cleanup'=> 7,
                'advanced_delete_data'   => false,
            ]);
        }
    }
}