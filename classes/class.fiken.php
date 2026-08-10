<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Fiken')) {

    include_once FIKEN_PLUGIN_DIR . 'classes/install.php';
    include_once FIKEN_PLUGIN_DIR . 'views/view.php';

    class Fiken
    {
        private static $page_hook;

        public static function plugin_activation()
        {
            FikenDB::install();
        }

        public static function plugin_deactivation()
        {
            FikenDB::uninstall();
        }

        public static function add_menu()
        {
            self::$page_hook = add_submenu_page(
                'woocommerce',
                'Fiken',
                'Fiken',
                'manage_woocommerce',
                'fiken-plugin-page',
                array('FikenView', 'render_view')
            );
        }

        public static function plugin_action_links($links)
        {
            $action_links = array(
                'settings' => '<a href="' . esc_url(admin_url('admin.php?page=fiken-plugin-page')) . '" title="' . esc_attr__('Fiken orders and settings', 'fiken') . '">' . esc_html__('Settings', 'fiken') . '</a>',
            );
            return array_merge($action_links, $links);
        }

        public static function load_media($hook)
        {
            if ($hook !== self::$page_hook) {
                return;
            }
            wp_enqueue_style('fiken', FIKEN_PLUGIN_URL . 'views/css/fiken.css', array(), FIKEN_VERSION);

            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_style(
                'jquery-ui-fiken',
                includes_url('js/jquery/ui/theme/smoothness/jquery-ui.min.css'),
                array(),
                '1.13.2'
            );

            wp_enqueue_script('fiken', FIKEN_PLUGIN_URL . 'views/js/fiken.js', array('jquery', 'jquery-ui-datepicker'), FIKEN_VERSION, true);

            wp_localize_script('fiken', 'fiken', array(
                'ajax_url'   => admin_url('admin-ajax.php'),
                'ajax_nonce' => wp_create_nonce('fiken_ajax'),
                'base_url'   => admin_url('admin.php?page=fiken-plugin-page'),
                'i18n'       => array(
                    'confirm_reg_if_transferred' => __('The order marked as %s. Are you sure?', 'fiken'),
                    'err_nothing_selected'       => __('Nothing selected!', 'fiken'),
                    'order_register_success_mass'=> __('Transfer selected orders successfully completed', 'fiken'),
                    'select_account'             => __('Select account:', 'fiken'),
                ),
            ));
        }

        public static function ajax_process_get_order_status_history()
        {
            include_once FIKEN_PLUGIN_DIR . 'classes/controller.php';
            FikenController::ajaxProcessGetOrderStatusHistory();
        }

        public static function ajax_process_register_sale()
        {
            include_once FIKEN_PLUGIN_DIR . 'classes/controller.php';
            FikenController::ajaxProcessRegisterSale();
        }

        public static function process_register_sale($order_id)
        {
            include_once FIKEN_PLUGIN_DIR . 'classes/model/sale.php';
            FikenSale::registerSale($order_id);
        }
    }
}
