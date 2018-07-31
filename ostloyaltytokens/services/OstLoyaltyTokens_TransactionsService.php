<?php
/**
 * OST Loyalty Tokens plugin for Craft CMS
 *
 * OstLoyaltyTokens_Transactions Service
 *
 * @author    Jay Nay
 * @copyright Copyright (c) 2018 Jay Nay
 * @link      https://github.com/realJayNay
 * @package   OstLoyaltyTokens
 * @since     0.9.2
 */

namespace Craft;

class OstLoyaltyTokens_TransactionsService extends BaseApplicationComponent {

    /**
     * From any other plugin file, call it like this:
     *
     *     craft()->ostLoyaltyTokens_transactions->getStatusForTransaction()
     */
    public function getStatusForTransaction($transactionUuid) {
        return OstLoyaltyTokensPlugin::getOstKitClient()->getTransaction($transactionUuid);
    }

    private function getStatusForTransactionHtmlRow($orderNumber, $transactions) {
        $rows = '';
        $token = OstLoyaltyTokensPlugin::getOstKitClient()->getToken();
        //https://view.ost.com/chain-id/1409/transaction/0x6d6332dc2ac55c2ae022c9f2f5eb62dbac0526e52e43b454258d48ebb4a5473e
        $viewUrl = 'https://view.ost.com/chain-id/' . $token['ost_utility_balance'][0][0] . '/transaction/';
        foreach ($transactions as $transactionUuid) {
            $transaction = $this->getStatusForTransaction($transactionUuid);
            OstLoyaltyTokensPlugin::log("Transaction $transactionUuid: " . $transaction['status'] . ' (' . $viewUrl . $transaction['transaction_hash'] . ')');
            $orderUrl = "<a href=\"" . UrlHelper::getUrl('shop/customer/order', array('number' => $orderNumber)) . "\">$orderNumber</a>";
            $viewLink = '<a target="_blank" title="View transaction ' . $transaction['transaction_hash'] . ' in OST View" href="' . $viewUrl . $transaction['transaction_hash'] . '">' . $transactionUuid . '</a>';
            if (isset($transaction['status'])) {
                $rows .= '<tr><td>' . $viewLink . '</td><td>' . $orderUrl . '</td><td>' . date('Y-m-d H:i:s', $transaction['timestamp']) . '</td><td>' . $transaction['status'] . '</td><td>' . $transaction['amount'] . ' ' . $token['symbol'] . '</td></tr>';
            } else {
                $rows .= "<tr></tr><td>Transaction #$transactionUuid</td><td>$orderUrl</td><td>?</td><td>unconfirmed</td><td>?</td></tr>";
            }
        }
        return $rows;
    }

    public function getTransactionsHtmlTableRows() {
        $table = '';
        $orders = craft()->commerce_orders->getOrdersByCustomer(craft()->commerce_customers->getCustomer());
        foreach ($orders as $order) {
            if (strlen($order->getContent()->ost_kit_transaction) > 0) {
                $transactions = explode(",", $order->getContent()->ost_kit_transaction);
                $table .= $this->getStatusForTransactionHtmlRow($order->number, $transactions);
            }
        }
        return $table;
    }

    public function executeRewardTransaction($order) {
        $settings = OstLoyaltyTokensPlugin::getPluginSettings();
        $token = OstLoyaltyTokensPlugin::getOstKitClient()->getToken();
        $fromUuid = $token['company_uuid'];
        $toUuid = craft()->userSession->getUser()->getContent()->ost_kit_uuid;
        $action = $settings['reward_action'];
        $transactions = array();
        for ($i = 0; $i < $order->getTotalQty(); $i++) {
            $transaction = OstLoyaltyTokensPlugin::getOstKitClient()->executeAction($action, $fromUuid, $toUuid);
            $transactionUuid = $transaction['id'];
            OstLoyaltyTokensPlugin::log("Executed reward action from '$fromUuid' to '$toUuid' ($transactionUuid)");
            array_push($transactions, $transactionUuid);
        }

        $order->getContent()->ost_kit_transaction = implode(",", $transactions);
        // save the order
        if (craft()->commerce_orders->saveOrder($order)) {
            craft()->userSession->setFlash('ostLoyaltyTokens_notice', 'You received ' . ($order->getTotalQty() * 10) . ' tokens for order #' . $order->number.'.');
        } else {
            $msg = "Unable to assign Branded Token transaction.";
            OstLoyaltyTokensPlugin::logError($msg);
            craft()->userSession->setError(Craft::t($msg));
        }
        return $order->getContent()->ost_kit_transaction;
    }

