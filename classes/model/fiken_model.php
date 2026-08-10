<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FikenModel')) {

    include_once FIKEN_PLUGIN_DIR . 'classes/utils.php';

    #[\AllowDynamicProperties]
    abstract class FikenModel
    {
        abstract protected function getDef();

        public function asArray()
        {
            $res = array();
            foreach ($this->getDef() as $item) {
                if (!property_exists($this, $item)) {
                    continue;
                }
                $value = $this->{$item};
                if ($value === null || $value === '' || $value === array()) {
                    continue;
                }
                $res[$item] = $value;
            }
            return $res;
        }
    }
}
