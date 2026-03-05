<?php
class PPL_CPT_Lesson {
    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('add_meta_boxes', [$this, 'add_metaboxes']);
        add_action('save_post_lesson', [$this, 'save_meta']);
    }

    public function register_cpt() {
        register_post_type('lesson', [
            'labels' => ['name' => 'Lessons', 'singular_name' => 'Lesson'],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-welcome-learn-more',
        ]);
    }

    public function add_metaboxes() {
        add_meta_box('ppl_teaser', 'Teaser Content (for non-buyers)', [$this, 'teaser_meta'], 'lesson', 'normal', 'high');
        add_meta_box('ppl_price', 'Pricing', [$this, 'price_meta'], 'lesson', 'side');
    }

    public function teaser_meta($post) {
        wp_nonce_field('ppl_teaser_nonce', 'ppl_teaser_nonce');
        $teaser = get_post_meta($post->ID, '_ppl_teaser', true);
        wp_editor($teaser, 'ppl_teaser', ['textarea_name' => 'ppl_teaser']);
    }

    public function price_meta($post) {
        wp_nonce_field('ppl_price_nonce', 'ppl_price_nonce');
        $price = get_post_meta($post->ID, '_ppl_price', true) ?: 0;
        $free = get_post_meta($post->ID, '_ppl_free', true);
        echo '<p><label>Price (in cents): <input type="number" name="ppl_price" value="' . esc_attr($price) . '"></label></p>';
        echo '<p><label><input type="checkbox" name="ppl_free" ' . checked($free, '1', false) . '> Free for all registered users</label></p>';
    }

    public function save_meta($post_id) {
        // Nonce + permission checks (omitted for brevity – add in production)
        if (isset($_POST['ppl_teaser'])) update_post_meta($post_id, '_ppl_teaser', wp_kses_post($_POST['ppl_teaser']));
        if (isset($_POST['ppl_price'])) update_post_meta($post_id, '_ppl_price', absint($_POST['ppl_price']));
        if (isset($_POST['ppl_free'])) update_post_meta($post_id, '_ppl_free', '1');
    }
}