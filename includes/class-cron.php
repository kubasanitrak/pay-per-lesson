<?php
/**
 * Pay Per Lesson - Cron Jobs
 * Handles:
 * 1. Daily cleanup of old pending baskets/orders
 * 2. Daily expiry warnings (based on purchase date + settings)
 *
 * Fully compatible with previous files.
 * Uses orders table + user_meta for flags (no DB schema change needed).
 * Schedules itself automatically.
 */

if (!defined('ABSPATH')) exit;

class PPL_Cron {

    public function __construct() {
        // Schedule crons on every page load (safe & standard WP way)
        add_action('wp', [$this, 'schedule_crons']);

        // Hook the actual jobs
        add_action('ppl_daily_cleanup', [$this, 'run_basket_cleanup']);
        add_action('ppl_daily_expiry_notify', [$this, 'send_expiry_notifications']);
    }

    /**
     * Schedule both daily cron jobs if not already scheduled
     */
    public function schedule_crons() {
        if (!wp_next_scheduled('ppl_daily_cleanup')) {
            wp_schedule_event(time() + 3600, 'daily', 'ppl_daily_cleanup'); // start in ~1h
        }
        if (!wp_next_scheduled('ppl_daily_expiry_notify')) {
            wp_schedule_event(time() + 7200, 'daily', 'ppl_daily_expiry_notify');
        }
    }

    /**
     * 1. Cleanup old pending orders (basket / abandoned checkouts)
     */
    public function run_basket_cleanup() {
        $settings = get_option('ppl_settings', []);
        $days = absint($settings['advanced_basket_cleanup'] ?? 7);
        if ($days < 1) return;

        global $wpdb;
        $table = $wpdb->prefix . 'ppl_orders';

        // Delete pending orders older than X days
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table}
             WHERE status = 'pending'
             AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        if ($deleted > 0) {
            // Optional: log to error_log for debugging
            error_log("PPL Cron: Cleaned up {$deleted} old pending orders.");
        }
    }

    /**
     * 2. Send expiry warnings to customers
     * Uses order created_at + advanced_expiry_days
     * Sends only once per order (using user_meta flag)
     */
    public function send_expiry_notifications() {
        $settings = get_option('ppl_settings', []);
        $expiry_days = absint($settings['advanced_expiry_days'] ?? 0);
        $notify_days = absint($settings['advanced_notify_days'] ?? 7);

        // If expiry is disabled (0 days) → do nothing
        if ($expiry_days === 0 || $notify_days < 1) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ppl_orders';

        // Get completed orders that might be expiring soon
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT id, user_id, lesson_ids, created_at
             FROM {$table}
             WHERE status = 'completed'
             AND created_at <= DATE_SUB(NOW(), INTERVAL %d DAY)",
            ($expiry_days - $notify_days) // only orders that have entered the warning window
        ));

        $emails = new PPL_Emails();

        foreach ($orders as $order) {
            $order_id = $order->id;
            $user_id  = $order->user_id;
            $lesson_ids = json_decode($order->lesson_ids, true) ?: [];

            // Calculate exact expiry date for this order
            $created_timestamp = strtotime($order->created_at);
            $expiry_timestamp  = $created_timestamp + ($expiry_days * DAY_IN_SECONDS);
            $days_left = ceil(($expiry_timestamp - time()) / DAY_IN_SECONDS);

            // Only notify if we are in the warning window (1 to notify_days days left)
            if ($days_left < 1 || $days_left > $notify_days) {
                continue;
            }

            // Prevent duplicate notifications (flag per order)
            $notified_key = '_ppl_expiry_notified_' . $order_id;
            if (get_user_meta($user_id, $notified_key, true)) {
                continue;
            }

            // Send the warning
            $emails->send_expiry_warning($user_id, $lesson_ids);

            // Mark as notified
            update_user_meta($user_id, $notified_key, current_time('mysql'));

            error_log("PPL Cron: Sent expiry warning for order #{$order_id} to user #{$user_id} ({$days_left} days left)");
        }
    }

    /**
     * Optional: Unschedule everything on plugin deactivation
     * (Add this line to pay-per-lesson.php if you want:
     * register_deactivation_hook(__FILE__, function() { (new PPL_Cron())->unschedule(); });
     */
    public function unschedule() {
        wp_clear_scheduled_hook('ppl_daily_cleanup');
        wp_clear_scheduled_hook('ppl_daily_expiry_notify');
    }
}