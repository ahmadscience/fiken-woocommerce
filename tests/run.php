<?php
/**
 * Test runner: php tests/run.php
 *
 * Each scenario in tests/harness.php runs in its own PHP process (the plugin ends
 * requests with die()), and the result is checked here. Any PHP notice, warning or
 * deprecation raised by plugin code also fails the scenario.
 *
 * Network: curl is pointed at a closed local proxy, so no request ever reaches Fiken.
 */
if (PHP_SAPI !== 'cli') {
    exit;
}

function run_scenario($name)
{
    $env = array_merge(getenv(), array(
        'https_proxy' => 'http://127.0.0.1:9', 'HTTPS_PROXY' => 'http://127.0.0.1:9',
        'http_proxy' => 'http://127.0.0.1:9', 'HTTP_PROXY' => 'http://127.0.0.1:9',
        'no_proxy' => '', 'NO_PROXY' => '',
    ));
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/harness.php') . ' ' . escapeshellarg($name);
    $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, __DIR__, $env);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    $pos = strrpos($stdout, '@@RESULT@@');
    if ($pos === false) {
        return array('_raw' => $stdout . $stderr);
    }
    $r = json_decode(substr($stdout, $pos + strlen('@@RESULT@@')), true);
    $r['_stderr'] = $stderr;
    return $r;
}

$failures = 0;
$passes = 0;

function check($cond, $label)
{
    global $failures, $passes;
    if ($cond) {
        $passes++;
        echo "  ok   $label\n";
    } else {
        $failures++;
        echo "  FAIL $label\n";
    }
}

function sql_with($r, $needle)
{
    foreach ($r['sql'] as $q) {
        if (strpos($q, $needle) !== false) {
            return $q;
        }
    }
    return null;
}

