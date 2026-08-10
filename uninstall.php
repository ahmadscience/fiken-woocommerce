<?php
/**
 * Fiken plugin uninstall handler. Runs when the user deletes the plugin.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tables = array(
    "{$wpdb->prefix}fiken_orders",
    "{$wpdb->prefix}fiken_order_history",
    "{$wpdb->prefix}fiken_states",
);
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

$options = array(
    'fiken_v1_login', 'fiken_v1_password', 'fiken_v1_company',
    'fiken_v1_pay_methods', 'fiken_v1_vats_mapping', 'fiken_v1_pdf_inv', 'fiken_v1_debug_mode',
    'fiken_v2_token', 'fiken_v2_company_slug',
    'fiken_v2_pay_methods', 'fiken_v2_vats_mapping', 'fiken_v2_pdf_inv', 'fiken_v2_debug_mode',
);
foreach ($options as $option) {
    delete_option($option);
    delete_site_option($option);
}
