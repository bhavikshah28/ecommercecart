<?php
class EcommerceCartAdmin {
    public function productsPage() {
        $data = array();
        $subscriptions = array('0' => 'None');
        if (class_exists('SpreedlySubscription')) {
            $spreedlySubscriptions = SpreedlySubscription::getSubscriptions();
            foreach ($spreedlySubscriptions as $s) {
                $subs[(int)$s->id] = (string)$s->name;
            }
            if (count($subs)) {
                asort($subs);
                foreach ($subs as $id => $name) {
                    $subscriptions[$id] = $name;
                }
            }
        } else {
            EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Not loading Spreedly data because Spreedly class has not been loaded");
        }
        if (class_exists('SimpleEcommCartPayPalSubscription')) {
            $ppsub = new SimpleEcommCartPayPalSubscription();
            $data['ppsubs'] = $ppsub->getModels('where id>0', 'order by name');
        }
        $data['subscriptions'] = $subscriptions;
        $view = EcommerceCartCommon::getView('admin/products.php', $data);
        echo $view;
    }
    public function settingsPage() {
        $view = EcommerceCartCommon::getView('admin/settings.php');
        echo $view;
    }
    public function ordersPage() {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && EcommerceCartCommon::getVal('task') == 'delete') {
            $order = new SimpleEcommCartOrder($_GET['id']);
            $order->deleteMe();
            $view = EcommerceCartCommon::getView('admin/orders.php');
        } elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && EcommerceCartCommon::postVal('task') == 'update order status') {
            $order = new SimpleEcommCartOrder($_POST['order_id']);
            $order->updateStatus(EcommerceCartCommon::postVal('status'));
            $view = EcommerceCartCommon::getView('admin/orders.php');
        } elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && EcommerceCartCommon::postVal('task') == 'update delivery status') {
            $order = new SimpleEcommCartOrder($_POST['order_id']);
            $order->updateDeliveryStatus(EcommerceCartCommon::postVal('delivery_status'));
            if (EcommerceCartCommon::postVal('delivery_status') == 'Pending') {
                EcommerceCartCommon::sendEmailOnPending($_POST['order_id']);
            } else if (EcommerceCartCommon::postVal('delivery_status') == 'Shipped') {
                EcommerceCartCommon::sendEmailOnShipped($_POST['order_id']);
            }
            //$view = EcommerceCartCommon::getView('admin/orders.php');
            //$order = new SimpleEcommCartOrder($_POST['order_id']);
            $order->updatePaymentStatus(EcommerceCartCommon::postVal('payment_status'));
            if (EcommerceCartCommon::postVal('payment_status') == 'Refund') {
                EcommerceCartCommon::sendEmailOnRefund($_POST['order_id']);
            } else if (EcommerceCartCommon::postVal('payment_status') == 'Complete') {
                EcommerceCartCommon::sendEmailOnPurchase($_POST['order_id']);
            }
            $view = EcommerceCartCommon::getView('admin/orders.php');
        } elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && EcommerceCartCommon::postVal('task') == 'update payment status') {
        } else {
            $view = EcommerceCartCommon::getView('admin/orders.php');
        }
        echo $view;
    }
    public function inventoryPage() {
        $view = EcommerceCartCommon::getView('admin/inventory.php');
        echo $view;
    }
    public function promotionsPage() {
        $view = EcommerceCartCommon::getView('admin/promotions.php');
        echo $view;
    }
    public function shippingPage() {
        $view = EcommerceCartCommon::getView('admin/shipping.php');
        echo $view;
    }
    public function reportsPage() {
        $view = EcommerceCartCommon::getView('admin/reports.php');
        echo $view;
    }
    public function SimpleEcommCartHelp() {
        $setting = new EcommerceCartSetting();
        define('HELP_URL', "http://simpleecommcartbasic.wordpress.com//simpleecommcart-help/?order_number=" . EcommerceCartSetting::getValue('order_number'));
        $view = EcommerceCartCommon::getView('admin/help.php');
        echo $view;
    }
    public function paypalSubscriptions() {
        $data = array();
        if (false) {
            $sub = new SimpleEcommCartPayPalSubscription();
            $data['subscription'] = $sub;
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && EcommerceCartCommon::postVal('simpleecommcart-action') == 'save paypal subscription') {
                $subData = EcommerceCartCommon::postVal('subscription');
                $sub->setData($subData);
                $errors = $sub->validate();
                if (count($errors) == 0) {
                    $sub->save();
                    $sub->clear();
                    $data['subscription'] = $sub;
                } else {
                    $data['errors'] = $sub->getErrors();
                    $data['jqErrors'] = $sub->getJqErrors();
                }
            } else {
                if (EcommerceCartCommon::getVal('task') == 'edit' && isset($_GET['id'])) {
                    $sub->load(EcommerceCartCommon::getVal('id'));
                    $data['subscription'] = $sub;
                } elseif (EcommerceCartCommon::getVal('task') == 'delete' && isset($_GET['id'])) {
                    $sub->load(EcommerceCartCommon::getVal('id'));
                    $sub->deleteMe();
                    $sub->clear();
                    $data['subscription'] = $sub;
                }
            }
            $data['plans'] = $sub->getModels('where is_paypal_subscription>0', 'order by name');
            $view = EcommerceCartCommon::getView('pro/admin/paypal-subscriptions.php', $data);
            echo $view;
        } else {
            echo '<h2>PayPal Subscriptions</h2><p class="description">This feature is only available in <a href="http://simpleecommcart.com">SimpleEcommCart Professional</a>.</p>';
        }
    }
    public function accountsPage() {
        $data = array();
        if (SIMPLEECOMMCART_PRO) {
            $data['plan'] = new SimpleEcommCartAccountSubscription();
            $data['activeUntil'] = '';
            $account = new SimpleEcommCartAccount();
            if (isset($_REQUEST['simpleecommcart-action']) && $_REQUEST['simpleecommcart-action'] == 'delete_account') {
                // Look for delete request
                if (isset($_REQUEST['accountId']) && is_numeric($_REQUEST['accountId'])) {
                    $account = new SimpleEcommCartAccount($_REQUEST['accountId']);
                    $account->deleteMe();
                    $account->clear();
                }
            } elseif (isset($_REQUEST['accountId']) && is_numeric($_REQUEST['accountId'])) {
                // Look in query string for account id
                $account = new SimpleEcommCartAccount();
                $account->load($_REQUEST['accountId']);
                $data['plan'] = $account->getCurrentAccountSubscription(true); // Return even if plan is expired
                if (date('Y', strtotime($data['plan']->activeUntil)) <= 1970) {
                    $data['activeUntil'] = '';
                } else {
                    $data['activeUntil'] = date('m/d/Y', strtotime($data['plan']->activeUntil));
                }
            }
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && EcommerceCartCommon::postVal('simpleecommcart-action') == 'save account') {
                $acctData = $_POST['account'];
                // Format or unset password
                if (empty($acctData['password'])) {
                    unset($acctData['password']);
                } else {
                    $acctData['password'] = md5($acctData['password']);
                }
                // Strip HTML tags on notes field
                $acctData['notes'] = strip_tags($acctData['notes'], '<a><strong><em>');
                $planData = $_POST['plan'];
                $planData['active_until'] = date('Y-m-d 00:00:00', strtotime($planData['active_until']));
                // Updating an existing account
                if ($acctData['id'] > 0) {
                    $account = new SimpleEcommCartAccount($acctData['id']);
                    $account->setData($acctData);
                    $errors = $account->validate();
                    $sub = new SimpleEcommCartAccountSubscription($planData['id']);
                    $sub->setData($planData);
                    if (count($errors) == 0) {
                        $account->save();
                        $sub->save();
                        $account->clear();
                        $sub->clear();
                    } else {
                        $data['errors'] = $errors;
                        $data['plan'] = $sub;
                        $data['activeUntil'] = date('m/d/Y', strtotime($sub->activeUntil));
                    }
                } else {
                    // Creating a new account
                    $account = new SimpleEcommCartAccount();
                    $account->setData($acctData);
                    $errors = $account->validate();
                    if (count($errors) == 0) {
                        $account->save();
                        $sub = new SimpleEcommCartAccountSubscription();
                        $planData['account_id'] = $account->id;
                        $sub->setData($planData);
                        $sub->billingFirstName = $account->firstName;
                        $sub->billingLastName = $account->lastName;
                        $sub->billingInterval = 'Manual';
                        $sub->save();
                        $account->clear();
                    }
                }
            }
            $data['url'] = EcommerceCartCommon::replaceQueryString('page=simpleecommcart-accounts');
            $data['account'] = $account;
            $data['accounts'] = $account->getModels('where id>0', 'order by last_name');
        }
        $view = EcommerceCartCommon::getView('admin/accounts.php', $data);
        echo $view;
    }
    public function taxPage() {
        $view = EcommerceCartCommon::getView('admin/tax.php');
        echo $view;
    }
}
