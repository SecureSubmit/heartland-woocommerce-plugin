<?php
/*
Plugin Name: WooCommerce SecureSubmit Gateway
Plugin URI: https://developer.heartlandpaymentsystems.com/SecureSubmit/
Description: Heartland Payment Systems gateway for WooCommerce.
Version: 1.6.0
Author: SecureSubmit
Author URI: https://developer.heartlandpaymentsystems.com/SecureSubmit/
*/

class WooCommerceSecureSubmitGateway
{
    const SECURESUBMIT_GATEWAY_CLASS = 'WC_Gateway_SecureSubmit';

    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'init'), 0);
        add_action('woocommerce_load', array($this, 'activate'));
    }

    public function init()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        load_plugin_textdomain('wc_securesubmit', false, dirname(plugin_basename(__FILE__)) . '/languages');

        $this->loadClasses();

        $securesubmit = call_user_func(array(self::SECURESUBMIT_GATEWAY_CLASS, 'instance'));
        add_filter('woocommerce_payment_gateways', array($this, 'addGateways'));
        add_action('woocommerce_after_my_account', array($this, 'savedCards'));
        add_action('woocommerce_order_actions', array($securesubmit->capture, 'addOrderAction'));
        add_action('woocommerce_order_action_' . $securesubmit->id . '_capture', array($securesubmit, 'process_capture'));

        $masterpass = call_user_func(array(self::SECURESUBMIT_GATEWAY_CLASS . '_MasterPass', 'instance'));
        add_action('wp_ajax_securesubmit_masterpass_lookup', array($masterpass, 'lookupCallback'));
        add_action('wp_ajax_nopriv_securesubmit_masterpass_lookup', array($masterpass, 'lookupCallback'));
        add_shortcode('woocommerce_masterpass_review_order', array($masterpass, 'reviewOrderShortcode'));
        add_action('woocommerce_order_actions', array($masterpass->capture, 'addOrderAction'));
        add_action('woocommerce_order_action_' . $masterpass->id . '_capture', array($masterpass, 'process_capture'));
        add_action('woocommerce_after_my_account', array($masterpass, 'myaccountConnect'));
        add_action('wp_loaded', array($masterpass->reviewOrder, 'processCheckout'));
    }

    /**
     * Handle behaviors that only should occur at plugin activation.
     */
    public function activate()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        $this->loadClasses();
        call_user_func(array(self::SECURESUBMIT_GATEWAY_CLASS . '_MasterPass', 'createOrderReviewPage'));
    }

    /**
     * Adds payment options to WooCommerce to be enabled by store admin.
     *
     * @param array $methods
     *
     * @return array
     */
    public function addGateways($methods)
    {
        $methods[] = self::SECURESUBMIT_GATEWAY_CLASS . '_MasterPass';
        if (class_exists('WC_Subscriptions_Order')) {
            $klass = self::SECURESUBMIT_GATEWAY_CLASS . '_Subscriptions';
            if (!function_exists('wcs_create_renewal_order')) {
                $klass .= '_Deprecated';
            }
            $methods[] = $klass;
        } else {
            $methods[] = self::SECURESUBMIT_GATEWAY_CLASS;
        }
        return $methods;
    }

    /**
     * Handles "Manage saved cards" interface to user.
     */
    public function savedCards()
    {
        $cards = get_user_meta(get_current_user_id(), '_secure_submit_card', false);

        if (!$cards) {
            return;
        }

        if (isset($_POST['delete_card']) && wp_verify_nonce($_POST['_wpnonce'], "secure_submit_del_card")) {
            $card = $cards[(int) $_POST['delete_card']];
            delete_user_meta(get_current_user_id(), '_secure_submit_card', $card);
        }

        if (!$cards) {
            return;
        }

        $path = plugin_dir_path(__FILE__);
        include $path . 'templates/saved-cards.php';
    }

    protected function loadClasses()
    {
        include_once('classes/class-wc-gateway-securesubmit.php');
        include_once('classes/class-wc-gateway-securesubmit-subscriptions.php');
        include_once('classes/class-wc-gateway-securesubmit-subscriptions-deprecated.php');
        include_once('classes/class-wc-gateway-securesubmit-masterpass.php');
    }
}
new WooCommerceSecureSubmitGateway();
