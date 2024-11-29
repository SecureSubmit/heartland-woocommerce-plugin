<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

if (!defined('ABSPATH')) {
    die();
}

/*
 * Stuff to do:
 * - Error conditions:
 *  - Gift Card already applied to order
 *  - Gift Card has zero balance
 */

class WC_Gateway_SecureSubmit_GiftCards extends WC_Gateway_SecureSubmit
{
    protected $gift_card               = null;
    protected $gift_card_submitted     = null;
    protected $gift_card_pin_submitted = null;
    protected $applied_gift_card       = null;

    public function update_gateway_title_checkout($title, $id)
    {
        if ($id === 'securesubmit' && $this->allow_gift_cards) {
            $title = $this->gift_card_title;
        }

        return $title;
    }

    public function update_gateway_description_checkout($description, $id)
    {
        if ($id === 'securesubmit' && $this->getSetting('gift_cards') === 'yes') {
            $description = $this->getSetting('gift_cards_gateway_description');
        }

        return $description;
    }

    public function set_ajax_url()
    {
        if ((is_checkout() || is_cart()) && $this->getSetting('gift_cards') === 'yes') {
            $html = '<script data-cfasync="false" type="text/javascript">';
            $html .= 'if( typeof ajaxurl === "undefined") { ';
            $html .= 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";';
            $html .= '}';
            $html .= '</script>';

            echo $html;
        }
    }

    public function applyGiftCard()
    {
        $this->gift_card_submitted     = $_POST['gift_card_number'];
        $this->gift_card_pin_submitted = $_POST['gift_card_pin'];
        $gift_card_balance = $this->gift_card_balance(
            $this->gift_card_submitted,
            $this->gift_card_pin_submitted
        );

        if ($gift_card_balance['error']) {
            echo json_encode(array(
                'error' => 1,
                'message' => $gift_card_balance['message'],
            ));
        } else {
            $this->gift_card->temp_balance = $gift_card_balance['message'];

            $this->addGiftCardToCartSession();
            $this->updateGiftCardCartTotal();
            echo json_encode(array(
                'error'   => 0,
                'balance' => html_entity_decode(get_woocommerce_currency_symbol()) . $gift_card_balance['message'],
            ));
        }

        wp_die();
    }

    protected function updateGiftCardCartTotal()
    {
        $gift_card_object_entered = WC()->session->get('securesubmit_gift_card_object');
        if (is_null($gift_card_object_entered)) {
            $gift_card_object_entered = (object)array();
        }

        $gift_card_object_applied = WC()->session->get('securesubmit_gift_card_applied');
        if (is_null($gift_card_object_applied)) {
            $gift_card_object_applied = (object)array();
        }

        $original_total = $this->getOriginalCartTotal();

        $securesubmit_data = WC()->session->get('securesubmit_data');
        if (!is_object($securesubmit_data)) {
            $securesubmit_data = new stdClass;
        }

        $securesubmit_data->original_total = $original_total;
        WC()->session->set('securesubmit_data', $securesubmit_data);

        $this->updateGiftCardTotals();

        if (is_object($gift_card_object_entered) && count(get_object_vars($gift_card_object_entered)) > 0) {
            if ($gift_card_object_entered->temp_balance === '0.00') {
                WC()->session->__unset('securesubmit_gift_card_object');

                $zero_balance_message = apply_filters(
                    'securesubmit_zero_balance_message',
                    sprintf(
                        __('%s has a balance of zero and could not be applied to this order.', 'wc_securesubmit'),
                        $gift_card_object_entered->gift_card_name
                    )
                );

                wc_add_notice($zero_balance_message, 'error');
            } else {
                if (!(is_object($gift_card_object_applied) && count(get_object_vars($gift_card_object_applied)) > 0)) {
                    $gift_card_object_applied = new stdClass;
                }

                $gift_card_object_entered->used_amount                               = $this->giftCardUsageAmount();
                $gift_card_object_applied->{$gift_card_object_entered->gift_card_id} = $gift_card_object_entered;

                WC()->session->set('securesubmit_gift_card_applied', $gift_card_object_applied);
                WC()->session->__unset('securesubmit_gift_card_object');
            }
        }

        return $gift_card_object_applied;
    }

