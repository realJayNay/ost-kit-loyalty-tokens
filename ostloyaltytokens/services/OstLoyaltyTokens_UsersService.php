<?php
/**
 * OST Loyalty Tokens plugin for Craft CMS
 *
 * OstLoyaltyTokens_Users Service
 *
 * @author    Jay Nay
 * @copyright Copyright (c) 2018 Jay Nay
 * @link      https://github.com/realJayNay
 * @package   OstLoyaltyTokens
 * @since     0.9.2
 */

namespace Craft;

class OstLoyaltyTokens_UsersService extends BaseApplicationComponent {
    /**
     * Creates a user in the OST KIT Branded Token economy associated with this plugin.
     *
     * From any other plugin file, call it like this:
     *
     *     craft()->ostLoyaltyTokens_userservice->createUser()
     */
    public function createUser($user) {
        try {
            // create a UUID in OST KIT for this user
            $ostUser = OstLoyaltyTokensPlugin::getOstKitClient()->createUser($user->name);

            // add it to the user
            $user->getContent()->ost_kit_uuid = $ostUser['uuid'];
            $msg = "Your Branded Token economy user ID is '" . $user->getContent()->ost_kit_uuid . "'";
            craft()->userSession->setFlash('ostLoyaltyTokens_notice', $msg);
        } catch (\Exception $e) {
            throw new CHttpException(400, 'Internet connection not available: ' . $e->getMessage());
        }

        return $user;
    }

    public function getCurrentUserTokenBalance() {
        $user = craft()->userSession->getUser();
        if ($user != null && isset($user->getContent()->ost_kit_uuid)) {
            $userTokenBalance = OstLoyaltyTokensPlugin::getOstKitClient()->getUserTokenBalance($user->getContent()->ost_kit_uuid, $user->name);
            OstLoyaltyTokensPlugin::log("Retrieved Branded Token balance for economy user '$user->name' -> $userTokenBalance");
            return $userTokenBalance;
        }
        return 0;
    }

}