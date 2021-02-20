<?php
class EcommerceCartShortcodeManager {
    public function storeHome($attrs) {
        $view = EcommerceCartCommon::getView('views/store-home.php', $attrs);
        return $view;
    }
    public function showCart($attrs, $content) {
        if (isset($_REQUEST['ecommcercecart-task']) && $_REQUEST['ecommcercecart-task'] == 'remove-attached-form') {
            $entryId = $_REQUEST['entry'];
            if (is_numeric($entryId)) {
                EcommerceCartSession::get('EcommerceCartCart')->detachFormEntry($entryId);
            }
        }
        $view = EcommerceCartCommon::getView('views/cart.php', $attrs);
        return $view;
    }
    public function checkoutSelect($attrs) {
        return $this->paypalCheckout($attrs);
    }
    public function paypalCheckout($attrs) {
        if (EcommerceCartSession::get('EcommerceCartCart')->countItems() > 0) {
            //if(!EcommerceCartSession::get('EcommerceCartCart')->hasSubscriptionProducts() && !EcommerceCartSession::get('EcommerceCartCart')->hasMembershipProducts()) {
            if (EcommerceCartSession::get('EcommerceCartCart')->getGrandTotal()) {
                $view = EcommerceCartCommon::getView('views/paypal-checkout.php', $attrs);
                return $view;
            }
            //}
            
        }
    }
    public static function showCartButton($attrs, $content) {
        $product = new EcommerceCartProduct();
        $product->loadFromShortcode($attrs);
        return EcommerceCartButtonManager::getCartButton($product, $attrs, $content);
    }
}
