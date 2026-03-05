<?php
/**
 * Pay Per Lesson - Payment Handler (FIXED VERSION)
 * Duplicate method removed + bank manual confirmation merged
 * Activator path bug also fixed in related files (no more issues)
 */

if (!defined('ABSPATH')) exit;

class PPL_Payment_Handler {

    public function __construct() {
        add_action('init', [$this, 'process_checkout']);
        add_action('template_redirect', [$this, 'handle_comgate_return']);
        add_action('template_redirect', [$this, 'maybe_render_bank_qr_page']);
    }

    private function calculate_total($basket) {
        $settings = get_option('ppl_settings', []);
        $total = 0;
        foreach ($basket as $lesson_id) {
            $price = absint(get_post_meta($lesson_id, '_ppl_price', true));
            $total += $price;
        }
        $count = count($basket);
        $discount = 0;
        if ($count >= 10) $discount = absint($settings['pricing_discount_10'] ?? 30);
        elseif ($count >= 5) $discount = absint($settings['pricing_discount_5'] ?? 15);
        if ($discount > 0) $total = $total * (100 - $discount) / 100;
        return absint($total);
    }

    private function save_pending_order($basket, $total_cents, $method, $trans_id = '') {
        global $wpdb;
        $user_id = get_current_user_id();
        $lesson_ids = wp_json_encode($basket);
        $ref_id = $trans_id ?: 'BANK-' . wp_generate_password(12, false);

        $wpdb->insert($wpdb->prefix . 'ppl_orders', [
            'user_id'        => $user_id,
            'lesson_ids'     => $lesson_ids,
            'total'          => $total_cents / 100,
            'payment_method' => $method,
            'status'         => 'pending',
            'trans_id'       => $ref_id,
        ]);
        return $wpdb->insert_id;
    }

    public function process_checkout() {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ppl_checkout')) {
            // Check for bank manual complete
            if (isset($_POST['complete_bank']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'ppl_bank_complete')) {
                $this->handle_bank_manual_complete();
            }
            return;
        }

        if (!is_user_logged_in()) {
            wp_redirect(home_url('/'));
            exit;
        }

        session_start();
        $basket = $_SESSION['ppl_basket'] ?? [];
        if (empty($basket)) {
            wp_redirect(home_url('/checkout/'));
            exit;
        }

        $total_cents = $this->calculate_total($basket);
        $user_id = get_current_user_id();

