<?php

if (!class_exists('FikenPDF')) {

    include_once FIKEN_PLUGIN_DIR . 'pdf/html_invoice.php';

    class FikenPDF
    {
        private $order;

        public function __construct($order)
        {
            $this->order = $order;
        }


        private function get_order_items()
        {
            $items = $this->order->get_items();
            $data_list = array();

            if (sizeof($items) > 0) {
                foreach ($items as $item) {
                    $data = array();
                    $data['product_id'] = $item['product_id'];
                    $data['variation_id'] = $item['variation_id'];
                    $data['name'] = $item['name'];
                    $data['quantity'] = $item['qty'];
                    $quantity_divider = ($item['qty'] == 0) ? 1 : $item['qty'];
                    $data['line_total'] = wc_price($item['line_total']);
                    $data['single_line_total'] = wc_price($item['line_total'] / $quantity_divider);
                    $data['line_tax'] = wc_price($item['line_tax']);
                    $data['single_line_tax'] = wc_price($item['line_tax'] / $quantity_divider);
                    $data['line_subtotal'] = wc_price($item['line_subtotal']);
                    $data['line_subtotal_tax'] = wc_price($item['line_subtotal_tax']);
                    $data['ex_price'] = $this->get_formatted_item_price($item, 'total', 'excl');
                    $data['price'] = $this->get_formatted_item_price($item, 'total');
                    $data['order_price'] = $this->order->get_formatted_line_subtotal($item);
                    $data['ex_single_price'] = $this->get_formatted_item_price($item, 'single', 'excl');
                    $data['single_price'] = $this->get_formatted_item_price($item, 'single');
                    $data['meta'] = $this->get_item_meta_html($item);
                    $data['item'] = $item;
                    $data['product'] = null;
                    $product = is_object($item) && method_exists($item, 'get_product')
                        ? $item->get_product()
                        : $this->order->get_product_from_item($item);
                    if (!empty($product)) {
                        $data['sku'] = $product->get_sku();
                        $data['weight'] = $product->get_weight();
                        $data['dimensions'] = $product->get_dimensions();
                        $data['product'] = $product;
                    }
                    $data_list[] = $data;
                }
            }

            return $data_list;
        }


        /**
         * Item meta (variation attributes etc.) as HTML.
         * WC_Order_Item_Meta is deprecated since WooCommerce 3.0.
         */
        private function get_item_meta_html($item)
        {
            if (function_exists('wc_display_item_meta') && is_object($item)) {
                return wc_display_item_meta($item, array('echo' => false));
            }
            if (class_exists('WC_Order_Item_Meta')) {
                $meta = new WC_Order_Item_Meta($item['item_meta']);
                return $meta->display(false, true);
            }
            return '';
        }


        private function get_formatted_item_price($item, $type, $tax_display = '')
        {
            $item_price = 0;
            $divider = ($type == 'single' && $item['qty'] != 0) ? $item['qty'] : 1;
            if (!isset($item['line_subtotal']) || !isset($item['line_subtotal_tax']))
                return;

            if ($tax_display == 'excl') {
                $item_price = wc_price(($this->order->get_line_subtotal($item)) / $divider);
            } else {
                $item_price = wc_price(($this->order->get_line_subtotal($item, true)) / $divider);
            }
            return $item_price;
        }

        private function get_order_totals()
        {
            $totals = $this->order->get_order_item_totals();
            foreach ($totals as $key => $total) {
                $label = $total['label'];
                $colon = strrpos($label, ':');
                if ($colon !== false) {
                    $label = substr_replace($label, '', $colon, 1);
                }
                $totals[$key]['label'] = $label;
            }
            return $totals;
        }


        private function  getViewData()
        {
            $data = array();
            $invNumber = FikenProvider::getInvoiceNumber($this->order);
            $data['invoice_number'] = !empty($invNumber) ? $invNumber :  $this->order->get_order_number();
            if (method_exists($this->order, 'get_date_created')) {
                // WooCommerce 3.0+
                $created = $this->order->get_date_created();
                $data['order_date'] = $created ? $created->date('Y-m-d') : '';
                $data['payment_method'] = $this->order->get_payment_method_title();
                $customerNote = $this->order->get_customer_note();
            } else {
                $data['order_date'] = substr($this->order->order_date, 0, 10);
                $data['payment_method'] = $this->order->payment_method_title;
                $customerNote = $this->order->customer_note;
            }
            $data['billing_address'] = $this->order->get_formatted_billing_address();
            $data['order_items'] = $this->get_order_items();
            $data['order_totals'] = $this->get_order_totals();
            $data['shipping_notes'] = $customerNote ? wpautop(wptexturize($customerNote)) : '';
            return $data;
        }


        public function generate_pdf($fileName)
        {
            // The autoloader is appended (not prepended), so if another plugin already
            // registered its own dompdf, that copy is used instead of ours.
            if (!class_exists('Dompdf\Dompdf')) {
                require_once(FIKEN_PLUGIN_DIR . 'pdf/lib/vendor/autoload.php');
            }

            // The invoice is self-contained: no remote files, no PHP, no JavaScript.
            $options = new Dompdf\Options();
            $options->setIsRemoteEnabled(false);
            $options->setIsPhpEnabled(false);
            $options->setIsJavascriptEnabled(false);
            $options->setDefaultFont('DejaVu Sans');

            $dompdf = new Dompdf\Dompdf($options);
            $dompdf->loadHtml(FikenHtmlInvoice::getHtmlInvoice($this->getViewData()), 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $written = file_put_contents($fileName, $dompdf->output());
            return $written !== false && $written > 0;
        }


    }

}