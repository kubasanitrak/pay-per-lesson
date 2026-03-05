<?php
/* Template Name: PPL Checkout */
session_start();
if (empty($_SESSION['ppl_basket'])) { wp_redirect(home_url()); exit; }

$settings = get_option('ppl_settings');
$items = $_SESSION['ppl_basket'];
$total = 0;
foreach ($items as $id) {
    $price = get_post_meta($id, '_ppl_price', true) ?: 0;
    $total += $price / 100;
}
// Simple bundle discount
if (count($items) >= 10) $total *= 0.7;
elseif (count($items) >= 5) $total *= 0.85;

get_header();
?>
<h1>Checkout</h1>
<!-- List items + total -->
<form id="ppl-checkout-form" method="post">
    <button type="submit" name="pay_comgate">Pay by Card (Comgate)</button>
    <?php if ($settings['bank_enabled']): ?>
        <button type="submit" name="pay_bank">Pay by Bank Transfer + QR</button>
    <?php endif; ?>
    <?php wp_nonce_field('ppl_checkout'); ?>
</form>
<?php
get_footer();