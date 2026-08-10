<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FikenSale')) {

    include_once FIKEN_PLUGIN_DIR . 'classes/model/fiken_model.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/model/customer.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/model/company.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/model/fiken_order.php';
    include_once FIKEN_PLUGIN_DIR . 'classes/api/client.php';
    include_once FIKEN_PLUGIN_DIR . 'pdf/class.pdf.php';

    class FikenSale extends FikenModel
    {
        public $saleNumber = '';
        public $date = '';
        public $kind = '';
        public $paymentAccount = '';
        public $paymentDate = '';
        public $customerId = 0;
        public $lines = array();

        public function setSaleNumber($v)    { $this->saleNumber = (string) $v; }
        public function getSaleNumber()      { return $this->saleNumber; }
        public function setDate($v)          { $this->date = (string) $v; }
        public function getDate()            { return $this->date; }
        public function setKind($v)          { $this->kind = (string) $v; }
        public function getKind()            { return $this->kind; }
        public function setPaymentAccount($v) { $this->paymentAccount = (string) $v; }
        public function getPaymentAccount()  { return $this->paymentAccount; }
        public function setPaymentDate($v)   { $this->paymentDate = (string) $v; }
        public function getPaymentDate()     { return $this->paymentDate; }
        public function setCustomerId($v)    { $this->customerId = (int) $v; }
        public function getCustomerId()      { return $this->customerId; }
        public function setLines(array $v)   { $this->lines = $v; }
        public function getLines()           { return $this->lines; }

        protected function getDef()
        {
            $def = array('saleNumber', 'date', 'kind', 'lines');
            if ($this->paymentAccount !== '') { $def[] = 'paymentAccount'; }
            if ($this->paymentDate    !== '') { $def[] = 'paymentDate'; }
            if ($this->customerId     >  0)  { $def[] = 'customerId'; }
            return $def;
        }

        public static function registerSale($order_id, $ajax = false)
        {
            $order_id = (int) $order_id;
            try {
                $order = wc_get_order($order_id);
                if (!$order) { throw new Exception(sprintf(__('Order id: %s not found!', 'fiken'), $order_id)); }

                $wcOrderPaymentMethod = $order->get_payment_method();
                $wcOrderStatus        = 'wc-' . $order->get_status();

                if (!$ajax) {
                    $fikenOrder = new FikenOrder($order_id, '1');
                    $fikenOrder->update();
                }

                $companySlug = get_option(FikenUtils::CONF_FIKEN_COMPANY);
                if (empty($companySlug)) { throw new Exception(__('Fiken company is not configured.', 'fiken')); }
                $company = FikenCompany::getCompanyBySlug($companySlug);
                if (!$company) { throw new Exception(__('Default company not found!', 'fiken')); }

                $payMethodSettings = json_decode(get_option(FikenUtils::CONF_FIKEN_PAY_METHODS));
                $payM = $wcOrderPaymentMethod ? $wcOrderPaymentMethod : 'free_order';
                $expectedStatus = isset($payMethodSettings->{$payM}->{FikenUtils::CTRL_NAME_PAY_STATUS})
                    ? $payMethodSettings->{$payM}->{FikenUtils::CTRL_NAME_PAY_STATUS} : false;

                if (!$expectedStatus) {
                    throw new Exception(sprintf(__('Order status for payment module "%s" not set!', 'fiken'), $payM));
                }
                if ($expectedStatus !== $wcOrderStatus) {
                    return $ajax
                        ? array('error' => sprintf(__('Order id: %s not in the expected state!', 'fiken'), $order_id))
                        : false;
                }

                try {
                    $resStatus  = '2';
                    $newSaleId  = self::registerSaleInner($order, $company, $payMethodSettings->{$payM}, $resStatus);
                    if ($newSaleId) {
                        $fikenOrder = new FikenOrder($order_id, $resStatus);
                        $fikenOrder->update();
                        try {
                            if (get_option(FikenUtils::CONF_FIKEN_PDF_INV)) {
                                self::uploadInvoicePdf($company, $newSaleId, $order);
                            }
                            return $ajax
                                ? array('success' => sprintf(__('The transfer order id: %s successfully completed', 'fiken'), $order_id))
                                : true;
                        } catch (Exception $exception) {
                            FikenUtils::log($exception->getMessage(), 'ERROR Add Attachment', FikenUtils::LOG_LEVEL_ERROR);
                            return $ajax ? array('error' => $exception->getMessage()) : false;
                        }
                    }
                } catch (Exception $exception) {
                    $errStatus  = ($exception->getCode() === 0) ? 3 : $exception->getCode();
                    $fikenOrder = new FikenOrder($order_id, $errStatus, $exception->getMessage());
                    $fikenOrder->update();
                    FikenUtils::log($exception->getMessage(), 'ERROR Register Sale', FikenUtils::LOG_LEVEL_ERROR);
                    return $ajax ? array('error' => $exception->getMessage()) : false;
                }
            } catch (Exception $exception) {
                FikenUtils::log($exception->getMessage(), 'ERROR Register Sale', FikenUtils::LOG_LEVEL_ERROR);
                return $ajax ? array('error' => $exception->getMessage()) : false;
            }
            return true;
        }

        private static function registerSaleInner($order, FikenCompany $company, $payMethodCurrentSettings, &$resStatus)
        {
            $wcOrderId            = $order->get_id();
            $wcOrderPaymentMethod = $order->get_payment_method();
            $dateCreated          = $order->get_date_created();
            $wcOrderDate          = $dateCreated ? $dateCreated->date('Y-m-d') : gmdate('Y-m-d');

            $acc = isset($payMethodCurrentSettings->{FikenUtils::CTRL_NAME_ACCOUNT})
                ? $payMethodCurrentSettings->{FikenUtils::CTRL_NAME_ACCOUNT} : '';
            if ($acc === '') {
                throw new Exception(sprintf(__('Account for payment extension "%s" not found!', 'fiken'), $wcOrderPaymentMethod));
            }

            $saleKind = isset($payMethodCurrentSettings->{FikenUtils::CTRL_NAME_SALE_KIND})
                ? $payMethodCurrentSettings->{FikenUtils::CTRL_NAME_SALE_KIND} : false;
            if (!$saleKind) {
                throw new Exception(sprintf(__('Sale type for payment extension "%s" not found!', 'fiken'), $wcOrderPaymentMethod));
            }

            $sale = new FikenSale();
            $sale->setDate($wcOrderDate);
            $invNumber = FikenProvider::getInvoiceNumber($order);
            $sale->setSaleNumber(!empty($invNumber) ? (string) $invNumber : (string) $order->get_order_number());

            $lines = array();

            foreach ($order->get_items() as $item) {
                if ('yes' === get_option('woocommerce_tax_round_at_subtotal')) {
                    $netPrice = (float) $item->get_total();
                    $vat      = (float) $item->get_total_tax();
                } else {
                    $netPrice = wc_round_tax_total($item->get_total());
                    $vat      = wc_round_tax_total($item->get_total_tax());
                }
                $line = array(
                    'description' => $item->get_quantity() . ' x ' . $item->get_name(),
                    'netPrice'    => $netPrice,
                    'vat'         => $vat,
                    'vatType'     => FikenProvider::getVatCodeFromItem($item),
                );
                if ($line['vatType'] === FikenUtils::VAT_MEDIUM) {
                    $line['incomeAccount'] = '3040';
                }
                $lines[] = $line;
            }

            foreach ($order->get_shipping_methods() as $shipping_item) {
                $cost = (float) $shipping_item->get_total();
                if ($cost > 0) {
                    $shipVat     = 0.0;
                    $shipVatType = FikenUtils::VAT_NONE;
                    $taxes       = $shipping_item->get_taxes();
                    if (!empty($taxes) && !empty($taxes['total'])) {
                        foreach ($taxes['total'] as $taxValue) {
                            if (!empty($taxValue)) {
                                $taxTotalValue = (float) $taxValue;
                                $shipVat       = wc_round_tax_total($taxTotalValue);
                                $shipVatType   = FikenProvider::calculateVatCode($cost, $taxTotalValue);
                                break;
                            }
                        }
                    }
                    $lines[] = array(
                        'description' => $shipping_item->get_name(),
                        'netPrice'    => wc_round_tax_total($cost),
                        'vat'         => $shipVat,
                        'vatType'     => (($shipVat > 0) && ($shipVatType === FikenUtils::VAT_NONE)) ? FikenUtils::VAT_HIGH : $shipVatType,
                    );
                }
            }

            foreach ($order->get_fees() as $fee_item) {
                $feeTotal = (float) $fee_item->get_total();
                if ($feeTotal) {
                    $totalTax = wc_round_tax_total((float) $fee_item->get_total_tax());
                    $lines[] = array(
                        'description' => $fee_item->get_name(),
                        'netPrice'    => wc_round_tax_total($feeTotal),
                        'vat'         => $totalTax,
                        'vatType'     => ($totalTax > 0 ? FikenUtils::VAT_HIGH : FikenUtils::VAT_NONE),
                    );
                }
            }

            if (FikenUtils::SKIP_EMPTY_PRICE) {
                $sl = array();
                foreach ($lines as $line) {
                    if ((int) $line['netPrice'] === 0) { $resStatus = FikenUtils::EMPTY_PRICE_PARTIAL_ERROR_CODE; }
                    else                                { $sl[] = $line; }
                }
                $lines = $sl;
                if (empty($lines)) {
                    throw new Exception(sprintf(__('Can not register order id: %s ("amount=0")', 'fiken'), $wcOrderId), FikenUtils::EMPTY_PRICE_ERROR_CODE);
                }
            }

            $packedLines = array(
                FikenUtils::VAT_HIGH => array(), FikenUtils::VAT_MEDIUM => array(),
                FikenUtils::VAT_LOW => array(), FikenUtils::VAT_NONE => array(),
                FikenUtils::VAT_EXEMPT => array(), FikenUtils::VAT_OUTSIDE => array(),
            );
            $vatCaptions = FikenUtils::getVatCaptions();
            foreach ($lines as $line) {
                if (!isset($packedLines[$line['vatType']]['description'])) {
                    $packedLines[$line['vatType']] = array(
                        'description' => $vatCaptions[$line['vatType']],
                        'netPrice' => 0, 'vat' => 0, 'vatType' => $line['vatType'],
                    );
                }
                $packedLines[$line['vatType']]['netPrice'] += $line['netPrice'];
                $packedLines[$line['vatType']]['vat']      += $line['vat'];
                if (isset($line['incomeAccount'])) {
                    $packedLines[$line['vatType']]['incomeAccount'] = $line['incomeAccount'];
                }
            }
            $packedLines = array_values(array_filter($packedLines));

            $wireLines = array();
            foreach ($packedLines as $line) {
                $wire = array(
                    'description'        => $line['description'],
                    'netPriceInCurrency' => FikenUtils::moneyToCent($line['netPrice']),
                    'vatInCurrency'      => FikenUtils::moneyToCent($line['vat']),
                    'vatType'            => $line['vatType'],
                );
                if (!empty($line['incomeAccount'])) { $wire['incomeAccount'] = $line['incomeAccount']; }
                $wireLines[] = $wire;
            }

            $sale->setLines($wireLines);
            $sale->setKind($saleKind);
            $sale->setPaymentAccount($acc);
            $sale->setPaymentDate($sale->getDate());

            if ($sale->getKind() === FikenUtils::EXTERNAL_INVOICE) {
                if ($acc === '0') {
                    $sale->setPaymentAccount('');
                    $sale->setPaymentDate('');
                }
                $customer = FikenCustomer::getCustomerFromFiken($order->get_billing_email(), $company);
                if (!$customer) {
                    $customer   = FikenCustomer::getCustomerFromOrderWC($order);
                    $customerId = $customer->register($company, $wcOrderId);
                    if (!$customerId) {
                        throw new Exception(sprintf(__('Can not add customer from order id=%s !', 'fiken'), $wcOrderId));
                    }
                }
                $sale->setCustomerId($customer->getContactId());
            }

            FikenUtils::log(wp_json_encode($sale->asArray()), 'FIKEN CREATE SALE', FikenUtils::LOG_LEVEL_INFO);

            $client = new FikenApiClient();
            $r = $client->createSale($company->getSlug(), $sale->asArray());
            if (!$r['ok']) {
                throw new Exception(sprintf(__('Can not register order id: %s :: %s', 'fiken'), $wcOrderId, $r['error']));
            }
            $newSaleId = FikenApiClient::idFromLocation($r['location']);
            if (!$newSaleId && is_array($r['body']) && isset($r['body']['saleId'])) {
                $newSaleId = (int) $r['body']['saleId'];
            }
            if (!$newSaleId) {
                throw new Exception(sprintf(__('Fiken did not return a sale id for order %s', 'fiken'), $wcOrderId));
            }
            return $newSaleId;
        }

        private static function uploadInvoicePdf(FikenCompany $company, $saleId, $order)
        {
            $pdf         = new FikenPDF($order);
            $target_base = wp_upload_dir();
            $filename    = trailingslashit($target_base['basedir']) . 'fiken_' . $order->get_id() . '_' . uniqid() . '.pdf';

            if (!$pdf->generate_pdf($filename)) {
                throw new Exception(sprintf(__('Could not generate PDF for order %s', 'fiken'), $order->get_id()));
            }

            $client = new FikenApiClient();
            $attachName = 'inv_' . $order->get_id() . '.pdf';
            $r = $client->uploadAttachment($company->getSlug(), $saleId, $filename, $attachName, true, true);
            @unlink($filename);

            if (!$r['ok']) {
                throw new Exception(sprintf(__('Error attaching invoice for order id=%s : %s', 'fiken'), $order->get_id(), $r['error']));
            }
        }
    }
}