    public function addGiftCards()
    {
        // TODO: Add warnings and success messages
        $gift_cards_allowed = $this->giftCardsAllowed();

        // No gift cards if there are subscription products in the cart
        if ($gift_cards_allowed) {
            $original_total = $this->getOriginalCartTotal();
            $gift_card_object_applied = $this->updateGiftCardCartTotal();

            if (is_object($gift_card_object_applied) && count(get_object_vars($gift_card_object_applied)) > 0) {
                $securesubmit_data = WC()->session->get('securesubmit_data');
                $securesubmit_data->original_total = $original_total;
                WC()->session->set('securesubmit_data', $securesubmit_data);

                $message           = __( 'Total Before Gift Cards', 'wc_securesubmit' );

                $order_total_html  = '<tr id="securesubmit_order_total" class="order-total">';
                $order_total_html .= '<th>' . $message . '</th>';
                $order_total_html .= '<td data-title="' . esc_attr($message) . '">' . wc_price($original_total) . '</td>';
                $order_total_html .= '</tr>';

                echo apply_filters('securesubmit_before_gift_cards_order_total', $order_total_html, $original_total, $message);

                foreach ($gift_card_object_applied as $applied_gift_card) {
                    $remove_link = '<a href="#" id="' . $applied_gift_card->gift_card_id . '" class="securesubmit-remove-gift-card">(Remove)</a>';

                    $gift_card_html  = '<tr class="fee">';
                    $gift_card_html .= '<th>' . $applied_gift_card->gift_card_name . ' ' . $remove_link . '</th>';
                    $gift_card_html .= '<td data-title="' . esc_attr($applied_gift_card->gift_card_name) . '">' . wc_price($applied_gift_card->used_amount) . '</td>';
                    $gift_card_html .= '</tr>';

                    echo apply_filters('securesubmit_gift_card_used_total', $gift_card_html, $applied_gift_card->gift_card_name, $remove_link, $applied_gift_card->used_amount);
                }
            }
        } else {
            $applied_cards = WC()->session->get('securesubmit_gift_card_applied');

            $this->removeAllGiftCardsFromSession();

            if (is_object($applied_cards) && count(get_object_vars($applied_cards)) > 0) {
                wc_add_notice(__('Sorry, we are unable to allow gift cards to be used when purchasing a subscription. Any gift cards already applied to the order have been cleared', 'wc_securesubmit'), 'notice');
            }
        }
    }

    public function removeGiftCard($removed_card = null)
    {
        if (isset($_POST['securesubmit_card_id']) && empty($removed_card)) {
            $removed_card = $_POST['securesubmit_card_id'];
        }

        $applied_cards = WC()->session->get('securesubmit_gift_card_applied');

        unset($applied_cards->{$removed_card});

        if (count((array) $applied_cards) > 0) {
            WC()->session->set('securesubmit_gift_card_applied', $applied_cards);
        } else {
            WC()->session->__unset('securesubmit_gift_card_applied');
        }

        if (isset($_POST['securesubmit_card_id']) && empty($removed_card)) {
            echo '';
            wp_die();
        }
    }

    public function removeGiftCardCode()
    {
        if ((is_cart() || is_checkout()) && $this->allow_gift_cards) {
            wp_enqueue_script(
                'woocommerce_securesubmit_removegiftcard',
                plugins_url('assets/js/removegiftcard.js', dirname(__FILE__)),
                array('jquery'),
                '1.0',
                true
            );
        }
    }