        if (isset($_POST['pay_comgate'])) {
            $this->create_comgate_payment($basket, $total_cents, $user_id);
        } elseif (isset($_POST['pay_bank'])) {
            $order_id = $this->save_pending_order($basket, $total_cents, 'bank');
            wp_redirect(home_url('/checkout/?bank_qr=1&order_id=' . $order_id));
            exit;
        }
    }

    private function handle_bank_manual_complete() {
        $order_id = absint($_POST['order_id'] ?? 0);
        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppl_orders WHERE id = %d AND user_id = %d AND status = 'pending'",
            $order_id, get_current_user_id()
        ));

        if ($order) {
            $this->complete_payment($order->id, 'bank');
            wp_redirect(home_url('/payment-success/'));
            exit;
        }
    }
    
    /**
     * Comgate payment creation (official REST API v2.0)
     */
    private function create_comgate_payment($basket, $total_cents, $user_id) {
        $settings = get_option('ppl_settings', []);
        if (empty($settings['comgate_enabled']) || empty($settings['comgate_merchant']) || empty($settings['comgate_secret'])) {
            wp_die('Comgate is not configured.');
        }

        $ref_id = 'ppl-' . $user_id . '-' . time();
        $order_id = $this->save_pending_order($basket, $total_cents, 'comgate', $ref_id); // we will update trans_id later if needed

        $data = [
            'test'        => !empty($settings['comgate_test']) ? 'true' : 'false',
            'price'       => $total_cents,
            'curr'        => $settings['currency_code'] ?? 'CZK',
            'label'       => 'Lessons × ' . count($basket),
            'refId'       => $ref_id,
            'method'      => 'ALL',
            'email'       => wp_get_current_user()->user_email,
            'fullName'    => wp_get_current_user()->display_name,
            'url_paid'    => home_url('/payment-success/?transId=${id}&refId=' . $ref_id),
            'url_cancelled' => home_url('/payment-failed/?transId=${id}&refId=' . $ref_id),
        ];

        $auth = base64_encode($settings['comgate_merchant'] . ':' . $settings['comgate_secret']);

        $ch = curl_init('https://payments.comgate.cz/v2.0/payment.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . $auth
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($response, true);

        if ($http_code === 201 && !empty($res['redirect'])) {
            // Update order with real transId from Comgate
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'ppl_orders',
                ['trans_id' => $res['transId']],
                ['id' => $order_id]
            );

            wp_redirect($res['redirect']);
            exit;
        }

        // Failure
        (new PPL_Emails())->send_payment_failed($user_id, $res['message'] ?? 'Comgate error');
        wp_redirect(home_url('/payment-failed/'));
        exit;
    }

    /**
     * Verify Comgate payment on return (required - status is not in URL)
     */
    private function verify_comgate_payment($trans_id) {
        $settings = get_option('ppl_settings', []);
        if (empty($settings['comgate_merchant']) || empty($settings['comgate_secret'])) {
            return false;
        }

        $auth = base64_encode($settings['comgate_merchant'] . ':' . $settings['comgate_secret']);

        $ch = curl_init('https://payments.comgate.cz/v2.0/payment/transId/' . urlencode($trans_id) . '.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $auth
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $res = json_decode($response, true);
        return !empty($res['status']) && $res['status'] === 'PAID';
    }

    /**
     * Handle Comgate redirect back
     */
    public function handle_comgate_return() {
        if (empty($_GET['transId'])) return;

        $trans_id = sanitize_text_field($_GET['transId']);
        $is_paid = $this->verify_comgate_payment($trans_id);

        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppl_orders WHERE trans_id = %s AND status = 'pending'",
            $trans_id
        ));

        if (!$order) {
            wp_redirect(home_url('/payment-failed/'));
            exit;
        }

        if ($is_paid) {
            $this->complete_payment($order->id, 'comgate');
            wp_redirect(home_url('/payment-success/'));
        } else {
            $this->mark_order_failed($order->id);
            (new PPL_Emails())->send_payment_failed($order->user_id);
            wp_redirect(home_url('/payment-failed/'));
        }
        exit;
    }

    /**
     * Render Bank Transfer QR page (simple self-contained page)
     */
    public function maybe_render_bank_qr_page() {
        if (empty($_GET['bank_qr']) || empty($_GET['order_id'])) return;

        $order_id = absint($_GET['order_id']);
        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppl_orders WHERE id = %d AND user_id = %d AND status = 'pending' AND payment_method = 'bank'",
            $order_id, get_current_user_id()
        ));

        if (!$order) {
            wp_redirect(home_url('/checkout/'));
            exit;
        }

        $settings = get_option('ppl_settings', []);
        $lesson_ids = json_decode($order->lesson_ids, true);
        $total = number_format($order->total, 2);
        $vs = $order->trans_id; // our generated VS for bank

        // SPD QR string (Czech standard)
        $iban = $settings['bank_iban'] ?: '';
        $amount = $total;
        $msg = 'Payment for lessons order #' . $order_id;
        $spd = "SPD*1.0*ACC:{$iban}*AM:{$amount}*CC:CZK*MSG:" . rawurlencode($msg) . "*X-VS:{$vs}";

        // Simple clean page
        ?>
        <!DOCTYPE html>
        <html lang="cs">
        <head>
            <meta charset="UTF-8">
            <title>Bank Transfer – Pay by QR</title>
            <style>
                body {font-family: Arial, sans-serif; background: #f8f9fa; margin:0; padding:40px; text-align:center;}
                .container {max-width:500px; margin:auto; background:white; padding:40px; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.1);}
                h1 {color:#0066cc;}
                #qrcode {margin:30px auto; padding:15px; background:white; display:inline-block; border:1px solid #ddd; border-radius:8px;}
                .info {margin:20px 0; font-size:15px;}
                button {background:#0066cc; color:white; border:none; padding:14px 32px; font-size:18px; border-radius:8px; cursor:pointer;}
                button:hover {background:#0052a3;}
            </style>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        </head>
        <body>
            <div class="container">
                <h1>Pay by Bank Transfer</h1>
                <p class="info">Total: <strong><?php echo $total . ' ' . ($settings['currency_code'] ?? 'CZK'); ?></strong><br>
                Variable symbol (VS): <strong><?php echo esc_html($vs); ?></strong></p>

                <div id="qrcode"></div>

                <div class="info">
                    <p>Scan the QR code with your banking app or transfer manually to the account below.</p>
                    <p><strong>Account:</strong> <?php echo esc_html($settings['bank_account'] ?? ''); ?> / <?php echo esc_html($settings['bank_code'] ?? ''); ?><br>
                    <strong>IBAN:</strong> <?php echo esc_html($settings['bank_iban'] ?? ''); ?><br>
                    <strong>Name:</strong> <?php echo esc_html($settings['bank_name'] ?? ''); ?></p>
                </div>

                <form method="post" style="margin-top:30px;">
                    <?php wp_nonce_field('ppl_bank_complete'); ?>
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <button type="submit" name="complete_bank">✓ I have paid – Unlock lessons now</button>
                </form>

                <p style="margin-top:40px;"><a href="<?php echo home_url('/checkout/'); ?>">← Back to Checkout</a></p>
            </div>

            <script>
                new QRCode(document.getElementById("qrcode"), {
                    text: "<?php echo esc_js($spd); ?>",
                    width: 280,
                    height: 280,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Complete payment – grant access, clear basket, send emails
     */
    private function complete_payment($order_id, $method) {
        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppl_orders WHERE id = %d", $order_id));
        if (!$order) return;

        $lesson_ids = json_decode($order->lesson_ids, true) ?: [];
        $user_id = $order->user_id;

        // Grant lifetime access (as used in class-access-control)
        $existing = get_user_meta($user_id, '_ppl_purchased_lessons', true) ?: [];
        $new = array_unique(array_merge((array)$existing, $lesson_ids));
        update_user_meta($user_id, '_ppl_purchased_lessons', $new);

        // Mark order completed
        $wpdb->update(
            $wpdb->prefix . 'ppl_orders',
            ['status' => 'completed'],
            ['id' => $order_id]
        );

        // Clear basket
        session_start();
        $_SESSION['ppl_basket'] = [];

        // Emails
        $emails = new PPL_Emails();
        $emails->send_purchase_confirmation($user_id, $lesson_ids, $order->total * 100, $method);
        $emails->notify_admin($user_id, $lesson_ids, $order->total * 100, $method);
    }

    /**
     * Mark order as failed
     */
    private function mark_order_failed($order_id) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'ppl_orders',
            ['status' => 'failed'],
            ['id' => $order_id]
        );
    }
}