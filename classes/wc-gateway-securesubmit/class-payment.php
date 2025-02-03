<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

if (!defined('ABSPATH')) {
    exit();
}

class WC_Gateway_SecureSubmit_Payment
{
    protected $parent = null;

    public function __construct(&$parent = null)
    {
        $this->parent = $parent;
    }

    public function call($orderId)
    {
        $order = wc_get_order($orderId);
        $securesubmit_token = isset($_POST['securesubmit_token'])
            ? $this->parent->cleanValue($_POST['securesubmit_token'])
            : '';

        // used for card saving:
        $last_four = isset($_POST['last_four']) ? $this->parent->cleanValue($_POST['last_four']) : '';
        $exp_month = isset($_POST['exp_month']) ? $this->parent->cleanValue($_POST['exp_month']) : '';
        $exp_year = isset($_POST['exp_year']) ? $this->parent->cleanValue($_POST['exp_year']) : '';
        $card_type = isset($_POST['card_type']) ? $this->parent->cleanValue($_POST['card_type']) : '';

        if (isset($_POST['save_card']) && $_POST['save_card'] === "true") {
            $save_card_to_customer = true;
        } else {
            $save_card_to_customer = false;
        }

        try {
            $this->checkVelocity();

            $post_data = array();

            if (empty($securesubmit_token)) {
                if (isset($_POST['secure_submit_card']) && $_POST['secure_submit_card'] === 'new') {
                    throw new Exception(__(
                        'Please make sure your card details have been entered correctly '
                            . 'and that your browser supports JavaScript.',
                        'wc_securesubmit'
                    ));
                }
            }

            $chargeService = $this->parent->getCreditService();
            $hpsaddress = $this->parent->getOrderAddress($order);
            $cardHolder = $this->parent->getOrderCardHolder($order, $hpsaddress);

            if ($this->parent->paymentaction === 'verify') {
                $save_card_to_customer = true;
            }

            $hpstoken = new HpsTokenData();

            if (
                is_user_logged_in()
                && isset($_POST['secure_submit_card'])
                && $_POST['secure_submit_card'] !== 'new'
            ) {
                $cards = get_user_meta(get_current_user_id(), '_secure_submit_card', false);

                if (isset($cards[$_POST['secure_submit_card']]['token_value'])) {
                    $hpstoken->tokenValue = $cards[$_POST['secure_submit_card']]['token_value'];
                    $save_card_to_customer = false;
                } else {
                    throw new Exception(__('Invalid saved card.', 'wc_securesubmit'));
                }
            } else {
                $hpstoken->tokenValue = $securesubmit_token;
            }

            $orderId = WC_SecureSubmit_Util::getData($order, 'get_id', 'id');

            $details = new HpsTransactionDetails();
            $details->invoiceNumber = $order->get_order_number();

            try {
                if ($this->parent->paymentaction == 'sale') {
                    $builder = $chargeService->charge();
                } elseif ($this->parent->paymentaction == 'verify') {
                    $builder = $chargeService->verify();
                } else {
                    $builder = $chargeService->authorize();
                }

                error_log('payment action: ' . $this->parent->paymentaction);

                if (OrderUtil::custom_orders_table_usage_is_enabled()) {
                    $metaId = wc_get_order($orderId)->update_meta_data(
                        '_heartland_order_payment_action',
                        $this->parent->paymentaction
                    );
                } else {
                    $metaId = update_post_meta($orderId, '_heartland_order_payment_action', $this->parent->paymentaction);
                }

                error_log('payment action meta: ' . print_r($metaId ?: 'false', true));

                $orderTotal = wc_format_decimal(WC_SecureSubmit_Util::getData($order, 'get_total', 'order_total'), 2);

                if ($this->parent->paymentaction == 'verify') {
                    $builder = $builder
                        ->withToken($hpstoken)
                        ->withCardHolder($cardHolder)
                        ->withRequestMultiUseToken($save_card_to_customer);
                } else {
                    $builder = $builder
                        ->withAmount($orderTotal)
                        ->withCurrency(strtolower(get_woocommerce_currency()))
                        ->withToken($hpstoken)
                        ->withCardHolder($cardHolder)
                        ->withRequestMultiUseToken($save_card_to_customer)
                        ->withDetails($details)
                        ->withAllowDuplicates(true)
                        ->withTxnDescriptor($this->parent->txndescriptor);
                }

                $defaultFraudResult = array('allow' => true, 'message' => '');

                /**
                 * Allows for third-party fraud tools to prevent an authorization request from
                 * hitting the Heartland payment gateway.
                 * 
                 * @param string $payment_action Checkout payment action configured by the merchant
                 * @param HpsBuilderAbstract $builder Builder implementation for the current request
                 */
                $preFlightFraudCheck = apply_filters(
                    'wc_securesubmit_pre_flight_fraud_check',
                    $defaultFraudResult,
                    $this->parent->paymentaction,
                    $builder
                );

                if (false === $preFlightFraudCheck['allow']) {
                    throw new Exception($preFlightFraudCheck['message']);
                }

                $response = $builder->execute();

                /**
                 * Allows for third-party fraud tools to take action on an authorization request that
                 * has already been submitted to the gateway and been approved.
                 * 
                 * @param string $payment_action Checkout payment action configured by the merchant
                 * @param HpsAuthorization $response Response implementation for the current response
                 */
                $postFlightFraudCheck = apply_filters(
                    'wc_securesubmit_post_flight_fraud_check',
                    $preFlightFraudCheck,
                    $this->parent->paymentaction,
                    $response
                );

                if (false === $postFlightFraudCheck['allow']) {
                    throw new Exception($postFlightFraudCheck['message']);
                }

                if ($save_card_to_customer) {
                    if (is_user_logged_in()) {
                        $tokenval = $response->tokenData->tokenValue;

                        if ($response->tokenData->responseCode == '0') {
                            try {
                                $uteResponse = $chargeService->updateTokenExpiration()
                                    ->withToken($tokenval)
                                    ->withExpMonth($exp_month)
                                    ->withExpYear($exp_year)
                                    ->execute();
                                $cards = get_user_meta(get_current_user_id(), '_secure_submit_card', false);
                                foreach ($cards as $card) {
                                    if ($card['token_value'] === (string)$tokenval) {
                                        delete_user_meta(get_current_user_id(), '_secure_submit_card', $card);
                                        break;
                                    }
                                }
                            } catch (Exception $e) {
                                /** om nom nom */
                            }
                            switch (strtolower($card_type)) {
                                case 'mastercard':
                                    $card_type = 'MasterCard';
                                    break;
                                default:
                                    $card_type = ucfirst($card_type);
                                    break;
                            }
                            add_user_meta(get_current_user_id(), '_secure_submit_card', array(
                                'last_four' => $last_four,
                                'exp_month' => $exp_month,
                                'exp_year' => $exp_year,
                                'token_value' => (string) $tokenval,
                                'card_type' => $card_type,
                            ));
                        }
                    }
                }

                if ($this->parent->paymentaction == 'verify') {
                    if ($save_card_to_customer) {
                        $tokenval = $response->tokenData->tokenValue;
                    } else {
                        $tokenval = $hpstoken->tokenValue;
                    }

                    if (OrderUtil::custom_orders_table_usage_is_enabled()) {
                        $order = wc_get_order($orderId);

                        $order->update_meta_data('_verify_secure_submit_card', array(
                            'last_four' => $last_four,
                            'exp_month' => $exp_month,
                            'exp_year' => $exp_year,
                            'token_value' => (string) $tokenval,
                            'card_type' => $card_type,
                        ));
                        $order->update_meta_data('_verify_amount', $orderTotal);
                        $order->update_meta_data($orderId, '_verify_currency', strtolower(get_woocommerce_currency()));
                        $order->update_meta_data('_verify_details', $details);
                        $order->update_meta_data('_verify_descriptor', $this->parent->txndescriptor);
                        $order->update_meta_data('_verify_cardholder', $cardHolder);
                    } else {
                        update_post_meta($orderId, '_verify_secure_submit_card', array(
                            'last_four' => $last_four,
                            'exp_month' => $exp_month,
                            'exp_year' => $exp_year,
                            'token_value' => (string) $tokenval,
                            'card_type' => $card_type,
                        ));
                        update_post_meta($orderId, '_verify_amount', $orderTotal);
                        update_post_meta($orderId, '_verify_currency', strtolower(get_woocommerce_currency()));
                        update_post_meta($orderId, '_verify_details', $details);
                        update_post_meta($orderId, '_verify_descriptor', $this->parent->txndescriptor);
                        update_post_meta($orderId, '_verify_cardholder', $cardHolder);
                    }
                }

                if ($this->parent->allow_gift_cards) {
                    $session_applied_gift_card = WC()->session->get('securesubmit_gift_card_applied');
                    if (!empty($session_applied_gift_card)) {
                        $gift_card_order_placement = new giftCardOrderPlacement();
                        $gift_card_order_placement->processGiftCardPayment($orderId);
                    }
                }

                $verb = '';

                if ($this->parent->paymentaction === 'sale') {
                    $verb = 'captured';
                } elseif ($this->parent->paymentaction === 'verify') {
                    $verb = 'verified';
                } else {
                    $verb = 'authorized';
                }

                $order->add_order_note(__(
                    'SecureSubmit payment ' . $verb,
                    'wc_securesubmit'
                ) . ' (Transaction ID: ' . $response->transactionId . ')');
                do_action('wc_securesubmit_order_credit_card_details', $orderId, $card_type, $last_four);
                if ($this->parent->paymentaction !== 'verify') {
                    $order->payment_complete($response->transactionId);
                }
                WC()->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $this->parent->get_return_url($order)
                );
            } catch (HpsException $e) {
                try {
                    $order->add_order_note(__(
                        'SecureSubmit payment failed. Gateway response message: "' .
                            $e->getMessage() . '"',
                        'wc_securesubmit'
                    ));
                } catch (Exception $f) {
                    // eat it
                }

                $this->updateVelocity($e);

                if (
                    $e->getCode() == HpsExceptionCodes::POSSIBLE_FRAUD_DETECTED
                    && $this->parent->email_fraud == 'yes'
                    && $this->parent->fraud_address != ''
                ) {
                    wc_mail(
                        $this->parent->fraud_address,
                        'Suspicious order ' . ($this->parent->allow_fraud == 'yes' ? 'allowed' : 'declined') . ' (' . $orderId . ')',
                        'Hello,<br><br>Heartland has determined that you should review order ' . $orderId . ' for the amount of ' . $orderTotal . '.<p><br></p>' .
                            '<p>You have received this email because you have configured the \'Email store owner on suspicious orders\' settings in the [WooCommerce | Checkout | SecureSubmit] options page.</p>'
                    );
                }

                if (
                    $this->parent->allow_fraud == 'yes'
                    && $e->getCode() == HpsExceptionCodes::POSSIBLE_FRAUD_DETECTED
                ) {
                    // we can skip the card saving: if it fails for possible fraud there will be no token.
                    $order->update_status('on-hold', __('<strong>Accepted suspicious transaction.</strong> Please use Virtual Terminal to review.', 'wc_securesubmit'));
                    $order->reduce_order_stock();
                    WC()->cart->empty_cart();

                    return array(
                        'result' => 'success',
                        'redirect' => $this->parent->get_return_url($order)
                    );
                } else {
                    if ($e->getCode() == HpsExceptionCodes::POSSIBLE_FRAUD_DETECTED) {
                        $this->parent->displayUserError($this->parent->fraud_text);
                    } else {
                        $this->parent->displayUserError($e->getMessage());
                    }

                    return array(
                        'result'   => 'fail',
                        'redirect' => ''
                    );
                }
            }
        } catch (Exception $e) {
            $error = __('Error:', 'wc_securesubmit') . ' "' . (string)$e->getMessage() . '"';
            $this->parent->displayUserError($error);

            return array(
                'result'   => 'fail',
                'redirect' => ''
            );
        }
    }

    private function checkVelocity()
    {
        if ($this->parent->enable_anti_fraud !== true) {
            return;
        }

        $count = (int)$this->getVelocityVar('Count');
        $issuerResponse = (string)$this->getVelocityVar('IssuerResponse');

        if (
            $count
            && $issuerResponse
            && $count >= $this->parent->fraud_velocity_attempts
        ) {
            sleep(5);
            throw new HpsException(sprintf($this->parent->fraud_text, $issuerResponse));
        }
    }

    private function updateVelocity($e)
    {
        if ($this->parent->enable_anti_fraud !== true) {
            return;
        }

        $count = (int)$this->getVelocityVar('Count');
        $issuerResponse = (string)$this->getVelocityVar('IssuerResponse');

        if ($issuerResponse !== $e->getMessage()) {
            $issuerResponse = $e->getMessage();
        }

        $this->setVelocityVar('Count', $count + 1);
        $this->setVelocityVar('IssuerResponse', $issuerResponse);
    }

    private function getVelocityVar($var)
    {
        return get_transient($this->getVelocityVarPrefix() . $var);
    }

    private function setVelocityVar($var, $data = null)
    {
        return set_transient(
            $this->getVelocityVarPrefix() . $var,
            $data,
            MINUTE_IN_SECONDS * $this->parent->fraud_velocity_timeout
        );
    }

    private function getVelocityVarPrefix()
    {
        return sprintf('HeartlandHPS_Velocity%s', md5(WC_Geolocation::get_ip_address()));
    }

    private function getRemoteIP()
    {
        static $remoteIP = '';
        if ($remoteIP !== '') {
            return $remoteIP;
        }

        $remoteIP = $_SERVER['REMOTE_ADDR'];
        if (
            array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)
            && $_SERVER['HTTP_X_FORWARDED_FOR'] != ''
        ) {
            $remoteIPArray = array_values(
                array_filter(
                    explode(
                        ',',
                        $_SERVER['HTTP_X_FORWARDED_FOR']
                    )
                )
            );
            $remoteIP = end($remoteIPArray);
        }

        return $remoteIP;
    }
}
