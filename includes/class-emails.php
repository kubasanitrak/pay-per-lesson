<?php
/**
 * Pay Per Lesson - Emails
 * Handles all transactional emails (customer + admin)
 * Uses wp_mail() with HTML/plain text support
 * Templates are fully coded here (as per your spec)
 * Reads settings from 'ppl_settings'
 */

if (!defined('ABSPATH')) exit;

class PPL_Emails {

    public function __construct() {
        // No hooks needed – called directly from Payment Handler / Cron
    }

    private function get_settings() {
        return wp_parse_args(
            get_option('ppl_settings', []),
            [
                'emails_from_name'    => get_bloginfo('name'),
                'emails_from_email'   => get_option('admin_email'),
                'emails_admin_email'  => get_option('admin_email'),
                'emails_html'         => true,
                'emails_notify_admin' => true,
            ]
        );
    }

    private function get_headers($settings) {
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        if ($settings['emails_html']) {
            $headers = ['Content-Type: text/html; charset=UTF-8'];
        }
        $headers[] = 'From: ' . $settings['emails_from_name'] . ' <' . $settings['emails_from_email'] . '>';
        return $headers;
    }

    /**
     * 1. Purchase Confirmation – Customer (Payment Completed)
     */
    public function send_purchase_confirmation($user_id, $lesson_ids, $total_cents, $payment_method) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $settings = $this->get_settings();
        $total = number_format($total_cents / 100, 2);

        $lesson_titles = [];
        foreach ($lesson_ids as $id) {
            $lesson_titles[] = get_the_title($id);
        }

        $subject = 'Thank you! Your lessons have been unlocked';

        $plain_text = "Hi {$user->display_name},\n\n" .
                      "Thank you for your purchase!\n\n" .
                      "Lessons purchased:\n" . implode("\n", $lesson_titles) . "\n\n" .
                      "Total: {$total} " . $settings['currency_code'] . "\n" .
                      "Payment method: " . ucfirst($payment_method) . "\n\n" .
                      "You can now access all lessons in your dashboard: " . home_url('/dashboard/') . "\n\n" .
                      "Best regards,\n" . $settings['emails_from_name'];

        $html = $this->get_html_template(
            'Purchase Confirmed!',
            "
            <p>Hi {$user->display_name},</p>
            <p>Thank you! Your payment was successful and your lessons are now unlocked.</p>
            
            <h3>Order summary</h3>
            <ul>
                " . implode('', array_map(fn($t) => "<li>$t</li>", $lesson_titles)) . "
            </ul>
            <p><strong>Total:</strong> {$total} {$settings['currency_code']}</p>
            <p><strong>Payment method:</strong> " . ucfirst($payment_method) . "</p>
            
            <p style='text-align:center; margin:30px 0;'>
                <a href='" . home_url('/dashboard/') . "' 
                   style='background:#0066cc;color:#fff;padding:14px 28px;text-decoration:none;border-radius:6px;font-weight:bold;'>
                    Go to My Lessons
                </a>
            </p>
            
            <p>If you have any questions, feel free to reply to this email.</p>
            "
        );

