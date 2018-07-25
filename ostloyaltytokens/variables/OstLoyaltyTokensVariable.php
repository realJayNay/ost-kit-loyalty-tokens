<?php
/**
 * OST Loyalty Tokens plugin for Craft CMS
 *
 * OST Loyalty Tokens Variable
 *
 * @author    Jay Nay
 * @copyright Copyright (c) 2018 Jay Nay
 * @link      https://github.com/realJayNay
 * @package   OstLoyaltyTokens
 * @since     0.9.2
 */

namespace Craft;

class OstLoyaltyTokensVariable {
    /**
     * Constructs a set of table rows that show all related OST transactions with click-through to OST View.
     *
     * Reference in any twig template like this:
     *     {{ craft.ostLoyaltyTokens.transactions }}
     */
    public function getTransactions() {
        return craft()->ostLoyaltyTokens_transactions->getLedger();
    }
}