<?php
/**
 * Runs ONE test scenario against the real plugin code, with WordPress and WooCommerce
 * replaced by small stubs and $wpdb replaced by a fake that records every query.
 * It prints what happened (options written, SQL, output, JSON responses) as JSON
 * after the marker line @@RESULT@@, also when the code under test ends the request.
 *
 * Not meant to be called directly: tests/run.php starts one process per scenario.
 * Usage: php tests/harness.php <scenario>
 */
if (PHP_SAPI !== 'cli') {
    exit;
}
error_reporting(E_ALL);

$pluginDir = dirname(__DIR__) . '/';
$scenario = isset($argv[1]) ? $argv[1] : '';

define('ABSPATH', __DIR__ . '/fakewp/');
define('WPINC', 'wp-includes');
define('WC_VERSION', '8.0.0');
define('ARRAY_A', 'ARRAY_A');
define('FIKEN_PLUGIN_DIR', $pluginDir);
define('FIKEN_PLUGIN_URL', 'https://shop.test/wp-content/plugins/fiken/');

$GLOBALS['T'] = array(
    'options' => array(),          // fake wp_options
    'options_written' => array(),  // update_option() calls
    'sql' => array(),
    'logs' => array(),
    'php_errors' => array(),
    'died' => null,
    'json' => null,
    'json_status' => null,
    'output' => '',
    'result' => null,              // set by scenarios
    'cap' => true,
    'nonce_ok' => true,
    'admin_nonce_ok' => true,
    'fiken_orders' => array(),     // id_order => current_state
    'get_lock' => '1',
    'post_status' => 'wc-completed',
    'failed_count' => 0,
);

class DieSignal extends Exception
{
}

set_error_handler(function ($no, $str, $file, $line) {
    // Notices/warnings/deprecations from plugin code are test failures.
    if (strpos($file, '/tests/') === false) {
        $GLOBALS['T']['php_errors'][] = "[$no] $str @ " . basename($file) . ":$line";
    }
    return false;
});

register_shutdown_function(function () {
    $out = '';
    while (ob_get_level()) {
        $out = ob_get_clean() . $out;
    }
    $T = $GLOBALS['T'];
    $T['output'] = $out;
    $err = error_get_last();
    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        $T['php_errors'][] = 'FATAL ' . $err['message'];
    }
    unset($T['options']);
    echo "\n@@RESULT@@" . json_encode($T, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n";
});
ob_start();

