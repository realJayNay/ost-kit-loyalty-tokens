<?php
/**
 * OST Loyalty Tokens plugin for Craft CMS
 *
 * OST KIT - Loyalty tokens plugin for Branded Tokens
 *
 * @author    Jay Nay
 * @copyright Copyright (c) 2018 Jay Nay
 * @link      https://github.com/realJayNay
 * @package   OstLoyaltyTokens
 * @since     0.9.2
 */

namespace Craft;
require_once __DIR__ . '../vendor/autoload.php';

use Ost\Kit\Php\Client\OstKitClient;

class OstLoyaltyTokensPlugin extends BasePlugin {

    private static $settings;
    private static $ost;

    /**
     * Initializes the OST KIT PHP client and registers the following event listeners:
     * - users.onBeforeSaveUser
     * - commerce_orders.onOrderComplete
     *
     * @return mixed
     */
    public function init() {
        parent::init();

        // get the plugin settings
        self::$settings = $this->getSettings();
        if (!isset(self::$settings) || !isset(self::$settings['api_key']) || !isset(self::$settings['secret'])) {
            OstLoyaltyTokensPlugin::log('Please configure this plugin before use. Disabling...');
            return false;
        }


        // initialize the OST KIT client
        self::$ost = OstKitClient::create(self::$settings['api_key'], self::$settings['secret'], self::$settings['base_url']);

        // listen for new user registrations only
        craft()->on('users.onBeforeSaveUser', function (Event $event) {
            // Only fire if new user, this should avoid an infinite loop
            $user = $event->params['user'];
            if ($event->params['isNewUser'] && craft()->request->isSiteRequest()) {
                // retrieve the userModel from the event
                craft()->ostLoyaltyTokens_users->createUser($user);
            }
            OstLoyaltyTokensPlugin::log("User '$user->name' has OST KIT UUID '" . $user->getContent()->ost_kit_uuid . "'");
        });

        // assign tokens on order completion
        craft()->on('commerce_orders.onOrderComplete', function (Event $event) {
            OstLoyaltyTokensPlugin::log('Executing reward transaction for Order #' . $event->params['order']);
            craft()->ostLoyaltyTokens_transactions->executeRewardTransaction($event->params['order']);
        });

        // subtract tokens on order cancellation
        craft()->on('commerce_payments.onRefundTransaction', function (Event $event) {
            OstLoyaltyTokensPlugin::log('Executing reward transaction for Order #' . $event->params['order']);
            $transaction = $event->params['transaction'];
            if ($transaction->status == 'success') {
                $transaction->order->orderStatusId = 2;
                craft()->ostLoyaltyTokens_transactions->executeRefundTransaction($transaction->order);
            }
        });
    }

    /**
     * Returns the user-facing name.
     *
     * @return mixed
     */
    public function getName() {
        return Craft::t('OST Loyalty Tokens');
    }

    /**
     * Plugins can have descriptions of themselves displayed on the Plugins page by adding a getDescription() method
     * on the primary plugin class:
     *
     * @return mixed
     */
    public function getDescription() {
        return Craft::t('OST KIT - Loyalty tokens plugin for Branded Tokens');
    }

    /**
     * Plugins can have links to their documentation on the Plugins page by adding a getDocumentationUrl() method on
     * the primary plugin class:
     *
     * @return string
     */
    public function getDocumentationUrl() {
        return 'https://github.com/realJayNay/ost-kit-loyalty-tokens/blob/master/README.md';
    }

    /**
     * Plugins can now take part in Craft’s update notifications, and display release notes on the Updates page, by
     * providing a JSON feed that describes new releases, and adding a getReleaseFeedUrl() method on the primary
     * plugin class.
     *
     * @return string
     */
    public function getReleaseFeedUrl() {
        return 'https://raw.githubusercontent.com/realJayNay/ost-kit-loyalty-tokens/master/releases.json';
    }

    /**
     * Returns the version number.
     *
     * @return string
     */
    public function getVersion() {
        return '0.9.2';
    }

