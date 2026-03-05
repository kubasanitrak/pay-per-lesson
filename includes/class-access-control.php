<?php
class PPL_Access_Control {
    public function __construct() {
        add_filter('the_content', [$this, 'filter_content']);
        add_action('template_redirect', [$this, 'redirect_non_logged_in']);
    }

    public function redirect_non_logged_in() {
        if (is_singular('lesson') && !is_user_logged_in()) {
            wp_redirect(home_url('/?login=required')); // or your landing page
            exit;
        }
    }

    public function filter_content($content) {
        if (!is_singular('lesson')) return $content;

        $post_id = get_the_ID();
        $user_id = get_current_user_id();

        if (get_post_meta($post_id, '_ppl_free', true) === '1' || $this->has_access($user_id, $post_id)) {
            return $content; // full content
        }

        $teaser = get_post_meta($post_id, '_ppl_teaser', true);
        $buy_btn = '<button class="ppl-add-to-basket" data-id="' . $post_id . '">Add to Basket – ' . $this->format_price(get_post_meta($post_id, '_ppl_price', true)) . '</button>';
        return wpautop($teaser) . $buy_btn;
    }

    private function has_access($user_id, $post_id) {
        $purchased = get_user_meta($user_id, '_ppl_purchased_lessons', true) ?: [];
        return in_array($post_id, (array)$purchased);
    }

    private function format_price($cents) {
        $settings = get_option('ppl_settings');
        $sym = $settings['currency_symbol'];
        $pos = $settings['currency_position'];
        $price = number_format($cents / 100, 2);
        return $pos === 'before' ? $sym . $price : $price . ' ' . $sym;
    }
}