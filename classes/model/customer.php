<?php

if ( ! class_exists( 'FikenCustomer' ) ) {

    include_once FIKEN_PLUGIN_DIR . 'classes/model/fiken_model.php';

    class FikenCustomer extends FikenModel
    {

        public $relSelf;
        public $email;
        public $name;
        /**
         * @var $address array
         */
        public $address;
        public $organizationIdentifier;
        public $customer = true; //needed for fiken serv

        function __construct($relSelf = '', $name = '', $email = '', $address = array(), $organizationIdentifier = '')
        {
            $this->name = $name;
            $this->email = $email;
            $this->relSelf = $relSelf;
            $this->address = $address;
            $this->organizationIdentifier = $organizationIdentifier;
        }


        /**
         * @param array $address
         */
        public function setAddress($address)
        {
            $this->address = $address;
        }

        /**
         * @return array
         */
        public function getAddress()
        {
            return $this->address;
        }

        /**
         * @param mixed $name
         */
        public function setName($name)
        {
            $this->name = $name;
        }

        /**
         * @return mixed
         */
        public function getName()
        {
            return $this->name;
        }

        /**
         * @return mixed
         */
        public function getEmail()
        {
            return $this->email;
        }

        /**
         * @param mixed $email
         */
        public function setEmail($email)
        {
            $this->email = $email;
        }


        /**
         * @param mixed $relSelf
         */
        public function setRelSelf($relSelf)
        {
            $this->relSelf = $relSelf;
        }

        /**
         * @return mixed
         */
        public function getRelSelf()
        {
            return $this->relSelf;
        }

        /**
         * @return mixed
         */
        public function getOrganizationIdentifier()
        {
            return $this->organizationIdentifier;
        }

        /**
         * @param mixed $organizationIdentifier
         */
        public function setOrganizationIdentifier($organizationIdentifier)
        {
            $this->organizationIdentifier = $organizationIdentifier;
        }

        protected function getDef()
        {
            return array('name', 'email', 'address', 'organizationIdentifier', 'customer');
        }

        /**
         * @param $email
         * @param $company FikenCompany
         * @return FikenCustomer|bool
         */
        public static function getCustomerFromFiken($email, $company)
        {
            /** @var $res FikenCustomer|bool */
            $res = false;
            // Without an email we would match any Fiken contact that has no email either.
            if (trim((string)$email) === '') {
                return false;
            }
            $result = FikenUtils::call($company->getRelContacts());
            if (isset($result['body']) && $result['body']) {
                $hal = FikenHal::fromJson($result['body'], 1);
                $resources = $hal->getResources();
                if (isset($resources) && $resources) {
                    if (array_key_exists(FikenUtils::FIKEN_BASE_URL . "/rel/contacts", $resources)) {
                        $contacts = $resources[FikenUtils::FIKEN_BASE_URL . "/rel/contacts"];
                        foreach ($contacts as $contact) {
                            $data = $contact->getData();
                            if (array_key_exists('email', $data) && strtoupper(trim((string)$data['email'])) == strtoupper(trim((string)$email))) {
                                $res = new FikenCustomer($contact->getUri(),
                                    isset($data['name']) ? $data['name'] : '',
                                    $data['email'],
                                    isset($data['address']) ? $data['address'] : array(),
                                    '');
                            }
                        }
                    }
                }
            }
            return $res;
        }


        /**
         * @param $order_info
         * @return FikenCustomer
         */

        public static function getCustomerFromOrderWC($order)
        {
            $fikenCustomer = new FikenCustomer();
            $fikenCustomer->setName(trim(FikenUtils::orderBilling($order, 'first_name') . " " . FikenUtils::orderBilling($order, 'last_name')));
            $fikenCustomer->setEmail(FikenUtils::orderBilling($order, 'email'));

            $orgNumber = FikenUtils::normalizeOrgNumber(FikenUtils::orderBilling($order, 'company'));
            if ($orgNumber) {
                $fikenCustomer->setOrganizationIdentifier($orgNumber);
            }

            $address = array();

            $postcode = FikenUtils::orderBilling($order, 'postcode');
            if ($postcode) {
                $address['postalCode'] = $postcode;
            }
            $city = FikenUtils::orderBilling($order, 'city');
            if ($city) {
                $address['postalPlace'] = $city;
            }
            $address1 = FikenUtils::orderBilling($order, 'address_1');
            if ($address1) {
                $address['address1'] = $address1;
            }
            $address2 = FikenUtils::orderBilling($order, 'address_2');
            if ($address2) {
                $address['address2'] = $address2;
            }
            $country = FikenUtils::orderBilling($order, 'country');
            if ($country) {
                $full_country = (isset(WC()->countries->countries[$country]))
                    ? WC()->countries->countries[$country]
                    : $country;
                $address['country'] = $full_country;
            }
            if ($address) {
                $fikenCustomer->setAddress($address);
            }
            return $fikenCustomer;
        }

        /**
         * @param $company
         * @param $idOrder
         * @return mixed  (location url)
         * @throws Exception
         */
        public function register($company, $idOrder)
        {
            if (!$this->getName() || !$this->getEmail()) {
                throw new Exception(sprintf(__('Customer name or email not set for order id: %s !', 'fiken'), $idOrder));
            }
            $res = FikenUtils::call($company->getRelContacts(), $this->asArray());
            if ($res === false) {
                throw new Exception(sprintf(__('Can not add customer from order id: %s !', 'fiken'), $idOrder));
            } else {
                return $res['location'];
            }
        }

    }
}