    public function updateOrderTotal($cart_total, $cart_object)
    {
        $gift_cards = WC()->session->get('securesubmit_gift_card_applied');

        if (is_object($gift_cards) && count(get_object_vars($gift_cards)) > 0) {
            $gift_card_totals = $this->getGiftCardTotals();
            $cart_total = $cart_total + $gift_card_totals;
        }

        return $cart_total;
    }

    public function removeAllGiftCardsFromSession()
    {
        WC()->session->__unset('securesubmit_gift_card_applied');
        WC()->session->__unset('securesubmit_gift_card_object');
        WC()->session->__unset('securesubmit_data');
    }

    public function processGiftCardSale($card_number, $card_pin, $used_amount)
    {
        $card            = $this->giftCardObject($card_number, $card_pin);
        $rounded_amount  = round($used_amount, 2);
        $positive_amount = abs($rounded_amount);

        try {
            $response = $this->giftCardService()->sale()
                ->withCard($card)
                ->withAmount($positive_amount)
                ->withCurrency('usd')
                ->execute();
        } catch (HpsException $e) {
            return $e;
        }

        return $response;
    }

    public function processGiftCardVoid($processed_cards, $order_id)
    {
        if (!empty($processed_cards)) {
            foreach ($processed_cards as $card_id => $card) {
                try {
                    $response = $this->giftCardService()->void($card->transaction_id)
                        ->execute();
                } catch (Exception $e) {
                }

                if (isset($response->responseCode) && $response->responseCode === '0') {
                    unset($processed_cards[$card_id]);
                }
            }
        } else {
            $response = false;
            if (OrderUtil::custom_orders_table_usage_is_enabled()) {
                wc_get_order($order_id)->delete_meta_data('_securesubmit_used_card_data');
            } else {
                delete_post_meta($order_id, '_securesubmit_used_card_data');
            }
        }

        return $response;
    }

    public function gift_card_balance($gift_card_number, $gift_card_pin)
    {
        if (empty($gift_card_pin)) {
            return array(
                'error'   => true,
                'message' => "PINs are required. Please enter a PIN and click apply again.",
            );
        }

        $this->gift_card = $this->giftCardObject($gift_card_number, $gift_card_pin);

        try {
            $response = $this->giftCardService()->balance()
                ->withCard($this->gift_card)
                ->execute();
        } catch (HpsException $e) {
            return array(
                'error'   => true,
                'message' => "The gift card number you entered is either incorrect or not yet activated.",
            );
        }

        wc_clear_notices();

        return array(
            'error' => false,
            'message' => $response->balanceAmount,
        );
    }

    public function giftCardsAllowed()
    {
        $subscriptions_active = $this->subscriptionsActive();

        if ($subscriptions_active) {
            if (!empty($_GET['change_payment_method'])) {
                $subscription = new WC_Subscription($_GET['change_payment_method']);
                if (!empty($subscription) && (false !== strpos($subscription->order_type, 'subscription'))) {
                    return false;
                }
            } else {
                return (WC_Subscriptions_Cart::cart_contains_subscription()) ? false : true;
            }
        }

        return true;
    }

    protected function subscriptionsActive()
    {
        if (class_exists('WC_Subscriptions')) {
            return true;
        }

        return false;
    }

    protected function updateGiftCardTotals()
    {
        $gift_cards_applied = WC()->session->get('securesubmit_gift_card_applied');
        $securesubmit_data  = WC()->session->get('securesubmit_data');

        $original_total = $this->getOriginalCartTotal();
        $remaining_total = $original_total;

        if (is_object($gift_cards_applied) && count(get_object_vars($gift_cards_applied)) > 0) {
            foreach ($gift_cards_applied as $gift_card) {
                $order_total_after_gift_card = $remaining_total - $gift_card->temp_balance;

                if ($order_total_after_gift_card > 0) {
                    $gift_card->used_amount = $gift_card->temp_balance;
                } else {
                    $gift_card->used_amount = $remaining_total;
                }

                $gift_cards_applied->{$gift_card->gift_card_id} = $gift_card;
                $remaining_total = $remaining_total - $gift_card->used_amount;
            }
        }

        WC()->session->set('securesubmit_gift_card_applied', $gift_cards_applied);
    }

