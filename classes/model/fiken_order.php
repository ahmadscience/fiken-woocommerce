<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FikenOrder')) {

    class FikenOrder
    {
        public $idOrder;
        public $currentState;
        public $mes;
        public $lastUpdate;
        public $stateName;

        public function __construct($idOrder, $currentState, $mes = '', $stateName = '', $lastUpdate = '')
        {
            $this->currentState = $currentState;
            $this->idOrder      = (int) $idOrder;
            $this->lastUpdate   = $lastUpdate;
            $this->mes          = $mes;
            $this->stateName    = $stateName;
        }

        public function getStateName()      { return $this->stateName; }
        public function getCurrentState()   { return $this->currentState; }
        public function setCurrentState($v) { $this->currentState = $v; }
        public function getIdOrder()        { return $this->idOrder; }
        public function setIdOrder($v)      { $this->idOrder = (int) $v; }
        public function getLastUpdate()     { return $this->lastUpdate; }
        public function setLastUpdate($v)   { $this->lastUpdate = $v; }
        public function getMes()            { return $this->mes; }
        public function setMes($v)          { $this->mes = $v; }

        public function asArray()
        {
            return array(
                'currentState' => $this->getCurrentState(),
                'idOrder'      => $this->getIdOrder(),
                'mes'          => $this->getMes(),
                'stateName'    => $this->getStateName(),
                'lastUpdate'   => $this->getLastUpdate(),
            );
        }

        public function update()
        {
            global $wpdb;
            $id = (int) $this->getIdOrder();
            if (!$id) { return; }
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id_order FROM {$wpdb->prefix}fiken_orders WHERE id_order = %d",
                $id
            ));
            if ($exists) {
                $wpdb->update(
                    "{$wpdb->prefix}fiken_orders",
                    array(
                        'current_state' => (int) $this->getCurrentState(),
                        'mes'           => (string) $this->getMes(),
                        'last_update'   => current_time('mysql'),
                    ),
                    array('id_order' => $id),
                    array('%d', '%s', '%s'),
                    array('%d')
                );
            } else {
                $wpdb->insert(
                    "{$wpdb->prefix}fiken_orders",
                    array(
                        'id_order'      => $id,
                        'current_state' => (int) $this->getCurrentState(),
                        'mes'           => (string) $this->getMes(),
                        'last_update'   => current_time('mysql'),
                    ),
                    array('%d', '%d', '%s', '%s')
                );
            }
            $wpdb->insert(
                "{$wpdb->prefix}fiken_order_history",
                array(
                    'id_order'    => $id,
                    'id_state'    => (int) $this->getCurrentState(),
                    'mes'         => (string) $this->getMes(),
                    'last_update' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%s')
            );
        }

        public static function getFikenOrderById($idOrder)
        {
            global $wpdb;
            $id = (int) $idOrder;
            if (!$id) { return false; }
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT fo.id_order, fo.current_state, fo.mes, fo.last_update, fs.name AS state_name
                 FROM {$wpdb->prefix}fiken_orders fo
                 LEFT JOIN {$wpdb->prefix}fiken_states fs ON fo.current_state = fs.id_state
                 WHERE fo.id_order = %d",
                $id
            ), ARRAY_A);
            if (!$row) { return false; }
            return new FikenOrder(
                $row['id_order'], $row['current_state'],
                (string) $row['mes'], (string) $row['state_name'], (string) $row['last_update']
            );
        }

        public static function getFikenOrderHistoryById($idOrder)
        {
            global $wpdb;
            $id = (int) $idOrder;
            if (!$id) { return array(); }
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT foh.id_order, foh.id_state, foh.mes, foh.last_update, fs.name AS state_name
                 FROM {$wpdb->prefix}fiken_order_history foh
                 LEFT JOIN {$wpdb->prefix}fiken_states fs ON foh.id_state = fs.id_state
                 WHERE foh.id_order = %d
                 ORDER BY foh.id_history ASC",
                $id
            ), ARRAY_A);
            $res = array();
            foreach ((array) $rows as $item) {
                $res[] = new FikenOrder(
                    $item['id_order'], $item['id_state'],
                    (string) $item['mes'], (string) $item['state_name'], (string) $item['last_update']
                );
            }
            return $res;
        }

        public static function getPaginatedOrders(array $args)
        {
            $wc_args = array(
                'type'     => 'shop_order',
                'limit'    => isset($args['limit']) ? (int) $args['limit'] : 20,
                'page'     => isset($args['page'])  ? max(1, (int) $args['page']) : 1,
                'paginate' => true,
                'order'    => isset($args['asc_desc']) && strtoupper($args['asc_desc']) === 'ASC' ? 'ASC' : 'DESC',
                'orderby'  => self::mapOrderBy(isset($args['sort']) ? $args['sort'] : ''),
            );

            $configuredStatuses = FikenUtils::getStatesFromSettings();
            if (!empty($configuredStatuses)) {
                $wc_args['status'] = array_map(function ($s) {
                    return strpos($s, 'wc-') === 0 ? substr($s, 3) : $s;
                }, $configuredStatuses);
            }

            $dateStart = !empty($args['filter_date_start']) ? $args['filter_date_start'] : '';
            $dateEnd   = !empty($args['filter_date_end'])   ? $args['filter_date_end']   : '';
            if ($dateStart && $dateEnd)  { $wc_args['date_created'] = $dateStart . '...' . $dateEnd; }
            elseif ($dateStart)          { $wc_args['date_created'] = '>=' . $dateStart; }
            elseif ($dateEnd)            { $wc_args['date_created'] = '<=' . $dateEnd; }

            $result = wc_get_orders($wc_args);
            $orders = isset($result->orders) ? $result->orders : array();
            $total  = isset($result->total)  ? (int) $result->total : count($orders);

            $ids = array_map(function ($o) { return (int) $o->get_id(); }, $orders);
            $states = $ids ? self::fetchStates($ids) : array();

            $filterFikenState = isset($args['filter_order_status_id']) ? (int) $args['filter_order_status_id'] : 0;

            $rows = array();
            foreach ($orders as $order) {
                $id = (int) $order->get_id();
                $s  = isset($states[$id]) ? $states[$id] : array('current_state' => 1, 'state_name' => __('Not transferred', 'fiken'));
                if ($filterFikenState && (int) $s['current_state'] !== $filterFikenState) { continue; }
                $created  = $order->get_date_created();
                $modified = $order->get_date_modified();
                $customer = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                if ($customer === '') { $customer = $order->get_billing_email(); }
                $rows[] = array(
                    'id_order'          => $id,
                    'current_state'     => (int) $s['current_state'],
                    'status_name_fiken' => (string) $s['state_name'],
                    'customer'          => $customer,
                    'total_paid'        => (float) $order->get_total(),
                    'date_add'          => $created  ? $created->date('Y-m-d')  : '',
                    'date_upd'          => $modified ? $modified->date('Y-m-d') : '',
                );
            }

            return array('orders' => $rows, 'total' => $total);
        }

        private static function fetchStates(array $ids)
        {
            global $wpdb;
            $ids = array_filter(array_map('intval', $ids));
            if (empty($ids)) { return array(); }
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $sql = "SELECT fo.id_order, fo.current_state, fs.name AS state_name
                    FROM {$wpdb->prefix}fiken_orders fo
                    LEFT JOIN {$wpdb->prefix}fiken_states fs ON fo.current_state = fs.id_state
                    WHERE fo.id_order IN ($placeholders)";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $ids), ARRAY_A);
            $out  = array();
            foreach ((array) $rows as $r) {
                $out[(int) $r['id_order']] = array(
                    'current_state' => (int) $r['current_state'],
                    'state_name'    => (string) $r['state_name'],
                );
            }
            return $out;
        }

        private static function mapOrderBy($sort)
        {
            $map = array(
                'id' => 'date', 'date_add' => 'date', 'date_created' => 'date',
                'date_upd' => 'modified', 'date_modified' => 'modified',
                'total' => 'total', 'total_paid' => 'total',
            );
            $sort = strtolower((string) $sort);
            return isset($map[$sort]) ? $map[$sort] : 'date';
        }
    }
}
