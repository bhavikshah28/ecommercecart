<?php
class EcommerceCartPayPalStandard {
    protected $_log;
    public function __construct() {
        $paypalUrl = EcommerceCartCommon::getPayPalUrl();
        EcommerceCartCommon::log("Constructing PayPal Gateway for IPN using URL: $paypalUrl");
    }
    /**
     * Save a PayPal IPN order from a Website Payments Pro cart sale.
     *
     * @param array $pp Urldecoded array of IPN key value pairs
     */
    public function saveOrder($pp) {
        EcommerceCartCommon::log("inside:saveOrder");
        global $wpdb;
        $orderTable = EcommerceCartCommon::getTableName('orders');
        // Make sure the transaction id is not already in the database
        $sql = "SELECT count(*) as c from $orderTable where trans_id=%s";
        $sql = $wpdb->prepare($sql, $pp['txn_id']);
        $count = $wpdb->get_var($sql);
        EcommerceCartCommon::log("count: $count");
        EcommerceCartCommon::log("line 26");
        if ($count < 1) {
            $hasDigital = false;
            EcommerceCartCommon::log("line 29");
            // Calculate subtotal
            $subtotal = 0;
            $numCartItems = ($pp['num_cart_items'] > 0) ? $pp['num_cart_items'] : 1;
            EcommerceCartCommon::log("line 32");
            for ($i = 1;$i <= $numCartItems;$i++) {
                EcommerceCartCommon::log("line 35");
                // PayPal in not consistent in the way it passes back the item amounts
                $amt = 0;
                if (isset($pp['mc_gross' . $i])) {
                    EcommerceCartCommon::log("line 38");
                    $amt = $pp['mc_gross' . $i];
                } elseif (isset($pp['mc_gross_' . $i])) {
                    $amt = $pp['mc_gross_' . $i];
                    EcommerceCartCommon::log("line 44");
                }
                EcommerceCartCommon::log("line 46");
                $subtotal+= $amt;
            }
            EcommerceCartCommon::log("line 49");
            $statusOptions = EcommerceCartCommon::getOrderStatusOptions();
            $status = $statusOptions[0];
            $ouid = md5($pp['txn_id'] . $pp['address_street']);
            // Parse custom value
            $referrer = false;
            $deliveryMethod = $pp['custom'];
            if (strpos($deliveryMethod, '|') !== false) {
                list($deliveryMethod, $referrer, $gfData, $coupon) = explode('|', $deliveryMethod);
            }
            EcommerceCartCommon::log("line 61");
            // Parse Gravity Forms ids
            $gfIds = array();
            if (!empty($gfData)) {
                $forms = explode(',', $gfData);
                foreach ($forms as $f) {
                    list($itemId, $formEntryId) = explode(':', $f);
                    $gfIds[$itemId] = $formEntryId;
                }
            }
            EcommerceCartCommon::log("line 66");
            // Look for discount amount
            $discount = 0;
            if (isset($pp['discount'])) {
                $discount = $pp['discount'];
            }
            // Look for coupon code
            $coupon_code = "none";
            if (isset($coupon) && $coupon != "") {
                $coupon_code = $coupon;
            }
            EcommerceCartCommon::log("will prepare data now:line 78");
            $data = array('bill_first_name' => $pp['address_name'], 'bill_address' => $pp['address_street'], 'bill_city' => $pp['address_city'], 'bill_state' => $pp['address_state'], 'bill_zip' => $pp['address_zip'], 'bill_country' => $pp['address_country'], 'ship_first_name' => $pp['address_name'], 'ship_address' => $pp['address_street'], 'ship_city' => $pp['address_city'], 'ship_state' => $pp['address_state'], 'ship_zip' => $pp['address_zip'], 'ship_country' => $pp['address_country'], 'shipping_method' => $deliveryMethod, 'email' => $pp['payer_email'], 'phone' => $pp['contact_phone'], 'shipping' => $pp['mc_handling'], 'tax' => $pp['tax'], 'subtotal' => $subtotal, 'total' => $pp['mc_gross'], 'coupon' => $coupon_code, 'discount_amount' => $discount, 'trans_id' => $pp['txn_id'], 'ordered_on' => date('Y-m-d H:i:s', EcommerceCartCommon::localTs()), 'status' => $status, 'ouid' => $ouid, 'payment_method' => 'Paypal', 'payment_status' => 'Complete');
            if (EcommerceCartSession::get('SimpleEcommCartCart')->isAllDigital()) {
                $data['delivery_status'] = 'Complete';
            } else if (EcommerceCartSession::get('SimpleEcommCartCart')->isAllPhysical()) {
                $data['delivery_status'] = 'Pending';
            } else {
                $data['delivery_status'] = 'Pending';
            }
            // Verify the first items in the IPN are for products managed by SimpleEcommCart. It could be an IPN from some other type of transaction.
            $productsTable = EcommerceCartCommon::getTableName('products');
            $orderItemsTable = EcommerceCartCommon::getTableName('order_items');
            $sql = "SELECT id from $productsTable where item_number = '" . $pp['item_number1'] . "'";
            $productId = $wpdb->get_var($sql);
            if (!$productId) {
                throw new Exception("This is not an IPN that should be managed by SimpleEcommCart");
            }
            // Look for the 100% coupons shipping item and move it back to a shipping costs rather than a product
            if ($data['shipping'] == 0) {
                for ($i = 1;$i <= $numCartItems;$i++) {
                    $itemNumber = strtoupper($pp['item_number' . $i]);
                    if ($itemNumber == 'SHIPPING') {
                        $data['shipping'] = isset($pp['mc_gross_' . $i]) ? $pp['mc_gross_' . $i] : $pp['mc_gross' . $i];
                    }
                }
            }
            $wpdb->insert($orderTable, $data);
            $orderId = $wpdb->insert_id;
            $product = new EcommerceCartProduct();
            for ($i = 1;$i <= $numCartItems;$i++) {
                EcommerceCartCommon::log("line 147");
                $sql = "SELECT id from $productsTable where item_number = '" . $pp['item_number' . $i] . "'";
                $productId = $wpdb->get_var($sql);
                if ($productId > 0) {
                    $product->load($productId);
                    $product_name = $product->name;
                    EcommerceCartCommon::log("Product Name: $product_name");
                    // Decrement inventory
                    $info = $pp['item_name' . $i];
                    if (strpos($info, '(') > 0) {
                        $start = strpos($info, '(');
                        $end = strpos($info, ')');
                        $length = $end - $start;
                        $variation = substr($info, $start + 1, $length - 1);
                        EcommerceCartCommon::log("PayPal Variation Information: $variation\n$info");
                    }
                    $qty = $pp['quantity' . $i];
                    EcommerceCartProduct::decrementInventory($productId, $variation, $qty);
                    EcommerceCartCommon::log("line 169");
                    if ($hasDigital == false) {
                        $hasDigital = $product->isDigital();
                    }
                    // PayPal is not consistent in the way it passes back the item amounts
                    $amt = 0;
                    if (isset($pp['mc_gross' . $i])) {
                        $amt = $pp['mc_gross' . $i];
                    } elseif (isset($pp['mc_gross_' . $i])) {
                        $amt = $pp['mc_gross_' . $i] / $pp['quantity' . $i];
                    }
                    // Look for Gravity Form Entry ID
                    $formEntryId = '';
                    if (is_array($gfIds) && !empty($gfIds) && isset($gfIds[$i])) {
                        $formEntryId = $gfIds[$i];
                    }
                    EcommerceCartCommon::log("line 189");
                    $duid = md5($pp['txn_id'] . '-' . $orderId . '-' . $productId);
                    $data = array('order_id' => $orderId, 'product_id' => $productId, 'item_number' => $pp['item_number' . $i], 'product_name' => $product_name, 'product_price' => $amt, 'description' => $pp['item_name' . $i], 'quantity' => $pp['quantity' . $i], 'duid' => $duid, 'form_entry_ids' => $formEntryId);
                    $wpdb->insert($orderItemsTable, $data);
                    EcommerceCartCommon::log("line 203");
                }
            }
            EcommerceCartCommon::log('$coupon_code = ' . $coupon_code);
            EcommerceCartCommon::log("will reedem coupon now");
            if ($coupon_code != 'none') {
                $promo = new SimpleEcommCartPromotion();
                $promo->loadByCode($coupon_code);
                if ($promo) {
                    SimpleEcommCartPromotion::redeemCoupon($promo->code);
                }
            }
            EcommerceCartCommon::log("calling EcommerceCartCommon::sendEmailOnPurchase");
            // Handle email receipts
            EcommerceCartCommon::sendEmailOnPurchase($orderId);
            // Process affiliate reward if necessary
            if ($referrer) {
                EcommerceCartCommon::awardCommission($orderId, $referrer);
            }
        } // end transaction id check
        
    }
}
