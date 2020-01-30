<?php

/**
 * Plugin Name:       Paytriot Payments Gateway
 * Plugin URI:        https://wordpress.org/plugins/woo-paytriot-gateway
 * Description:       Easily enable Paytriot payment methods for WooCommerce.
 * Version:           1.0.0
 * Author:            Hassan Ali
 * Author URI:        https://hassanali.pro
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       woo-paytriot-gateway
 * Domain Path:       /languages
 */
if (!defined('WPINC')) {
    die;
}
if (!function_exists('wpg_gateway_add_gateway_class')) { 
	add_filter('woocommerce_payment_gateways', 'wpg_gateway_add_gateway_class');
	function wpg_gateway_add_gateway_class($gateways)
	{
	    $gateways[] = 'WC_Paytriot_Gateway'; // your class name is here
	    return $gateways;
	}
}
if (!function_exists('wpg_gateway_action_links')) { 
	function wpg_gateway_action_links($links)
	{
	    $plugin_links = array();
	
	    if (function_exists('WC')) {
	        if (version_compare(WC()->version, '2.6', '>=')) {
	            $section_slug = 'paytriot_gateway';
	        } else {
	            $section_slug = strtolower('WC_Paytriot_Gateway');
	        }
	        $setting_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $section_slug);
	        $plugin_links[] = '<a href="' . esc_url($setting_url) . '">' . esc_html__('Settings', 'woo-paytriot-gateway') . '</a>';
	    }
	
	    return array_merge($plugin_links, $links);
	}
	add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wpg_gateway_action_links');
}

// Adding Meta container admin shop_order pages
if (! function_exists('wpg_gateway_add_meta_boxes')) {
	add_action('add_meta_boxes', 'wpg_gateway_add_meta_boxes');
	function wpg_gateway_add_meta_boxes()
	{
	    add_meta_box('wpg_gateway_other_fields', __('Paytriot Invoice', 'woo-paytriot-gateway'), 'wpg_gateway_add_other_fields_for_packaging', 'shop_order', 'side', 'core');
	}
}

