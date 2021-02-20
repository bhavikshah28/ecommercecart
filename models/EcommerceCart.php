<?php
class EcommerceCart {
    public function install() {
        global $wpdb;
        $prefix = EcommerceCartCommon::getTablePrefix();
        $sqlFile = EcommerceCart_PATH . '/sql/database.sql';
        $sql = str_replace('[prefix]', $prefix, file_get_contents($sqlFile));
        $queries = explode(";\n", $sql);
        $wpdb->hide_errors();
        foreach ($queries as $sql) {
            if (strlen($sql) > 5) {
                $wpdb->query($sql);
                EcommerceCartCommon::log("Running: $sql");
            }
        }
        require_once (EcommerceCart_PATH . "/getInitPages.php");
        $this->installDefaultSettings();
    }
    public function installDefaultSettings() {
        // Set the version number for this version of SimpleEcommCart
        require_once (EcommerceCart_PATH . "/models/EcommerceCartSetting.php");
        EcommerceCartSetting::setValue('version', ECOMMERCECART_VERSION_NUMBER);
        // Look for hard coded order number
        if (ECOMMERCECART_ORDER_NUMBER !== false) {
            EcommerceCartSetting::setValue('order_number', ECOMMERCECART_ORDER_NUMBER);
            $versionInfo = SimpleEcommCartProCommon::getVersionInfo();
            EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Trying to register order number: " . ECOMMERCECART_ORDER_NUMBER . print_r($versionInfo, true));
            if (!$versionInfo) {
                EcommerceCartSetting::setValue('order_number', '');
            }
        }
        //track inventory
        EcommerceCartSetting::setValue('track_inventory', '1');
        //api key
        if (EcommerceCartSetting::getValue('webservice_iphone_api_key') == NULL) EcommerceCartSetting::setValue('webservice_iphone_api_key', 'wsiak123456');
        $upload_dir = wp_upload_dir();
        $upload_base_path = $upload_dir['basedir'];
        //create wpsimpleecommcart directory inside wpsimpleecommcart
        $wpsimpleecommcart_path = $upload_base_path . DIRECTORY_SEPARATOR . 'ecommercecart' . DIRECTORY_SEPARATOR;
        if (!is_dir($wpsimpleecommcart_path)) {
            mkdir($wpsimpleecommcart_path);
        }
        //digital product forlder path
        $digital_product_folder_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'ecommercecart' . DIRECTORY_SEPARATOR . 'digproduct' . DIRECTORY_SEPARATOR;
        echo $digital_product_folder_path;
        if (!is_dir($digital_product_folder_path)) {
            mkdir($digital_product_folder_path);
        }
        EcommerceCartCommon::log($digital_product_folder_path);
        EcommerceCartSetting::setValue('product_folder', $digital_product_folder_path);
        //tmp forlder path
        $tmp_product_folder_path = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'simpleecommcart' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
        if (!is_dir($tmp_product_folder_path)) {
            mkdir($tmp_product_folder_path);
        }
        EcommerceCartCommon::log($tmp_product_folder_path);
        EcommerceCartSetting::setValue('tmp_folder', $tmp_product_folder_path);
        //inventory stock settings
        EcommerceCartSetting::setValue('out_of_stock_notification', '1');
        EcommerceCartSetting::setValue('out_of_stock_threshhold', '0');
        EcommerceCartSetting::setValue('low_stock_notification', '0');
        //default liscence n
        EcommerceCartSetting::setValue('order_number', '0000000');
        //home country
        if (EcommerceCartSetting::getValue('home_country') == NULL) EcommerceCartSetting::setValue('home_country', 'US~United States');
        //store type
        if (EcommerceCartSetting::getValue('store_type') == NULL) EcommerceCartSetting::setValue('store_type', 'mixed');
        //store page settings
        if (EcommerceCartSetting::getValue('display_products') == NULL) EcommerceCartSetting::setValue('display_products', 'list');
        //status option
        if (EcommerceCartSetting::getValue('delivery_status_options') == NULL) EcommerceCartSetting::setValue('delivery_status_options', 'Pending,Shipped,Complete');
        if (EcommerceCartSetting::getValue('status_options') == NULL) EcommerceCartSetting::setValue('status_options', 'Pending,Complete');
        if (EcommerceCartSetting::getValue('payment_status_options') == NULL) EcommerceCartSetting::setValue('payment_status_options', 'Pending,Complete,Refund');
        if (EcommerceCartSetting::getValue('terms_and_condition') == NULL) EcommerceCartSetting::setValue('terms_and_condition', 'no');
        if (EcommerceCartSetting::getValue('disable_caching') == NULL) EcommerceCartSetting::setValue('disable_caching', '0');
        //default shipping settings
        if (EcommerceCartSetting::getValue('shipping_options_radio') == NULL) EcommerceCartSetting::setValue('shipping_options_radio', '1');
        if (EcommerceCartSetting::getValue('shipping_options_flat_rate_option') == NULL) EcommerceCartSetting::setValue('shipping_options_flat_rate_option', '1');
        //default tax settings
        $t_data['option'] = '1';
        $t_data['flat_rate'] = '';
        $t_data['logic'] = '2';
        $tax_settings_forSave = serialize($t_data);
        if (EcommerceCartSetting::getValue('tax_settings') == NULL) EcommerceCartSetting::setValue('tax_settings', $tax_settings_forSave);
        //default email settings
        if (EcommerceCartSetting::getValue('email_from_name') == NULL) EcommerceCartSetting::setValue('email_from_name ', get_option('blogname'));
        if (EcommerceCartSetting::getValue('email_from_address') == NULL) EcommerceCartSetting::setValue('email_from_address ', get_option('admin_email'));
        if (EcommerceCartSetting::getValue('email_sent_on_purchase') == NULL) EcommerceCartSetting::setValue('email_sent_on_purchase ', 'on');
        if (EcommerceCartSetting::getValue('email_sent_on_purchase_subject') == NULL) EcommerceCartSetting::setValue('email_sent_on_purchase_subject ', 'Thank you for your purchase');
        $mail_body = "Dear {first_name} {last_name}
       
  Thank you for your purchase!
   {product_details}
  Tax:{total_tax}
  Shipping:{total_shipping}
  Total:{total_minus_total_tax} 
  Any items to be shipped will be processed as soon as possible, any items that can be downloaded can be  downloaded using the encrypted links below.
  {product_link_digital_items_only}
  
  Thanks";
        if (EcommerceCartSetting::getValue('email_sent_on_purchase_body') == NULL) EcommerceCartSetting::setValue('email_sent_on_purchase_body ', $mail_body);
    }
    public function init() {
        $this->loadCoreModels();
        $this->initCurrencySymbols();
        // Verify that upgrade has been run
        if (IS_ADMIN) {
            $dbVersion = EcommerceCartSetting::getValue('version');
            //if(version_compare(SIMPLEECOMMCART_VERSION_NUMBER, $dbVersion)) {
            $this->install();
            //}
            
        }
        // Set default admin page roles if there isn't any
        $pageRoles = EcommerceCartSetting::getValue('admin_page_roles');
        if (empty($pageRoles)) {
            $defaultPageRoles = array('orders' => 'edit_pages', 'products' => 'manage_options', 'paypal-subscriptions' => 'manage_options', 'inventory' => 'manage_options', 'promotions' => 'manage_options', 'shipping' => 'manage_options', 'settings' => 'manage_options', 'reports' => 'manage_options', 'accounts' => 'manage_options', 'tax' => 'manage_options');
            EcommerceCartSetting::setValue('admin_page_roles', serialize($defaultPageRoles));
        }
        // Define debugging and testing info
        $simpleecommcartLogging = EcommerceCartSetting::getValue('enable_logging') ? true : false;
        $sandbox = EcommerceCartSetting::getValue('paypal_sandbox') ? true : false;
        define("ECOMMERCECART_DEBUG", $simpleecommcartLogging);
        define("SANDBOX", $sandbox);
        add_action('wp_ajax_check_inventory_on_add_to_cart', array('EcommerceCartAjax', 'checkInventoryOnAddToCart'));
        add_action('wp_ajax_nopriv_check_inventory_on_add_to_cart', array('EcommerceCartAjax', 'checkInventoryOnAddToCart'));
        add_filter('query_vars', array($this, 'addJsTrigger'));
        add_action('template_redirect', array($this, 'jsTriggerCheck'));
        if (IS_ADMIN) {
            //add_action( 'admin_notices', 'simpleecommcart_data_collection' );
            add_action('admin_head', array($this, 'registerBasicScripts'));
            if (strpos($_SERVER['QUERY_STRING'], 'page=ecommercecart') !== false) {
                add_action('admin_head', array($this, 'registerAdminStyles'));
                add_action('admin_init', array($this, 'registerCustomScripts'));
            }
            add_action('admin_menu', array($this, 'buildAdminMenu'));
            add_action('admin_init', array($this, 'addEditorButtons'));
            add_action('admin_init', array($this, 'forceDownload'));
            add_action('wp_ajax_save_settings', array('EcommerceCartAjax', 'saveSettings'));
        } else {
            $this->initShortcodes();
            $this->initCart();
            add_action('wp_enqueue_scripts', array('EcommerceCart', 'enqueueScripts'));
            add_action('wp_head', array($this, 'displayVersionInfo'));
            add_action('template_redirect', array($this, 'dontCacheMeBro'));
            add_action('shutdown', array('EcommerceCartSession', 'touch'));
        }
    }
    public function displayVersionInfo() {
        echo '<meta name="SimpleEcommCartVersion" content="Lite ' . EcommerceCartSetting::getValue('version') . '" />' . "\n";
    }
    public function dontCacheMeBro() {
        if (!IS_ADMIN) {
            global $post;
            $sendHeaders = false;
            if ($disableCaching = EcommerceCartSetting::getValue('disable_caching')) {
                if ($disableCaching === '1') {
                    $cartPage = get_page_by_path('store/cart');
                    $checkoutPage = get_page_by_path('store/checkout');
                    $cartPages = array($checkoutPage->ID, $cartPage->ID);
                    if (isset($post->ID) && in_array($post->ID, $cartPages)) {
                        $sendHeaders = true;
                        //EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] set to send no cache headers for cart pages");
                        
                    } else {
                        if (!isset($post->ID)) {
                            EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] The POST ID is not set");
                        }
                        EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Not a cart page! Therefore need to set the headers to disable cache");
                    }
                } elseif ($disableCaching === '2') {
                    $sendHeaders = true;
                }
            }
            EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Disable caching is: $disableCaching");
            if ($sendHeaders) {
                // EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Sending no cache headers");
                header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                header('Cache-Control: no-store, no-cache, must-revalidate');
                header('Cache-Control: post-check=0, pre-check=0', FALSE);
                header('Pragma: no-cache');
            }
        }
    }
    public function initCart() {
        if (!EcommerceCartSession::get('EcommerceCartCart')) {
            EcommerceCartSession::set('EcommerceCartCart', new EcommerceCartCart());
            EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Creating a new EcommerceCartCart OBJECT for the database session.");
        }
        if (isset($_POST['task'])) {
            if ($_POST['task'] == 'addToCart') {
                EcommerceCartSession::get('EcommerceCartCart')->addToCart();
            } elseif ($_POST['task'] == 'updateCart') {
                EcommerceCartSession::get('EcommerceCartCart')->updateCart();
            }
        } elseif (isset($_GET['task'])) {
            if ($_GET['task'] == 'removeItem') {
                $itemIndex = EcommerceCartCommon::getVal('itemIndex');
                EcommerceCartSession::get('EcommerceCartCart')->removeItem($itemIndex);
            }
        } elseif (isset($_POST['simpleecommcart-action'])) {
            $task = EcommerceCartCommon::postVal('simpleecommcart-action');
            if ($task == 'authcheckout') {
                $inventoryMessage = EcommerceCartSession::get('EcommerceCartCart')->checkCartInventory();
                if (!empty($inventoryMessage)) {
                    EcommerceCartSession::set('SimpleEcommCartInventoryWarning', $inventoryMessage);
                }
            }
        }
    }
    public function forceDownload() {
        ob_end_clean();
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && EcommerceCartCommon::postVal('simpleecommcart-action') == 'export_csv') {
            require_once (ECOMMERCECART_PATH . "/models/SimpleEcommCartExporter.php");
            $start = str_replace(';', '', $_POST['start_date']);
            $end = str_replace(';', '', $_POST['end_date']);
            EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Date parameters for report: START $start and END $end");
            $report = EcommerceCartExporter::exportOrders($start, $end);
            header('Content-Type: application/csv');
            header('Content-Disposition: inline; filename="SimpleEcommCartReport.csv"');
            echo $report;
            die();
        } elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && EcommerceCartCommon::postVal('simpleecommcart-action') == 'download log file') {
            $logFilePath = EcommerceCartLog::getLogFilePath();
            if (file_exists($logFilePath)) {
                $logData = file_get_contents($logFilePath);
                $cartSettings = EcommerceCartLog::getCartSettings();
                header('Content-Description: File Transfer');
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename=SimpleEcommCartLogFile.txt');
                header('Expires: 0');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
                echo $cartSettings . "\n\n";
                echo $logData;
                die();
            }
        }
    }
    public static function enqueueScripts() {
        $url = EcommerceCart_URL . '/simpleecommcart.css';
        wp_enqueue_style('simpleecommcart-css', $url, null, ECOMMERCECART_VERSION_NUMBER, 'all');
        if ($css = EcommerceCartSetting::getValue('styles_url')) {
            wp_enqueue_style('simpleecommcart-custom-css', $css, null, ECOMMERCECART_VERSION_NUMBER, 'all');
        }
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');
        // Include the simpleecommcart javascript library
        $path = EcommerceCart_URL . '/js/simpleecommcart-library.js';
        wp_enqueue_script('simpleecommcart-library', $path, array('jquery'), ECOMMERCECART_VERSION_NUMBER);
    }
    public function registerCustomScripts() {
        if (strpos($_SERVER['QUERY_STRING'], 'page=ecommercecart') !== false) {
            $path = EcommerceCart_URL . '/js/ajax-setting-form.js';
            wp_enqueue_script('ajax-setting-form', $path);
            // Include jquery-multiselect and jquery-ui
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('jquery-ui-dialog');
            $path = ECOMMERCECART_URL . '/js/ui.multiselect.js';
            wp_enqueue_script('jquery-multiselect', $path, null, null, true);
            // Include the jquery table quicksearch library
            $path = ECOMMERCECART_URL . '/js/jquery.quicksearch.js';
            wp_enqueue_script('quicksearch', $path, array('jquery'));
        }
    }
    public function addJsTrigger($vars) {
        $vars[] = 'ecommercecartdialog';
        return $vars;
    }
    public function jsTriggerCheck() {
        if (intval(get_query_var('ecommercecartdialog')) == 1) {
            include (EcommerceCart_PATH . '/js/ecommCartDialog.php');
            exit;
        }
    }
    public function loadCoreModels() {
        require_once (EcommerceCart_PATH . "/models/EcommerceCartBaseModelAbstract.php");
        require_once (EcommerceCart_PATH . "/models/EcommerceCartModelAbstract.php");
        require_once (EcommerceCart_PATH . "/models/EcommerceCartSetting.php");
        require_once (EcommerceCart_PATH . "/models/EcommerceCartAdmin.php");
        require_once (EcommerceCart_PATH . "/models/EcommerceCartAjax.php");
        require_once (EcommerceCart_PATH . "/models/EcommerceCartCommon.php");
        require_once (EcommerceCart_PATH . "/models/EcommerceCartProduct.php");
        require_once (EcommerceCart_PATH . "/models/EcommerceCartProductCategory.php");
        require_once (EcommerceCart_PATH . "/models/EcommerceCartSession.php");
        require_once (EcommerceCart_PATH . "/models/EcommerceCartShortcodeManager.php");
        require_once (EcommerceCart_PATH . "/models/EcommerceCartCart.php");
        require_once (EcommerceCart_PATH . "/models/EcommerceCartCartItem.php");
        require_once (EcommerceCart_PATH . "/models/EcommerceCartButtonManager.php");
        require_once (EcommerceCart_PATH . "/models/payment_gateways/EcommerceCartGatewayAbstract.php");
        require_once (EcommerceCart_PATH . "/models/payment_gateways/EcommerceCartPaypalStandard.php");
        require_once (EcommerceCart_PATH . "/models/EcommerceCartAuthorizeNet.php");
    }
    public function loadExternalModels() {
        require_once (EcommerceCart_PATH . "/models/EcommerceCartSetting.php");
    }
    public function registerBasicScripts() {
?><script type="text/javascript">var wpurl = '<?php echo esc_js(home_url('/')); ?>';</script><?php
    }
    public function initCurrencySymbols() {
        $cs = EcommerceCartSetting::getValue('ECOMMERCECART_CURRENCY_SYMBOL');
        $cs = $cs ? $cs : '$';
        $cst = EcommerceCartSetting::getValue('ECOMMERCECART_CURRENCY_SYMBOL_TEXT');
        $cst = $cst ? $cst : '$';
        //$ccd = EcommerceCartSetting::getValue('currency_code');
        $ccd = EcommerceCartSetting::getValue('ECOMMERCECART_CURRENCY_SYMBOL_TEXT');
        $ccd = $ccd ? $ccd : 'USD';
        define("ECOMMERCECART_CURRENCY_SYMBOL", $cs);
        define("ECOMMERCECART_CURRENCY_SYMBOL_TEXT", $cst);
        define("CURRENCY_CODE", $ccd);
    }
    public function registerAdminStyles() {
        if (strpos($_SERVER['QUERY_STRING'], 'page=ecommercecart') !== false) {
            $widgetCss = WPURL . '/wp-admin/css/widgets.css';
            echo "<link rel='stylesheet' type='text/css' href='$widgetCss' />\n";
            $adminCss = EcommerceCart_URL . '/admin/admin-styles.css';
            echo "<link rel='stylesheet' type='text/css' href='$adminCss' />\n";
            $uiCss = EcommerceCart_URL . '/admin/jquery-ui-1.7.1.custom.css';
            echo "<link rel='stylesheet' type='text/css' href='$uiCss' />\n";
        }
    }
    public function buildAdminMenu() {
        $icon = EcommerceCart_URL . '/images/simpleecommcart_logo_16.gif';
        $pageRoles = EcommerceCartSetting::getValue('admin_page_roles');
        $pageRoles = unserialize($pageRoles);
        add_menu_page('eCommerce', 'eCommerce', $pageRoles['reports'], 'ecommercecart_admin', null, $icon);
        add_submenu_page('ecommercecart_admin', __('Add/Edit Products', 'ecommercecart'), __('Add/Edit Products', 'ecommercecart'), $pageRoles['products'], 'ecommercecart-products', array('EcommerceCartAdmin', 'productsPage'));
        /* add_submenu_page('simpleecommcart_admin', __('PayPal Subscriptions', 'simpleecommcart'), __('PayPal Subscriptions', 'simpleecommcart'), $pageRoles['paypal-subscriptions'], 'simpleecommcart-paypal-subscriptions', array('SimpleEcommCartAdmin', 'paypalSubscriptions'));*/
        // add_submenu_page('ecommercecart_admin', __('Inventory', 'ecommercecart'), __('Inventory', 'ecommercecart'), $pageRoles['inventory'], 'ecommercecart-inventory', array('EcommerceCartAdmin', 'inventoryPage'));
        // add_submenu_page('ecommercecart_admin', __('Coupons', 'ecommercecart'), __('Coupons', 'ecommercecart'), $pageRoles['promotions'], 'ecommercecart-promotions', array('EcommerceCartAdmin', 'promotionsPage'));
        // add_submenu_page('ecommercecart_admin', __('Tax', 'ecommercecart'), __('Tax', 'ecommercecart'), $pageRoles['tax'], 'ecommercecart-tax', array('EcommerceCartAdmin', 'taxPage'));
        // add_submenu_page('ecommercecart_admin', __('Shipping', 'ecommercecart'), __('Shipping', 'ecommercecart'), $pageRoles['shipping'], 'ecommercecart-shipping', array('EcommerceCartAdmin', 'shippingPage'));
        // add_submenu_page('ecommercecart_admin', __('Orders', 'ecommercecart'), __('Orders', 'ecommercecart'), $pageRoles['orders'], 'ecommercecart-orders', array('EcommerceCartAdmin', 'ordersPage'));
        /*  add_submenu_page('simpleecommcart_admin', __('Accounts', 'simpleecommcart'), __('Accounts', 'simpleecommcart'), $pageRoles['accounts'], 'simpleecommcart-accounts', array('SimpleEcommCartAdmin', 'accountsPage'));*/
        add_submenu_page('ecommercecart_admin', __('Settings', 'ecommercecart'), __('Settings', 'ecommercecart'), $pageRoles['settings'], 'ecommercecart-settings', array('EcommerceCartAdmin', 'settingsPage'));
    }
    public function initShortcodes() {
        $sc = new EcommerceCartShortcodeManager();
        //add_shortcode('simpleecommcart_add_to_cart',          array($sc, 'showCartButton'));
        add_shortcode('ecommercecart_show_cart', array($sc, 'showCart'));
        //added by dipankar
        add_shortcode('ecommerce_store_home', array($sc, 'storeHome'));
        add_shortcode('simpleecommcart_checkout_select', array($sc, 'checkoutSelect'));
    }
    public function addEditorButtons() {
        // Don't bother doing this stuff if the current user lacks permissions
        if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) return;
        // Add only in Rich Editor mode
        if (get_user_option('rich_editing') == 'true') {
            add_filter('mce_external_plugins', array('EcommerceCart', 'addTinymcePlugin'));
            add_filter('mce_buttons', array('EcommerceCart', 'registerEditorButton'));
        }
    }
    public function addTinymcePlugin($plugin_array) {
        $plugin_array['ecommercecart'] = EcommerceCart_URL . '/js/editor_plugin_src.js';
        return $plugin_array;
    }
    public function registerEditorButton($buttons) {
        array_push($buttons, "|", "ecommercecart");
        return $buttons;
    }
}
