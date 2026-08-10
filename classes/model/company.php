<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FikenCompany')) {

    include_once FIKEN_PLUGIN_DIR . 'classes/model/fiken_model.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/api/client.php';

    class FikenCompany extends FikenModel
    {
        public $slug = '';
        public $name = '';
        public $organizationNumber = '';

        public function __construct($slug = '', $name = '', $organizationNumber = '')
        {
            $this->slug               = (string) $slug;
            $this->name               = (string) $name;
            $this->organizationNumber = (string) $organizationNumber;
        }

        protected function getDef()
        {
            return array('slug', 'name', 'organizationNumber');
        }

        public function getSlug()                 { return $this->slug; }
        public function setSlug($v)               { $this->slug = (string) $v; }
        public function getName()                 { return $this->name; }
        public function setName($v)               { $this->name = (string) $v; }
        public function getOrganizationNumber()   { return $this->organizationNumber; }
        public function setOrganizationNumber($v) { $this->organizationNumber = (string) $v; }

        public static function fromArray(array $data)
        {
            return new self(
                isset($data['slug']) ? $data['slug'] : '',
                isset($data['name']) ? $data['name'] : '',
                isset($data['organizationNumber']) ? $data['organizationNumber'] : ''
            );
        }

        public static function getCompaniesFromFiken()
        {
            $res = array();
            $client = new FikenApiClient();
            if (!$client->hasToken()) { return $res; }
            $r = $client->getCompanies();
            if (!$r['ok'] || !is_array($r['body'])) { return $res; }
            foreach ($r['body'] as $row) {
                if (is_array($row)) { $res[] = self::fromArray($row); }
            }
            usort($res, function ($a, $b) { return strcasecmp($a->getName(), $b->getName()); });
            return $res;
        }

        public static function getCompanyBySlug($slug)
        {
            if (empty($slug)) { return false; }
            $client = new FikenApiClient();
            if (!$client->hasToken()) { return false; }
            $r = $client->getCompany($slug);
            if (!$r['ok'] || !is_array($r['body'])) { return false; }
            return self::fromArray($r['body']);
        }
    }
}