    /**
     * As of Craft 2.5, Craft no longer takes the whole site down every time a plugin’s version number changes, in
     * case there are any new migrations that need to be run. Instead plugins must explicitly tell Craft that they
     * have new migrations by returning a new (higher) schema version number with a getSchemaVersion() method on
     * their primary plugin class:
     *
     * @return string
     */
    public function getSchemaVersion() {
        return '0.9.2';
    }

    /**
     * Returns the developer’s name.
     *
     * @return string
     */
    public function getDeveloper() {
        return 'Jay Nay';
    }

    /**
     * Returns the developer’s website URL.
     *
     * @return string
     */
    public function getDeveloperUrl() {
        return 'https://github.com/realJayNay';
    }

    /**
     * Returns whether the plugin should get its own tab in the CP header.
     *
     * @return bool
     */
    public function hasCpSection() {
        return false;
    }

    /**
     * Called right before your plugin’s row gets stored in the plugins database table, and tables have been created
     * for it based on its records.
     */
    public function onBeforeInstall() {
    }

    /**
     * Called right after your plugin’s row has been stored in the plugins database table, and tables have been
     * created for it based on its records.
     */
    public function onAfterInstall() {
    }

    /**
     * Called right before your plugin’s record-based tables have been deleted, and its row in the plugins table
     * has been deleted.
     */
    public function onBeforeUninstall() {
    }

    /**
     * Called right after your plugin’s record-based tables have been deleted, and its row in the plugins table
     * has been deleted.
     */
    public function onAfterUninstall() {
    }

    /**
     * Defines the attributes that model your plugin’s available settings.
     *
     * @return array
     */
    protected function defineSettings() {
        return array(
            'api_key' => array(AttributeType::String, 'label' => 'OST KIT - API key', 'required' => true, 'default' => ''),
            'secret' => array(AttributeType::String, 'label' => 'OST KTI - API secret', 'required' => true, 'default' => ''),
            'base_url' => array(AttributeType::String, 'label' => 'OST KTI - REST base URL', 'default' => 'https://playgroundapi.ost.com'),
            'debug' => array(AttributeType::Bool, 'label' => 'Debug logging', 'default' => false),
            'company_to_user_transaction_type' => array(AttributeType::String, 'label' => 'OST KIT - Company-to-user reward transaction type', 'required' => true, 'default' => 'Reward'),
            'user_to_company_transaction_type' => array(AttributeType::String, 'label' => 'OST KIT - User-to-Company refund transaction type', 'required' => true, 'default' => 'Refund'),
        );
    }

    /**
     * Returns the HTML that displays your plugin’s settings.
     *
     * @return mixed
     */
    public function getSettingsHtml() {
        return craft()->templates->render('ostloyaltytokens/OstLoyaltyTokens_Settings', array(
            'settings' => $this->getSettings()
        ));
    }

    /**
     * If you need to do any processing on your settings’ post data before they’re saved to the database, you can
     * do it with the prepSettings() method:
     *
     * @param mixed $settings The plugin's settings
     *
     * @return mixed
     */
    public function prepSettings($settings) {
        // Modify $settings here...

        return $settings;
    }

    public static function getPluginSettings() {
        return self::$settings;
    }

    public static function getOstKitClient() {
        return self::$ost;
    }

    public static function log($msg, $level = LogLevel::Info, $force = false) {
        if (self::$settings['debug']) {
            $force = true;
        }
        if (!is_string($msg)) {
            $msg = print_r($msg, true);
        }
        parent::log($msg, $level, $force);
    }

    public static function logTrace($msg) {
        OstLoyaltyTokensPlugin::log($msg, LogLevel::Trace, $force = true);
    }

    public static function logError($msg) {
        OstLoyaltyTokensPlugin::log($msg, LogLevel::Error, $force = true);
    }

    public static function logWarning($msg) {
        OstLoyaltyTokensPlugin::log($msg, LogLevel::Warning, $force = true);
    }

}