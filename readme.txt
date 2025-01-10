=== WooCommerce SecureSubmit Gateway ===
Contributors: markhagan
Tags: woocommerce, woo, heartland, payment, tokenize
Tested up to: 6.6.2
Stable tag: trunk
License: GPLv2
License URI: https://github.com/hps/heartland-woocommerce-plugin/blob/master/LICENSE.md

SecureSubmit allows merchants to take PCI-Friendly Credit Card payments on WooCommerce using Heartland Payment Systems Payment Gateway.

== Description ==

This plugin provides a Heartland Payment Systems Gateway addon to the WooCommerce plugin using our SecureSubmit card tokenization library.

Features of SecureSubmit:

* Only two configuration fields: public and secret API key
* Simple to install and configure
* Tokenized payments help reduce PCI Scope
* Enables credit card saving for a friction-reduced checkout

== Installation ==
After you have installed and configured the main WooCommerce plugin use the following steps to install the Heartland Payment Systems Gateway addon:
1. In your WordPress admin, go to Plugins > Add New and search for "WooCommerce SecureSubmit"
2. Click Install, once installed click Activate
3. Configure and Enable the gateway in WooCommerce by adding your public and secret Api Keys

Note: Recent changes to WooCommerce's default configuration options have added a step or two to the initial setup process. Please see the following instructions for help reverting WooCommerce to shortcodes: https://woo.com/document/cart-checkout-blocks-status/#section-6

== How do I get started? ==
Get your Certification (Dev/Sandbox) Api Keys by creating an account on https://developer.heartlandpaymentsystems.com/SecureSubmit/

== Screenshots ==

1. The SecureSubmit gateway configuration screen
2. A view of the front-end payment form
3. A view of the Manage Cards section

== Changelog ==
= 2.3.2 =
* Minor update to configuration-screen descriptions

= 2.3.1 =
* Declare current incompatability with WooCommerce "Cart and Checkout Blocks"

= 2.3.0 =
* Add support for WooCommerce HPOS

= 2.2.3 =
* Allow 8-digit pin HMS gift cards

= 2.2.2 =
* Fix configuration error handlings

= 2.2.1 =
* Various UI improvements

= 2.1.3 =
* Bugfix to disallow gift card payments on orders containing subscriptions

= 2.1.2 =
* Bugfix for some orders that include failed transaction attempts

= 2.1.1 =
* Adds order notes for failed transaction attempts

= 2.1.0 =
* Switch from iframe order button to default WooCommerce order button

= 2.0.3 =
* Fix invalid cache after plugin update
* Improvements to 'capture' payment action

= 2.0.2 =
* Fix saved-card functionality

= 2.0.1 =
* Fix incompatability with some themes/plugins

= 2.0.0 =
* Remove support for non-iframe payment fields
* Add support for transaction optimization
* Utilize latest Heartland tokenization library

= 1.13.1 =
* Security patch

= 1.13.0 =
* Add filters for third-party fraud solutions

= 1.12.2 =
* Get invoice number from order number

= 1.12.1 =
* Ensure card holder data is included in refund requests

= 1.12.0 =
* Remove PayPal
* Prevent payment scripts from loading on order-received page

= 1.11.25 =
* Fix button on cart page to remove applied gift card

= 1.11.24 =
* Correct totals when gift cards are used

= 1.11.23 =
* Script tags updated to opt out Cloudflare Rocket loader
* Add support for verify-only checkouts

= 1.11.22 =
* Subscription renewal verification order note updated

= 1.11.21 =
* Fix UI quirks with storefront theme

= 1.11.20 =
* Ensure subscriptionhas token when a previously stored card is used for payment
* Prevent missing expiration date from preventing checkout submit

= 1.11.19 =
* Fix JavaScript error on order confirmation screen
* Prevent missing expiration date from preventing checkout submit
* Replace 'undefined' with 'missing' in tokenization error messages

= 1.11.18 =
* Fix notices and warnings when applying gift cards

= 1.11.17 =
* Fix issue with applying gift cards.

= 1.11.16 =
* Fix issue with gift cards not being applied correctly at checkout.

= 1.11.15 =
* Fix issue with PayPal passing back the billing country inconsistantly

= 1.11.14 =
* Fix issue with gift card fields still showing for subscription change paymemts.

= 1.11.13 =
* Fix issue with subscriptions. When there is a change payment, the existing post meta record needs to be updated with the new token.
* Fix issue with Avatax extension. The shipping tax was being subtracted from the subtotal incorrectly.
* Pass order id with PayPal transactions.

= 1.11.12 =
* Fix issue with payment updates. Prevent payment method update from charging.
* Add new setting to SecureSubmit that will pass a value as the TxnDescriptor.
* Fixed issue with subscription products. Disable gift cards when users change payment.

= 1.11.11 =
* Fix issues with subscription products. Gift cards will be disabled for subscription products
* Fix issues with subscription products. Prevent amount error when updating payment

= 1.11.10 =
* Fix PayPal button issue in Firefox on cart page
* Fix PayPal checkout issue when Terms and Conditions are set up through WooCommerce

= 1.11.9 =
* Reformat all amounts being sent to the payment gateway to have only two decimal places