    public function executeRefundTransaction($order) {
        OstLoyaltyTokensPlugin::log('Assigning refund transactions for order #' . $order->number);
        $settings = OstLoyaltyTokensPlugin::getPluginSettings();
        $token = OstLoyaltyTokensPlugin::getOstKitClient()->getToken();
        $toUuid = $token['company_uuid'];
        $fromUuid = craft()->userSession->getUser()->getContent()->ost_kit_uuid;
        $action = $settings['refund_action'];
        $transaction = OstLoyaltyTokensPlugin::getOstKitClient()->executeAction($action, $fromUuid, $toUuid);
        $transactionUuid = $transaction['transaction_uuid'];
        OstLoyaltyTokensPlugin::log("Executed '$action' transaction from '$fromUuid' to '$toUuid' ($transactionUuid)");

        $order->getContent()->ost_kit_transaction = $transactionUuid;
        // save the order
        if (craft()->commerce_orders->saveOrder($order)) {
            $msg = "Branded Token transaction '$transactionUuid' has been assigned to order #$order->number.";
            OstLoyaltyTokensPlugin::log($msg);
            craft()->userSession->setFlash('ostLoyaltyTokens_notice', $msg);
        } else {
            $msg = "Unable to assign Branded Token transaction.";
            OstLoyaltyTokensPlugin::logError($msg);
            craft()->userSession->setError(Craft::t($msg));
        }
        return $transactionUuid;
    }

    public function executeRegistrationTransaction($toUuid) {
        $settings = OstLoyaltyTokensPlugin::getPluginSettings();
        if (isset($settings['registration_action'])) {
            $token = OstLoyaltyTokensPlugin::getOstKitClient()->getToken();
            $fromUuid = $token['company_uuid'];
            OstLoyaltyTokensPlugin::log('Assigning registration bonus to user ' . $toUuid);
            $transaction = OstLoyaltyTokensPlugin::getOstKitClient()->executeAction($settings['registration_action'], $fromUuid, $toUuid);
            OstLoyaltyTokensPlugin::log("Executed reward action from '$fromUuid' to '$toUuid' ({$transaction['id']})");
            return $transaction['id'];
        }
    }

    public function executeDiscountTransaction($order, $discount) {
        $settings = OstLoyaltyTokensPlugin::getPluginSettings();
        if (isset($settings['discount_action'])) {
            $fromUuid = craft()->userSession->getUser()->getContent()->ost_kit_uuid;
            $balance = OstLoyaltyTokensPlugin::getOstKitClient()->getCombinedBalance($fromUuid);
            if (isset($balance['usd_value']) && $balance['usd_value'] > 0) {
//                $order->
            }
            $token = OstLoyaltyTokensPlugin::getOstKitClient()->getToken();
            $toUuid = $token['company_uuid'];
            OstLoyaltyTokensPlugin::log("Applied arbitrary discount of $discount USD to order #{$order->number}.");
            $transaction = OstLoyaltyTokensPlugin::getOstKitClient()->executeAction($settings['discount_action'], $fromUuid, $toUuid, $discount);
            OstLoyaltyTokensPlugin::log("Executed arbitrary discount action from '$fromUuid' to '$toUuid' ({$transaction['id']})");
            return $transaction['id'];
        }
    }

    public function getLedger() {
        $token = OstLoyaltyTokensPlugin::getOstKitClient()->getToken();
        $userId = craft()->userSession->getUser()->getContent()->ost_kit_uuid;
        OstLoyaltyTokensPlugin::log("Consulting ledger for $userId");
        $transactions = OstLoyaltyTokensPlugin::getOstKitClient()->getLedger($userId);
        // filter out failed transactions, they mess up the list anyway
        return array_filter($transactions, function ($transaction) use ($token) {
            if ($transaction['status'] !== 'failed') {
                if ($token['company_uuid'] == $transaction['from_user_id']) {
                    $transaction['from_user_id'] = 'Branded Token Company';
                } else if ($token['company_uuid'] == $transaction['to_user_id']) {
                    $transaction['to_user_id'] = 'Branded Token Company';
                }
                return true;
            }
            return false;
        }, ARRAY_FILTER_USE_BOTH);
    }

}