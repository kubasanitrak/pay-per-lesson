<?php
/**
 * Pay Per Lesson - Checkout Page Renderer
 * Handles the /checkout/ page content (basket list + total + payment buttons)
 * Works with class-payment-handler.php
 */

if (!defined('ABSPATH')) exit;

class PPL_Checkout {

    public function __construct() {
        add_action('template_redirect', [$this, 'render_checkout_page']);
        add_filter('the_content', [$this, 'replace_checkout_content']);
    }

    /**
     * Render full checkout if on /checkout/ page
     */
    public function render_checkout_page() {
        if (!is_page('checkout')) return;

        // If bank QR is requested, let payment-handler handle it
        if (!empty($_GET['bank_qr'])) return;

        // Start session for basket
        if (!session_id()) session_start();
    }

    /**
     * Replace page content on /checkout/ with our beautiful checkout UI
     */
    public function replace_checkout_content($content) {
        if (!is_page('checkout') || !empty($_GET['bank_qr'])) return $content;

        session_start();
        $basket = $_SESSION['ppl_basket'] ?? [];

        if (empty($basket)) {
            return '<div class="ppl-checkout"><h1>Your basket is empty</h1><p><a href="' . home_url() . '">Browse lessons</a></p></div>';
        }

        $settings = get_option('ppl_settings', []);
        $items = [];
        $subtotal = 0;

        foreach ($basket as $lesson_id) {
            $price_cents = absint(get_post_meta($lesson_id, '_ppl_price', true));
            $price = $price_cents / 100;
            $subtotal += $price;
            $items[] = [
                'id'    => $lesson_id,
                'title' => get_the_title($lesson_id),
                'price' => $price
            ];
        }

        // Bundle discount
        $count = count($basket);
        $discount_percent = 0;
        if ($count >= 10) $discount_percent = absint($settings['pricing_discount_10'] ?? 30);
        elseif ($count >= 5) $discount_percent = absint($settings['pricing_discount_5'] ?? 15);

        $discount_amount = $subtotal * ($discount_percent / 100);
        $total = $subtotal - $discount_amount;

        ob_start();
        ?>
        <div class="ppl-checkout">
            <h1>Checkout — <?php echo count($basket); ?> lesson<?php echo count($basket) > 1 ? 's' : ''; ?></h1>

            <ul class="basket-items">
                <?php foreach ($items as $item) : ?>
                    <li>
                        <span><?php echo esc_html($item['title']); ?></span>
                        <span><?php echo number_format($item['price'], 2) . ' ' . esc_html($settings['currency_code'] ?? 'CZK'); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="total">
                Subtotal: <?php echo number_format($subtotal, 2); ?> <?php echo esc_html($settings['currency_code'] ?? 'CZK'); ?><br>
                <?php if ($discount_percent > 0) : ?>
                    <span class="discount">Bundle discount (<?php echo $discount_percent; ?>%): -<?php echo number_format($discount_amount, 2); ?> <?php echo esc_html($settings['currency_code'] ?? 'CZK'); ?></span><br>
                <?php endif; ?>
                <strong>Total: <?php echo number_format($total, 2); ?> <?php echo esc_html($settings['currency_code'] ?? 'CZK'); ?></strong>
            </div>

            <form method="post" class="payment-buttons">
                <?php wp_nonce_field('ppl_checkout'); ?>

                <?php if (!empty($settings['comgate_enabled'])) : ?>
                    <button type="submit" name="pay_comgate" class="comgate">
                        💳 Pay by Card (Comgate)
                    </button>
                <?php endif; ?>

                <?php if (!empty($settings['bank_enabled'])) : ?>
                    <button type="submit" name="pay_bank" class="bank">
                        🏦 Pay by Bank Transfer + QR Code
                    </button>
                <?php endif; ?>
            </form>

            <?php if (!empty($settings['terms_page'])) : ?>
                <p style="margin-top:30px; font-size:13px; color:#777;">
                    By completing your purchase you agree to our <a href="<?php echo get_permalink($settings['terms_page']); ?>" target="_blank">Terms &amp; Conditions</a>.
                </p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}