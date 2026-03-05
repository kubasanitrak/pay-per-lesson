<?php
/**
 * Template Name: PPL Payment Success
 * Page: /payment-success/
 */
if (!defined('ABSPATH')) exit;

get_header();
?>
<div class="ppl-success" style="max-width:700px;margin:80px auto;padding:40px;text-align:center;background:white;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.08);">
    <div class="success-icon">✅</div>
    
    <h1 style="font-size:42px;margin:20px 0 15px;color:#28a745;">Payment Successful!</h1>
    <p style="font-size:19px;color:#333;max-width:500px;margin:0 auto 40px;">
        Thank you! Your lessons have been unlocked and added to your account.
    </p>

    <a href="<?php echo home_url('/dashboard/'); ?>" 
       class="ppl-add-to-basket" 
       style="font-size:18px;padding:18px 40px;">
        Go to My Lessons →
    </a>

    <p style="margin-top:50px;color:#777;">
        A confirmation email has been sent to you.<br>
        Questions? Just reply to the email.
    </p>
</div>
<?php
get_footer();