<?php

if (!class_exists('FikenController')) {

    include_once FIKEN_PLUGIN_DIR . 'classes/utils.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/provider.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/model/fiken_order.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/pagination.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/model/company.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/model/account.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/model/sale.php';

    class FikenController
    {

        public $data = array();

        /**
         * @param bool $loadPageData Build the admin page (and handle a settings save).
         *                           AJAX handlers pass false: they must never save settings
         *                           or make the page's Fiken API calls.
         */
        function __construct($loadPageData = true)
        {
            if ($loadPageData) {
                $this->init();
            }
        }


        private function init()
        {
            if (FikenUtils::getIsset('submitSettings')) {
                $this->postProcess();
            }

            $this->data['plugin_name'] = 'Fiken';
            $this->data['img_dir'] = FIKEN_PLUGIN_URL . 'views/img/';
            $this->data['admin_fiken_base_url_ajax'] = admin_url('admin-ajax.php');
            $this->data['admin_fiken_base_url'] = admin_url('admin.php?page=fiken-plugin-page');
            $this->data = array_merge($this->data, $this->getAssignData());
        }


        private function getAssignData()
        {
            $data = array();
            //companies
            $data['companies'] = FikenCompany::getCompaniesFromFiken();
            //paylist
            $data['list_pay'] = FikenProvider::getListPayWC();
            //accounts
            $data['accounts'] = FikenAccount::getAccountsFromFiken(get_option(FikenUtils::CONF_FIKEN_COMPANY), FikenUtils::ACC_FILTER);
            //order satatuses
            $data['list_states_wc'] = FikenProvider::getOrderStatesWC();
            //sales king
            $data['list_sales_kind_fiken'] = FikenProvider::getSalesKindFiken();
            //fiken vat codes
            $data['list_vat_types_fiken'] = FikenProvider::getVatTypesFiken();
            //taxes
            $data['list_taxes_ps'] = FikenProvider::getTaxesWC();
            //fikenStates
            $data['states_fiken'] = FikenProvider::getStatesFiken();
            //orders
            $data = array_merge($data, $this->getListOrders());
            //from setings
            $data = array_merge($data, $this->getSettingsData());
            $data['current_tab'] = FikenUtils::getIsset('submitSettings') ? 'tab-settings' : 'tab-orders';
            return $data;
        }

        private function postProcess()
        {
            if (!current_user_can(Fiken::CAPABILITY)) {
                wp_die(__('You are not allowed to change Fiken settings.', 'fiken'), 403);
            }
            check_admin_referer(Fiken::SETTINGS_NONCE_ACTION, Fiken::SETTINGS_NONCE_NAME);

            if (FikenUtils::getIsset('submitSettings')) {
                if (FikenUtils::getIsset(FikenUtils::CTRL_NAME_LOGIN)) {
                    update_option(FikenUtils::CONF_FIKEN_LOGIN, FikenUtils::getValue(FikenUtils::CTRL_NAME_LOGIN));
                }
                if (FikenUtils::getIsset(FikenUtils::CTRL_NAME_PASSW)) {
                    update_option(FikenUtils::CONF_FIKEN_PASSW, FikenUtils::getValue(FikenUtils::CTRL_NAME_PASSW));
                }
                if (FikenUtils::getIsset(FikenUtils::CTRL_NAME_COMPANY)) {
                    update_option(FikenUtils::CONF_FIKEN_COMPANY, FikenUtils::getValue(FikenUtils::CTRL_NAME_COMPANY));
                }
                update_option(FikenUtils::CONF_FIKEN_PAY_METHODS, json_encode(
                    FikenUtils::parseDataFromPost(array(FikenUtils::CTRL_NAME_ACCOUNT, FikenUtils::CTRL_NAME_PAY_STATUS, FikenUtils::CTRL_NAME_SALE_KIND))));

                update_option(FikenUtils::CONF_FIKEN_VATS_MAPPING, json_encode(FikenUtils::parseDataFromPost(FikenUtils::CTRL_NAME_VAT)));

                update_option(FikenUtils::CONF_FIKEN_DEBUG_MODE, FikenUtils::getValue(FikenUtils::CTRL_NAME_DEBUG_MODE));
            }
        }


        private function getSettingsData()
        {
            $data = array();

            $data['login'] = get_option(FikenUtils::CONF_FIKEN_LOGIN);
            $data['password'] = get_option(FikenUtils::CONF_FIKEN_PASSW);
            $data['company_number'] = get_option(FikenUtils::CONF_FIKEN_COMPANY);
            $data['pdf_inv'] = get_option(FikenUtils::CONF_FIKEN_PDF_INV);
            $data['debug_mode'] = get_option(FikenUtils::CONF_FIKEN_DEBUG_MODE);

            $selectedAccounts = array();
            $selectedPayStatuses = array();
            $selectedSalesKind = array();
            $selectedVat = array();

            $res = json_decode(get_option(FikenUtils::CONF_FIKEN_PAY_METHODS));

            if ($res) {
                foreach ($res as $key => $value) {
                    $selectedAccounts[FikenUtils::CTRL_NAME_ACCOUNT . $key] = isset($value->{FikenUtils::CTRL_NAME_ACCOUNT}) ? $value->{FikenUtils::CTRL_NAME_ACCOUNT} : '';
                    $selectedPayStatuses[FikenUtils::CTRL_NAME_PAY_STATUS . $key] = isset($value->{FikenUtils::CTRL_NAME_PAY_STATUS}) ? $value->{FikenUtils::CTRL_NAME_PAY_STATUS} : '';
                    $selectedSalesKind[FikenUtils::CTRL_NAME_SALE_KIND . $key] = isset($value->{FikenUtils::CTRL_NAME_SALE_KIND}) ? $value->{FikenUtils::CTRL_NAME_SALE_KIND} : '';
                }
            }

            $res = json_decode(get_option(FikenUtils::CONF_FIKEN_VATS_MAPPING));
            if ($res) {
                foreach ($res as $key => $value) {
                    $selectedVat[FikenUtils::CTRL_NAME_VAT . $key] = $value->{FikenUtils::CTRL_NAME_VAT};
                }
            }

            $data['selectedAccounts'] = $selectedAccounts;
            $data['selectedPayStatuses'] = $selectedPayStatuses;
            $data['selectedSalesKind'] = $selectedSalesKind;
            $data['selectedVat'] = $selectedVat;

            $data = array_merge($data, $this->checkLogin($data['login'], $data['password']));

            return $data;
        }

        private function checkLogin($user, $pass)
        {
            $data = array();
            $result = FikenUtils::call("/whoAmI", null, true, $user, $pass);
            if (isset($result['body']) && $result['body']) {
                $body = json_decode($result['body'], TRUE);
                if (array_key_exists('name', $body)) {
                    /*NOP*/
                } else {
                    $data['error_warning'] = __('Authentication failed', 'fiken');
                }
            } else {
                $data['error_warning'] = __('Authentication failed', 'fiken');
            }
            return $data;
        }


        private function getListOrders()
        {
            $data = array();
            $dt = new DateTime();

            // Every request value below ends up in SQL and in links on the page,
            // so only well-formed values are accepted; anything else falls back to the default.
            $filter_date_start = self::validDate(FikenUtils::getValue('filter_date_start'), $dt->format('Y-m-d'));
            $filter_date_end = self::validDate(FikenUtils::getValue('filter_date_end'), $dt->format('Y-m-d'));
            $filter_order_status_id = max(0, intval(FikenUtils::getValue('filter_order_status_id', 0)));

            $sort = FikenUtils::getValue('sort');
            if (!FikenOrder::isSortableColumn($sort)) {
                $sort = 'posts.id';
            }

            $asc_desc = strtoupper((string)FikenUtils::getValue('asc_desc')) === 'ASC' ? 'ASC' : 'DESC';

            $page = max(1, intval(FikenUtils::getValue('cur_page', 1)));

            $data['orders'] = array();

            $filter_data = array();
            $filter_data['filter_date_start'] = $filter_date_start;
            $filter_data['filter_date_end'] = $filter_date_end;
            $filter_data['filter_order_status_id'] = $filter_order_status_id;
            $filter_data['limit'] = get_option('posts_per_page');
            $filter_data['start'] = ($page - 1) * $filter_data['limit'];
            $filter_data['sort'] = $sort;
            $filter_data['asc_desc'] = $asc_desc;

            $results = FikenOrder::getOrdersWC($filter_data);
            $order_total = FikenOrder::getTotalOrdersWC($filter_data);

            foreach ($results as $result) {
                $data['orders'][] = array(
                    'id_order' => $result['id_order'],
                    'view' => admin_url('post.php?action=edit&post=' . $result['id_order']),
                    'current_state' => $result['current_state'],
                    'status_name_fiken' => $result['status_name_fiken'],
                    'customer' => $result['customer'],
                    'total_paid' => wc_price($result['total_paid']),
                    'date_add' => substr($result['date_add'], 0, 10),
                    'date_upd' => substr($result['date_upd'], 0, 10),
                );
            }

            $url = '';

            if (FikenUtils::getIsset('filter_date_start')) {
                $url .= '&filter_date_start=' . $filter_date_start;
            }

            if (FikenUtils::getIsset('filter_date_end')) {
                $url .= '&filter_date_end=' . $filter_date_end;
            }

            if (FikenUtils::getIsset('filter_order_status_id')) {
                $url .= '&filter_order_status_id=' . $filter_order_status_id;
            }

            $pagination = new FikenPagination();
            $pagination->total = $order_total;
            $pagination->page = $page;
            $pagination->limit = get_option('posts_per_page');
            $pagination->url = admin_url('admin.php?page=fiken-plugin-page') . $url . '&asc_desc=' . $asc_desc . '&sort=' . $sort . '&cur_page={page}';
            $data['pagination'] = $pagination->render();

            if ($asc_desc == 'ASC') {
                $url .= '&asc_desc=DESC';
            } else {
                $url .= '&asc_desc=ASC';
            }

            $url .= '&cur_page=' . $page;

            $linkController = admin_url('admin.php?page=fiken-plugin-page') . $url;

            $data['sort_order'] = $linkController . '&sort=posts.id';
            $data['sort_customer'] = $linkController . '&sort=customer';
            $data['sort_customer'] = $linkController . '&sort=customer';
            $data['sort_status'] = $linkController . '&sort=current_state';
            $data['sort_total'] = $linkController . '&sort=total_paid';
            $data['sort_date_add'] = $linkController . '&sort=date_add';
            $data['sort_date_upd'] = $linkController . '&sort=date_upd';

            $data['filter_date_start'] = $filter_date_start;
            $data['filter_date_end'] = $filter_date_end;
            $data['filter_order_status_id'] = $filter_order_status_id;

            $data['sort'] = $sort;
            $data['asc_desc'] = $asc_desc;

            return $data;
        }


        public function ajaxProcessGetOrderStatusHistory()
        {
            $res = array();
            if (FikenUtils::getIsset('order_id')) {
                $fikenOrders = FikenOrder::getFikenOrderHistoryById(FikenUtils::getValue('order_id'));
                if ($fikenOrders) {
                    foreach ($fikenOrders as $fOrder) {
                        $res[] = $fOrder->asArray();
                    }
                }
            } else {
                $res['error'] = __('Order id is not set!', 'fiken');
            }
            die(json_encode($res));
        }


        /**
         * @param mixed $value
         * @param string $default
         * @return string $value if it is a real YYYY-MM-DD date, otherwise $default
         */
        private static function validDate($value, $default)
        {
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $parts = explode('-', $value);
                if (checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
                    return $value;
                }
            }
            return $default;
        }


        function ajaxProcessRegisterSale()
        {
            $res = array();
            if (FikenUtils::getIsset('order_id')) {
                // 'force' is only sent after the shop manager confirmed re-sending an order
                // that is already in Fiken.
                $force = (bool)FikenUtils::getValue('force', false);
                $result = FikenSale::registerSale(intval(FikenUtils::getValue('order_id')), -1, true, $force);
                if ($result) {
                    if (array_key_exists('error', $result)) {
                        $res = $result;
                    } else {
                        $res['success'] = sprintf(__('The transfer order id: %s successfully completed', 'fiken'), FikenUtils::getValue('order_id'));
                    }
                } else {
                    $res['error'] = __('Unknown error', 'fiken');
                }
                $fikenOrderResult = FikenOrder::getFikenOrderById(FikenUtils::getValue('order_id'));
                $res = array_merge($res, array('order' => $fikenOrderResult->asArray()));
            } else {
                $res['error'] = __('Order id not set!', 'fiken');
            }
            die(json_encode($res));
        }


    }
}