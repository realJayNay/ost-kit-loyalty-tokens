<?php

use Craft\OstLoyaltyTokensPlugin;

/**
 * OST Kit PHP client v0.1.
 *
 * This client is based on <a href="https://github.com/realJayNay/ost-kit-php-client">OST KIT PHP Client</a>, slightly modified to log to Craft CMS's log system.
 */
class OstKitClient {

    private $baseUrl; // OST REST base URL
    private $apiKey; // OST KIT API key
    private $apiSecret; // OST KIT API secret
    private $companyUuid; // UUID that represents the company in user_to_company and company_to_user transactions
    private $networkId; // OST utility chain ID

    /**
     * Static factory for OstKitClient instances. Creates a new OST KIT PHP client with the specified properties.
     *
     * @param string $apiKey OST API key
     * @param string $apiSecret OST KIT API secret
     * @param string $companyUuid UUID that represents the company in user_to_company and company_to_user transactions
     * @param string $baseUrl OST REST base URL
     * @param integer $networkId OST utility chain ID
     * @return OstKitClient
     */
    public static function create($apiKey, $apiSecret, $companyUuid = null, $baseUrl = 'https://playgroundapi.ost.com', $networkId = 1409) {
        return new OstKitClient($apiKey, $apiSecret, $baseUrl, $companyUuid, $networkId);
    }

    private function __construct($apiKey, $apiSecret, $baseUrl, $companyUuid, $networkId) {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->baseUrl = $baseUrl;
        $this->companyUuid = $companyUuid;
        $this->networkId = $networkId;
    }

    public function createUser($name) {
        $json = $this->post('/users/create', array('name' => $name));
        return reset($json['data']['economy_users']);
    }

    public function editUser($uuid, $name) {
        $json = $this->post('/users/edit', array('uuid' => $uuid, 'name' => $name));
        return reset($json['data']['economy_users']);
    }

    public function listUsers($fetchAll = false, $page = 1, $filter = 'all', $orderBy = 'creation_time', $order = 'desc') {
        $json = $this->get('/users/list', array('page_no' => $page, 'filter' => $filter, 'order_by' => $orderBy, 'order' => $order), $fetchAll);
        return $json['data']['economy_users'];
    }

    public function createTransactionType($name, $kind, $currencyValue, $currencyType = 'BT', $commissionPercent = '0.0') {
        $json = $this->post('/transaction-types/create', array('name' => $name, 'kind' => $kind, 'currency_type' => $currencyType, 'currency_value' => $currencyValue, 'commission_percent' => $commissionPercent));
        return $json['data']['transactions'][0];
    }

    public function listTransactionTypes($fetchAll = false) {
        $json = $this->get('/transaction-types/list', array(), $fetchAll);
        return $json['data']['transaction_types'];
    }

    public function executeTransactionType($fromUuid, $toUuid, $transactionTypeName) {
        $json = $this->post('/transaction-types/execute', array('from_uuid' => $fromUuid, 'to_uuid' => $toUuid, 'transaction_kind' => $transactionTypeName));
        return $json['data'];
    }

    public function getTransactionStatus($transactionUuid) {
        $json = $this->post('/transaction-types/status', array('transaction_uuids[]' => $transactionUuid));
        return array(
            'status' => $json['data']['transactions'][0]['status'],
            'transaction_hash' => $json['data']['transactions'][0]['transaction_hash'],
            'bt_transfer_value' => $json['data']['transactions'][0]['bt_transfer_value'],
            'transaction_timestamp' => $json['data']['transactions'][0]['transaction_timestamp'],
            'view_url' => "https://view.ost.com/chain-id/$this->networkId/transaction/" . $json['data']['transactions'][0]['transaction_hash'],
            'transaction_uuid' => $transactionUuid
        );
    }

    public function airdrop($amount, $listType = 'never_airdropped') {
        $json = $this->post('/users/airdrop/drop', array('amount' => $amount, 'list_type' => $listType));
        return $json['data']['airdrop_uuid'];
    }

    public function getAirdropStatus($airdropUuid) {
        $json = $this->post('/users/airdrop/status', array('airdrop_uuid' => $airdropUuid));
        return $json['data']['current_status'];
    }

    public function getUserTokenBalance($uuid, $username) {
        $user = $this->editUser($uuid, $username);
        return $user['token_balance'];
    }

    private function get($endpoint, $arguments = array(), $fetchAll) {
        $arguments['api_key'] = $this->apiKey;
        $arguments['request_timestamp'] = time();
        ksort($arguments);
        foreach ($arguments as $key => $value) {
            $value = urlencode(str_replace(' ', '+', $value));
            $arguments[$key] = $value;
        }

        $query = $endpoint . '?' . http_build_query($arguments, '', '&');
        $url = $this->baseUrl . $query . '&signature=' . hash_hmac('sha256', $query, $this->apiSecret);
        OstLoyaltyTokensPlugin::log(str_replace($this->apiKey, '*****', "GET $url"));
        $json = file_get_contents($url);

        if ($json == FALSE) {
            throw new Exception("GET request failed: $url");
        }
        OstLoyaltyTokensPlugin::log("JSON $json");
        $jsonObject = json_decode($json, true);
        if (!$jsonObject['success']) {
            throw new Exception("GET request unsuccessful: '" . $jsonObject['err']['msg'] . "': $url");
        }

        if ($fetchAll && isset($jsonObject['data']['meta']['next_page_payload']['page_no'])) {
            // recursively fetch all items
            $nextPage = $jsonObject['data']['meta']['next_page_payload']['page_no'];
            while (isset($nextPage) && $nextPage != $arguments['page_no']) {
                OstLoyaltyTokensPlugin::log("Fetching page $nextPage ");
                $arguments['page_no'] = $nextPage;
                $add = $this->get($endpoint, $arguments, $fetchAll);
                $jsonObject = array_merge_recursive($jsonObject, $add);
            }
        }

        return $jsonObject;
    }

    private function post($endpoint, $arguments = array()) {
        $arguments['api_key'] = $this->apiKey;
        $arguments['request_timestamp'] = time();
        ksort($arguments);
        $query = $endpoint . '?' . http_build_query($arguments, '', '&');
        $query = str_replace('%5B%5D', '[]', $query);
        $query = str_replace('%20', '+', $query);
        $arguments['signature'] = hash_hmac('sha256', $query, $this->apiSecret);
        $encoded = http_build_query($arguments);
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $encoded
            )
        ));

        OstLoyaltyTokensPlugin::log("POST $this->baseUrl$endpoint with body " . (str_replace($this->apiKey, '*****', $encoded)));
        $json = file_get_contents($this->baseUrl . $endpoint, FALSE, $context);

        if ($json == FALSE) {
            throw new Exception("POST request failed: $this->baseUrl$endpoint with body $encoded");
        }
        OstLoyaltyTokensPlugin::log("JSON $json");
        $jsonObject = json_decode($json, true);
        if (!$jsonObject['success']) {
            throw new Exception($jsonObject['err']['msg']);
        }
        return $jsonObject;
    }

    public function getCompanyUuid() {
        return $this->companyUuid;
    }
}
