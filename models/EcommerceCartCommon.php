<?php
class EcommerceCartCommon {
    public static function getTablePrefix() {
        global $wpdb;
        return $wpdb->prefix . "ecommerce_";
    }
    public static function getTableName($name, $prefix = 'ecommerce_') {
        global $wpdb;
        return $wpdb->prefix . $prefix . $name;
    }
    public static function localTs($timestamp = null) {
        $timestamp = isset($timestamp) ? $timestamp : time();
        if (date('T') == 'UTC') {
            $timestamp+= (get_option('gmt_offset') * 3600);
        }
        return $timestamp;
    }
    public static function getCustomCountries() {
        $list = false;
        $setting = new EcommerceCartSetting();
        $countries = EcommerceCartSetting::getValue('countries');
        if ($countries) {
            $countries = explode(',', $countries);
            foreach ($countries as $c) {
                list($code, $name) = explode('~', $c);
                $list[$code] = $name;
            }
        }
        return $list;
    }
    public static function getPageLink($path) {
        $page = get_page_by_path($path);
        $link = get_permalink($page->ID);
        if ($_SERVER['SERVER_PORT'] == '443') {
            $link = str_replace('http://', 'https://', $link);
        }
        return $link;
    }
    public static function getButtonId($id) {
        global $simpleecommcartCartButtons;
        $idSuffix = '';
        if (!is_array($simpleecommcartCartButtons)) {
            $simpleecommcartCartButtons = array();
        }
        if (in_array($id, array_keys($simpleecommcartCartButtons))) {
            $simpleecommcartCartButtons[$id]+= 1;
        } else {
            $simpleecommcartCartButtons[$id] = 1;
        }
        if ($simpleecommcartCartButtons[$id] > 1) {
            $idSuffix = '_' . $simpleecommcartCartButtons[$id];
        }
        $id.= $idSuffix;
        return $id;
    }
    public static function isLoggedIn() {
        $isLoggedIn = false;
        if (EcommerceCartSession::get('SimpleEcommCartAccountId') && is_numeric(EcommerceCartSession::get('SimpleEcommCartAccountId')) && SimpleEcommCartSession::get('SimpleEcommCartAccountId') > 0) {
            $isLoggedIn = EcommerceCartSession::get('SimpleEcommCartAccountId');
        }
        return $isLoggedIn;
    }
    public static function endSlashPath($path) {
        if (stripos(strrev($path), '/') !== 0) {
            $path.= '/';
        }
        return $path;
    }
    public static function log($data) {
        if (defined('ECOMMCART_DEBUG') && ECOMMCART_DEBUG) {
            $tz = '- Server time zone ' . date('T');
            $date = date('m/d/Y g:i:s a', self::localTs());
            $header = strpos($_SERVER['REQUEST_URI'], 'wp-admin') ? "\n\n======= ADMIN REQUEST =======\n[LOG DATE: $date $tz]\n" : "\n\n[LOG DATE: $date $tz]\n";
            $filename = EcommerceCart_PATH . "/log.txt";
            if (file_exists($filename) && is_writable($filename)) {
                file_put_contents($filename, $header . $data, FILE_APPEND);
            }
        }
    }
    public static function getRandNum($numChars = 7) {
        $id = '';
        mt_srand((double)microtime() * 1000000);
        for ($i = 0;$i < $numChars;$i++) {
            $id.= chr(mt_rand(ord(0), ord(9)));
        }
        return $id;
    }
    public static function getRandStr($length = 16) {
        $string = '';
        $chrs = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        for ($i = 0;$i < $length;$i++) {
            $loc.= mt_rand(0, strlen($chrs) - 1);
            $string.= $chrs[$loc];
        }
        return $string;
    }
    public static function camel2human($val) {
        $val = strtolower(preg_replace('/([A-Z])/', ' $1', $val));
        return $val;
    }
    public static function isDateValid($str) {
        $stamp = strtotime($str);
        if (!is_numeric($stamp)) return false;
        //checkdate(month, day, year)
        if (checkdate(date('m', $stamp), date('d', $stamp), date('Y', $stamp))) {
            return true;
        }
        return false;
    }
    public static function formatPhone($phone) {
        $phone = preg_replace("/[^0-9]/", "", $phone);
        if (strlen($phone) == 7) return preg_replace("/([0-9]{3})([0-9]{4})/", "$1-$2", $phone);
        elseif (strlen($phone) == 10) return preg_replace("/([0-9]{3})([0-9]{3})([0-9]{4})/", "($1) $2-$3", $phone);
        else return $phone;
    }
    public static function showValue($value) {
        echo isset($value) ? $value : '';
    }
    public static function getView($filename, $data = null) {
        $unregistered = '';
        if (strpos($filename, 'admin') !== false) {
            if (!self::isRegistered()) {
                $hardCoded = '';
                $settingsUrl = get_bloginfo('wpurl') . '/wp-admin/admin.php?page=ecommercecart-settings';
                if (false !== false) {
                    $hardCoded = "<br/><br/><em>An invalid order number has be hard coded<br/> into the main simpleecommcart.php file.</em>";
                }
                $unregistered = '
            <div class="unregistered">
              This is not a registered copy of SimpleEcommCart.<br/>
              Please <a href="' . $settingsUrl . '">enter your order number</a> or
              <a href="http://simpleecommcartbasic.wordpress.com//pricing">buy a license for your site.</a> ' . $hardCoded . '
            </div>
          ';
            }
        }
        $customView = false;
        $themeDirectory = get_stylesheet_directory();
        $approvedOverrideFiles = array("views/cart.php", "views/cart-button.php", "views/account-login.php", "views/checkout-form.php", "views/cart-sidebar.php", "views/cart-sidebar-advanced.php", "views/receipt.php", "views/receipt_print_version.php", "pro/views/terms.php");
        $overrideDirectory = $themeDirectory . "/ecommercecart-templates";
        $userViewFile = $overrideDirectory . "/$filename";
        //SimpleEcommCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Override: $overrideDirectory\nUser view file: $userViewFile");
        if (file_exists($userViewFile) && in_array($filename, $approvedOverrideFiles)) {
            // File exists, make sure it's not empty
            if (filesize($userViewFile) > 10) {
                // It's not empty
                $customView = true;
                $customViewPath = $userViewFile;
            } else {
                EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] User file was empty: $userViewFile");
            }
        } else {
            EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] File exists: " . var_export(file_exists($userViewFile), true) . "\n");
            EcommerceCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Approved Override: " . var_export(in_array($filename, $approvedOverrideFiles), true));
        }
        // Check for override and confirm we have a registered plugin
        if ($customView && self::isRegistered()) {
            // override is present
            $filename = $customViewPath;
        } else {
            // no override, render standard view
            $filename = EcommerceCart_PATH . "/$filename";
        }
        ob_start();
        include $filename;
        $contents = ob_get_contents();
        ob_end_clean();
        return $unregistered . $contents;
    }
    public function isRegistered() {
        $setting = new EcommerceCartSetting();
        $orderNumber = EcommerceCartSetting::getValue('order_number');
        $isRegistered = ($orderNumber !== false) ? true : false;
        return $isRegistered;
    }
    public static function getCountryName($code) {
        $countries = self::getCountries(true);
        return $countries[$code];
    }
    public static function getVal($key) {
        $value = false;
        if (isset($_GET[$key])) {
            $value = strip_tags($_GET[$key]);
            $value = preg_replace('/[<>\\\\\/]/', '', $value);
        }
        return $value;
    }
    public static function postVal($key) {
        $value = false;
        if (isset($_POST[$key])) {
            $value = self::deepTagClean($_POST[$key]);
        }
        return $value;
    }
    public static function deepTagClean(&$data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = self::deepTagClean($value);
                } else {
                    $value = strip_tags($value);
                    $data[$key] = preg_replace('/[<>\\\\\/]/', '', $value);
                }
            }
        } else {
            $data = strip_tags($data);
            $data = preg_replace('/[<>\\\\\/]/', '', $data);;
        }
        return $data;
    }
    public static function getCountries($all = false) {
        $countries = array('AD' => 'Andorra', 'AE' => 'United Arab Emirates', 'AF' => 'Afghanistan', 'AG' => 'Antigua and Barbuda', 'AI' => 'Anguilla', 'AL' => 'Albania', 'AM' => 'Armenia', 'AN' => 'Netherlands Antilles', 'AO' => 'Angola', 'AQ' => 'Antarctica', 'AR' => 'Argentina', 'AS' => 'American Samoa', 'AT' => 'Austria', 'AU' => 'Australia', 'AW' => 'Aruba', 'AX' => 'Aland Islands', 'AZ' => 'Azerbaijan', 'BA' => 'Bosnia and Herzegovina', 'BB' => 'Barbados', 'BD' => 'Bangladesh', 'BE' => 'Belgium', 'BF' => 'Burkina Faso', 'BG' => 'Bulgaria', 'BH' => 'Bahrain', 'BI' => 'Burundi', 'BJ' => 'Benin', 'BM' => 'Bermuda', 'BN' => 'Brunei Darussalam', 'BO' => 'Bolivia', 'BR' => 'Brazil', 'BS' => 'Bahamas', 'BT' => 'Bhutan', 'BV' => 'Bouvet Island', 'BW' => 'Botswana', 'BY' => 'Belarus', 'BZ' => 'Belize', 'CA' => 'Canada', 'CC' => 'Cocos (Keeling) Islands', 'CD' => 'Democratic Republic of the Congo', 'CF' => 'Central African Republic', 'CG' => 'Congo', 'CH' => 'Switzerland', 'CI' => "Cote D'Ivoire (Ivory Coast)", 'CK' => 'Cook Islands', 'CL' => 'Chile', 'CM' => 'Cameroon', 'CN' => 'China', 'CO' => 'Colombia', 'CR' => 'Costa Rica', 'CS' => 'Serbia and Montenegro', 'CU' => 'Cuba', 'CV' => 'Cape Verde', 'CX' => 'Christmas Island', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DE' => 'Germany', 'DJ' => 'Djibouti', 'DK' => 'Denmark', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'DZ' => 'Algeria', 'EC' => 'Ecuador', 'EE' => 'Estonia', 'EG' => 'Egypt', 'EH' => 'Western Sahara', 'ER' => 'Eritrea', 'ES' => 'Spain', 'ET' => 'Ethiopia', 'FI' => 'Finland', 'FJ' => 'Fiji', 'FK' => 'Falkland Islands (Malvinas)', 'FM' => 'Federated States of Micronesia', 'FO' => 'Faroe Islands', 'FR' => 'France', 'FX' => 'France, Metropolitan', 'GA' => 'Gabon', 'GB' => 'Great Britain (UK)', 'GD' => 'Grenada', 'GE' => 'Georgia', 'GF' => 'French Guiana', 'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GL' => 'Greenland', 'GM' => 'Gambia', 'GN' => 'Guinea', 'GP' => 'Guadeloupe', 'GQ' => 'Equatorial Guinea', 'GR' => 'Greece', 'GS' => 'S. Georgia and S. Sandwich Islands', 'GT' => 'Guatemala', 'GU' => 'Guam', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HK' => 'Hong Kong', 'HM' => 'Heard Island and McDonald Islands', 'HN' => 'Honduras', 'HR' => 'Croatia (Hrvatska)', 'HT' => 'Haiti', 'HU' => 'Hungary', 'ID' => 'Indonesia', 'IE' => 'Ireland', 'IL' => 'Israel', 'IN' => 'India', 'IO' => 'British Indian Ocean Territory', 'IQ' => 'Iraq', 'IR' => 'Iran', 'IS' => 'Icelandv', 'IT' => 'Italy', 'JM' => 'Jamaica', 'JO' => 'Jordan', 'JP' => 'Japan', 'KE' => 'Kenya', 'KG' => 'Kyrgyzstan', 'KH' => 'Cambodia', 'KI' => 'Kiribativ', 'KM' => 'Comoros', 'KN' => 'Saint Kitts and Nevis', 'KP' => 'Korea (North)', 'KR' => 'Korea (South)', 'KW' => 'Kuwait', 'KY' => 'Cayman Islands', 'KZ' => 'Kazakhstan', 'LA' => 'Laos', 'LB' => 'Lebanon', 'LC' => 'Saint Lucia', 'LI' => 'Liechtenstein', 'LK' => 'Sri Lanka', 'LR' => 'Liberia', 'LS' => 'Lesotho', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'LV' => 'Latvia', 'LY' => 'Libya', 'MA' => 'Morocco', 'MC' => 'Monaco', 'MD' => 'Moldova', 'MG' => 'Madagascar', 'MH' => 'Marshall Islands', 'MK' => 'Macedonia', 'ML' => 'Mali', 'MM' => 'Myanmar', 'MN' => 'Mongolia', 'MO' => 'Macao', 'MP' => 'Northern Mariana Islands', 'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MS' => 'Montserrat', 'MT' => 'Malta', 'MU' => 'Mauritius', 'MV' => 'Maldives', 'MW' => 'Malawi', 'MX' => 'Mexico', 'MY' => 'Malaysia', 'MZ' => 'Mozambique', 'NA' => 'Namibia', 'NC' => 'New Caledonia', 'NE' => 'Niger', 'NF' => 'Norfolk Island', 'NG' => 'Nigeria', 'NI' => 'Nicaragua', 'NL' => 'Netherlands', 'NO' => 'Norway', 'NP' => 'Nepal', 'NR' => 'Nauru', 'NU' => 'Niue', 'NZ' => 'New Zealand (Aotearoa)', 'OM' => 'Oman', 'PA' => 'Panama', 'PE' => 'Peru', 'PF' => 'French Polynesia', 'PG' => 'Papua New Guinea', 'PH' => 'Philippines', 'PK' => 'Pakistan', 'PL' => 'Poland', 'PM' => 'Saint Pierre and Miquelon', 'PN' => 'Pitcairn', 'PR' => 'Puerto Rico', 'PS' => 'Palestinian Territory', 'PT' => 'Portugal', 'PW' => 'Palau', 'PY' => 'Paraguay', 'QA' => 'Qatar', 'RE' => 'Reunion', 'RO' => 'Romania', 'RU' => 'Russian Federation', 'RW' => 'Rwanda', 'SA' => 'Saudi Arabia', 'SB' => 'Solomon Islands', 'SC' => 'Seychelles', 'SD' => 'Sudan', 'SE' => 'Sweden', 'SG' => 'Singapore', 'SH' => 'Saint Helena', 'SI' => 'Slovenia', 'SJ' => 'Svalbard and Jan Mayen', 'SK' => 'Slovakia', 'SL' => 'Sierra Leone', 'SM' => 'San Marino', 'SN' => 'Senegal', 'SO' => 'Somalia', 'SR' => 'Suriname', 'ST' => 'Sao Tome and Principe', 'SU' => 'USSR (former)', 'SV' => 'El Salvador', 'SY' => 'Syria', 'SZ' => 'Swaziland', 'TC' => 'Turks and Caicos Islands', 'TD' => 'Chad', 'TF' => 'French Southern Territories', 'TG' => 'Togo', 'TH' => 'Thailand', 'TJ' => 'Tajikistan', 'TK' => 'Tokelau', 'TL' => 'Timor-Leste', 'TM' => 'Turkmenistan', 'TN' => 'Tunisia', 'TO' => 'Tonga', 'TP' => 'East Timor', 'TR' => 'Turkey', 'TT' => 'Trinidad and Tobago', 'TV' => 'Tuvalu', 'TW' => 'Taiwan', 'TZ' => 'Tanzania', 'UA' => 'Ukraine', 'UG' => 'Uganda', 'UK' => 'United Kingdom', 'UM' => 'United States Minor Outlying Islands', 'US' => 'United States', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VA' => 'Vatican City State (Holy See)', 'VC' => 'Saint Vincent and the Grenadines', 'VE' => 'Venezuela', 'VG' => 'Virgin Islands (British)', 'VI' => 'Virgin Islands (U.S.)', 'VN' => 'Viet Nam', 'VU' => 'Vanuatu', 'WF' => 'Wallis and Futuna', 'WS' => 'Samoa', 'YE' => 'Yemen', 'YT' => 'Mayotte', 'YU' => 'Yugoslavia (former)', 'ZA' => 'South Africa', 'ZM' => 'Zambia', 'ZR' => 'Zaire (former)', 'ZW' => 'Zimbabwe');
        // Put home country at the top of the list
        $setting = new EcommerceCartSetting();
        $home_country = EcommerceCartSetting::getValue('home_country');
        if ($home_country) {
            list($code, $name) = explode('~', $home_country);
            $countries = array_merge(array($code => $name), $countries);
        } else {
            $countries = array_merge(array('US' => 'United States'), $countries);
        }
        $customCountries = self::getCustomCountries();
        if ($all) {
            if (is_array($customCountries)) {
                foreach ($customCountries as $code => $name) {
                    unset($countries[$code]);
                }
                foreach ($countries as $code => $name) {
                    $customCountries[$code] = $name;
                }
                $countries = $customCountries;
            }
        } else {
            $international = EcommerceCartSetting::getValue('international_sales');
            if ($international) {
                if ($customCountries) {
                    //SimpleEcommCartCommon::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Got some custom countries: " . print_r($customCountries, true));
                    $countries = $customCountries;
                }
            } else {
                $countries = array_slice($countries, 0, 1, true);
            }
        }
        return $countries;
    }
    public static function getWpContentUrl() {
        $wpurl = WP_CONTENT_URL;
        if (empty($wpurl)) {
            $wpurl = get_bloginfo('wpurl') . '/wp-content';
        }
        if (self::isHttps()) {
            $wpurl = str_replace('http://', 'https://', $wpurl);
        }
        return $wpurl;
    }
    /**
     * Return the WordPress URL taking into account HTTPS
     */
    public static function getWpUrl() {
        $wpurl = get_bloginfo('wpurl');
        if (self::isHttps()) {
            $wpurl = str_replace('http://', 'https://', $wpurl);
        }
        return $wpurl;
    }
    public static function isHttps() {
        $isHttps = false;
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
            $isHttps = true;
        }
        return $isHttps;
    }
    public static function getPayPalCurrencyCodes() {
        $currencies = array('United States Dollar' => 'USD', 'Australian Dollar' => 'AUD', 'Canadian Dollar' => 'CAD', 'Czech Koruna' => 'CZK', 'Danish Krone' => 'DKK', 'Euro' => 'EUR', 'Hong Kong Dollar' => 'HKD', 'Hungarian Forint' => 'HUF', 'Israeli New Sheqel' => 'ILS', 'Japanese Yen' => 'JPY', 'Malaysian Ringgit' => 'MYR', 'Mexican Peso' => 'MXN', 'Norwegian Krone' => 'NOK', 'New Zealand Dollar' => 'NZD', 'Philippine Peso' => 'PHP', 'Polish Zloty' => 'PLN', 'Pound Sterling' => 'GBP', 'Singapore Dollar' => 'SGD', 'Swedish Krona' => 'SEK', 'Swiss Franc' => 'CHF', 'Taiwan New Dollar' => 'TWD', 'Thai Baht' => 'THB');
        return $currencies;
    }
    public static function getZones($code = 'all') {
        $zones = array();
        $au = array();
        $au['NSW'] = 'New South Wales';
        $au['NT'] = 'Northern Territory';
        $au['QLD'] = 'Queensland';
        $au['SA'] = 'South Australia';
        $au['TAS'] = 'Tasmania';
        $au['VIC'] = 'Victoria';
        $au['WA'] = 'Western Australia';
        $zones['AU'] = $au;
        $br = array();
        $br['Acre'] = 'Acre';
        $br['Alagoas'] = 'Alagoas';
        $br['Amapa'] = 'Amapa';
        $br['Amazonas'] = 'Amazonas';
        $br['Bahia'] = 'Bahia';
        $br['Ceara'] = 'Ceara';
        $br['Distrito Federal'] = 'Distrito Federal';
        $br['Espirito Santo'] = 'Espirito Santo';
        $br['Goias'] = 'Goias';
        $br['Maranhao'] = 'Maranhao';
        $br['Mato Grosso'] = 'Mato Grosso';
        $br['Mato Grosso do Sul'] = 'Mato Grosso do Sul';
        $br['Minas Gerais'] = 'Minas Gerais';
        $br['Para'] = 'Para';
        $br['Paraiba'] = 'Paraiba';
        $br['Parana'] = 'Parana';
        $br['Pernambuco'] = 'Pernambuco';
        $br['Piaui'] = 'Piaui';
        $br['Rio de Janeiro'] = 'Rio de Janeiro';
        $br['Rio Grande do Norte'] = 'Rio Grande do Norte';
        $br['Rio Grande do Sul'] = 'Rio Grande do Sul';
        $br['Rondonia'] = 'Rondonia';
        $br['Roraima'] = 'Roraima';
        $br['Santa Catarina'] = 'Santa Catarina';
        $br['Sao Paulo'] = 'Sao Paulo';
        $br['Sergipe'] = 'Sergipe';
        $br['Tocantins'] = 'Tocantins';
        $zones['BR'] = $br;
        $ca = array();
        $ca['AB'] = 'Alberta';
        $ca['BC'] = 'British Columbia';
        $ca['MB'] = 'Manitoba';
        $ca['NB'] = 'New Brunswick';
        $ca['NF'] = 'Newfoundland';
        $ca['NT'] = 'Northwest Territories';
        $ca['NS'] = 'Nova Scotia';
        $ca['NU'] = 'Nunavut';
        $ca['ON'] = 'Ontario';
        $ca['PE'] = 'Prince Edward Island';
        $ca['PQ'] = 'Quebec';
        $ca['SK'] = 'Saskatchewan';
        $ca['YT'] = 'Yukon Territory';
        $zones['CA'] = $ca;
        $us = array();
        $us['AL'] = 'Alabama';
        $us['AK'] = 'Alaska ';
        $us['AZ'] = 'Arizona';
        $us['AR'] = 'Arkansas';
        $us['CA'] = 'California ';
        $us['CO'] = 'Colorado';
        $us['CT'] = 'Connecticut';
        $us['DE'] = 'Delaware';
        $us['DC'] = 'D. C.';
        $us['FL'] = 'Florida';
        $us['GA'] = 'Georgia ';
        $us['HI'] = 'Hawaii';
        $us['ID'] = 'Idaho';
        $us['IL'] = 'Illinois';
        $us['IN'] = 'Indiana';
        $us['IA'] = 'Iowa';
        $us['KS'] = 'Kansas';
        $us['KY'] = 'Kentucky';
        $us['LA'] = 'Louisiana';
        $us['ME'] = 'Maine';
        $us['MD'] = 'Maryland';
        $us['MA'] = 'Massachusetts';
        $us['MI'] = 'Michigan';
        $us['MN'] = 'Minnesota';
        $us['MS'] = 'Mississippi';
        $us['MO'] = 'Missouri';
        $us['MT'] = 'Montana';
        $us['NE'] = 'Nebraska';
        $us['NV'] = 'Nevada';
        $us['NH'] = 'New Hampshire';
        $us['NJ'] = 'New Jersey';
        $us['NM'] = 'New Mexico';
        $us['NY'] = 'New York';
        $us['NC'] = 'North Carolina';
        $us['ND'] = 'North Dakota';
        $us['OH'] = 'Ohio';
        $us['OK'] = 'Oklahoma';
        $us['OR'] = 'Oregon';
        $us['PA'] = 'Pennsylvania';
        $us['RI'] = 'Rhode Island';
        $us['SC'] = 'South Carolina';
        $us['SD'] = 'South Dakota';
        $us['TN'] = 'Tennessee';
        $us['TX'] = 'Texas';
        $us['UT'] = 'Utah';
        $us['VT'] = 'Vermont';
        $us['VA'] = 'Virginia';
        $us['WA'] = 'Washington';
        $us['WV'] = 'West Virginia';
        $us['WI'] = 'Wisconsin';
        $us['WY'] = 'Wyoming';
        $us['AE'] = 'Armed Forces';
        $zones['US'] = $us;
        switch ($code) {
            case 'AU':
                $zones = $zones['AU'];
            break;
            case 'BR':
                $zones = $zones['BR'];
            break;
            case 'CA':
                $zones = $zones['CA'];
            break;
            case 'US':
                $zones = $zones['US'];
            break;
        }
        return $zones;
    }
    public static function getRandString($length = 14) {
        $string = '';
        $chrs = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        for ($i = 0;$i < $length;$i++) {
            $loc = mt_rand(0, strlen($chrs) - 1);
            $string.= $chrs[$loc];
        }
        return $string;
    }
}
