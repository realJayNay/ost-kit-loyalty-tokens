<?php
/**
 * OST Loyalty Tokens plugin for Craft CMS
 *
 * OstLoyaltyTokens_Transactions Service
 *
 * --snip--
 * All of your plugin’s business logic should go in services, including saving data, retrieving data, etc. They
 * provide APIs that your controllers, template variables, and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 * --snip--
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
        return OstLoyaltyTokensPlugin::getOstKitClient()->getTransactionStatus($transactionUuid);
    }

    private function getStatusForTransactionHtmlRow($orderNumber, $transactions) {
        $rows = '';
        foreach ($transactions as $transactionUuid) {
            $transaction = $this->getStatusForTransaction($transactionUuid);
            OstLoyaltyTokensPlugin::log("Transaction $transactionUuid: " . $transaction['status'] . ' (' . $transaction['view_url'] . ')');
            $orderUrl = "<a href=\"" . UrlHelper::getUrl('shop/customer/order', array('number' => $orderNumber)) . "\">$orderNumber</a>";
            $viewUrl = '<a target="_blank" title="View transaction ' . $transaction['transaction_hash'] . ' in OST View" href="' . $transaction['view_url'] . '">' . $transactionUuid . '</a>';
            if (isset($transaction['status'])) {
                $rows .= '<tr><td>' . $viewUrl . '</td><td>' . $orderUrl . '</td><td>' . date('Y-m-d H:i:s', $transaction['transaction_timestamp']) . '</td><td>' . $transaction['status'] . '</td><td>' . $transaction['bt_transfer_value'] . '</td></tr>';
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
        $fromUuid = $settings['company_uuid'];
        $toUuid = craft()->userSession->getUser()->getContent()->ost_kit_uuid;
        $transactionType = $settings['company_to_user_transaction_type'];
        $transactions = array();
        for ($i = 0; $i < $order->getTotalQty(); $i++) {
            $transaction = OstLoyaltyTokensPlugin::getOstKitClient()->executeTransactionType($fromUuid, $toUuid, $transactionType);
            $transactionUuid = $transaction['transaction_uuid'];
            OstLoyaltyTokensPlugin::log("Executed '$transactionType' transaction from '$fromUuid' to '$toUuid' (OST KIT Transaction ID=$transactionUuid)");
            array_push($transactions, $transactionUuid);
        }

        $order->getContent()->ost_kit_transaction = implode(",", $transactions);
        // save the order
        if (craft()->commerce_orders->saveOrder($order)) {
            craft()->userSession->setFlash('ostLoyaltyTokens_notice', 'You received '.($order->getTotalQty()*3).' loyalty tokens for order #'.$order->shortNumber.' ('.$order->getContent()->ost_kit_transaction.')');
        } else {
            $msg = "Unable to assign Branded Token transaction.";
            OstLoyaltyTokensPlugin::logError($msg);
            craft()->userSession->setError(Craft::t($msg));
        }
        return $order->getContent()->ost_kit_transaction;
    }

    public function executeRefundTransaction($order) {
        OstLoyaltyTokensPlugin::log('Assigning refund transactions for order ' . $order->id);
        $settings = OstLoyaltyTokensPlugin::getPluginSettings();
        $toUuid = $settings['company_uuid'];
        $fromUuid = craft()->userSession->getUser()->getContent()->ost_kit_uuid;
        $transactionType = $settings['user_to_company_transaction_type'];
        $transaction = OstLoyaltyTokensPlugin::getOstKitClient()->executeTransactionType($fromUuid, $toUuid, $transactionType);
        $transactionUuid = $transaction['transaction_uuid'];
        OstLoyaltyTokensPlugin::log("Executed '$transactionType' transaction from '$fromUuid' to '$toUuid' (OST KIT Transaction UUID='$transactionUuid')");

        $order->getContent()->ost_kit_transaction = $transactionUuid;
        // save the order
        if (craft()->commerce_orders->saveOrder($order)) {
            $msg = "Branded Token transaction '$transactionUuid' has been assigned to order #'$order->number'";
            OstLoyaltyTokensPlugin::log($msg);
            craft()->userSession->setFlash('ostLoyaltyTokens_notice', $msg);
        } else {
            $msg = "Unable to assign Branded Token transaction.";
            OstLoyaltyTokensPlugin::logError($msg);
            craft()->userSession->setError(Craft::t($msg));
        }
        return $transactionUuid;
    }
}