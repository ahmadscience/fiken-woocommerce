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

            if (count($items) > 0) {
                foreach ($items as $item) {
                    $qty = $item->get_quantity();
                    $quantity_divider = ($qty == 0) ? 1 : $qty;

                    $data = array();
                    $data['product_id'] = $item->get_product_id();
                    $data['variation_id'] = $item->get_variation_id();
                    $data['name'] = $item->get_name();
                    $data['quantity'] = $qty;
                    $data['line_total'] = wc_price($item->get_total());
                    $data['single_line_total'] = wc_price($item->get_total() / $quantity_divider);
                    $data['line_tax'] = wc_price($item->get_total_tax());
                    $data['single_line_tax'] = wc_price($item->get_total_tax() / $quantity_divider);
                    $data['line_subtotal'] = wc_price($item->get_subtotal());
                    $data['line_subtotal_tax'] = wc_price($item->get_subtotal_tax());
                    $data['ex_price'] = $this->get_formatted_item_price($item, 'total', 'excl');
                    $data['price'] = $this->get_formatted_item_price($item, 'total');
                    $data['order_price'] = $this->order->get_formatted_line_subtotal($item);
                    $data['ex_single_price'] = $this->get_formatted_item_price($item, 'single', 'excl');
                    $data['single_price'] = $this->get_formatted_item_price($item, 'single');

                    $meta_data = $item->get_formatted_meta_data('_', true);
                    $meta_parts = array();
                    foreach ($meta_data as $meta) {
                        $meta_parts[] = wp_strip_all_tags($meta->display_key) . ': ' . wp_strip_all_tags($meta->display_value);
                    }
                    $data['meta'] = implode(', ', $meta_parts);

                    $data['item'] = $item;
                    $data['product'] = null;
                    $product = $item->get_product();
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


        private function get_formatted_item_price($item, $type, $tax_display = '')
        {
            $qty = $item->get_quantity();
            $divider = ($type == 'single' && $qty != 0) ? $qty : 1;

            if ($tax_display == 'excl') {
                return wc_price($this->order->get_line_subtotal($item) / $divider);
            }
            return wc_price($this->order->get_line_subtotal($item, true) / $divider);
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


        private function getViewData()
        {
            $data = array();
            $invNumber = FikenProvider::getInvoiceNumber($this->order);
            $data['invoice_number'] = !empty($invNumber) ? $invNumber : $this->order->get_order_number();
            $date_created = $this->order->get_date_created();
            $data['order_date'] = $date_created ? $date_created->format('Y-m-d') : '';
            $data['payment_method'] = $this->order->get_payment_method_title();
            $data['billing_address'] = $this->order->get_formatted_billing_address();
            $data['order_items'] = $this->get_order_items();
            $data['order_totals'] = $this->get_order_totals();
            $data['shipping_notes'] = wpautop(wptexturize($this->order->get_customer_note()));
            return $data;
        }


        public function generate_pdf($fileName)
        {
            if (!class_exists('Dompdf')) {
                require_once(FIKEN_PLUGIN_DIR . "pdf/lib/dompdf/autoload.inc.php");
            }

            $dompdf = new Dompdf\Dompdf();
            $dompdf->loadHtml(FikenHtmlInvoice::getHtmlInvoice($this->getViewData()));
            $dompdf->set_Paper('A4', 'portrait');
            $dompdf->render();
            file_put_contents($fileName, $dompdf->output());
            return file_exists($fileName);
        }


    }

}