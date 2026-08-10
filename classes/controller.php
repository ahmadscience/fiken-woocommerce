<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FikenController')) {

    include_once FIKEN_PLUGIN_DIR . 'classes/utils.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/provider.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/model/fiken_order.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/pagination.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/model/company.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/model/account.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/model/sale.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/api/client.php';

    class FikenController
    {
        public $data = array();

        public function __construct()
        {
            if (!current_user_can('manage_woocommerce')) {
                wp_die(esc_html__('You do not have permission to access the Fiken settings.', 'fiken'), 403);
            }
            $this->init();
        }

        private function init()
        {
            if (FikenUtils::getIsset('submitSettings')) {
                $this->postProcess();
            }

            $this->data['plugin_name']              = 'Fiken';
            $this->data['img_dir']                  = FIKEN_PLUGIN_URL . 'views/img/';
            $this->data['admin_fiken_base_url_ajax'] = admin_url('admin-ajax.php');
            $this->data['admin_fiken_base_url']     = admin_url('admin.php?page=fiken-plugin-page');
            $this->data                             = array_merge($this->data, $this->getAssignData());
        }

        private function getAssignData()
        {
            $data = array();
            $data['companies']             = FikenCompany::getCompaniesFromFiken();
            $data['list_pay']              = FikenProvider::getListPayWC();
            $data['accounts']              = FikenAccount::getAccountsFromFiken(get_option(FikenUtils::CONF_FIKEN_COMPANY), FikenUtils::ACC_FILTER);
            $data['list_states_wc']        = FikenProvider::getOrderStatesWC();
            $data['list_sales_kind_fiken'] = FikenProvider::getSalesKindFiken();
            $data['list_vat_types_fiken']  = FikenProvider::getVatTypesFiken();
            $data['list_taxes_ps']         = FikenProvider::getTaxesWC();
            $data['states_fiken']          = FikenProvider::getStatesFiken();
            $data                          = array_merge($data, $this->getListOrders());
            $data                          = array_merge($data, $this->getSettingsData());
            $data['current_tab']           = FikenUtils::getIsset('submitSettings') ? 'tab-settings' : 'tab-orders';
            return $data;
        }

        private function postProcess()
        {
            check_admin_referer('fiken_save_settings');

            if (FikenUtils::getIsset(FikenUtils::CTRL_NAME_TOKEN)) {
                $token = FikenUtils::getString(FikenUtils::CTRL_NAME_TOKEN);
                if ($token !== '') {
                    update_option(FikenUtils::CONF_FIKEN_TOKEN, $token);
                    delete_option(FikenUtils::CONF_V1_LOGIN);
                    delete_option(FikenUtils::CONF_V1_PASSWORD);
                    delete_option(FikenUtils::CONF_V1_COMPANY);
                }
            }
            if (FikenUtils::getIsset(FikenUtils::CTRL_NAME_COMPANY)) {
                update_option(FikenUtils::CONF_FIKEN_COMPANY, FikenUtils::getKey(FikenUtils::CTRL_NAME_COMPANY));
            }
            update_option(FikenUtils::CONF_FIKEN_PAY_METHODS, wp_json_encode(
                FikenUtils::parseDataFromPost(array(
                    FikenUtils::CTRL_NAME_ACCOUNT,
                    FikenUtils::CTRL_NAME_PAY_STATUS,
                    FikenUtils::CTRL_NAME_SALE_KIND,
                ))
            ));
            update_option(FikenUtils::CONF_FIKEN_VATS_MAPPING, wp_json_encode(
                FikenUtils::parseDataFromPost(FikenUtils::CTRL_NAME_VAT)
            ));
            update_option(FikenUtils::CONF_FIKEN_DEBUG_MODE, FikenUtils::getInt(FikenUtils::CTRL_NAME_DEBUG_MODE));
        }

        private function getSettingsData()
        {
            $data = array();

            $token = get_option(FikenUtils::CONF_FIKEN_TOKEN);
            $data['token_configured'] = !empty($token);
            $data['company_slug']     = get_option(FikenUtils::CONF_FIKEN_COMPANY);
            $data['pdf_inv']          = get_option(FikenUtils::CONF_FIKEN_PDF_INV);
            $data['debug_mode']       = get_option(FikenUtils::CONF_FIKEN_DEBUG_MODE);

            $selectedAccounts    = array();
            $selectedPayStatuses = array();
            $selectedSalesKind   = array();
            $selectedVat         = array();

            $res = json_decode(get_option(FikenUtils::CONF_FIKEN_PAY_METHODS));
            if ($res) {
                foreach ($res as $key => $value) {
                    $selectedAccounts[FikenUtils::CTRL_NAME_ACCOUNT . $key]       = isset($value->{FikenUtils::CTRL_NAME_ACCOUNT})    ? $value->{FikenUtils::CTRL_NAME_ACCOUNT}    : '';
                    $selectedPayStatuses[FikenUtils::CTRL_NAME_PAY_STATUS . $key] = isset($value->{FikenUtils::CTRL_NAME_PAY_STATUS}) ? $value->{FikenUtils::CTRL_NAME_PAY_STATUS} : '';
                    $selectedSalesKind[FikenUtils::CTRL_NAME_SALE_KIND . $key]    = isset($value->{FikenUtils::CTRL_NAME_SALE_KIND})  ? $value->{FikenUtils::CTRL_NAME_SALE_KIND}  : '';
                }
            }
            $res = json_decode(get_option(FikenUtils::CONF_FIKEN_VATS_MAPPING));
            if ($res) {
                foreach ($res as $key => $value) {
                    if (isset($value->{FikenUtils::CTRL_NAME_VAT})) {
                        $selectedVat[FikenUtils::CTRL_NAME_VAT . $key] = $value->{FikenUtils::CTRL_NAME_VAT};
                    }
                }
            }

            $data['selectedAccounts']    = $selectedAccounts;
            $data['selectedPayStatuses'] = $selectedPayStatuses;
            $data['selectedSalesKind']   = $selectedSalesKind;
            $data['selectedVat']         = $selectedVat;

            $data = array_merge($data, $this->checkToken($token));
            return $data;
        }

        private function checkToken($token)
        {
            $data = array();
            if (empty($token)) {
                $data['error_warning'] = __('No Fiken API token configured.', 'fiken');
                return $data;
            }
            $client = new FikenApiClient($token);
            $r      = $client->whoAmI();
            if (!$r['ok']) {
                $data['error_warning'] = __('Authentication failed', 'fiken');
            }
            return $data;
        }

        private function getListOrders()
        {
            $data = array();
            $today = current_time('Y-m-d');

            $filter_date_start      = FikenUtils::getIsset('filter_date_start')      ? FikenUtils::getString('filter_date_start')      : $today;
            $filter_date_end        = FikenUtils::getIsset('filter_date_end')        ? FikenUtils::getString('filter_date_end')        : $today;
            $filter_order_status_id = FikenUtils::getIsset('filter_order_status_id') ? FikenUtils::getInt('filter_order_status_id')    : 0;
            $sort                   = FikenUtils::getIsset('sort')                    ? FikenUtils::getKey('sort')                     : 'id';
            $asc_desc               = FikenUtils::getIsset('asc_desc')                ? FikenUtils::getKey('asc_desc')                 : 'desc';
            $asc_desc               = (strtoupper($asc_desc) === 'ASC') ? 'ASC' : 'DESC';
            $page                   = FikenUtils::getIsset('cur_page')                ? max(1, FikenUtils::getInt('cur_page'))         : 1;
            $limit                  = (int) get_option('posts_per_page', 20);
            if ($limit < 1) { $limit = 20; }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_start)) { $filter_date_start = $today; }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_end))   { $filter_date_end   = $today; }

            $result = FikenOrder::getPaginatedOrders(array(
                'filter_date_start'      => $filter_date_start,
                'filter_date_end'        => $filter_date_end,
                'filter_order_status_id' => $filter_order_status_id,
                'sort'                   => $sort,
                'asc_desc'               => $asc_desc,
                'limit'                  => $limit,
                'page'                   => $page,
            ));

            $data['orders'] = array();
            foreach ($result['orders'] as $row) {
                $data['orders'][] = array(
                    'id_order'          => (int) $row['id_order'],
                    'view'              => \Automattic\WooCommerce\Utilities\OrderUtil::get_order_admin_edit_url((int) $row['id_order']),
                    'current_state'     => (int) $row['current_state'],
                    'status_name_fiken' => $row['status_name_fiken'],
                    'customer'          => $row['customer'],
                    'total_paid'        => wc_price($row['total_paid']),
                    'date_add'          => $row['date_add'],
                    'date_upd'          => $row['date_upd'],
                );
            }

            $baseArgs = array();
            if (FikenUtils::getIsset('filter_date_start'))      { $baseArgs['filter_date_start']      = $filter_date_start; }
            if (FikenUtils::getIsset('filter_date_end'))        { $baseArgs['filter_date_end']        = $filter_date_end; }
            if (FikenUtils::getIsset('filter_order_status_id')) { $baseArgs['filter_order_status_id'] = $filter_order_status_id; }
            $baseArgs['asc_desc'] = $asc_desc;
            $baseArgs['sort']     = $sort;
            $baseArgs['page']     = 'fiken-plugin-page';

            $paginationBaseUrl = add_query_arg(array_merge($baseArgs, array('cur_page' => '{page}')), admin_url('admin.php'));

            $pagination = new FikenPagination();
            $pagination->total = (int) $result['total'];
            $pagination->page  = $page;
            $pagination->limit = $limit;
            $pagination->url   = $paginationBaseUrl;
            $data['pagination'] = $pagination->render();

            $flip     = ($asc_desc === 'ASC') ? 'DESC' : 'ASC';
            $sortArgs = array_merge($baseArgs, array('asc_desc' => $flip, 'cur_page' => $page));
            $mkSort   = function ($by) use ($sortArgs) {
                return esc_url(add_query_arg(array_merge($sortArgs, array('sort' => $by)), admin_url('admin.php')));
            };
            $data['sort_order']    = $mkSort('id');
            $data['sort_customer'] = $mkSort('customer');
            $data['sort_status']   = $mkSort('current_state');
            $data['sort_total']    = $mkSort('total');
            $data['sort_date_add'] = $mkSort('date_add');
            $data['sort_date_upd'] = $mkSort('date_upd');

            $data['filter_date_start']      = $filter_date_start;
            $data['filter_date_end']        = $filter_date_end;
            $data['filter_order_status_id'] = $filter_order_status_id;
            $data['sort']                   = $sort;
            $data['asc_desc']               = $asc_desc;

            return $data;
        }

        public static function ajaxProcessGetOrderStatusHistory()
        {
            check_ajax_referer('fiken_ajax');
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(array('error' => __('Permission denied', 'fiken')), 403);
            }
            $res = array();
            if (FikenUtils::getIsset('order_id')) {
                $fikenOrders = FikenOrder::getFikenOrderHistoryById(FikenUtils::getInt('order_id'));
                if ($fikenOrders) {
                    foreach ($fikenOrders as $fOrder) {
                        $res[] = $fOrder->asArray();
                    }
                }
            } else {
                $res['error'] = __('Order id is not set!', 'fiken');
            }
            wp_send_json($res);
        }

        public static function ajaxProcessRegisterSale()
        {
            check_ajax_referer('fiken_ajax');
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(array('error' => __('Permission denied', 'fiken')), 403);
            }
            $res = array();
            if (FikenUtils::getIsset('order_id')) {
                $orderId = FikenUtils::getInt('order_id');
                $result  = FikenSale::registerSale($orderId, true);
                if ($result && is_array($result)) {
                    $res = $result;
                } elseif ($result) {
                    $res['success'] = sprintf(__('The transfer order id: %s successfully completed', 'fiken'), $orderId);
                } else {
                    $res['error'] = __('Unknown error', 'fiken');
                }
                $fikenOrderResult = FikenOrder::getFikenOrderById($orderId);
                if ($fikenOrderResult) {
                    $res = array_merge($res, array('order' => $fikenOrderResult->asArray()));
                }
            } else {
                $res['error'] = __('Order id not set!', 'fiken');
            }
            wp_send_json($res);
        }
    }
}