= 1.11.8 =
* Update token expiration on token request

= 1.11.7 =
* Fix amount issue with PayPal transactions by normalizing amounts sent to the gateway

= 1.11.6 =
* Fix issue with utility class when testing existence of properties on classes with magic methods

= 1.11.5 =
* Fix issue with WooCommerce 3.0 + WooCommerce Subscriptions that caused renewal payments to fail
* Remove deprecation notice from gift card code directly accessing object property

= 1.11.4 =
* Fix issue with WooCommerce 3.0 changes running with WooCommerce 2.6

= 1.11.3 =
* Add support for WooCommerce 3.0

= 1.11.2 =
* Fix errant PayPal prompt
* Move call of `wc_securesubmit_order_credit_card_details` so that it is called before order confirmation email is sent

= 1.11.1 =
* Add action hook (`wc_securesubmit_order_credit_card_details`) to provide card type and last four digits of the card number upon successful payment

= 1.11.0 =
* Add PayPal Credit as a payment option. All settings for PayPal apply to PayPal Credit
* Fix styling issues with TwentySeventeen theme

= 1.10.2 =
* Fix issue with PayPal starting incontext when not enabled
* Fix capture against partially reversed transactions

= 1.10.1 =
* Fix context error with PHP versions before 5.5

= 1.10.0 =
* Restructured PayPal code for ease of future enhancements
* Add support for PayPal In-Context/One Touch

= 1.9.2 =
* Updated PHP SDK to sanitize the card holder details
* validations are added to check the length of first name, last name, state, city, phone, email and zip values
* Test against Wordpress 4.7
* Correct CSS issue with some themes when CC image is scaled improperly
* Fix issue with capturing credit card authorizations

= 1.9.1 =
* Corrected CSS bug with Rosa theam displaying contents of script tags

= 1.9.0 =
* Fix full reversals
* Add consumer authentication (3DSecure) support
* Fix PayPal Express order totals

= 1.8.5 =
* Begin unit/integration testing
* Add basic velocity checking
* Fix "Capture credit card authorization" action when managing orders
* Use reverse instead of void for active authorizations
* Support partial refunds

= 1.8.4 =
* Remove console.log() call from javascript

= 1.8.3 =
* Fix Javascript with iFrames during order review

= 1.8.2 =
* Fix PayPal issue with discounts

= 1.8.1 =
* Fix issue with invalid header on plugin activation

= 1.8.0 =
* Heartland Gift and Loyalty support
* Fix issues with MasterPass and production accounts

= 1.7.2 =
* Bug Fix - Resolves WooCommerce PayPal collision
* Improvement - Sets Heartland PayPal enabled flag to false for new installs

= 1.7.1 =
* Bug Fix

= 1.7.0 =
* PayPal as a payment method

= 1.6.0 =
* Restructure SecureSubmit gateway class to reflect MasterPass structure
* Enable capture/void functionality through WooCommerce interface

= 1.5.1 =
* Fix missing Subscriptions class

= 1.5.0 =
* Improve WooCommerce Subscriptions 2.0 support to support new features
* Fix MasterPass lightbox firing when MasterPass not selected
* Fix MasterPass warnings with missing variable and missing address
* Fix Javascript library collision with slug used in wp_enqueue_script
* Fix PHP 5.2 compatibility issues with MasterPass feature

= 1.4.0 =
* MasterPass as a payment method
* Fix issue with submitting order review page

= 1.3.5 =
* Force scripts to be loaded with UTF-8 character set
* Fix JS typo in iframe tokenization
* Remove double tokenization
* Remove token value after resubmitting

= 1.3.4 =
* Removed Heartland logo

= 1.3.3 =
* Change bullet to middle dot

= 1.3.2 =
* Fix bug with WooCommerce checkout form submit handlers
* Add support for subscriptions with free trials ($0 initial payment)

= 1.3.1 =
* Fix bug with Javascript removing single-use token too soon after form submission

= 1.3.0 =
* New option to use gateway-hosted iframes for credit card form fields
* New user experience changes in credit card form
* Fixed basic compatibility issues with WooCommerce Subscriptions 2.0. Support for new features has not been completed.

= 1.2.5 =
* Change CERT gateway url

= 1.2.4 =
* Remove possible failure point of using saved card while requesting to save a card. Uses saved card in this instance.
* Fix SimpleXMLElement serialization error when catching HpsException with gateway faultstring

= 1.2.3 =
* Update certification url to support PCI DSS 3.1

= 1.2.2 =
* Changed how errors are reported back

= 1.2.1 =
* Fix bug with refund method name
* Fix SDK bug with older PHP versions

= 1.2.0 =
* Updated SDK
* Added support for recurring payments through WooCommerce Subscriptions
* Added capability for setting custom error messages

= 1.1.1 =
* Ensure SDK isn't already loaded

= 1.1.0 =
* Adding refund capabilities

= 1.0.5 =
* Clearing token variable after form submission

= 1.0.4 =
* Clearing token if it already exists after error

= 1.0.3 =
* Version only update

= 1.0.2 =
* Fixed optional card-saving

= 1.0.1 =
* Made Card-Saving optional
* Reversed order of Public/Secret Keys

= 1.0.0 =
* Initial Release
