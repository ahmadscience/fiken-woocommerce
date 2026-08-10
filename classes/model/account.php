<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FikenAccount')) {

    include_once FIKEN_PLUGIN_DIR . 'classes/model/fiken_model.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/api/client.php';

    class FikenAccount extends FikenModel
    {
        public $code = '';
        public $name = '';

        public function __construct($code = '', $name = '')
        {
            $this->code = (string) $code;
            $this->name = (string) $name;
        }

        protected function getDef()
        {
            return array('code', 'name');
        }

        public function getCode()      { return $this->code; }
        public function setCode($v)    { $this->code = (string) $v; }
        public function getName()      { return $this->name; }
        public function setName($v)    { $this->name = (string) $v; }

        public static function getAccountsFromFiken($companySlug, $accFilter = '', $year = 0)
        {
            $res = array();
            $res[] = new FikenAccount('0', __('Empty account', 'fiken'));

            if (empty($companySlug)) { return $res; }
            if ($year == 0) { $year = (int) date('Y'); }

            $client = new FikenApiClient();
            if (!$client->hasToken()) { return $res; }

            $r = $client->getAccounts($companySlug, $year);
            if (!$r['ok'] || !is_array($r['body'])) { return $res; }

            foreach ($r['body'] as $data) {
                if (!is_array($data) || empty($data['code'])) { continue; }
                if ($accFilter) {
                    $match = @preg_match($accFilter, $data['code']);
                    if ($match !== 1) { continue; }
                }
                $res[] = new FikenAccount($data['code'], isset($data['name']) ? $data['name'] : '');
            }

            usort($res, function ($a, $b) { return strcmp($a->getCode(), $b->getCode()); });
            return $res;
        }
    }
}