        wp_mail($user->user_email, $subject, $settings['emails_html'] ? $html : $plain_text, $this->get_headers($settings));
    }

    /**
     * 2. Payment Failed – Customer
     */
    public function send_payment_failed($user_id, $reason = 'Payment was declined or cancelled') {
        $user = get_userdata($user_id);
        if (!$user) return;

        $settings = $this->get_settings();

        $subject = 'Payment issue – Please try again';

        $plain_text = "Hi {$user->display_name},\n\n" .
                      "Unfortunately your payment could not be completed.\n\n" .
                      "Reason: {$reason}\n\n" .
                      "You can try again here: " . home_url('/checkout/') . "\n\n" .
                      "Best regards,\n" . $settings['emails_from_name'];

        $html = $this->get_html_template(
            'Payment Failed',
            "
            <p>Hi {$user->display_name},</p>
            <p>Unfortunately your payment could not be completed.</p>
            <p><strong>Reason:</strong> {$reason}</p>
            
            <p style='text-align:center; margin:30px 0;'>
                <a href='" . home_url('/checkout/') . "' 
                   style='background:#cc0000;color:#fff;padding:14px 28px;text-decoration:none;border-radius:6px;font-weight:bold;'>
                    Try Payment Again
                </a>
            </p>
            "
        );

        wp_mail($user->user_email, $subject, $settings['emails_html'] ? $html : $plain_text, $this->get_headers($settings));
    }

    /**
     * 3. Admin Notification – New Purchase
     */
    public function notify_admin($user_id, $lesson_ids, $total_cents, $payment_method) {
        $settings = $this->get_settings();
        if (!$settings['emails_notify_admin']) return;

        $user = get_userdata($user_id);
        $total = number_format($total_cents / 100, 2);

        $lesson_titles = array_map('get_the_title', $lesson_ids);

        $subject = 'New Lesson Purchase – ' . $user->display_name;

        $plain_text = "New purchase!\n\n" .
                      "Customer: {$user->display_name} ({$user->user_email})\n" .
                      "Lessons: " . implode(', ', $lesson_titles) . "\n" .
                      "Total: {$total} {$settings['currency_code']}\n" .
                      "Method: " . ucfirst($payment_method) . "\n\n" .
                      "View in admin: " . admin_url('admin.php?page=pay-per-lesson-stats');

        $html = $this->get_html_template(
            'New Purchase!',
            "
            <p>New lesson purchase received:</p>
            <p><strong>Customer:</strong> {$user->display_name} (<a href='mailto:{$user->user_email}'>{$user->user_email}</a>)</p>
            <p><strong>Lessons:</strong> " . implode(', ', $lesson_titles) . "</p>
            <p><strong>Total:</strong> {$total} {$settings['currency_code']}</p>
            <p><strong>Method:</strong> " . ucfirst($payment_method) . "</p>
            ",
            '#0066cc'
        );

        wp_mail($settings['emails_admin_email'], $subject, $settings['emails_html'] ? $html : $plain_text, $this->get_headers($settings));
    }

    /**
     * 4. Order Expiry Warning – Customer (called by cron)
     */
    public function send_expiry_warning($user_id, $lesson_ids) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $settings = $this->get_settings();

        $lesson_titles = array_map('get_the_title', $lesson_ids);

        $subject = 'Your lesson access expires soon';

        $plain_text = "Hi {$user->display_name},\n\n" .
                      "Your access to the following lessons will expire in " . $settings['advanced_notify_days'] . " days:\n" .
                      implode("\n", $lesson_titles) . "\n\n" .
                      "If you want to keep access, please purchase again.";

        $html = $this->get_html_template(
            'Access Expiring Soon',
            "
            <p>Hi {$user->display_name},</p>
            <p>Your access to the following lessons will expire in <strong>{$settings['advanced_notify_days']} days</strong>:</p>
            <ul>" . implode('', array_map(fn($t) => "<li>$t</li>", $lesson_titles)) . "</ul>
            <p>If you want to keep lifetime access, just purchase the lessons again.</p>
            "
        );

        wp_mail($user->user_email, $subject, $settings['emails_html'] ? $html : $plain_text, $this->get_headers($settings));
    }

    /**
     * Internal: Beautiful HTML email wrapper
     */
    private function get_html_template($title, $body, $accent_color = '#0066cc') {
        $site_name = get_bloginfo('name');
        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:40px 0;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);">
                            <!-- Header -->
                            <tr>
                                <td style="background:' . $accent_color . ';padding:30px 40px;color:#ffffff;text-align:center;">
                                    <h1 style="margin:0;font-size:24px;">' . esc_html($site_name) . '</h1>
                                </td>
                            </tr>
                            <!-- Content -->
                            <tr>
                                <td style="padding:40px 50px;color:#333;">
                                    <h2 style="margin-top:0;color:#222;">' . $title . '</h2>
                                    ' . $body . '
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td style="background:#f9f9f9;padding:25px 40px;text-align:center;color:#777;font-size:13px;">
                                    ' . esc_html($site_name) . ' — ' . date('Y') . '<br>
                                    This is an automated message. Please do not reply directly.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    }
}