<?php
/**
 * Plugin Name:       Pay Per Lesson
 * Description:       Pay-per-post for Lessons (Comgate + Bank QR). No WooCommerce.
 * Version:           1.0.0
 * Author:            Your Name
 * Text Domain:       pay-per-lesson
 */

if (!defined('ABSPATH')) exit;

define('PPL_VERSION', '1.0.0');
define('PPL_PATH', plugin_dir_path(__FILE__));
define('PPL_URL', plugin_dir_url(__FILE__));

require_once PPL_PATH . 'includes/class-activator.php';
require_once PPL_PATH . 'includes/class-cpt-lesson.php';
require_once PPL_PATH . 'includes/class-settings.php';
require_once PPL_PATH . 'includes/class-basket.php';
require_once PPL_PATH . 'includes/class-checkout.php';      // ← NEW
require_once PPL_PATH . 'includes/class-payment-handler.php';
require_once PPL_PATH . 'includes/class-access-control.php';
require_once PPL_PATH . 'includes/class-emails.php';
require_once PPL_PATH . 'includes/class-cron.php';
require_once PPL_PATH . 'includes/class-statistics.php';

class PayPerLesson {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init() {
        new PPL_Activator();
        new PPL_CPT_Lesson();
        new PPL_Settings();
        new PPL_Basket();
        new PPL_Checkout();                    // ← NEW
        new PPL_Payment_Handler();
        new PPL_Access_Control();
        new PPL_Emails();
        new PPL_Cron();
        new PPL_Statistics();

        // Custom template loader for our pages
        add_filter('template_include', function($template) {
            if (is_page('dashboard')) {
                return PPL_PATH . 'public/templates/my-lessons.php';
            }
            if (is_page('payment-success')) {
                return PPL_PATH . 'public/templates/payment-success.php';
            }
            if (is_page('payment-failed')) {
                // Bonus: you can create payment-failed.php the same way
                return PPL_PATH . 'public/templates/payment-failed.php'; // optional
            }
            return $template;
        });
        
        add_action('wp_enqueue_scripts', [$this, 'public_assets']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);

        add_action( 'enqueue_block_editor_assets', function() {
            wp_enqueue_editor();   // forces TinyMCE + Quicktags to load on Gutenberg pages
        } );

        // Deactivation cleanup
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }


    public function public_assets() {
        wp_enqueue_style('ppl-style', PPL_URL . 'public/assets/css/style.css', [], PPL_VERSION);
        wp_enqueue_script('ppl-basket', PPL_URL . 'public/assets/js/basket.js', ['jquery'], PPL_VERSION, true);
        wp_localize_script('ppl-basket', 'ppl_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ppl_nonce')
        ]);
        wp_enqueue_script('qrcode', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', [], null, true);
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'pay-per-lesson') !== false) {
            wp_enqueue_style('ppl-admin', PPL_URL . 'public/assets/css/style.css', [], PPL_VERSION);
        }
    }

    /**
     * Deactivation: unschedule crons + optional data cleanup
     */
    public function deactivate() {
        $settings = get_option('ppl_settings', []);
        if (!empty($settings['advanced_delete_data'])) {
            // Full cleanup (as requested in Advanced settings)
            global $wpdb;
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ppl_orders");
            delete_option('ppl_settings');
            delete_metadata('user', 0, '_ppl_purchased_lessons', '', true);
            delete_metadata('user', 0, '_ppl_expiry_notified_', '', true);
        }

        (new PPL_Cron())->unschedule();
    }
}

PayPerLesson::get_instance();