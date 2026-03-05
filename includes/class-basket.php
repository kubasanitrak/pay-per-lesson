<?php
class PPL_Basket {
    public function __construct() {
        add_action('wp_ajax_ppl_add_to_basket', [$this, 'add']);
        add_action('wp_ajax_nopriv_ppl_add_to_basket', [$this, 'add']); // optional
    }

    public function add() {
        check_ajax_referer('ppl_nonce', 'nonce');
        session_start();
        $id = absint($_POST['id']);
        $_SESSION['ppl_basket'] = $_SESSION['ppl_basket'] ?? [];
        if (!in_array($id, $_SESSION['ppl_basket'])) $_SESSION['ppl_basket'][] = $id;
        wp_send_json_success(['count' => count($_SESSION['ppl_basket'])]);
    }
}