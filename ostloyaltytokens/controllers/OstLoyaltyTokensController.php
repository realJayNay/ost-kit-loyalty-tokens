<?php
/**
 * OST Loyalty Tokens plugin for Craft CMS
 *
 * OstLoyaltyTokens Controller
 *
 * --snip--
 * Generally speaking, controllers are the middlemen between the front end of the CP/website and your plugin’s
 * services. They contain action methods which handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering post data, saving it on a model,
 * passing the model off to a service, and then responding to the request appropriately depending on the service
 * method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what the method does (for example,
 * actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 * --snip--
 *
 * @author    Jay Nay
 * @copyright Copyright (c) 2018 Jay Nay
 * @link      https://github.com/realJayNay
 * @package   OstLoyaltyTokens
 * @since     0.9.2
 */

namespace Craft;

class OstLoyaltyTokensController extends BaseController {

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     * @access protected
     */
    protected $allowAnonymous = array('actionBalance',
    );

    /**
     * Handle a request going to our plugin's balance action URL, e.g.: actions/ostLoyaltyTokens/balance
     */
    public function actionBalance() {
        $user = craft()->userSession->getUser();
        if ($user != null) {
            try {
                $this->renderTemplate('ostLoyaltyTokens/_balance.twig', array(
                    'balance' => craft()->ostLoyaltyTokens_users->getCurrentUserTokenBalance(),
                    'uuid' => $user->getContent()->ost_kit_uuid
                ));
            } catch (HttpException $e) {
                craft()->userSession->setFlash('ostLoyaltyTokens_error', 'Unable to retrieve token balance: '.$e->getMessage());
            }
        }
    }

    /**
     * Handle a request going to our plugin's transactions action URL, e.g.: actions/ostLoyaltyTokens/transactions
     */
    public function actionTransactions() {
        $user = craft()->userSession->getUser();
        if ($user != null) {
            return craft()->ostLoyaltyTokens_transactions->getTransactionsHtmlTableRows($user->getContent()->ost_kit_uuid);
        }
    }
}