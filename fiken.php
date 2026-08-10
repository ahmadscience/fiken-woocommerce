<?php
/**
 * @package Fiken
 */
/*
Plugin Name: Fiken
Plugin URI: https://www.fiken.no/
Description: The Module is used for transferring orders to <a target='_blank' href='https://www.fiken.no'>Fiken</a>.
Version: 2.0.0
Author: Fiken
Author URI: https://www.fiken.no/
License: GPLv2 or later
Text Domain: fiken
Requires at least: 6.5
Requires PHP: 8.0
WC requires at least: 8.2
WC tested up to: 9.6
*/

if (!defined('ABSPATH')) {
    exit;
}

define('FIKEN_VERSION', '2.0.0');
define('FIKEN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FIKEN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FIKEN_PLUGIN_BASENAME', plugin_basename(__FILE__));

include_once FIKEN_PLUGIN_DIR . 'classes/class.fiken.php';

register_activation_hook(__FILE__, array('Fiken', 'plugin_activation'));
register_deactivation_hook(__FILE__, array('Fiken', 'plugin_deactivation'));

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', 'fiken_load_lang');
add_action('init', 'fiken_init', 0);
add_action('admin_notices', 'fiken_v1_upgrade_notice');

function fiken_init()
{
    if (fiken_is_woocommerce_active()) {
        add_action('admin_menu', array('Fiken', 'add_menu'));
        add_filter('plugin_action_links_' . FIKEN_PLUGIN_BASENAME, array('Fiken', 'plugin_action_links'));
        add_action('admin_enqueue_scripts', array('Fiken', 'load_media'));
        add_action('wp_ajax_fiken_get_order_status_history', array('Fiken', 'ajax_process_get_order_status_history'));
        add_action('wp_ajax_fiken_register_sale', array('Fiken', 'ajax_process_register_sale'));
        add_action('woocommerce_order_status_changed', array('Fiken', 'process_register_sale'));
    } else {
        add_action('admin_notices', 'fiken_err_woo_message');
    }
}

function fiken_load_lang()
{
    $locale = apply_filters('plugin_locale', get_locale(), 'fiken');
    load_textdomain('fiken', trailingslashit(WP_LANG_DIR) . 'plugins/fiken-' . $locale . '.mo');
    load_plugin_textdomain('fiken', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

function fiken_is_woocommerce_active()
{
    return class_exists('WooCommerce');
}

function fiken_err_woo_message()
{
    if (!current_user_can('activate_plugins')) { return; }
    echo '<div class="error"><p>' . esc_html__('Fiken requires WooCommerce to be installed and active!', 'fiken') . '</p></div>';
}

function fiken_v1_upgrade_notice()
{
    if (!current_user_can('manage_woocommerce')) { return; }
    $hasV1 = get_option('fiken_v1_login') || get_option('fiken_v1_password');
    $hasV2 = get_option('fiken_v2_token');
    if (!$hasV1 || $hasV2) { return; }
    $settings_url = admin_url('admin.php?page=fiken-plugin-page');
    echo '<div class="notice notice-warning"><p>';
    echo esc_html__('The Fiken plugin has been upgraded to Fiken API v2. Your old username/password no longer works. Please generate a personal API token in Fiken (Rediger konto → Sikkerhet → Personlige API-nøkler) and enter it in ', 'fiken');
    echo '<a href="' . esc_url($settings_url) . '">' . esc_html__('Fiken settings', 'fiken') . '</a>.';
    echo '</p></div>';
}
