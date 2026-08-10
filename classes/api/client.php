<?php
/**
 * Fiken API v2 HTTP client.
 *
 * https://api.fiken.no/api/v2/docs/
 * Uses personal access tokens (Bearer). No HAL parsing — v2 is plain JSON.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FikenApiClient')) {

    include_once FIKEN_PLUGIN_DIR . 'classes/utils.php';

    class FikenApiClient
    {
        const BASE_URL = 'https://api.fiken.no/api/v2';
        const TIMEOUT = 45;

        private $token;

        public function __construct($token = null)
        {
            $this->token = $token ?: get_option(FikenUtils::CONF_FIKEN_TOKEN);
        }

        public function hasToken()
        {
            return !empty($this->token);
        }

        /**
         * @param string $method GET|POST|PUT|PATCH|DELETE
         * @param string $path   path starting with '/', e.g. '/companies'
         * @param array|null $body JSON body for write requests
         * @return array{ok: bool, status: int, body: mixed, location: string, error: string}
         */
        public function request($method, $path, $body = null)
        {
            if (empty($this->token)) {
                return array('ok' => false, 'status' => 0, 'body' => null, 'location' => '', 'error' => __('Fiken API token is not configured.', 'fiken'));
            }

            $url = self::BASE_URL . $path;
            global $wp_version;

            $args = array(
                'method'    => strtoupper($method),
                'timeout'   => self::TIMEOUT,
                'sslverify' => true,
                'headers'   => array(
                    'Authorization' => 'Bearer ' . $this->token,
                    'Accept'        => 'application/json',
                    'User-Agent'    => 'fiken-woocommerce/' . FIKEN_VERSION . ' wp/' . $wp_version . ' wc/' . (defined('WC_VERSION') ? WC_VERSION : ''),
                ),
            );

            if ($body !== null) {
                $args['headers']['Content-Type'] = 'application/json';
                $args['body'] = wp_json_encode($body);
            }

            FikenUtils::log($args['method'] . ' ' . $url, 'API CALL', FikenUtils::LOG_LEVEL_INFO);
            if ($body !== null) {
                FikenUtils::log(wp_json_encode($body), 'API BODY', FikenUtils::LOG_LEVEL_INFO);
            }

            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                $err = $response->get_error_message();
                FikenUtils::log($err, 'HTTP ERROR', FikenUtils::LOG_LEVEL_ERROR);
                return array('ok' => false, 'status' => 0, 'body' => null, 'location' => '', 'error' => $err);
            }

            $status   = (int) wp_remote_retrieve_response_code($response);
            $raw      = wp_remote_retrieve_body($response);
            $location = wp_remote_retrieve_header($response, 'location');
            $decoded  = null;

            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $decoded = null;
                }
            }

            $log_body = strlen($raw) > 512 ? substr($raw, 0, 512) . '…' : $raw;
            FikenUtils::log($status . ' :: ' . $log_body, 'API RESULT', FikenUtils::LOG_LEVEL_INFO);

            if ($status >= 300) {
                $error = self::extractErrorMessage($decoded, $raw, $status);
                FikenUtils::log($status . ' ' . $error, 'API ERROR', FikenUtils::LOG_LEVEL_ERROR);
                return array('ok' => false, 'status' => $status, 'body' => $decoded, 'location' => (string) $location, 'error' => $error);
            }

            return array('ok' => true, 'status' => $status, 'body' => $decoded, 'location' => (string) $location, 'error' => '');
        }

        public function uploadAttachment($companySlug, $saleId, $filePath, $filename, $attachToSale = true, $attachToPayment = true)
        {
            if (empty($this->token)) {
                return array('ok' => false, 'status' => 0, 'error' => __('Fiken API token is not configured.', 'fiken'));
            }
            if (!is_readable($filePath)) {
                return array('ok' => false, 'status' => 0, 'error' => sprintf(__('Attachment file not readable: %s', 'fiken'), $filePath));
            }

            $url = self::BASE_URL . '/companies/' . rawurlencode($companySlug) . '/sales/' . (int) $saleId . '/attachments';

            $ch = curl_init();
            if (!$ch) {
                return array('ok' => false, 'status' => 0, 'error' => 'curl_init failed');
            }

            global $wp_version;

            $postFields = array(
                'file'            => new CURLFile($filePath, 'application/pdf', $filename),
                'filename'        => $filename,
                'attachToSale'    => $attachToSale ? 'true' : 'false',
                'attachToPayment' => $attachToPayment ? 'true' : 'false',
            );

            curl_setopt_array($ch, array(
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postFields,
                CURLOPT_HTTPHEADER     => array(
                    'Authorization: Bearer ' . $this->token,
                    'Accept: application/json',
                    'User-Agent: fiken-woocommerce/' . FIKEN_VERSION . ' wp/' . $wp_version,
                ),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_HEADER         => false,
            ));

            $body   = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($body === false) {
                $err = curl_error($ch);
                curl_close($ch);
                FikenUtils::log($err, 'ATTACHMENT CURL ERROR', FikenUtils::LOG_LEVEL_ERROR);
                return array('ok' => false, 'status' => 0, 'error' => $err);
            }
            curl_close($ch);

            if ($status >= 300) {
                $decoded = json_decode($body, true);
                $error   = self::extractErrorMessage($decoded, $body, $status);
                FikenUtils::log($status . ' ' . $error, 'ATTACHMENT ERROR', FikenUtils::LOG_LEVEL_ERROR);
                return array('ok' => false, 'status' => $status, 'error' => $error);
            }

            return array('ok' => true, 'status' => $status, 'error' => '');
        }

        public function whoAmI()                        { return $this->request('GET', '/user'); }
        public function getCompanies()                  { return $this->request('GET', '/companies'); }
        public function getCompany($s)                  { return $this->request('GET', '/companies/' . rawurlencode($s)); }
        public function getAccounts($s, $year = null)   { $year = $year ?: (int) date('Y'); return $this->request('GET', '/companies/' . rawurlencode($s) . '/accounts/' . (int) $year); }
        public function getContactByEmail($s, $email)   {
            $email = trim((string) $email);
            if ($email === '') { return array('ok' => true, 'status' => 200, 'body' => array(), 'location' => '', 'error' => ''); }
            return $this->request('GET', '/companies/' . rawurlencode($s) . '/contacts?email=' . rawurlencode($email));
        }
        public function createContact($s, array $data)  { return $this->request('POST', '/companies/' . rawurlencode($s) . '/contacts', $data); }
        public function createSale($s, array $data)     { return $this->request('POST', '/companies/' . rawurlencode($s) . '/sales', $data); }
        public function getSale($s, $saleId)            { return $this->request('GET', '/companies/' . rawurlencode($s) . '/sales/' . (int) $saleId); }

        public static function idFromLocation($location)
        {
            if (empty($location)) { return 0; }
            if (preg_match('~/(\d+)(?:\?|$)~', $location, $m)) { return (int) $m[1]; }
            return 0;
        }

        private static function extractErrorMessage($decoded, $raw, $status)
        {
            if (is_array($decoded)) {
                if (isset($decoded['message'])) { return (string) $decoded['message']; }
                if (isset($decoded['validationMessages']) && is_array($decoded['validationMessages'])) {
                    $parts = array();
                    foreach ($decoded['validationMessages'] as $m) {
                        if (isset($m['message'])) { $parts[] = $m['message']; }
                    }
                    if ($parts) { return implode('; ', $parts); }
                }
                if (isset($decoded[0]['message'])) { return (string) $decoded[0]['message']; }
            }
            if (is_string($raw) && $raw !== '') {
                return 'HTTP ' . $status . ': ' . (strlen($raw) > 200 ? substr($raw, 0, 200) . '…' : $raw);
            }
            return 'HTTP ' . $status;
        }
    }
}
