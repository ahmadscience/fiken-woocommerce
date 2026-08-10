<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FikenCustomer')) {

    include_once FIKEN_PLUGIN_DIR . 'classes/model/fiken_model.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/api/client.php';

    class FikenCustomer extends FikenModel
    {
        public $contactId = 0;
        public $email = '';
        public $name = '';
        public $address = array();
        public $organizationNumber = '';
        public $customer = true;

        public function __construct($contactId = 0, $name = '', $email = '', array $address = array(), $organizationNumber = '')
        {
            $this->contactId          = (int) $contactId;
            $this->name               = (string) $name;
            $this->email              = (string) $email;
            $this->address            = $address;
            $this->organizationNumber = (string) $organizationNumber;
        }

        public function getContactId()            { return $this->contactId; }
        public function setContactId($v)          { $this->contactId = (int) $v; }
        public function getName()                 { return $this->name; }
        public function setName($v)               { $this->name = (string) $v; }
        public function getEmail()                { return $this->email; }
        public function setEmail($v)              { $this->email = (string) $v; }
        public function getAddress()              { return $this->address; }
        public function setAddress(array $v)      { $this->address = $v; }
        public function getOrganizationNumber()   { return $this->organizationNumber; }
        public function setOrganizationNumber($v) { $this->organizationNumber = (string) $v; }

        protected function getDef()
        {
            return array('name', 'email', 'address', 'organizationNumber', 'customer');
        }

        public static function getCustomerFromFiken($email, FikenCompany $company)
        {
            if (empty($email) || empty($company->getSlug())) { return false; }
            $client = new FikenApiClient();
            $r = $client->getContactByEmail($company->getSlug(), $email);
            if (!$r['ok'] || !is_array($r['body'])) { return false; }
            foreach ($r['body'] as $row) {
                if (!is_array($row) || empty($row['email'])) { continue; }
                if (strcasecmp($row['email'], $email) === 0) {
                    $id = isset($row['contactId']) ? (int) $row['contactId'] : 0;
                    return new self(
                        $id,
                        isset($row['name']) ? $row['name'] : '',
                        $row['email'],
                        isset($row['address']) && is_array($row['address']) ? $row['address'] : array(),
                        isset($row['organizationNumber']) ? $row['organizationNumber'] : ''
                    );
                }
            }
            return false;
        }

        public static function getCustomerFromOrderWC($order)
        {
            $c = new self();
            $c->setName(trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()));
            $c->setEmail($order->get_billing_email());

            if ($order->get_billing_company()) {
                $c->setOrganizationNumber($order->get_billing_company());
            }

            $address = array();
            $street  = trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2());
            if ($street !== '') { $address['streetAddress'] = $street; }
            if ($order->get_billing_postcode()) { $address['postalCode'] = $order->get_billing_postcode(); }
            if ($order->get_billing_city())     { $address['city']       = $order->get_billing_city(); }
            $country = $order->get_billing_country();
            if ($country) {
                $address['country'] = isset(WC()->countries->countries[$country])
                    ? WC()->countries->countries[$country]
                    : $country;
            }
            if (!empty($address)) { $c->setAddress($address); }
            return $c;
        }

        public function register(FikenCompany $company, $wcOrderId)
        {
            if (empty($this->getName()) || empty($this->getEmail())) {
                throw new Exception(sprintf(__('Customer name or email not set for order id: %s !', 'fiken'), $wcOrderId));
            }
            $client = new FikenApiClient();
            $r = $client->createContact($company->getSlug(), $this->asArray());
            if (!$r['ok']) {
                throw new Exception(sprintf(__('Can not add customer from order id: %s !', 'fiken'), $wcOrderId) . ' :: ' . $r['error']);
            }
            $id = FikenApiClient::idFromLocation($r['location']);
            $this->setContactId($id);
            return $id;
        }
    }
}
