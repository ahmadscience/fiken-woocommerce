<?php
if (!class_exists('Fiken')) {


    include_once FIKEN_PLUGIN_DIR . 'classes/install.php';
    include_once FIKEN_PLUGIN_DIR . 'views/view.php';

    class Fiken
    {

        const CAPABILITY = 'manage_woocommerce';
        const AJAX_NONCE_ACTION = 'fiken_ajax';
        const SETTINGS_NONCE_ACTION = 'fiken_save_settings';
        const SETTINGS_NONCE_NAME = 'fiken_settings_nonce';

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
           self::$page_hook = add_submenu_page('woocommerce', 'Fiken', 'Fiken', self::CAPABILITY, 'fiken-plugin-page', array('FikenView', 'render_view'));
        }


        public static function plugin_action_links($links)
        {
            $action_links = array(
                'settings' => '<a href="' . admin_url('admin.php?page=fiken-plugin-page') . '" title="' . esc_attr(__('Fiken orders and settings', 'fiken')) . '">' . __('Settings', 'fiken') . '</a>',
            );
            return array_merge($action_links, $links);
        }


        public static function  load_media($hook)
        {
            if( $hook == self::$page_hook)
            {
                wp_enqueue_style('fiken', FIKEN_PLUGIN_URL . 'views/css/fiken.css');
                wp_enqueue_style('jquery_alerts', FIKEN_PLUGIN_URL . 'views/css/jquery_alerts.css');
                wp_enqueue_script('jquery-ui-datepicker', array('jquery'));
                wp_enqueue_style('jquery-style', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css');
                wp_enqueue_script('fiken', FIKEN_PLUGIN_URL . 'views/js/fiken.js', array('jquery',), '', true);
                wp_enqueue_script('jquery_alerts', FIKEN_PLUGIN_URL . 'views/js/jquery_alerts.js', array('jquery',), '', true);
            }

        }

        /**
         * AJAX endpoints are only for shop managers on the Fiken admin page:
         * require the same capability as the menu page and a valid nonce.
         */
        private static function verify_ajax_request()
        {
            if (!current_user_can(self::CAPABILITY)) {
                wp_send_json(array('error' => __('You are not allowed to do this.', 'fiken')), 403);
            }
            check_ajax_referer(self::AJAX_NONCE_ACTION);
        }

        public static function ajax_process_get_order_status_history()
        {
            self::verify_ajax_request();
            $ctr = new FikenController(false);
            $ctr->ajaxProcessGetOrderStatusHistory();
        }

        public static function ajax_process_register_sale()
        {
            self::verify_ajax_request();
            $ctr = new FikenController(false);
            $ctr->ajaxProcessRegisterSale();
        }

        /**
         * Transfers run in the background of a status change, so a failure is otherwise only
         * visible on the Fiken page. Warn shop managers about recent failures.
         */
        public static function failed_transfers_notice()
        {
            if (!current_user_can(self::CAPABILITY)) {
                return;
            }
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen && $screen->id === self::$page_hook) {
                return; // the Fiken page shows the states itself
            }
            global $wpdb;
            $failed = (int)$wpdb->get_var(
                "SELECT COUNT(*) FROM `{$wpdb->prefix}fiken_orders`
                 WHERE `current_state` = 3 AND `last_update` >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            if ($failed < 1) {
                return;
            }
            $url = add_query_arg(array(
                'page' => 'fiken-plugin-page',
                'filter_order_status_id' => 3,
                'filter_date_start' => gmdate('Y-m-d', strtotime('-1 year')),
            ), admin_url('admin.php'));
            echo '<div class="notice notice-warning"><p>'
                . esc_html(sprintf(_n('%d order failed to transfer to Fiken in the last 30 days.', '%d orders failed to transfer to Fiken in the last 30 days.', $failed, 'fiken'), $failed))
                . ' <a href="' . esc_url($url) . '">' . esc_html__('Show failed orders', 'fiken') . '</a></p></div>';
        }

        public static function process_register_sale($order_id)
        {
            include_once FIKEN_PLUGIN_DIR . 'classes/model/sale.php';
            FikenSale::registerSale($order_id);
        }


    }
}