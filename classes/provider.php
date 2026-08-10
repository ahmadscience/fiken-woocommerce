<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FikenProvider')) {

    include_once FIKEN_PLUGIN_DIR . 'classes/utils.php';

    class FikenProvider
    {
        public static function getSalesKindFiken()
        {
            return array(
                array('code' => FikenUtils::CASH_SALE,        'name' => __('Cash sale', 'fiken')),
                array('code' => FikenUtils::EXTERNAL_INVOICE, 'name' => __('External invoice', 'fiken')),
            );
        }

        public static function getVatTypesFiken()
        {
            return array(
                array('code' => FikenUtils::VAT_HIGH,    'name' => FikenUtils::VAT_HIGH),
                array('code' => FikenUtils::VAT_MEDIUM,  'name' => FikenUtils::VAT_MEDIUM),
                array('code' => FikenUtils::VAT_LOW,     'name' => FikenUtils::VAT_LOW),
                array('code' => FikenUtils::VAT_EXEMPT,  'name' => FikenUtils::VAT_EXEMPT),
                array('code' => FikenUtils::VAT_OUTSIDE, 'name' => FikenUtils::VAT_OUTSIDE),
                array('code' => FikenUtils::VAT_NONE,    'name' => FikenUtils::VAT_NONE),
            );
        }

        public static function getStatesFiken($id_state = '')
        {
            global $wpdb;
            if ($id_state !== '' && $id_state !== null) {
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT id_state, name FROM {$wpdb->prefix}fiken_states WHERE id_state = %d ORDER BY id_state",
                    (int) $id_state
                ), ARRAY_A);
            }
            return $wpdb->get_results("SELECT id_state, name FROM {$wpdb->prefix}fiken_states ORDER BY id_state", ARRAY_A);
        }

        public static function getListPayWC()
        {
            $res = array();
            if (!function_exists('WC')) { return $res; }
            $gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
            foreach ($gateways as $gateway) {
                if ($gateway->enabled === 'yes') {
                    $res[] = array('id' => $gateway->id, 'name' => $gateway->title);
                }
            }
            $res[] = array('id' => 'free_order', 'name' => __('Free order', 'fiken'));
            return $res;
        }

        public static function getOrderStatesWC()
        {
            $res = array();
            foreach (wc_get_order_statuses() as $key => $name) {
                $res[] = array('id' => $key, 'name' => $name);
            }
            return $res;
        }

        public static function getTaxesWC()
        {
            global $wpdb;
            $ret = array();
            $result = $wpdb->get_results(
                "SELECT tax_rate_id, tax_rate_name, tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates",
                ARRAY_A
            );
            foreach ((array) $result as $item) {
                $ret[] = array(
                    'id'   => (int) $item['tax_rate_id'],
                    'name' => $item['tax_rate_name'] . ' (' . number_format((float) $item['tax_rate'], 2, ',', '') . ' %)',
                    'rate' => (float) $item['tax_rate'],
                );
            }
            return $ret;
        }

        public static function getVatCodeFromItem($item)
        {
            $res   = FikenUtils::VAT_NONE;
            $taxes = $item->get_taxes();
            if (empty($taxes) || empty($taxes['total'])) { return $res; }
            $vatSettings = json_decode(get_option(FikenUtils::CONF_FIKEN_VATS_MAPPING));
            foreach ($taxes['total'] as $taxCode => $taxValue) {
                if (empty($taxValue)) { continue; }
                if (isset($vatSettings->{$taxCode}->{FikenUtils::CTRL_NAME_VAT})
                    && $vatSettings->{$taxCode}->{FikenUtils::CTRL_NAME_VAT}) {
                    $res = $vatSettings->{$taxCode}->{FikenUtils::CTRL_NAME_VAT};
                }
                break;
            }
            return $res;
        }

        public static function calculateVatCode($cost, $vat)
        {
            $cost = (float) $cost;
            if ($cost == 0.0) {
                throw new Exception(__('Cannot calculate VAT code: shipping cost is zero.', 'fiken'));
            }
            $vatRate = 100 * (float) $vat / $cost;
            foreach (self::getTaxesWC() as $tax) {
                if (abs($tax['rate'] - $vatRate) < 0.01) {
                    return self::getVatCodeByTaxCode($tax['id']);
                }
            }
            throw new Exception(sprintf(__('No WooCommerce tax rate matches calculated rate %0.2f', 'fiken'), $vatRate));
        }

        public static function getVatCodeByTaxCode($taxCode)
        {
            $res = FikenUtils::VAT_NONE;
            if (empty($taxCode)) { return $res; }
            $vatSettings = json_decode(get_option(FikenUtils::CONF_FIKEN_VATS_MAPPING));
            if (isset($vatSettings->{$taxCode}->{FikenUtils::CTRL_NAME_VAT})
                && $vatSettings->{$taxCode}->{FikenUtils::CTRL_NAME_VAT}) {
                $res = $vatSettings->{$taxCode}->{FikenUtils::CTRL_NAME_VAT};
            }
            return $res;
        }

        public static function getInvoiceNumber($order)
        {
            $creditNote = $order->get_meta('_wcpdf_credit_note_number');
            if (!empty($creditNote)) { return $creditNote; }
            $invoiceNumber = $order->get_meta('_wcpdf_invoice_number');
            if (!empty($invoiceNumber)) { return $invoiceNumber; }
            return '';
        }
    }
}
