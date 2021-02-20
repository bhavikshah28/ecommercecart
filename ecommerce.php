<?php
/*
Plugin Name: eCommerce
Plugin URI: http://wordpress.org/
Version: 2.2.5
Author: Bhavik Shah
*/
if (!class_exists('EcommerceCart')) {
    ob_start();
    define('EcommerceCart_PATH', plugin_dir_path(__FILE__));
    define('ECOMMCART_DEBUG', true);
    define('EcommerceCart_URL', plugins_url() . '/' . basename(dirname(__FILE__)));
    require_once (EcommerceCart_PATH . "/models/EcommerceCart.php");
    require_once (EcommerceCart_PATH . "/models/EcommerceCartCommon.php");
    define('ECOMMERCECART_VERSION_NUMBER', '1.0.0');
    define("ECOMMERCECART_ORDER_NUMBER", false);
    define("WPCURL", EcommerceCartCommon::getWpContentUrl());
    define("WPURL", EcommerceCartCommon::getWpUrl());
    define("INFO_ICON", EcommerceCart_URL . '/images/info.png');
    define("SHOPPING_CART_IMAGE", EcommerceCart_URL . '/images/Shoppingcart.png');
    define("BIZCART_BOX_1_IMAGE", EcommerceCart_URL . '/images/more/bizcartbox1.png');
    define("PHOTOBIZCART_BOX_1_IMAGE", EcommerceCart_URL . '/images/more/photobizcartbox1.png');
    define("BUG_IMAGE", EcommerceCart_URL . '/images/more/bug.png');
    define("HELP_IMAGE", EcommerceCart_URL . '/images/more/help.png');
    define("MONEY_IMAGE", EcommerceCart_URL . '/images/more/money.png');
    define("TABLE_IMAGE", EcommerceCart_URL . '/images/more/table.png');
    if (!defined("IS_ADMIN")) {
        define("IS_ADMIN", is_admin());
    }
    $ecommerceCart = new EcommerceCart();
    load_plugin_textdomain('ecommercecart', false, '/' . basename(dirname(__FILE__)) . '/languages/');
    // Register activation hook to install SimpleEcommCart database tables and system code
    register_activation_hook(__FILE__, array($ecommerceCart, 'install'));
    if (function_exists('register_update_hook')) {
        register_update_hook(__FILE__, array($ecommerceCart, 'install'));
    }
    add_action('init', array($ecommerceCart, 'init'));
}
remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