    protected function getGiftCardTotals()
    {
        $this->updateGiftCardTotals();

        $gift_cards = WC()->session->get('securesubmit_gift_card_applied');

        if (!empty($gift_cards)) {
            $total = 0;

            foreach ($gift_cards as $gift_card) {
                $total -= $gift_card->used_amount;
            }

            return $total;
        }
    }

    protected function giftCardUsageAmount($updated = false)
    {
        if ($updated) {
            $cart_total       = $this->getTotalMinusSecureSubmitGiftCards();
            $gift_card_object = $this->applied_gift_card;
        } else {
            $cart_totals = WC()->session->get('cart_totals');
            $cart_total = round($cart_totals['total'], 2);
            $gift_card_object = WC()->session->get('securesubmit_gift_card_object');
        }

        if (round($gift_card_object->temp_balance, 2) <= $cart_total) {
            $gift_card_applied_amount = $gift_card_object->temp_balance;
        } else {
            $gift_card_applied_amount = $cart_total;
        }

        return $gift_card_applied_amount;
    }

    protected function giftCardName($gift_card_number)
    {
        $digits_to_display = 5;
        $last_digits       = substr($gift_card_number, $digits_to_display * - 1);

        return __( 'Gift Card', 'wc_securesubmit' ) . ' ' . $last_digits;
    }

    protected function convertToNegativeAmount($amount)
    {
        if ($amount > 0) {
            return $amount * - 1;
        }

        return $amount;
    }

    protected function addGiftCardToCartSession()
    {
        $this->gift_card->gift_card_name = $this->giftCardName($this->gift_card->number);
        $this->gift_card->gift_card_id   = sanitize_title($this->gift_card->gift_card_name);
        $this->gift_card->pin            = $this->gift_card_pin_submitted;

        WC()->session->set('securesubmit_gift_card_object', $this->gift_card);
    }

    protected function getCartDiscountTotal()
    {
		$discount = WC()->cart->get_cart_discount_total();
		if(!$discount){
			$discount = WC()->cart->get_discount_total();
		}
        return $discount;
    }

    protected function giftCardService()
    {
        $config                = new HpsServicesConfig();
        $config->secretApiKey  = $this->secret_key;
        $config->versionNumber = '1510';
        $config->developerId   = '002914';

        return new HpsFluentGiftCardService($config);
    }

    protected function giftCardObject($gift_card_number, $gift_card_pin)
    {
        $gift_card         = new HpsGiftCard();
        $gift_card->number = $gift_card_number;
        $gift_card->pin    = $gift_card_pin;

        return $gift_card;
    }

    protected function getOriginalCartTotal()
    {
        $cart_totals = WC()->session->get('cart_totals');
        $discount = $this->getCartDiscountTotal();
        $original_total = round(
            array_sum(
                array(
                    (!empty($cart_totals['subtotal']) ? $cart_totals['subtotal'] : 0),
                    (!empty($cart_totals['subtotal_tax']) ? $cart_totals['subtotal_tax'] : 0),
                    (!empty($cart_totals['shipping_total']) ? $cart_totals['shipping_total'] : 0),
                    (!empty($cart_totals['shipping_tax']) ? $cart_totals['shipping_tax'] : 0),
                    (!empty($cart_totals['fee_total']) ? $cart_totals['fee_total'] : 0),
                    (!empty($cart_totals['fee_tax']) ? $cart_totals['fee_tax'] : 0),
                    (!empty($discount) ? ($discount > 0 ? ($discount * -1) : $discount) : 0),
                )
            ),
            2
        );
        return $original_total;
    }
}
