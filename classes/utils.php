<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FikenUtils')) {

    class FikenUtils
    {
        const ACC_FILTER = "/^19[26]0:/";

        const EXTERNAL_INVOICE = "EXTERNAL_INVOICE";
        const CASH_SALE = "CASH_SALE";

        const VAT_HIGH = "HIGH";
        const VAT_MEDIUM = "MEDIUM";
        const VAT_LOW = "LOW";
        const VAT_EXEMPT = "EXEMPT";
        const VAT_OUTSIDE = "OUTSIDE";
        const VAT_NONE = "NONE";

        const SKIP_EMPTY_PRICE = true;
        const EMPTY_PRICE_ERROR_CODE = 4;
        const EMPTY_PRICE_PARTIAL_ERROR_CODE = 5;

        const LOG_LEVEL_INFO = 1;
        const LOG_LEVEL_WARNING = 2;
        const LOG_LEVEL_ERROR = 3;
        const LOG_LEVEL_CRASH = 4;

        const DEFAULT_LOG_LEVEL = self::LOG_LEVEL_ERROR;

        const CONF_FIKEN_TOKEN         = 'fiken_v2_token';
        const CONF_FIKEN_COMPANY       = 'fiken_v2_company_slug';
        const CONF_FIKEN_PAY_METHODS   = 'fiken_v2_pay_methods';
        const CONF_FIKEN_VATS_MAPPING  = 'fiken_v2_vats_mapping';
        const CONF_FIKEN_PDF_INV       = 'fiken_v2_pdf_inv';
        const CONF_FIKEN_DEBUG_MODE    = 'fiken_v2_debug_mode';

        const CONF_V1_LOGIN    = 'fiken_v1_login';
        const CONF_V1_PASSWORD = 'fiken_v1_password';
        const CONF_V1_COMPANY  = 'fiken_v1_company';

        const CTRL_NAME_ACCOUNT    = 'fiken_account_';
        const CTRL_NAME_PAY_STATUS = 'fiken_pay_status_';
        const CTRL_NAME_SALE_KIND  = 'fiken_sale_kind_';
        const CTRL_NAME_VAT        = 'fiken_vat_';
        const CTRL_NAME_TOKEN      = 'fiken_token';
        const CTRL_NAME_COMPANY    = 'fiken_company';
        const CTRL_NAME_PDF_INV    = 'fiken_pdf_inv';
        const CTRL_NAME_DEBUG_MODE = 'fiken_debug_mode';

        public static function getVatCaptions()
        {
            return array(
                self::VAT_HIGH    => 'Ordrelinjer høy mva. sats',
                self::VAT_MEDIUM  => 'Ordrelinjer middels mva. sats',
                self::VAT_LOW     => 'Ordrelinjer lav mva. sats',
                self::VAT_NONE    => 'Ordrelinjer',
                self::VAT_EXEMPT  => 'Ordrelinjer fritatt for mva.',
                self::VAT_OUTSIDE => 'Ordrelinjer utenfor avgiftsområdet',
            );
        }

        public static function log($mes, $title = '', $severity = self::LOG_LEVEL_INFO)
        {
            $debug_mode = get_option(self::CONF_FIKEN_DEBUG_MODE);
            if ($debug_mode || (int) $severity >= (int) self::DEFAULT_LOG_LEVEL) {
                if (function_exists('wc_get_logger')) {
                    $logger = wc_get_logger();
                    $logger->log('debug', $title . ' :: ' . (is_scalar($mes) ? $mes : wp_json_encode($mes)), array('source' => 'fiken'));
                }
            }
        }

        public static function moneyToCent($value)
        {
            return (int) round($value * 100);
        }

        public static function getStatesFromSettings()
        {
            $res = array();
            $methods = json_decode(get_option(self::CONF_FIKEN_PAY_METHODS));
            if (isset($methods) && $methods) {
                foreach ($methods as $key => $value) {
                    if (isset($value->{self::CTRL_NAME_PAY_STATUS}) && !in_array((string) $value->{self::CTRL_NAME_PAY_STATUS}, $res, true)) {
                        $res[] = (string) $value->{self::CTRL_NAME_PAY_STATUS};
                    }
                }
            }
            return $res;
        }

        private static function getPostValueByPartialKey($keyPartial)
        {
            if (empty($keyPartial) || !is_string($keyPartial)) { return array(); }
            $res = array();
            foreach ($_POST as $key => $value) {
                if (strpos($key, $keyPartial) !== false) {
                    $res[$key] = is_string($value) ? sanitize_text_field(wp_unslash($value)) : $value;
                }
            }
            return $res;
        }

        private static function parsePostControlData($keyPartial, $data)
        {
            $res = array();
            foreach ($data as $key => $value) {
                $code = explode($keyPartial, $key);
                if (count($code) == 2) {
                    $res[$code[1]] = array($keyPartial => $value);
                }
            }
            return $res;
        }

        public static function parseDataFromPost($controlNamesPartial)
        {
            $res = array();
            $data = array();
            if (!is_array($controlNamesPartial)) { $controlNamesPartial = array($controlNamesPartial); }
            foreach ($controlNamesPartial as $item) {
                $data[$item] = self::parsePostControlData($item, self::getPostValueByPartialKey($item));
            }
            foreach ($data as $value) {
                foreach ($value as $key_1 => $value_1) {
                    $res[$key_1] = array_merge(isset($res[$key_1]) ? $res[$key_1] : array(), $value_1);
                }
            }
            return $res;
        }

        public static function getIsset($key)
        {
            if (empty($key) || !is_string($key)) { return false; }
            return isset($_POST[$key]) || isset($_GET[$key]);
        }

        public static function getValue($key, $default_value = '')
        {
            if (empty($key) || !is_string($key)) { return $default_value; }
            if (isset($_POST[$key]))       { $raw = $_POST[$key]; }
            elseif (isset($_GET[$key]))    { $raw = $_GET[$key]; }
            else                           { return $default_value; }
            if (is_string($raw)) {
                $raw = wp_unslash($raw);
                $raw = str_replace("\0", '', $raw);
            }
            return $raw;
        }

        public static function getString($key, $default_value = '')
        {
            $v = self::getValue($key, $default_value);
            return is_string($v) ? sanitize_text_field($v) : $default_value;
        }

        public static function getInt($key, $default_value = 0)
        {
            $v = self::getValue($key, $default_value);
            return is_scalar($v) ? absint($v) : (int) $default_value;
        }

        public static function getKey($key, $default_value = '')
        {
            $v = self::getValue($key, $default_value);
            return is_string($v) ? sanitize_key($v) : $default_value;
        }
    }
}
