<?php
if (!class_exists('FikenOrder')) {


    class FikenOrder
    {
        public $idOrder;
        public $currentState;
        public $mes;
        public $lastUpdate;
        public $stateName;

        function __construct($idOrder, $currentState, $mes = '', $stateName = '', $lastUpdate = '')
        {
            $this->currentState = $currentState;
            $this->idOrder = $idOrder;
            $this->lastUpdate = $lastUpdate;
            $this->mes = $mes;
            $this->stateName = $stateName;
        }

        /**
         * @return mixed
         */
        public function getStateName()
        {
            return $this->stateName;
        }

        /**
         * @return mixed
         */
        public function getCurrentState()
        {
            return $this->currentState;
        }

        /**
         * @param mixed $currentState
         */
        public function setCurrentState($currentState)
        {
            $this->currentState = $currentState;
        }

        /**
         * @return mixed
         */
        public function getIdOrder()
        {
            return $this->idOrder;
        }

        /**
         * @param mixed $idOrder
         */
        public function setIdOrder($idOrder)
        {
            $this->idOrder = $idOrder;
        }

        /**
         * @return mixed
         */
        public function getLastUpdate()
        {
            return $this->lastUpdate;
        }

        /**
         * @param mixed $lastUpdate
         */
        public function setLastUpdate($lastUpdate)
        {
            $this->lastUpdate = $lastUpdate;
        }

        /**
         * @return mixed
         */
        public function getMes()
        {
            return $this->mes;
        }

        /**
         * @param mixed $mes
         */
        public function setMes($mes)
        {
            $this->mes = $mes;
        }


        public function asArray()
        {
            return array(
                'currentState' => $this->getCurrentState(),
                'idOrder' => $this->getIdOrder(),
                'mes' => $this->getMes(),
                'stateName' => $this->getStateName(),
                'lastUpdate' => $this->getLastUpdate()
            );
        }


        public function update()
        {
            global $wpdb;
            if ($this->getIdOrder()) {
                if ($wpdb->get_var("SELECT `id_order` FROM `{$wpdb->prefix}fiken_orders` WHERE `id_order` = " . (intval($this->getIdOrder())))) {
                    $wpdb->query("UPDATE `{$wpdb->prefix}fiken_orders` SET `current_state` = " . intval($this->getCurrentState()) . ", mes = '" . $wpdb->_escape($this->getMes()) . "', `last_update` = NOW() WHERE `id_order` = " . intval($this->getIdOrder()));
                } else {
                    $wpdb->query("INSERT INTO `{$wpdb->prefix}fiken_orders` (`id_order`, `current_state`, `mes`, `last_update`)
                                            VALUES(" . intval($this->getIdOrder()) . ", " . intval($this->getCurrentState()) . ", '" . $wpdb->_escape($this->getMes()) . "', NOW())");
                }
                $wpdb->query("INSERT INTO `{$wpdb->prefix}fiken_order_history` (`id_order`, `id_state`, `mes`, `last_update`)
                                            VALUES(" . intval($this->getIdOrder()) . ", " . intval($this->getCurrentState()) . ", '" . $wpdb->_escape($this->getMes()) . "', NOW())");
            }
        }

        /**
         * @param $idOrder
         * @return bool|FikenOrder
         */

        public static function getFikenOrderById($idOrder)
        {
            global $wpdb;
            $res = false;
            $sql = "SELECT fo.`id_order`, fo.`current_state`, fo.`mes`, fo.`last_update`
                    ,(select fs.`name` FROM `{$wpdb->prefix}fiken_states` as fs WHERE fo.`current_state` = fs.`id_state`) as state_name
                    FROM  `{$wpdb->prefix}fiken_orders` fo
                    WHERE fo.`id_order` = " . intval($idOrder);
            $result = $wpdb->get_row($sql, ARRAY_A);

            if ($result) {
                $res = new FikenOrder($result['id_order'], $result['current_state'], $result['mes'], $result['state_name'], $result['last_update']);
            }
            return $res;
        }

        /**
         * @param $idOrder
         * @return FikenOrder[]|bool
         * @throws PrestaShopDatabaseException
         */
        public static function getFikenOrderHistoryById($idOrder)
        {
            global $wpdb;
            $res = false;
            $sql = "SELECT foh.`id_order`, foh.`id_state`, foh.`mes`, foh.`last_update`, foh.last_update
                  ,(select fs.`name` FROM `{$wpdb->prefix}fiken_states` as fs WHERE foh.`id_state` = fs.`id_state`) as state_name
                  FROM  `{$wpdb->prefix}fiken_order_history` foh
                  WHERE foh.`id_order` = " . intval($idOrder) .
                " ORDER BY foh.`id_history` ";

            $result = $wpdb->get_results($sql, ARRAY_A);
            if (is_array($result) && count($result)) {
                $res = array(); // appending to false is deprecated since PHP 8.1
                foreach ($result as $item) {
                    $res[] = new FikenOrder($item['id_order'], $item['id_state'], $item['mes'], $item['state_name'], $item['last_update']);
                }
            }
            return $res;
        }

        /**
         * Sort keys accepted from the request, mapped to the SQL used in ORDER BY.
         * @return array
         */
        public static function getSortableColumns()
        {
            return array(
                'posts.id' => 'posts.`id`',
                'customer' => 'customer',
                'current_state' => 'fo.`current_state`',
                'total_paid' => 'total_paid',
                'date_add' => 'date_add',
                'date_upd' => 'date_upd',
            );
        }

        /**
         * @param mixed $sort
         * @return bool
         */
        public static function isSortableColumn($sort)
        {
            return is_string($sort) && array_key_exists($sort, self::getSortableColumns());
        }

        public static function getOrdersWC($data = array())
        {
            global $wpdb;

            $sql = "SELECT posts.`id` as id_order,  posts.`post_date` as date_add, posts.`post_modified` as date_upd
                ,fo.`current_state`
                ,(SELECT fs.`name` FROM `{$wpdb->prefix}fiken_states` fs  where fs.`id_state` = fo.`current_state`) as status_name_fiken
                ,(SELECT `meta_value` FROM `{$wpdb->prefix}postmeta` AS meta where posts.`ID` = meta.`post_id` and `meta_key` = '_order_total') as total_paid
                ,(SELECT group_concat(`meta_value` SEPARATOR ' ') FROM `{$wpdb->prefix}postmeta` AS meta where posts.`ID` = meta.`post_id` and `meta_key` in ('_billing_first_name', '_billing_last_name' )) as customer
                FROM `{$wpdb->prefix}fiken_orders` fo
                INNER JOIN `{$wpdb->prefix}posts` AS posts ON (posts.`id` = fo.`id_order`)
                WHERE posts.`post_type` = 'shop_order'";

            //only statuses from settings
            $impl = array();
            $statates = FikenUtils::getStatesFromSettings();

            if ($statates) {
                foreach ($statates as $st) {
                    $impl[] = "posts.`post_status` = '" . $wpdb->_escape($st) . "'";
                }
                $sql .= " AND (" . implode(" OR ", $impl) . ") ";
            }

            if (isset($data['filter_date_start']) && $data['filter_date_start']) {
                $sql .= " AND DATE(posts.`post_date`) >= '" . $wpdb->_escape($data['filter_date_start']) . "'";
            }
            if (isset($data['filter_date_end']) && $data['filter_date_end']) {
                $sql .= " AND DATE(posts.`post_date`) <= '" . $wpdb->_escape($data['filter_date_end']) . "'";
            }

            if (isset($data['filter_order_status_id']) && $data['filter_order_status_id']) {
                $sql .= " and  fo.`current_state` = '" . (int)$data['filter_order_status_id'] . "'";
            }

            // ORDER BY cannot be escaped, only whitelisted: map the request value to a fixed column.
            $sortColumns = self::getSortableColumns();
            $sortKey = (isset($data['sort']) && self::isSortableColumn($data['sort'])) ? $data['sort'] : 'posts.id';
            $sql .= " ORDER BY " . $sortColumns[$sortKey];

            if (isset($data['asc_desc']) && strtoupper((string)$data['asc_desc']) === 'ASC') {
                $sql .= " ASC ";
            } else {
                $sql .= " DESC ";
            }

            if ((isset($data['start']) && $data['start']) || (isset($data['limit']) && $data['limit'])) {
                if (intval($data['start']) < 0) {
                    $data['start'] = 0;
                }
                if (intval($data['limit']) < 1) {
                    $data['limit'] = 20;
                }
                $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
            }
            return $wpdb->get_results($sql, ARRAY_A);
        }


        public static function getTotalOrdersWC($data = array())
        {
            global $wpdb;

            $sql = "SELECT count(posts.`id`) as cnt
                FROM `{$wpdb->prefix}posts` AS posts
                INNER JOIN `{$wpdb->prefix}fiken_orders` fo ON (posts.`id` = fo.`id_order`)
                WHERE posts.`post_type` = 'shop_order'";

            //only statuses from settings
            $impl = array();
            $statates = FikenUtils::getStatesFromSettings();

            if ($statates) {
                foreach ($statates as $st) {
                    $impl[] = "posts.`post_status` = '" . $wpdb->_escape($st) . "'";
                }
                $sql .= " and (" . implode(" OR ", $impl) . ") ";
            }

            if (isset($data['filter_date_start']) && $data['filter_date_start']) {
                $sql .= " AND DATE(posts.`post_date`) >= '" . $wpdb->_escape($data['filter_date_start']) . "'";
            }
            if (isset($data['filter_date_end']) && $data['filter_date_end']) {
                $sql .= " AND DATE(posts.`post_date`) <= '" . $wpdb->_escape($data['filter_date_end']) . "'";
            }

            if (isset($data['filter_order_status_id']) && $data['filter_order_status_id']) {
                $sql .= " and  fo.`current_state` = '" . (int)$data['filter_order_status_id'] . "'";
            }

            return $wpdb->get_var($sql);
        }
    }
}