<?php

/**
 * Plugin Name: X-Pay CartaSi e-commerce
 * Plugin URI: 
 * Description: New CartaSi e-commerce payments gateway. Official CartaSi X-Pay plugin.
 * Version: 1.1
 * Author: CartaSi S.p.a.
 * Author URI: https://www.cartasi.it
 * Text Domain: xpay-cartasi-payment-gateway
 *
 * Copyright: © 2016, CartaSi S.p.a.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('wpg_xpay_init')) {

    add_action('plugins_loaded', 'wpg_xpay_init', 0);

    function wpg_xpay_init() {
        load_plugin_textdomain('xpay-cartasi-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/lang');
        if (!class_exists('WC_Payment_Gateway'))
            return;

        class WC_Gateway_XPay extends WC_Payment_Gateway {

            protected $msg = array();

            //STRIGHE
            const TITOLO_MODULO = 'CartaSi E-Commerce';
            const DESCRIZIONE_MODULO = 'CartaSi E-commerce credit/debit card payment gateway.';
            const RESULT_TRANSACTION_KO = "Thank you for shopping with us. However, the transaction has been declined.";
            const RESULT_TRANSACTION_OK = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
            const RESULT_TRANSACTION_OK_2 = "CartaSi payment successful<br>Bank Reply Details<br>";
            const CLICK_BUTTON = 'Please click the button below to pay with CartaSi.';
            const BUTTON_CONTINUE = 'Pay via CartaSi';
            const BUTTON_BACK = 'Cancel order &amp; restore cart';
            //VALORI DI DEFAULT DEL MODULO
            const TITLE_ENABLED = 'Enable/Disable';
            const LABEL_ENABLED = "Enable CartaSi Payment Module.";
            const TITLE_TITOLO = "Title:";
            const DESCRIPTION_TITOLO = 'This controls the title which the user sees during checkout.';
            const DEFAULT_TITOLO = "Payment Cards";
            const TITLE_DESCRIZIONE = "Description:";
            const DESCRIPTION_DESCRIZIONE = 'This controls the description which the user sees during checkout.';
            const DEFAULT_DESCRIZIONE = "Pay securely by credit and debit card or alternative payment methods through CartaSi. Card accepted:";
            const TITLE_CC_ACCETTATE = 'Credit Card Accepted:';
            const DESCRIPTION_CC_ACCETTATE = 'Choose the credit card scheme by which six agreement.';
            const TITLE_ALIAS = 'CartaSi Alias';
            const DESCRIPTION_ALIAS = 'Given to Merchant by CartaSi.';
            const TITLE_MAC = 'CartaSi key MAC';
            //const DESCRIPTION_MAC = 'Given to Merchant by CartaSi.';
            const TITLE_LANGUAGE_FORM = 'Language form';
            const DESCRIPTION_LANGUAGE_FORM = 'Select the language for CartaSi form';
            const TITLE_TEST = 'Enable/Disable TEST Mode';
            const LABEL_TEST = 'Enable CartaSi Payment Module in testing mode.';
            const TITLE_ALIAS_TEST = 'CartaSi Alias TEST';
            const DESCRIPTION_ALIAS_TEST = "Register on <a href='https://ecommerce.cartasi.it/area-test'>ecommerce.cartasi.it/area-test</a> to have TEST's credentials.";
            const TITLE_MAC_TEST = 'CartaSi key MAC TEST';
            //const DESCRIPTION_MAC_TEST = "Register on <a href='https://ecommerce.cartasi.it/area-test'>ecommerce.cartasi.it/area-test</a> to have TEST's credentials.";

            /**
             * Costruttore del plugin
             */
            public function __construct() {

                load_plugin_textdomain('xpay-cartasi-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/lang');

                $this->id = 'xpay';
                $this->has_fields = true;
                $this->method_title = __(self::TITOLO_MODULO, 'xpay-cartasi-payment-gateway');
                $this->method_description = __(self::DESCRIZIONE_MODULO, 'xpay-cartasi-payment-gateway');
                $this->icon = plugins_url('images/logo.jpg', __FILE__);

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->get_option('titolo');
                $this->description = $this->get_option('descrizione');
                if (is_array($this->get_option('cc_accettate'))) {
                    foreach ($this->get_option('cc_accettate') as $cc) {
                        $this->description .= $cc . ", ";
                    }
                    $this->description = substr($this->description, 0, strlen($this->description) - 2) . ".";
                }
                $this->instructions = $this->get_option('instructions', $this->description);

                $this->cartasi_alias = $this->settings['cartasi_alias'];
                $this->cartasi_mac = $this->settings['cartasi_mac'];
                $this->cartasi_alias_test = $this->settings['cartasi_alias_test'];
                $this->cartasi_mac_test = $this->settings['cartasi_mac_test'];
                $this->cartasi_form_language = $this->settings['cartasi_form_language'];
                $this->cartasi_modalita_test = $this->settings['cartasi_modalita_test'];
                $this->url_test = 'https://int-ecommerce.cartasi.it/ecomm/ecomm/DispatcherServlet';
                $this->url_produzione = 'https://ecommerce.cartasi.it/ecomm/ecomm/DispatcherServlet';
                $this->url_notifica = home_url('/wc-api/WC_Gateway_XPay');
                $this->msg['message'] = '';
                $this->msg['class'] = '';

                // Actions
                add_action('woocommerce_api_wc_gateway_xpay', array($this, 'wxpgt_page_ritorno'));
                add_action('valid-cartasi-request', array($this, 'successful_request'));

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
                }

                add_action('woocommerce_thankyou_xpay', array($this, 'wxpgt_page_ringrazia'));
                add_action('woocommerce_receipt_xpay', array($this, 'wxpgt_page_invioform'));
            }

            /**
             * Setta i campi per il form di configurazione
             */
            function init_form_fields() {

                load_plugin_textdomain('xpay-cartasi-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/lang');

                if (get_locale() == 'it_IT') {
                    $LANG_IT = "Italiano";
                    $LANG_EN = "Inglese";
                    $LANG_SP = "Spagnolo";
                    $LANG_FR = "Francese";
                    $LANG_DE = "Tedesco";
                    $LANG_JP = "Giapponese";
                    $LANG_AR = "Arabo";
                    $LANG_CH = "Cinese";
                    $LANG_RU = "Russo";
                    $LANG_AUTO = "Automatico";
                } else {
                    $LANG_IT = "Italian";
                    $LANG_EN = "English";
                    $LANG_SP = "Spanish";
                    $LANG_FR = "Franch";
                    $LANG_DE = "German";
                    $LANG_JP = "Japanese";
                    $LANG_AR = "Arabic";
                    $LANG_CH = "Chinese";
                    $LANG_RU = "Russian";
                    $LANG_AUTO = "Automatic";
                }

                $cartasi_form_language_ids = array(
                    "AUTO" => $LANG_AUTO,
                    "ITA-ENG" => $LANG_IT . "-" . $LANG_EN,
                    "ITA" => $LANG_IT,
                    "ARA" => $LANG_AR,
                    "CHI" => $LANG_CH,
                    "ENG" => $LANG_EN,
                    "RUS" => $LANG_RU,
                    "SPA" => $LANG_SP,
                    "FRA" => $LANG_FR,
                    "GER" => $LANG_DE,
                    "JPG" => $LANG_JP,
                );

                $cartasi_form_cc_accepted = array(
                    "American Express" => "American Express",
                    "Diners" => "Diners",
                    "JCB" => "JCB",
                    "Maestro" => "Maestro",
                    "MasterCard" => "MasterCard",
                    "Masterpass" => "Masterpass",
                    "MyBank" => "MyBank",
                    "MySì" => "MySì",
                    "Visa" => "Visa",
                );

                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __(self::TITLE_ENABLED, 'xpay-cartasi-payment-gateway'),
                        'type' => 'checkbox',
                        'label' => __(self::LABEL_ENABLED, 'xpay-cartasi-payment-gateway'),
                        'default' => 'no'
                    ),
                    'titolo' => array(
                        'title' => __(self::TITLE_TITOLO, 'xpay-cartasi-payment-gateway'),
                        'type' => 'text',
                        'description' => __(self::DESCRIPTION_TITOLO, 'xpay-cartasi-payment-gateway'),
                        'default' => __(self::TITOLO_MODULO, 'xpay-cartasi-payment-gateway')
                    ),
                    'descrizione' => array(
                        'title' => __(self::TITLE_DESCRIZIONE, 'xpay-cartasi-payment-gateway'),
                        'type' => 'textarea',
                        'description' => __(self::DESCRIPTION_DESCRIZIONE, 'xpay-cartasi-payment-gateway'),
                        'default' => __(self::DEFAULT_DESCRIZIONE, 'xpay-cartasi-payment-gateway')
                    ),
                    'cc_accettate' => array(
                        'title' => __(self::TITLE_CC_ACCETTATE, 'xpay-cartasi-payment-gateway'),
                        'type' => 'multiselect',
                        'options' => $cartasi_form_cc_accepted,
                        'description' => __(self::DESCRIPTION_CC_ACCETTATE, 'xpay-cartasi-payment-gateway'),
                        'default' => __('', 'xpay-cartasi-payment-gateway')
                    ),
                    'cartasi_alias' => array(
                        'title' => __(self::TITLE_ALIAS, 'xpay-cartasi-payment-gateway'),
                        'type' => 'text',
                        'description' => __(self::DESCRIPTION_ALIAS, 'xpay-cartasi-payment-gateway')
                    ),
                    'cartasi_mac' => array(
                        'title' => __(self::TITLE_MAC, 'xpay-cartasi-payment-gateway'),
                        'type' => 'text',
                        'description' => __(self::DESCRIPTION_ALIAS, 'xpay-cartasi-payment-gateway')
                    ),
                    'cartasi_form_language' => array(
                        'title' => __(self::TITLE_LANGUAGE_FORM, 'xpay-cartasi-payment-gateway'),
                        'type' => 'select',
                        'options' => $cartasi_form_language_ids,
                        'description' => __(self::DESCRIPTION_LANGUAGE_FORM, 'xpay-cartasi-payment-gateway')
                    ),
                    'cartasi_modalita_test' => array(
                        'title' => __(self::TITLE_TEST, 'xpay-cartasi-payment-gateway'),
                        'type' => 'checkbox',
                        'label' => __(self::LABEL_TEST, 'xpay-cartasi-payment-gateway'),
                        'default' => 'no'
                    ),
                    'cartasi_alias_test' => array(
                        'title' => __(self::TITLE_ALIAS_TEST, 'xpay-cartasi-payment-gateway'),
                        'type' => 'text',
                        'description' => __(self::DESCRIPTION_ALIAS_TEST, 'xpay-cartasi-payment-gateway')
                    ),
                    'cartasi_mac_test' => array(
                        'title' => __(self::TITLE_MAC_TEST, 'xpay-cartasi-payment-gateway'),
                        'type' => 'text',
                        'description' => __(self::DESCRIPTION_ALIAS_TEST, 'xpay-cartasi-payment-gateway')
                    )
                );
            }

            /**
             * pagina di invio form, con tasto se non funziona il redirect JS
             * 
             * @param type $order_id
             */
            function wxpgt_page_invioform($order_id) {
                echo '<p>' . __(self::CLICK_BUTTON, 'xpay-cartasi-payment-gateway') . '</p>';
                echo $this->wxpgt_xpay_genera_form($order_id);
            }

            /**
             * pagina di ringraziamento dopo aver concluso il pagamento
             * 
             * @param type $order
             */
            function wxpgt_page_ringrazia($order) {
                if (!empty($this->instructions))
                    echo wpautop(wptexturize($this->instructions));
            }

            /**
             * Funzione obbigatoria per WP, processa il pagamento e fa il redirect
             * 
             * @param type $order_id
             * @return type
             */
            function process_payment($order_id) {
                $order = new WC_Order($order_id);
                update_post_meta($order_id, '_post_data', $_POST);
                return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true));
            }

            /**
             * pagina di ricezione parametri
             * */
            function wxpgt_page_ritorno() {

                global $woocommerce;

                $msg['class'] = 'error';
                $msg['message'] = self::RESULT_TRANSACTION_KO;

                if (!isset($_REQUEST)) {
                    return false;
                }

                $bank_reply = false;
                foreach ($_REQUEST as $k => $v) {
                    $bank_reply .= $k . ': ' . $v . '<br>';
                }

                if (isset($_REQUEST['codTrans']) && isset($_REQUEST['esito'])) {

                    $codTrans = $_REQUEST['codTrans'];
                    if (strpos($codTrans, '_') !== false) {
                        $order_id = explode('_', $codTrans);
                        $order_id = (int) $order_id[0];
                    } else {
                        $order_id = (int) $codTrans;
                    }

                    $esito = $_REQUEST['esito'];

                    if (isset($_REQUEST['languageId']) && !empty($_REQUEST['languageId'])) {
                        $language_id = strtoupper($_REQUEST['languageId']);
                    } else {
                        $language_id = 'ENG';
                    }

                    if ($order_id != '') {

                        try {

                            $order = new WC_Order($order_id);

                            $session_time = '';
                            $session_order_total = '';
                            if (isset($_REQUEST['session_id'])) {
                                $session_id = explode("_", $_REQUEST['session_id']);
                                $session_time = $session_id[0];
                                $session_order_total = $session_id[1];
                            }

                            $order_total = $order->get_total();

                            $transauthorised = false;

                            if (($order->status !== 'completed') && ($order_total == $session_order_total)) {

                                if ($esito == 'OK') {

                                    $transauthorised = true;
                                    $msg['class'] = 'success';
                                    $msg['message'] = self::RESULT_TRANSACTION_OK;

                                    if ($order->status == 'processing') {
                                        
                                    } else {
                                        $order->payment_complete();
                                        $order->add_order_note(self::RESULT_TRANSACTION_OK_2 . $bank_reply);
                                        $order->add_order_note($msg['message']);
                                        $woocommerce->cart->empty_cart();
                                    }
                                } else {
                                    $msg['class'] = 'error';
                                    $msg['message'] = self::RESULT_TRANSACTION_KO;
                                }

                                if ($transauthorised == false) {
                                    $order->update_status('failed');
                                    $order->add_order_note('Failed');
                                    $order->add_order_note($msg['message']);
                                }
                            }
                        } catch (Exception $e) {
                            $msg['class'] = 'error';
                            $msg['message'] = self::RESULT_TRANSACTION_KO;
                        }
                    }
                }


                if (function_exists('wc_add_notice')) {
                    wc_add_notice($msg['message'], $msg['class']);
                } else {
                    if ($msg['class'] == 'success') {
                        $woocommerce->add_message($msg['message']);
                    } else {
                        $woocommerce->add_error($msg['message']);
                    }
                    $woocommerce->set_messages();
                }

                $redirect_url = $this->get_return_url($order);
                wp_redirect($redirect_url);
                exit;
            }

            /**
             * genera form da inviare
             * 
             * @global type $woocommerce
             * @param string $cod_trans
             * @return string
             */
            public function wxpgt_xpay_genera_form($cod_trans) {

                global $woocommerce;
                $order = new WC_Order($cod_trans);

                $cod_trans = $cod_trans . '_' . time();

                //se è attivata la modalità test
                if ($this->cartasi_modalita_test == "yes") {
                    $form_url = $this->url_test;
                    $alias = $this->cartasi_alias_test;
                    $chiavemac = $this->cartasi_mac_test;
                } else {
                    $form_url = $this->url_produzione;
                    $alias = $this->cartasi_alias;
                    $chiavemac = $this->cartasi_mac;
                }

                $importo = str_replace(",", "", str_replace(".", "", $order->order_total));

                //url di ritorno
                $url = $this->url_notifica;

                //session_id
                $session_id = time() . '_' . $order->order_total;

                //url di annullo
                $url_back = $order->get_cancel_order_url();

                //lingua di default poi sovrascritta da quella scelta dall'utente
                $language_id = 'ENG';
                if (isset($this->cartasi_form_language)) {
                    //se scelta automatica guardo com'è settato il sito
                    if ($this->cartasi_form_language == "AUTO") {
                        $locale = get_locale();
                        switch ($locale) {

                            case 'it_IT':
                                $language_id = 'ITA';
                                break;

                            case 'ar':
                                $language_id = 'ARA';
                                break;

                            case 'zh_CN':
                                $language_id = 'CHI';
                                break;

                            case 'ru_RU':
                                $language_id = 'RUS';
                                break;

                            case 'es_ES':
                                $language_id = 'SPA';
                                break;

                            case 'fr_FR':
                                $language_id = 'FRA';
                                break;

                            case 'de_DE':
                                $language_id = 'GER';
                                break;

                            case 'ja':
                                $language_id = 'GER';
                                break;

                            case 'en_GB':
                            case 'en_US':
                            default:
                                $language_id = 'ENG';
                                break;
                        }
                    } else {
                        $language_id = $this->cartasi_form_language;
                    }
                }

                $divisa = 'EUR';
                $cartasi_mac = sha1('codTrans=' . $cod_trans . 'divisa=' . $divisa . 'importo=' . $importo . $chiavemac);

                $param = array(
                    'alias' => $alias,
                    'importo' => $importo,
                    'divisa' => $divisa,
                    'codTrans' => $cod_trans,
                    'mail' => $order->billing_email,
                    'url' => $url,
                    'session_id' => $session_id,
                    'url_back' => $url_back,
                    'languageId' => $language_id,
                    'mac' => $cartasi_mac,
                    'descrizione' => "Order: " . $order->get_order_number(),
                    'urlpost' => $url,
                    'Note1' => 'woocommerce',
                    'Note2' => wpbo_get_woo_version_number()
                );

                $aParam = array();
                foreach ($param as $key => $value) {
                    $value = addslashes($value);
                    $aParam[] = '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . PHP_EOL;
                }

                $cartasi_xpay_inputs = implode('', $aParam);

                $submit_form = '<form action="' . $form_url . '" method="post" id="cartasi_xpay_payment_form">
                                        ' . $cartasi_xpay_inputs . '
                                        <input type="submit" class="button alt" id="submit_cartasi_payment_form" value="' . __('Pay via CartaSi X-Pay', 'xpay-cartasi-payment-gateway') . '" /> 
                                        <a class="button" style="float:right;" href="' . $url_back . '">' . __('Cancel order &amp; restore cart', 'xpay-cartasi-payment-gateway') . '</a>
                                </form>
                                <script type="text/javascript">
                                    jQuery( document ).ready(function() {
                                        jQuery(function() {
                                            jQuery("body").block(
                                            {
                                                    message: "<img src=\"' . WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/loader_iplus.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />' . __('Thank you for your order. We are now redirecting you to CartaSi X-Pay to make the payment.', 'xpay-cartasi-payment-gateway') . '",
                                                    overlayCSS: {background: "#fff",opacity: 0.6},
                                                    css: {padding:20,textAlign:"center",color:"#555",border:"3px solid #aaa",backgroundColor:"#fff",cursor:"wait",lineHeight:"32px"}
                                            });
                                            jQuery("#submit_cartasi_payment_form").click();
                                        });
                                    });
                                </script>';

                return $submit_form;
            }

        }

        /**
         * Aggiunge il metodo di pagamento a WooCommerce
         * 
         * @param array $methods
         * @return string
         */
        function wxpgt_add_xpay_gateway($methods) {
            $methods[] = 'WC_Gateway_XPay';
            return $methods;
        }

        add_filter('woocommerce_payment_gateways', 'wxpgt_add_xpay_gateway');
    }

}

function wpbo_get_woo_version_number() {
    // If get_plugins() isn't available, require it
    if (!function_exists('get_plugins'))
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

    // Create the plugins folder and file variables
    $plugin_folder = get_plugins('/' . 'woocommerce');
    $plugin_file = 'woocommerce.php';

    // If the plugin version number is set, return it 
    if (isset($plugin_folder[$plugin_file]['Version'])) {
        return $plugin_folder[$plugin_file]['Version'];
    } else {
        // Otherwise return null
        return NULL;
    }
}
