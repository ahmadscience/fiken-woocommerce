<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FikenDB')) {

    include_once FIKEN_PLUGIN_DIR . 'classes/utils.php';

    class FikenDB
    {
        public static function install()
        {
            global $wpdb;
            $wpdb->hide_errors();

            $charset_collate = $wpdb->get_charset_collate();
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            $tables = "
CREATE TABLE {$wpdb->prefix}fiken_orders (
    id_order BIGINT(20) UNSIGNED NOT NULL,
    current_state INT(10) NOT NULL,
    mes TEXT,
    last_update DATETIME NOT NULL,
    PRIMARY KEY  (id_order)
) $charset_collate;
CREATE TABLE {$wpdb->prefix}fiken_order_history (
    id_history BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    id_order BIGINT(20) UNSIGNED NOT NULL,
    id_state INT(10) NOT NULL,
    mes TEXT,
    last_update DATETIME NOT NULL,
    PRIMARY KEY  (id_history),
    KEY id_order (id_order)
) $charset_collate;
CREATE TABLE {$wpdb->prefix}fiken_states (
    id_state INT(10) NOT NULL,
    name VARCHAR(50) NOT NULL,
    PRIMARY KEY  (id_state)
) $charset_collate;
";
            dbDelta($tables);

            self::seedStates();
            self::backfillOrders();
        }

        private static function seedStates()
        {
            global $wpdb;
            $states = array(
                array(1, 'Not transferred'),
                array(2, 'Transferred'),
                array(3, 'Failed'),
                array(4, 'Skipped'),
                array(5, 'Partially-skipped'),
            );
            foreach ($states as $s) {
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO {$wpdb->prefix}fiken_states (id_state, name) VALUES (%d, %s)",
                    $s[0],
                    $s[1]
                ));
            }
        }

        private static function backfillOrders()
        {
            if (!function_exists('wc_get_orders')) {
                return;
            }
            global $wpdb;
            $page  = 1;
            $limit = 500;
            do {
                $ids = wc_get_orders(array(
                    'type'   => 'shop_order',
                    'limit'  => $limit,
                    'page'   => $page,
                    'return' => 'ids',
                    'status' => 'any',
                    'orderby'=> 'id',
                    'order'  => 'ASC',
                ));
                if (empty($ids)) {
                    break;
                }
                foreach ($ids as $id) {
                    $id = (int) $id;
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT id_order FROM {$wpdb->prefix}fiken_orders WHERE id_order = %d",
                        $id
                    ));
                    if ($exists) {
                        continue;
                    }
                    $wpdb->insert(
                        "{$wpdb->prefix}fiken_orders",
                        array(
                            'id_order'      => $id,
                            'current_state' => 1,
                            'mes'           => '',
                            'last_update'   => current_time('mysql'),
                        ),
                        array('%d', '%d', '%s', '%s')
                    );
                }
                if (count($ids) < $limit) {
                    break;
                }
                $page++;
            } while (true);
        }

        public static function uninstall()
        {
            // No-op — actual cleanup lives in top-level uninstall.php.
        }
    }
}