// Adding Meta field in the meta container admin shop_order pages
if (! function_exists('wpg_gateway_add_other_fields_for_packaging')) {
    function wpg_gateway_add_other_fields_for_packaging()
    {
        global $post;

        $invoice_number = get_post_meta($post->ID, "wpg_invoice_number", true);
        $invoice_id = get_post_meta($post->ID, "wpg_invoice_id", true);

        echo '<p><strong>  '. __('Invoice Status', 'woo-paytriot-gateway') .': </strong> PAID </p>';
        echo '<p><strong> '. __('Invoice ID', 'woo-paytriot-gateway') .': </strong>'. $invoice_id . '</p>';
        echo '<p><strong> '. __('Invoice Number', 'woo-paytriot-gateway') .': </strong>'. $invoice_number . '</p>';
    }
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
if (! function_exists('wpg_gateway_init_gateway_class')) {
	add_action('plugins_loaded', 'wpg_gateway_init_gateway_class');
	function wpg_gateway_init_gateway_class()
	{
	
	    class WC_Paytriot_Gateway extends WC_Payment_Gateway
	    {
	        /**
	         * __construct function.
	         *
	         * @access public
	         * @return void
	         */
	        public function __construct()
	        {
	            $this->id = 'paytriot_gateway';
	            $this->icon = plugin_dir_url(__FILE__) . "images/paytriot-small.png";
	            $this->has_fields = true;
	            $this->method_title = __('Paytriot Gateway', 'woo-paytriot-gateway');
	            $this->method_description = __('Description of Paytriot payment gateway', 'woo-paytriot-gateway');
	
	            $this->supports = array(
	                'products'
	            );
	
	            // Method with all the options fields
	            $this->init_form_fields();
	
	            // Load the settings.
	            $this->init_settings();
	            $this->title = $this->get_option('title');
	            $this->description = $this->get_option('description');
	            $this->enabled = $this->get_option('enabled');
	            $this->paytriotmode = 'yes' === $this->get_option('paytriotmode');
	            $this->service_url = $this->get_option('paytriot_service_url');
	            $this->merchant_key = $this->get_option('paytriot_merchant_key');
	            $this->secret_key = $this->get_option('paytriot_secret_key');
	            $this->des_key = $this->get_option('paytriot_3des_key');
	            $this->account_id = $this->get_option('paytriot_account_id');
	            $this->deposit_category = $this->get_option('paytriot_deposit_category');
	            $this->paytriot_type = $this->get_option('paytriot_type');
	
	            // This action hook saves the settings
	            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
	
	            // We need custom JavaScript to obtain a token
	            add_action('wp_enqueue_scripts', array( $this, 'payment_scripts' ));
	
	            // You can also register a webhook here
	            add_action('woocommerce_api_paytriot', array( $this, 'wpg_paytriot_webhook' ));
	        }
	
	        /**
	         * process_admin_options function.
	         *
	         * @access public
	         * @return void
	         */
	        function process_admin_options()
	        {
	            parent::process_admin_options();
	        }
	
	        /**
	         * init_form_fields function.
	         * Plugin options
	         *
	         * @access public
	         * @return void
	         */
	        public function init_form_fields()
	        {
	            $this->form_fields = array(
	                'enabled' => array(
	                    'title'       => 'Enable/Disable',
	                    'label'       => 'Enable Paytriot Gateway',
	                    'type'        => 'checkbox',
	                    'description' => '',
	                    'default'     => 'no'
	                ),
	                'title' => array(
	                    'title'       => 'Title',
	                    'type'        => 'text',
	                    'description' => 'This controls the title which the user sees during checkout.',
	                    'default'     => 'Paytriot ',
	                    'desc_tip'    => true,
	                ),
	                'description' => array(
	                    'title'       => 'Description',
	                    'type'        => 'textarea',
	                    'description' => 'This controls the description which the user sees during checkout.',
	                    'default'     => 'Pay with your Paytriot payment gateway.',
	                ),
	                'paytriot_service_url' => array(
	                    'title'       => 'Paytriot Service URL',
	                    'type'        => 'text',
	                ),
	                'paytriot_merchant_key' => array(
	                    'title'       => "Paytriot Merchant's Key",
	                    'type'        => 'text',
	                ),
	                'paytriot_secret_key' => array(
	                    'title'       => "Paytriot Secret key",
	                    'type'        => 'text',
	                ),
	                'paytriot_3des_key' => array(
	                    'title'       => "Paytriot 3DES Key",
	                    'type'        => 'text',
	                ),
	                'paytriot_account_id' => array(
	                    'title'       => "Paytriot Account ID",
	                    'type'        => 'text',
	                ),
	                'paytriot_deposit_category' => array(
	                    'title'     => 'Paytriot Deposit Category',
	                    'type'      => 'select',
	                    'options'   => array(
	                        '1'     => __('Gambling', 'woo-paytriot-gateway'),
	                        '2'     => __('Non gambling', 'woo-paytriot-gateway'),
	                        '3'     => __('Forex', 'woo-paytriot-gateway')
	                    ),
	                    'default'   => '1',
	                ),
	                'paytriot_type' => array(
	                    'title'     => 'Paytriot Request Type',
	                    'type'      => 'select',
	                    'options'   => array(
	                        'creditcard'     => __('Credit Card', 'woo-paytriot-gateway'),
	                        'paypal'     => __('Paypal', 'woo-paytriot-gateway'),
	                        'polipay'     => __('Poli Pay', 'woo-paytriot-gateway'),
	                        'bitcoin'     => __('Bitcoin', 'woo-paytriot-gateway'),
	                        'ethereum'     => __('Ethereum', 'woo-paytriot-gateway'),
	                        'litecoin'     => __('Litecoin', 'woo-paytriot-gateway'),
	                        'ripple'     => __('Ripple', 'woo-paytriot-gateway'),
	                        'bitcoincash'     => __('Bitcoin Cash', 'woo-paytriot-gateway')
	                    ),
	                    'default'   => 'creditcard',
	                ),
	            );
	        }
	
	        /**
	         * payment_scripts function.
	         *
	         * @access public
	         * @return void
	         */
	        public function payment_scripts()
	        {
	            // we need JavaScript to process a token only on cart/checkout pages, right?
	            if (! is_cart() && ! is_checkout() && ! isset($_GET['pay_for_order'])) {
	                return;
	            }
	
	            // if our payment gateway is disabled, we do not have to enqueue JS too
	            if ('no' === $this->enabled) {
	                return;
	            }
	
	            // no reason to enqueue JavaScript if API keys are not set
	            if (empty($this->paytriot_merchant_key) || empty($this->paytriot_service_url) || empty($this->paytriot_secret_key) || empty($this->paytriot_3des_key)) {
	                return;
	            }
	
	            if (! $this->paytriotmode && ! is_ssl()) {
	                return;
	            }
	            // and this is our custom JS in your plugin directory that works with token.js
	            wp_register_script('woocommerce_paytriot', plugins_url('assets/js/paytriot.js', __FILE__), array( 'jquery' ));
	            wp_enqueue_script('woocommerce_paytriot');
	        }
	
	        /**
	         * process_payment function.
	         *
	         * @access public
	         * @param mixed $order_id
	         * @return void
	         */
	        public function process_payment($order_id)
	        {
	            global $woocommerce;
	            // we need it to get any order detailes
	            $order = wc_get_order($order_id);
	
	            include_once("includes/paytriot.php");
	
	            $paytriotAPI = new WPG_Merchant_Api($this->service_url, $this->merchant_key, $this->secret_key, $verboseMode = false, $this->des_key);
	            $createPaymentRequestLink = $paytriotAPI->createPaymentRequestLink(
	                $this->paytriot_type,
	                $order->get_total(),
	                $this->account_id,
	                get_woocommerce_currency(),
	                add_query_arg(
	                    array( 'status' => 'success', 'response_id' => $order->get_id(), 'response_secret' => md5("response_id" . $order->get_id()) ),
	                    site_url('/wc-api/paytriot')
	                ),
	                add_query_arg(
	                    array( 'status' => 'fail', 'response_id' => $order->get_id(), 'response_secret' => md5("response_id" . $order->get_id()) ),
	                    site_url('/wc-api/paytriot')
	                ),
	                $url_api_on_success = null,
	                $url_api_on_fail = null,
	                $no_expiration = 0,
	                $this->deposit_category
	            );
	
	            if (!empty($createPaymentRequestLink) && $createPaymentRequestLink["status"] == "success") {
	                add_post_meta($order_id, 'wpg_paytriot_payment_request_link', $createPaymentRequestLink["payment_request_link"]);
	
	                $redirect_link = $createPaymentRequestLink["payment_request_link_url"];
	                return array(
	                    'result' => 'success',
	                    'redirect' => $redirect_link
	                );
	            } else {
	                wc_add_notice(__('Paytriot API Connection error.', 'woo-paytriot-gateway'), 'error');
	                return;
	            }
	        }
	        /**
	         * paytriot_webhook function.
	         *
	         * @access public
	         * @return void
	         */
	        public function wpg_paytriot_webhook()
	        {
	            global $woocommerce;
	
	            include_once("includes/paytriot.php");
	
	            $order_id = sanitize_key($_GET['response_id']);
	            $status = sanitize_text_field($_GET['status']);
	            $response_secret = sanitize_text_field($_GET['response_secret']);
	
	            if (md5("response_id" . $order_id) == $response_secret) {
	                $order = wc_get_order($order_id);
	                if ($status == "success") {
	                    $order->payment_complete();
	                    $order->reduce_order_stock();
	                    $order->add_order_note(__('Hey, your order is paid! Thank you', 'woo-paytriot-gateway'), true);
	
	                    // Empty cart
	                    $woocommerce->cart->empty_cart();
	                    wp_redirect($this->get_return_url($order));
	                    exit();
	                } else {
	                    $url = get_permalink(get_option('woocommerce_checkout_page_id'));
	                    wc_add_notice(__('Please try again.', 'woo-paytriot-gateway'), 'error');
	                    wp_redirect($url);
	                    exit();
	                }
	            } else {
	                $url = get_permalink(get_option('woocommerce_checkout_page_id'));
	                wc_add_notice(__('Please try again.', 'woo-paytriot-gateway'), 'error');
	                wp_redirect($url);
	                exit();
	            }
	        }
	    }
	}
}
