<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ppl_orders");
delete_option('ppl_settings');
delete_metadata('user', 0, '_ppl_purchased_lessons', '', true);