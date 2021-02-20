<?php
$supportedGateways = array('EcommerceCartAuthorizeNet', 'EcommerceCartPayPalPro', 'EcommerceCartManualGateway');
$errors = array();
$createAccount = false;
$gateway = $data['gateway']; // Object instance inherited from SimpleEcommCartGatewayAbstract
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $cart = EcommerceCartSession::get('EcommerceCartCart');
    $account = false;
    if ($cart->hasMembershipProducts() || $cart->hasSpreedlySubscriptions()) {
        // Set up a new SimpleEcommCartAccount and start by pre-populating the data or load the logged in account
        if ($accountId = EcommerceCartCommon::isLoggedIn()) {
            $account = new SimpleEcommCartAccount($accountId);
        } else {
            $account = new SimpleEcommCartAccount();
            if (isset($_POST['account'])) {
                $acctData = EcommerceCartCommon::postVal('account');
                EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] New Account Data: " . print_r($acctData, true));
                $account->firstName = $acctData['first_name'];
                $account->lastName = $acctData['last_name'];
                $account->email = $acctData['email'];
                $account->username = $acctData['username'];
                $account->password = md5($acctData['password']);
                $errors = $account->validate();
                $jqErrors = $account->getJqErrors();
                if ($acctData['password'] != $acctData['password2']) {
                    $errors[] = __("Passwords do not match", "simpleecommcart");
                    $jqErrors[] = 'account-password';
                    $jqErrors[] = 'account-password2';
                }
                if (count($errors) == 0) {
                    $createAccount = true;
                } // An account should be created and the account data is valid
                
            }
        }
    }
    $gatewayName = EcommerceCartCommon::postVal('simpleecommcart-gateway-name');
    EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] CHECKOUT: with gateway: $gatewayName");
    if (in_array($gatewayName, $supportedGateways)) {
        $gateway->validateCartForCheckout();
        $gateway->setBilling(EcommerceCartCommon::postVal('billing'));
        $gateway->setPayment(EcommerceCartCommon::postVal('payment'));
        if (isset($_POST['sameAsBilling'])) {
            if (EcommerceCartSession::get('EcommerceCartCart')->requireShipping()) {
                $gateway->setShipping(EcommerceCartCommon::postVal('billing'));
            }
        } elseif (isset($_POST['shipping'])) {
            if (EcommerceCartSession::get('EcommerceCartCart')->requireShipping()) {
                $gateway->setShipping(EcommerceCartCommon::postVal('shipping'));
            }
        }
        if (count($errors) == 0) {
            $errors = $gateway->getErrors(); // Error info for server side error code
            $jqErrors = $gateway->getJqErrors(); // Error info for client side error code
            
        }
        if (count($errors) == 0 || 1) {
            // Calculate final billing amounts
            $taxLocation = $gateway->getTaxLocation();
            $tax = $gateway->getTaxAmount();
            $total = EcommerceCartSession::get('EcommerceCartCart')->getGrandTotal() + $tax;
            $subscriptionAmt = EcommerceCartSession::get('EcommerceCartCart')->getSubscriptionAmount();
            $oneTimeTotal = $total - $subscriptionAmt;
            EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Tax: $tax | Total: $total | Subscription Amount: $subscriptionAmt | One Time Total: $oneTimeTotal");
            // Throttle checkout attempts
            if (!EcommerceCartSession::get('SimpleEcommCartCheckoutThrottle')) {
                EcommerceCartSession::set('SimpleEcommCartCheckoutThrottle', SimpleEcommCartCheckoutThrottle::getInstance(), true);
            }
            if (!EcommerceCartSession::get('SimpleEcommCartCheckoutThrottle')->isReady($gateway->getCardNumberTail(), $oneTimeTotal)) {
                $errors[] = "You must wait " . EcommerceCartSession::get('SimpleEcommCartCheckoutThrottle')->getTimeRemaining() . " more seconds before trying to checkout again.";
            }
        }
        // Charge credit card for one time transaction using Authorize.net API
        if (count($errors) == 0 && !EcommerceCartSession::get('SimpleEcommCartInventoryWarning')) {
            // =============================
            // = Start Spreedly Processing =
            // =============================
            if (EcommerceCartSession::get('EcommerceCartCart')->hasSpreedlySubscriptions()) {
                $accountErrors = $account->validate();
                if (count($accountErrors) == 0) {
                    $account->save(); // Save account data locally which will create an account id and/or update local values
                    EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Account data validated and saved for account id: " . $account->id);
                    try {
                        $spreedlyCard = new SpreedlyCreditCard();
                        $spreedlyCard->hydrateFromCheckout();
                        $subscriptionId = EcommerceCartSession::get('EcommerceCartCart')->getSpreedlySubscriptionId();
                        EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] About to create a new spreedly account subscription: Account ID: $account->id | Subscription ID: $subscriptionId");
                        $accountSubscription = new SimpleEcommCartAccountSubscription();
                        $accountSubscription->createSpreedlySubscription($account->id, $subscriptionId, $spreedlyCard);
                    }
                    catch(SpreedlyException $e) {
                        EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Failed to checkout: " . $e->getCode() . ' ' . $e->getMessage());
                        $errors['spreedly failed'] = $e->getMessage();
                        $accountSubscription->refresh();
                        if (empty($accountSubscription->subscriberToken)) {
                            EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] About to delete local account after spreedly failure: " . print_r($account->getData(), true));
                            $account->deleteMe();
                        } else {
                            // Set the subscriber token in the session for repeat attempts to create the subscription
                            EcommerceCartSession::set('SimpleEcommCartSubscriberToken', $account->subscriberToken);
                        }
                    }
                } else {
                    $errors = $account->getErrors();
                    $jqErrors = $account->getJqErrors();
                    EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Account validation failed. " . print_r($errors, true));
                }
            }
            // ===========================
            // = End Spreedly Processing =
            // ===========================
            if (count($errors) == 0) {
                $gatewayName = get_class($gateway);
                $gateway->initCheckout($oneTimeTotal);
                if ($oneTimeTotal > 0 || $gatewayName == 'SimpleEcommCartManualGateway') {
                    $transactionId = $gateway->doSale();
                } else {
                    // Do not attempt to charge $0.00 transactions to live gateways
                    $transactionId = $transId = 'MT-' . EcommerceCartCommon::getRandString();
                }
                if ($transactionId) {
                    // Set order status based on SimpleEcommCart settings
                    $statusOptions = EcommerceCartCommon::getOrderStatusOptions();
                    $status = $statusOptions[0];
                    // Check for account creation
                    $accountId = 0;
                    if ($createAccount) {
                        $account->save();
                    }
                    if ($mp = EcommerceCartSession::get('EcommerceCartCart')->getMembershipProduct()) {
                        $account->attachMembershipProduct($mp, $account->firstName, $account->lastName);
                        $accountId = $account->id;
                    }
                    // Save the order locally
                    $orderId = $gateway->saveOrder($total, $tax, $transactionId, $status, $accountId);
                    EcommerceCartSession::drop('SimpleEcommCartSubscriberToken');
                    EcommerceCartSession::set('order_id', $orderId);
                    $receiptLink = EcommerceCartCommon::getPageLink('store/receipt');
                    $newOrder = new SimpleEcommCartOrder($orderId);
                    // Send email receipts
                    //EcommerceCartCommon::sendEmailReceipts($orderId);
                    EcommerceCartCommon::sendEmailOnPurchase($orderId);
                    // Send buyer to receipt page
                    //$receiptVars = strpos($receiptLink, '?') ? '&' : '?';
                    //$receiptVars .= "ouid=" . $newOrder->ouid;
                    //header("Location: " . $receiptLink . $receiptVars);
                    //clear cart
                    EcommerceCartSession::drop('EcommerceCartCart');
                    //Send buyer to landing page
                    $landing_page_id = 4;
                    if (SimpleEcommCartSetting::getValue('landing_page') != NULL) {
                        $landing_page_id = SimpleEcommCartSetting::getValue('landing_page');
                    }
                    $landing_page_link = get_permalink($landing_page_id);
                    header("Location: " . $landing_page_link);
                } else {
                    // Attempt to discover reason for transaction failure
                    $errors['Could Not Process Transaction'] = $gateway->getTransactionResponseDescription();
                }
            }
        }
    } // End if supported gateway
    
} // End if POST
// Show inventory warning if there is one
if (EcommerceCartSession::get('SimpleEcommCartInventoryWarning')) {
    echo EcommerceCartSession::get('SimpleEcommCartInventoryWarning');
    EcommerceCartSession::drop('SimpleEcommCartInventoryWarning');
}
// Build checkout form action URL
$checkoutPage = get_page_by_path('store/checkout');
$ssl = SimpleEcommCartSetting::getValue('auth_force_ssl');
$url = get_permalink($checkoutPage->ID);
if (EcommerceCartCommon::isHttps()) {
    $url = str_replace('http:', 'https:', $url);
}
// Determine which gateway is in use
$gatewayName = get_class($data['gateway']);
// Make it easier to get to payment, billing, and shipping data
$p = $gateway->getPayment();
$b = $gateway->getBilling();
$s = $gateway->getShipping();
$billingCountryCode = (isset($b['country']) && !empty($b['country'])) ? $b['country'] : EcommerceCartCommon::getHomeCountryCode();
$shippingCountryCode = (isset($s['country']) && !empty($s['country'])) ? $s['country'] : EcommerceCartCommon::getHomeCountryCode();
// Include the HTML markup for the checkout form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include_once (SIMPLEECOMMCART_PATH . '/views/checkout-form.php');
} else {
    include (SIMPLEECOMMCART_PATH . '/views/checkout-form.php');
}
// Include the client side javascript validation
include_once (SIMPLEECOMMCART_PATH . '/views/client/checkout.php');