// ---------------------------------------------------------------- WordPress stubs
function __($s, $d = null) { return $s; }
function _e($s, $d = null) { echo $s; }
function _n($single, $plural, $n, $d = null) { return $n == 1 ? $single : $plural; }
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_html__($s, $d = null) { return esc_html($s); }
function esc_url($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_js($s) { return addslashes((string)$s); }
function wp_kses_post($s) { return preg_replace('#<script\b[^>]*>.*?</script>#is', '', (string)$s); }
function wp_kses($s, $allowed) { return strip_tags((string)$s, '<br>'); }
function admin_url($p = '') { return 'https://shop.test/wp-admin/' . $p; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function wc_price($p) { return '<span class="amount">kr ' . number_format((float)$p, 2, ',', ' ') . '</span>'; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['T']['options']) ? $GLOBALS['T']['options'][$k] : $d; }
function update_option($k, $v) { $GLOBALS['T']['options'][$k] = $v; $GLOBALS['T']['options_written'][$k] = $v; return true; }
function current_user_can($cap) { return $GLOBALS['T']['cap']; }
function get_current_screen() { return null; }
function check_ajax_referer($action = -1, $q = false, $die = true)
{
    if (!$GLOBALS['T']['nonce_ok']) {
        $GLOBALS['T']['died'] = 'check_ajax_referer';
        throw new DieSignal('nonce');
    }
    return 1;
}
function check_admin_referer($action = -1, $q = '_wpnonce')
{
    if (!$GLOBALS['T']['admin_nonce_ok']) {
        $GLOBALS['T']['died'] = 'check_admin_referer';
        throw new DieSignal('nonce');
    }
    return 1;
}
function wp_send_json($data, $status = null)
{
    $GLOBALS['T']['json'] = $data;
    $GLOBALS['T']['json_status'] = $status;
    $GLOBALS['T']['died'] = 'wp_send_json';
    throw new DieSignal('json');
}
function wp_die($msg = '', $status = null) { $GLOBALS['T']['died'] = 'wp_die'; throw new DieSignal('die'); }
function wp_create_nonce($a) { return 'nonce123'; }
function get_post_status($id) { return $GLOBALS['T']['post_status']; }
function get_post_meta($id, $k, $s) { return ''; }
function get_bloginfo($k) { return 'Testbutikk AS'; }
function wc_round_tax_total($v) { return round((float)$v, 2); }
function maybe_unserialize($v) { return $v; }
function wpautop($s) { return '<p>' . $s . '</p>'; }
function wptexturize($s) { return $s; }
function wc_display_item_meta($item, $args) { return '<ul class="wc-item-meta"><li>Størrelse: XL</li></ul>'; }
function wc_get_logger()
{
    return new class {
        function log($level, $msg, $ctx) { $GLOBALS['T']['logs'][] = $msg; }
    };
}
function wc_get_order($id) { return $id > 0 ? new WC_Order($id) : false; }
function wc_get_order_statuses() { return array('wc-processing' => 'Processing', 'wc-completed' => 'Completed'); }
function esc_url_raw($s) { return (string)$s; }
function esc_attr_e($s, $d = null) { echo esc_attr($s); }
function esc_html_e($s, $d = null) { echo esc_html($s); }
function wp_nonce_field($action, $name) { echo '<input type="hidden" name="' . esc_attr($name) . '" value="nonce123">'; }
$GLOBALS['woocommerce'] = (object)array('payment_gateways' => (object)array('payment_gateways' => array(
    (object)array('enabled' => 'yes', 'id' => 'bacs', 'title' => 'Bank <b>transfer</b>'),
)));
function WC()
{
    return (object)array('countries' => (object)array('countries' => array('NO' => 'Norge')));
}

class FakeWpdb
{
    public $prefix = 'wp_';
    function _escape($s) { return addslashes($s); }
    function prepare($q)
    {
        $args = array_slice(func_get_args(), 1);
        foreach ($args as $a) {
            $q = preg_replace('/%s/', "'" . addslashes($a) . "'", $q, 1);
        }
        return $q;
    }
    function rec($sql) { $GLOBALS['T']['sql'][] = preg_replace('/\s+/', ' ', trim($sql)); }
    function get_var($sql)
    {
        $this->rec($sql);
        if (stripos($sql, 'GET_LOCK') !== false) return $GLOBALS['T']['get_lock'];
        if (stripos($sql, '`current_state` = 3') !== false) return $GLOBALS['T']['failed_count'];
        if (preg_match('/FROM `wp_fiken_orders` WHERE `id_order` = (\d+)/', $sql, $m)) {
            return isset($GLOBALS['T']['fiken_orders'][$m[1]]) ? $m[1] : null;
        }
        return 0;
    }
    function get_row($sql, $t = null)
    {
        $this->rec($sql);
        if (preg_match('/fo.`id_order` = (\d+)/', $sql, $m) && isset($GLOBALS['T']['fiken_orders'][$m[1]])) {
            $st = $GLOBALS['T']['fiken_orders'][$m[1]];
            return array('id_order' => $m[1], 'current_state' => (string)$st, 'mes' => '', 'last_update' => '', 'state_name' => 'state' . $st);
        }
        return null;
    }
    function get_results($sql, $t = null)
    {
        $this->rec($sql);
        if (stripos($sql, 'fiken_order_history') !== false) {
            return array(array('id_order' => '1', 'id_state' => '3', 'mes' => '<img src=x onerror=alert(1)>', 'last_update' => '2026-01-01', 'state_name' => 'Failed'));
        }
        return array();
    }
    function query($sql)
    {
        $this->rec($sql);
        if (preg_match('/INSERT INTO `wp_fiken_orders`.*VALUES\((\d+), (\d+)/s', $sql, $m)) $GLOBALS['T']['fiken_orders'][$m[1]] = (int)$m[2];
        if (preg_match('/UPDATE `wp_fiken_orders` SET `current_state` = (\d+).*`id_order` = (\d+)/s', $sql, $m)) $GLOBALS['T']['fiken_orders'][$m[2]] = (int)$m[1];
        return 1;
    }
}
$GLOBALS['wpdb'] = new FakeWpdb();

class FakeDate
{
    function date($f) { return '2026-11-02'; }
    function getOffsetTimestamp() { return 1793577600; }
}

class FakeItem implements ArrayAccess
{
    private $d;
    function __construct($d) { $this->d = $d; }
    function offsetExists($k): bool { return isset($this->d[$k]); }
    #[\ReturnTypeWillChange]
    function offsetGet($k) { return isset($this->d[$k]) ? $this->d[$k] : null; }
    function offsetSet($k, $v): void {}
    function offsetUnset($k): void {}
    function get_product() { return null; }
}

class WC_Order
{
    public $id;
    function __construct($id) { $this->id = $id; }
    function get_id() { return $this->id; }
    function get_payment_method() { return 'bacs'; }
    function get_items() { return isset($GLOBALS['T']['items']) ? $GLOBALS['T']['items'] : array(); }
    function get_shipping_methods() { return array(); }
    function get_fees() { return isset($GLOBALS['T']['fees']) ? $GLOBALS['T']['fees'] : array(); }
    function get_order_number() { return (string)$this->id; }
    function get_date_created() { return new FakeDate(); }
    function get_payment_method_title() { return 'Vipps'; }
    function get_customer_note() { return 'Lever på døra, takk!'; }
    function get_formatted_billing_address() { return 'Åse Øvrebø<br/>Storgata 1<br/>0155 Oslo'; }
    function get_line_subtotal($i, $inc = false) { return $inc ? 200 : 160; }
    function get_formatted_line_subtotal($i) { return wc_price(200); }
    function get_order_item_totals() { return array('order_total' => array('label' => 'Total:', 'value' => wc_price(200.5))); }
    function get_billing_first_name() { return 'Åse'; }
    function get_billing_last_name() { return 'Øvrebø'; }
    function get_billing_email() { return isset($GLOBALS['T']['billing_email']) ? $GLOBALS['T']['billing_email'] : 'ase@example.no'; }
    function get_billing_company() { return isset($GLOBALS['T']['billing_company']) ? $GLOBALS['T']['billing_company'] : ''; }
    function get_billing_postcode() { return '0155'; }
    function get_billing_city() { return 'Oslo'; }
    function get_billing_address_1() { return 'Storgata 1'; }
    function get_billing_address_2() { return ''; }
    function get_billing_country() { return 'NO'; }
}

// ---------------------------------------------------------------- load the plugin
require $pluginDir . 'classes/class.fiken.php';
include_once $pluginDir . 'classes/controller.php';

function setPaySettings()
{
    update_option(FikenUtils::CONF_FIKEN_PAY_METHODS, json_encode(array('bacs' => array(
        'fiken_account_' => '1920:10001', 'fiken_pay_status_' => 'wc-completed', 'fiken_sale_kind_' => 'CASH_SALE'))));
    update_option(FikenUtils::CONF_FIKEN_VATS_MAPPING, json_encode(array('1' => array('fiken_vat_' => 'HIGH'))));
    $GLOBALS['T']['options_written'] = array();
}

function callPrivate($class, $method, $args = array())
{
    $m = new ReflectionMethod($class, $method);
    $m->setAccessible(true);
    $obj = $m->isStatic() ? null : (new ReflectionClass($class))->newInstanceWithoutConstructor();
    return $m->invokeArgs($obj, $args);
}

/** Runs registerSaleInner with the given items/fees and returns the lines that would be sent. */
function linesSentToFiken()
{
    update_option(FikenUtils::CONF_FIKEN_DEBUG_MODE, '1');
    $company = new class {
        function getRelSales() { return '/companies/0/sales'; }
    };
    $settings = (object)array('fiken_account_' => '1920:10001', 'fiken_sale_kind_' => 'CASH_SALE');
    $resStatus = '2';
    $error = null;
    try {
        $m = new ReflectionMethod('FikenSale', 'registerSaleInner');
        $m->setAccessible(true);
        $m->invokeArgs(null, array(new WC_Order(50), $company, $settings, &$resStatus));
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    $lines = null;
    foreach ($GLOBALS['T']['logs'] as $l) {
        if (strpos($l, 'FIKEN LINES :: ') === 0) {
            $lines = eval('return ' . substr($l, strlen('FIKEN LINES :: ')) . ';');
        }
    }
    $GLOBALS['T']['logs'] = array();
    return array('lines' => $lines, 'resStatus' => $resStatus, 'error' => $error);
}

// ---------------------------------------------------------------- scenarios
try {
    switch ($scenario) {

        case 'ajax_customer_overwrites_settings':
            $GLOBALS['T']['cap'] = false;
            $GLOBALS['T']['nonce_ok'] = false;
            $_POST = array('action' => 'fiken_register_sale', 'order_id' => '1', 'submitSettings' => '',
                'fiken_login' => 'attacker@evil.test', 'fiken_passw' => 'attackerpass', 'fiken_company' => '999999999');
            Fiken::ajax_process_register_sale();
            break;

        case 'ajax_bad_nonce':
            $GLOBALS['T']['nonce_ok'] = false;
            $_POST = array('action' => 'fiken_register_sale', 'order_id' => '1');
            Fiken::ajax_process_register_sale();
            break;

        case 'ajax_manager_with_settings_fields':
            setPaySettings();
            $GLOBALS['T']['fiken_orders'][1] = 2;
            $_POST = array('action' => 'fiken_register_sale', 'order_id' => '1', 'submitSettings' => '',
                'fiken_login' => 'other@test', 'fiken_passw' => 'x');
            Fiken::ajax_process_register_sale();
            break;

        case 'ajax_customer_reads_history':
            $GLOBALS['T']['cap'] = false;
            $_POST = array('action' => 'fiken_get_order_status_history', 'order_id' => '1');
            Fiken::ajax_process_get_order_status_history();
            break;

        case 'ajax_manager_reads_history':
            $_POST = array('action' => 'fiken_get_order_status_history', 'order_id' => '1');
            Fiken::ajax_process_get_order_status_history();
            break;

        case 'ajax_register_unknown_order':
            setPaySettings();
            $_POST = array('action' => 'fiken_register_sale', 'order_id' => '0');
            Fiken::ajax_process_register_sale();
            break;

        case 'settings_csrf':
            $GLOBALS['T']['admin_nonce_ok'] = false;
            $_POST = array('submitSettings' => '', 'fiken_login' => 'attacker@evil.test', 'fiken_passw' => 'p');
            callPrivate('FikenController', 'postProcess');
            break;

        case 'settings_valid_save':
            update_option(FikenUtils::CONF_FIKEN_PASSW, 'old-secret');
            $_POST = array('submitSettings' => '', 'fiken_login' => 'me@shop.test', 'fiken_passw' => 'new-secret', 'fiken_debug_mode' => '0');
            callPrivate('FikenController', 'postProcess');
            break;

        case 'settings_save_keeps_password':
            update_option(FikenUtils::CONF_FIKEN_PASSW, 'old-secret');
            $GLOBALS['T']['options_written'] = array();
            $_POST = array('submitSettings' => '', 'fiken_login' => 'me@shop.test', 'fiken_passw' => '', 'fiken_debug_mode' => '0');
            callPrivate('FikenController', 'postProcess');
            $GLOBALS['T']['result'] = get_option(FikenUtils::CONF_FIKEN_PASSW);
            break;

        case 'sort_injection':
            update_option('posts_per_page', '20');
            $_GET = array('sort' => "(CASE WHEN (SELECT SUBSTR(user_pass,1,1) FROM wp_users WHERE ID=1)=0x24 THEN date_add ELSE date_upd END)",
                'asc_desc' => 'DESC, (SELECT SLEEP(5))',
                'filter_date_start' => "2024-01-01'\"><script>alert(1)</script>",
                'filter_order_status_id' => '1 OR 1=1',
                'cur_page' => '-3');
            $r = callPrivate('FikenController', 'getListOrders');
            $GLOBALS['T']['result'] = array('filter_date_start' => $r['filter_date_start'], 'sort' => $r['sort'],
                'asc_desc' => $r['asc_desc'], 'filter_order_status_id' => $r['filter_order_status_id']);
            break;

        case 'sort_valid':
            update_option('posts_per_page', '20');
            $_GET = array('sort' => 'total_paid', 'asc_desc' => 'ASC', 'cur_page' => '2',
                'filter_date_start' => '2026-01-01', 'filter_date_end' => '2026-01-31');
            $r = callPrivate('FikenController', 'getListOrders');
            $GLOBALS['T']['result'] = array('sort' => $r['sort'], 'asc_desc' => $r['asc_desc']);
            break;

        case 'hook_on_transferred_order':
            setPaySettings();
            $GLOBALS['T']['fiken_orders'][42] = 2;
            $GLOBALS['T']['result'] = array('return' => FikenSale::registerSale(42), 'state_after' => $GLOBALS['T']['fiken_orders'][42]);
            break;

        case 'ajax_retransfer_no_force':
            setPaySettings();
            $GLOBALS['T']['fiken_orders'][42] = 5;
            $GLOBALS['T']['result'] = FikenSale::registerSale(42, -1, true);
            break;

        case 'ajax_retransfer_force':
            setPaySettings();
            $GLOBALS['T']['fiken_orders'][42] = 2;
            $GLOBALS['T']['result'] = FikenSale::registerSale(42, -1, true, true);
            break;

        case 'hook_new_and_failed_order':
            setPaySettings();
            $GLOBALS['T']['post_status'] = 'wc-processing';
            $GLOBALS['T']['fiken_orders'][7] = 3;
            FikenSale::registerSale(6);
            FikenSale::registerSale(7);
            $GLOBALS['T']['result'] = array('state_6' => isset($GLOBALS['T']['fiken_orders'][6]) ? $GLOBALS['T']['fiken_orders'][6] : null,
                'state_7' => $GLOBALS['T']['fiken_orders'][7]);
            break;

        case 'lock_held':
            setPaySettings();
            $GLOBALS['T']['get_lock'] = '0';
            $GLOBALS['T']['result'] = FikenSale::registerSale(43, -1, true);
            break;

        case 'small_fee_line':
            $GLOBALS['T']['items'] = array(array('line_total' => 100, 'line_tax' => 25, 'qty' => 1, 'name' => 'Vare',
                'line_tax_data' => array('total' => array('1' => 25))));
            $GLOBALS['T']['fees'] = array(array('name' => 'Gebyr', 'total' => 0.5, 'total_tax' => 0));
            setPaySettings();
            $GLOBALS['T']['result'] = linesSentToFiken();
            break;

        case 'vat_unmapped':
            setPaySettings();
            $GLOBALS['T']['items'] = array(array('line_total' => 100, 'line_tax' => 15, 'qty' => 1, 'name' => 'Mat',
                'line_tax_data' => array('total' => array('2' => 15))));
            $GLOBALS['T']['result'] = linesSentToFiken();
            break;

        case 'vat_zero_rate_unmapped':
            setPaySettings();
            $GLOBALS['T']['items'] = array(array('line_total' => 100, 'line_tax' => 0, 'qty' => 1, 'name' => 'Bok',
                'line_tax_data' => array('total' => array('3' => '0.00'))));
            $GLOBALS['T']['result'] = linesSentToFiken();
            break;

        case 'org_number':
            $GLOBALS['T']['result'] = array(
                FikenUtils::normalizeOrgNumber('923 609 016'),
                FikenUtils::normalizeOrgNumber('923609016'),
                FikenUtils::normalizeOrgNumber('Billige Tester AS'),
                FikenUtils::normalizeOrgNumber('12345'),
                FikenUtils::normalizeOrgNumber(''),
            );
            break;

        case 'customer_from_order':
            $GLOBALS['T']['billing_company'] = 'Billige Tester AS';
            $c = FikenCustomer::getCustomerFromOrderWC(new WC_Order(9));
            $GLOBALS['T']['result'] = $c->asArray();
            break;

        case 'customer_lookup_empty_email':
            $company = new class {
                function getRelContacts() { $GLOBALS['T']['result'] = 'API CALLED'; return '/x'; }
            };
            $GLOBALS['T']['result'] = FikenCustomer::getCustomerFromFiken('', $company);
            break;

        case 'pdf_render':
            $file = sys_get_temp_dir() . '/fiken-test-' . getmypid() . '.pdf';
            $GLOBALS['T']['items'] = array(new FakeItem(array('product_id' => 1, 'variation_id' => 0,
                'name' => 'Ærlig ølglass <script>alert(1)</script>', 'qty' => 2, 'line_total' => 160, 'line_tax' => 40,
                'line_subtotal' => 160, 'line_subtotal_tax' => 40)));
            include_once FIKEN_PLUGIN_DIR . 'pdf/class.pdf.php';
            $pdf = new FikenPDF(new WC_Order(1042));
            $ok = $pdf->generate_pdf($file);
            $content = $ok ? file_get_contents($file) : '';
            @unlink($file);
            $GLOBALS['T']['result'] = array('ok' => $ok, 'is_pdf' => substr($content, 0, 5) === '%PDF-', 'bytes' => strlen($content),
                'dompdf_file' => str_replace(FIKEN_PLUGIN_DIR, '', (new ReflectionClass('Dompdf\Dompdf'))->getFileName()));
            break;

        case 'invoice_html_escaped':
            include_once FIKEN_PLUGIN_DIR . 'pdf/html_invoice.php';
            $GLOBALS['T']['result'] = FikenHtmlInvoice::getHtmlInvoice(array(
                'invoice_number' => '<b>1</b>', 'order_date' => '2026-11-02', 'payment_method' => '<i>Vipps</i>',
                'billing_address' => 'Åse<br/><script>x</script>Oslo',
                'order_items' => array(array('name' => '<script>alert(1)</script>', 'meta' => '', 'sku' => '"><svg onload=1>',
                    'weight' => '', 'quantity' => 1, 'order_price' => wc_price(10))),
                'order_totals' => array('order_total' => array('label' => 'Total', 'value' => wc_price(10))),
                'shipping_notes' => ''));
            break;

        case 'render_admin_page':
            update_option(FikenUtils::CONF_FIKEN_LOGIN, 'me@shop.test\'"><script>alert(1)</script>');
            update_option(FikenUtils::CONF_FIKEN_PASSW, 'SUPER-SECRET-PW');
            update_option('posts_per_page', '20');
            setPaySettings();
            $_GET = array('page' => 'fiken-plugin-page', 'filter_date_start' => '2026-01-01');
            FikenView::render_view();
            break;

        case 'failed_notice':
            $GLOBALS['T']['failed_count'] = 2;
            Fiken::failed_transfers_notice();
            break;

        case 'failed_notice_customer':
            $GLOBALS['T']['cap'] = false;
            $GLOBALS['T']['failed_count'] = 2;
            Fiken::failed_transfers_notice();
            break;

        default:
            $GLOBALS['T']['php_errors'][] = 'unknown scenario ' . $scenario;
    }
} catch (DieSignal $e) {
    // the request ended the way wp_die()/wp_send_json() would end it
}
