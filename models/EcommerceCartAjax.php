<?php
class EcommerceCartAjax {
    public static function saveSettings() {
        $error = '';
        foreach ($_REQUEST as $key => $value) {
            if ($key[0] != '_' && $key != 'action' && $key != 'submit') {
                if (is_array($value) && $key != 'admin_page_roles') {
                    $value = implode('~', $value);
                }
                if ($key == 'home_country') {
                    $hc = EcommerceCartSetting::getValue('home_country');
                    if ($hc != $value) {
                        $method = new SimpleEcommCartShippingMethod();
                        $method->clearAllLiveRates();
                    }
                } elseif ($key == 'countries') {
                    if ($value === 'All Countries') {
                        //do nothing
                        
                    } else {
                        if (strpos($value, '~') === false) {
                            EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] country list value: $value");
                            $value = '';
                        }
                        if (empty($value) && !empty($_REQUEST['international_sales'])) {
                            $error = "Please select at least one country to ship to.";
                        }
                    }
                } elseif ($key == 'enable_logging' && $value == '1') {
                    try {
                        EcommerceCartLog::createLogFile();
                    }
                    catch(EcommerceCartException $e) {
                        $error = '<span style="color: red;">' . $e->getMessage() . '</span>';
                        EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Caught SimpleEcommCart exception: " . $e->getMessage());
                    }
                } elseif ($key == 'constantcontact_list_ids') {
                } elseif ($key == 'admin_page_roles') {
                    $value = serialize($value);
                    EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Saving Admin Page Roles: " . print_r($value, true));
                }
                EcommerceCartSetting::setValue($key, trim(stripslashes($value)));
            }
        }
        if ($error) {
            $result[0] = 'SimpleEcommCartErrorModal';
            $result[1] = "<strong style='color: red;'>Warning</strong><br/>$error";
        } else {
            $result[0] = 'SimpleEcommCartSuccessModal';
            $result[1] = '<strong>Success</strong><br/>' . $_REQUEST['_success'] . '<br>';
        }
        $out = json_encode($result);
        echo $out;
        die();
    }
    public static function updateGravityProductQuantityField() {
        $formId = EcommerceCartCommon::getVal('formId');
        $gr = new SimpleEcommCartGravityReader($formId);
        $fields = $gr->getStandardFields();
        header('Content-type: application/json');
        echo json_encode($fields);
        die();
    }
    function checkInventoryOnAddToCart() {
        $result = array(true);
        $itemId = EcommerceCartCommon::postVal('simpleecommcartItemId');
        $options = '';
        $optionsMsg = '';
        $opt1 = EcommerceCartCommon::postVal('options_1');
        $opt2 = EcommerceCartCommon::postVal('options_2');
        if (!empty($opt1)) {
            $options = $opt1;
            $optionsMsg = trim(preg_replace('/\s*([+-])[^$]*\$.*$/', '', $opt1));
        }
        if (!empty($opt2)) {
            $options.= '~' . $opt2;
            $optionsMsg.= ', ' . trim(preg_replace('/\s*([+-])[^$]*\$.*$/', '', $opt2));
        }
        $scrubbedOptions = EcommerceCartProduct::scrubVaritationsForIkey($options);
        if (!EcommerceCartProduct::confirmInventory($itemId, $scrubbedOptions)) {
            $result[0] = false;
            $p = new EcommerceCartProduct($itemId);
            /*
            $counts = $p->getInventoryNamesAndCounts();
            $out = '';
            
            if(count($counts)) {
            $out = '<table class="inventoryCountTableModal">';
            $out .= '<tr><td colspan="2"><strong>Currently In Stock</strong></td></tr>';
            foreach($counts as $name => $qty) {
            $out .= '<tr>';
            $out .= "<td>$name</td><td>$qty</td>";
            $out .= '</tr>';
            }
            $out .= '</table>';
            }
            
            $result[1] = $p->name . " " . $optionsMsg . " is&nbsp;out&nbsp;of&nbsp;stock $out";
            */
            $result[1] = $p->name . " (" . $scrubbedOptions . ") is&nbsp;out&nbsp;of&nbsp;stock";
            EcommerceCartCommon::log($result[1]);
        }
        $result = json_encode($result);
        echo $result;
        die();
    }
}