$tests = array(

    'ajax_customer_overwrites_settings' => function ($r) {
        check($r['json_status'] === 403, 'customer gets 403');
        check(empty($r['options_written']), 'no option written');
    },

    'ajax_bad_nonce' => function ($r) {
        check($r['died'] === 'check_ajax_referer', 'request without valid nonce is rejected');
        check(empty($r['sql']), 'nothing queried');
    },

    'ajax_manager_with_settings_fields' => function ($r) {
        check(empty($r['options_written']), 'AJAX request never saves settings');
        $json = json_decode($r['output'], true);
        check(isset($json['error']) && strpos($json['error'], 'already transferred') !== false, 'already transferred order is skipped');
    },

    'ajax_customer_reads_history' => function ($r) {
        check($r['json_status'] === 403, 'customer cannot read order history');
    },

    'ajax_manager_reads_history' => function ($r) {
        $json = json_decode($r['output'], true);
        check(is_array($json) && isset($json[0]['mes']), 'manager gets the history as JSON');
    },

    'ajax_register_unknown_order' => function ($r) {
        $json = json_decode($r['output'], true);
        check(isset($json['error']) && !isset($json['order']), 'unknown order returns an error, no fatal error');
    },

    'settings_csrf' => function ($r) {
        check($r['died'] === 'check_admin_referer', 'settings POST without nonce is rejected');
        check(empty($r['options_written']), 'no option written');
    },

    'settings_valid_save' => function ($r) {
        check(isset($r['options_written']['fiken_v1_login']) && $r['options_written']['fiken_v1_login'] === 'me@shop.test', 'login saved');
        check(isset($r['options_written']['fiken_v1_password']) && $r['options_written']['fiken_v1_password'] === 'new-secret', 'new password saved');
    },

    'settings_save_keeps_password' => function ($r) {
        check(!isset($r['options_written']['fiken_v1_password']), 'empty password field does not overwrite');
        check($r['result'] === 'old-secret', 'stored password kept');
    },

    'sort_injection' => function ($r) {
        $q = sql_with($r, 'ORDER BY');
        check($q !== null && substr($q, strpos($q, 'ORDER BY')) === 'ORDER BY posts.`id` DESC LIMIT 0,20', 'ORDER BY is whitelisted');
        check(strpos(implode("\n", $r['sql']), 'user_pass') === false && strpos(implode("\n", $r['sql']), 'SLEEP') === false, 'payload never reaches SQL');
        check($r['result']['filter_date_start'] === date('Y-m-d'), 'invalid date falls back to today');
        check($r['result']['filter_order_status_id'] === 1, 'status id is an integer');
    },

    'sort_valid' => function ($r) {
        $q = sql_with($r, 'ORDER BY');
        check($q !== null && strpos($q, 'ORDER BY total_paid ASC LIMIT 20,20') !== false, 'valid sort and page still work');
        check(strpos($q, "DATE(posts.`post_date`) <= '2026-01-31'") !== false, 'end date filters on order date');
    },

    'hook_on_transferred_order' => function ($r) {
        check($r['result']['return'] === false && $r['result']['state_after'] === 2, 'status change does not resend a transferred order');
        check(sql_with($r, 'UPDATE `wp_fiken_orders`') === null, 'state is not reset');
    },

    'ajax_retransfer_no_force' => function ($r) {
        check(isset($r['result']['error']) && strpos($r['result']['error'], 'already transferred') !== false, 'partially-skipped order is not resent without confirmation');
    },

    'ajax_retransfer_force' => function ($r) {
        check(isset($r['result']['error']) && $r['result']['error'] === 'Default company not found!', 'confirmed resend goes on to Fiken (no network in tests)');
    },

    'hook_new_and_failed_order' => function ($r) {
        check($r['result']['state_6'] === 1, 'new order gets state "Not transferred"');
        check($r['result']['state_7'] === 3, 'failed order keeps state "Failed"');
    },

    'lock_held' => function ($r) {
        check(isset($r['result']['error']) && strpos($r['result']['error'], 'already being transferred') !== false, 'concurrent transfer is refused');
        check(sql_with($r, 'RELEASE_LOCK') === null, 'lock held by the other request is not released');
    },

    'small_fee_line' => function ($r) {
        $lines = $r['result']['lines'];
        $byType = array();
        foreach ((array)$lines as $l) {
            $byType[$l['vatType']] = $l;
        }
        check(isset($byType['HIGH']) && $byType['HIGH']['netPrice'] == 10000 && $byType['HIGH']['vat'] == 2500, 'item sent on the mapped HIGH rate (100.00 + 25.00 VAT)');
        check(isset($byType['NONE']) && $byType['NONE']['netPrice'] == 50 && $byType['NONE']['vat'] == 0, '0.50 kr fee is included (50 øre, no VAT)');
        check($r['result']['resStatus'] === '2', 'not marked as partially skipped');
    },

    'vat_unmapped' => function ($r) {
        check($r['result']['lines'] === null, 'nothing is sent');
        check(strpos((string)$r['result']['error'], 'tax rate id 2 is not mapped') !== false, 'clear error names the unmapped tax rate');
    },

    'vat_zero_rate_unmapped' => function ($r) {
        $lines = $r['result']['lines'];
        check(is_array($lines) && $lines[0]['vatType'] === 'NONE' && $lines[0]['vat'] == 0, '0 % line without mapping is still sent as NONE');
    },

    'org_number' => function ($r) {
        check($r['result'] === array('923609016', '923609016', '', '', ''), 'only 9-digit numbers are org numbers');
    },

    'customer_from_order' => function ($r) {
        $c = $r['result'];
        check($c['name'] === 'Åse Øvrebø' && $c['email'] === 'ase@example.no', 'name and email from WooCommerce getters');
        check(!isset($c['organizationIdentifier']), 'company name is not sent as org number');
        check($c['address']['country'] === 'Norge' && $c['address']['postalCode'] === '0155', 'address filled');
    },

    'customer_lookup_empty_email' => function ($r) {
        check($r['result'] === false, 'empty email never matches a Fiken contact');
    },

    'pdf_render' => function ($r) {
        check($r['result']['ok'] === true && $r['result']['is_pdf'] === true && $r['result']['bytes'] > 1000, 'invoice PDF renders');
        check($r['result']['dompdf_file'] === 'pdf/lib/vendor/dompdf/dompdf/src/Dompdf.php', 'bundled dompdf 3 is used');
    },

    'invoice_html_escaped' => function ($r) {
        $h = $r['result'];
        check(strpos($h, '<script>') === false && strpos($h, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'product name is escaped');
        check(strpos($h, '<svg') === false && strpos($h, '<b>1</b>') === false && strpos($h, '<i>Vipps</i>') === false, 'other text fields are escaped');
        check(strpos($h, 'Åse<br/>') !== false || strpos($h, 'Åse<br />') !== false, 'address keeps line breaks');
        check(strpos($h, 'kr 10,00') !== false, 'formatted prices kept');
    },

    'render_admin_page' => function ($r) {
        $h = $r['output'];
        check(strpos($h, 'id="form_settings"') !== false && strpos($h, 'name="fiken_settings_nonce"') !== false, 'page renders with the settings nonce');
        check(strpos($h, "var admin_fiken_ajax_nonce = 'nonce123'") !== false, 'AJAX nonce is passed to the script');
        check(strpos($h, 'SUPER-SECRET-PW') === false, 'stored password is not in the page');
        check(strpos($h, '<script>alert(1)</script>') === false, 'stored login is escaped');
        check(strpos($h, 'Bank &lt;b&gt;transfer&lt;/b&gt;') !== false, 'payment method title is escaped');
        check(strpos($h, 'value="2026-01-01"') !== false, 'filter date is shown');
        check(strpos($h, ' elected=') === false, 'no broken selected attribute');
    },

    'failed_notice' => function ($r) {
        check(strpos($r['output'], '2 orders failed to transfer to Fiken') !== false, 'manager sees failed transfers');
        check(strpos($r['output'], 'filter_order_status_id=3') !== false, 'link filters on Failed');
    },

    'failed_notice_customer' => function ($r) {
        check($r['output'] === '', 'no notice without the capability');
    },
);

foreach ($tests as $name => $assert) {
    echo "$name\n";
    $r = run_scenario($name);
    if (!isset($r['sql'])) {
        check(false, 'scenario produced a result: ' . substr(isset($r['_raw']) ? $r['_raw'] : '', 0, 500));
        continue;
    }
    $assert($r);
    check(empty($r['php_errors']), 'no PHP errors' . (empty($r['php_errors']) ? '' : ': ' . implode(' | ', array_slice($r['php_errors'], 0, 3))));
}

echo "static checks\n";
$utils = file_get_contents(dirname(__DIR__) . '/classes/utils.php');
check(strpos($utils, 'CURLOPT_SSL_VERIFYPEER, true') !== false && strpos($utils, 'CURLOPT_SSL_VERIFYHOST, 2') !== false
    && strpos($utils, 'VERIFYPEER, false') === false, 'TLS certificate verification is on for Fiken calls');

echo "\n$passes passed, $failures failed\n";
exit($failures ? 1 : 0);
