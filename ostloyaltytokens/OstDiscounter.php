<?php

namespace Craft;

use Commerce\Adjusters\Commerce_AdjusterInterface;
use Commerce\Helpers\CommerceCurrencyHelper;
use Craft\Commerce_DiscountModel;
use Craft\Commerce_DiscountRecord;
use Craft\Commerce_LineItemModel;
use Craft\Commerce_OrderAdjustmentModel;
use Craft\Commerce_OrderModel;
use Craft\StringHelper;

/**
 * Custom cart adjuster to use loyalty tokens as discount on orders.
 *
 * @package Craft
 */
class OstDiscounter implements Commerce_AdjusterInterface {

    /**
     * The adjust method modifies the order values (like baseShippingCost),
     * and records all adjustments by returning one or more orderAdjusterModels
     * to be saved on the order.
     *
     * @param Commerce_OrderModel $order
     * @param array $lineItems
     *
     * @return array of \Craft\Commerce_OrderAdjustmentModel objects
     * @throws \Exception
     */
    public function adjust(Commerce_OrderModel &$order, array $lineItems = array()) {
        $settings = OstLoyaltyTokensPlugin::getPluginSettings();
        if (isset($settings['discount_action'])) {
            $fromUuid = craft()->userSession->getUser()->getContent()->ost_kit_uuid;
            $balance = OstLoyaltyTokensPlugin::getOstKitClient()->getCombinedBalance($fromUuid);
            if (isset($balance['usd_value']) && $balance['usd_value'] > 0) {
                $myAdjuster = new Commerce_OrderAdjustmentModel();

                $discount = $balance['usd_value'] <= $order->totalPrice ? $balance['usd_value'] : $order->totalPrice;
                $order->baseDiscount = $order->baseDiscount - $discount;
                $myAdjuster->type = "Discount";
                $myAdjuster->name = "Loyalty token discount";
                $myAdjuster->description = "Loyalty token discount";
                $myAdjuster->amount = -$discount;
                $myAdjuster->orderId = $order->id;
                $myAdjuster->optionsJson = array('lineItemsAffected' => null);
                $myAdjuster->included = false;

                return array($myAdjuster);
            }
        }

        return array();
    }
}