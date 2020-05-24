<?php
/*
Plugin Name: WooCommerce Extension for Genie
Plugin URI: https://www.jsfullstack.dev/ginie
Description: This extension allows you to accept payments via Genie on your Woocommerce store.
Version: 1.0.0
Author: JS Fullstack Dev
Author URI: https://www.jsfullstack.dev
*/

add_action('plugins_loaded', 'initialize_woocommerce_genie_pg', 0);
define('genie_IMG', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/');

function initialize_woocommerce_genie_pg() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	class WC_Gateway_Genie extends WC_Payment_Gateway {
		public function __construct(){
			
			$this->id 					= 'genie';
            $this->icon 				= WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__ )) . '/assets/logo.png';
			$this->method_title 		= 'Genie';
			$this->method_description	= 'The eCommerce Payment Service Provider of Sri Lanka';
			$this->has_fields 			= false;
			
			$this->init_form_fields();
			$this->init_settings();
						
			$this->title 			= $this->settings['title'];
			$this->description 		= $this->settings['description'];
			$this->base_url 		= $this->settings['base_url'];
			$this->merchant_id 		= $this->settings['merchant_id'];
			$this->secret_code 		= $this->settings['secret_code'];
			$this->currency 		= $this->settings['currency'];
			$this->store_name		= $this->settings['store_name'];
			
            $this->msg['message']	= '';
            $this->msg['class'] 	= '';
			
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_genie_response')); //update for woocommerce >2.0

            if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) ); //update for woocommerce >2.0
                 } else {
                    add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) ); // WC-1.6.6
                }
            add_action('woocommerce_receipt_genie', array(&$this, 'receipt_page'));	
		}

		function init_form_fields(){

			$this->form_fields = array(
				'enabled' => array(
					'title' 		=> __('Enable/Disable', 'woo_genie'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Enable Genie', 'woo_genie'),
					'default' 		=> 'yes',
					'description' 	=> 'Display as a payment option'
				),
				'title' => array(
					'title' 		=> __('Title (*)', 'woo_genie'),
					'type'			=> 'text',
					'default' 		=> __('Pay by Genie', 'woo_genie'),
					'description' 	=> __('This controls the title which the user sees during checkout.', 'woo_genie'),
					'desc_tip' 		=> true
				),
      			'description' => array(
					'title' 		=> __('Description (*)', 'woo_genie'),
					'type' 			=> 'textarea',
					'description' 	=> __('This controls the description which the user sees during checkout.', 'woo_genie'),
					'desc_tip' 		=> true
				),
      			'merchant_id' => array(
					'title' 		=> __('Merchant PG Identifier (*)', 'woo_genie'),
					'type' 			=> 'text',
					'description' 	=> __('Your Genie Merchant PG Identifier'),
					'desc_tip' 		=> true
				),
				'secret_code' => array(
					'title' 		=> __('Secret Code (*)', 'woo_genie'),
					'type' 			=> 'text',
					'description' 	=> __('Secret Code'),
					'desc_tip' 		=> true
				),
				'currency' => array(
					'title' 		=> __('Currency (*)', 'woo_genie'),
					'type' 			=> 'text',
					'description' 	=> __('Currency - LKR or USD'),
					'desc_tip' 		=> true
				),
				'base_url' => array(
					'title' 		=> __('Base URL (*)', 'woo_genie'),
					'type'			=> 'text',
					'description' 	=> __('Base URL.', 'woo_genie'),
					'desc_tip' 		=> true
				),
    			'store_name' => array(
					'title' 		=> __('Store Name (*)', 'woo_genie'),
					'type' 			=> 'text',
					'description' 	=> __('Make of the outlet under the merchant. This outlet should be registered in Genie. Merchant should pass the outlet identifier as the value for store name'),
					'desc_tip' 		=> true
                ),
			);

		}
		
		public function admin_options(){
			echo '<h3>'.__('Genie', 'woo_genie').'</h3>';
			echo '<p>'.__('WooCommerce Payment Plugin of Genie Payment Gateway, The Digital Payment Service Provider of Sri Lanka').'</p>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}

        /**
         *  There are no payment fields, but we want to show the description if set.
         **/
		function payment_fields(){
			if( $this->description ) {
				echo wpautop( wptexturize( $this->description ) );
			}
		}
		
        /**
         * Receipt Page
         **/
		function receipt_page($order){
			echo '<p><strong>' . __('Thank you for your order.', 'woo_genie').'</strong><br/>' . __('The payment page will open soon.', 'woo_genie').'</p>';
			echo $this->generate_genie_form($order);
		}
    
        /**
         * Generate Payment Form
         **/
		function generate_genie_form($order_id){
			global $woocommerce;
			$order = new WC_Order( $order_id );
			
			$notify_url = get_site_url().'/wc-api/'.strtolower(get_class($this));

			$transactionDateTime = date("yy-m-d H:i:s");
			$token = sha1($this->store_name.$this->currency.$this->secret_code.$transactionDateTime.$order->order_total);
			$genie_args = array(
				'merchantPgIdentifier' => $this->merchant_id,
				'token' => $token,
				'storeName' => $this->store_name,
                'successUrl' => $notify_url,
                'errorUrl' => $notify_url,
				'currency' => $this->currency,
				'paymentMethod' => 'Genie',
                'merchantCustomerEmail' => $order->billing_email,
                'merchantCustomerPhone' => $order->billing_phone,
				'transactionDateTime' => $transactionDateTime,
				'orderId' => $order_id,
				'invoiceNumber' => $order_id,
                'chargeTotal' => ($order->order_total),
			);
            
            $products = array();
            foreach ($order->get_items() as $item) {
                $products[] = $item["name"].' '.$item["qty"];
            }
            
            $genie_args['itemList'] = implode( ', ', $products);
			
			
			$genie_args_array = array();
			foreach($genie_args as $key => $value){
				$genie_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
			}

			return '<form action="'.$this->base_url.'" method="post" id="genie_payment_form">
				' . implode('', $genie_args_array) . '
				<input type="submit" class="button-alt" id="submit_genie_payment_form" value="'.__('Pay via Genie', 'woo_genie').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'woo_genie').'</a>
				<script type="text/javascript">
				jQuery(function(){
				jQuery("body").block({
					message: "'.__('Thanks for your order! We are now redirecting you to Genie Payment Gateway to make the payment.', 'woo_genie').'",
					overlayCSS: {
						background		: "#fff",
						opacity			: 0.8
					},
					css: {
						padding			: 20,
						textAlign		: "center",
						color			: "#333",
						border			: "1px solid #eee",
						backgroundColor	: "#fff",
						cursor			: "wait",
						lineHeight		: "32px"
					}
				});
				jQuery("#submit_genie_payment_form").click();});
				</script>
			</form>';
		
		}

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
			global $woocommerce;
            $order = new WC_Order($order_id);
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) {
			  	$checkout_payment_url = $order->get_checkout_payment_url( true );
			} else {
				$checkout_payment_url = get_permalink( get_option ( 'woocommerce_pay_page_id' ) );
			}

			return array(
				'result' => 'success', 
				'redirect' => add_query_arg(
					'order', 
					$order->id, 
					add_query_arg(
						'key', 
						$order->order_key, 
						$checkout_payment_url						
					)
				)
			);
		}

        /**
         * Check for valid gateway server callback
         **/
        function check_genie_response(){
           global $woocommerce;

			if( isset($_REQUEST['invoice_number'])){
				$order_id = $_REQUEST['invoice_number'];

				$redirect_url = get_permalink( get_option('woocommerce_myaccount_page_id') );

				if($order_id != ''){
					
					$order = new WC_Order( $order_id );
					$code = $_REQUEST['code'];
					$status = $_REQUEST['status'];
					$redirect_url = $order->get_checkout_order_received_url();
					
					if( $order->status !=='completed' ){
						if($status=="YES"){
							$this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
							$this->msg['class'] = 'woocommerce-message';
							$order->payment_complete();
							$order->add_order_note('Genie payment successful.<br/>Genie Payment ID: '.$_REQUEST['trx_ref_number']);
							$woocommerce->cart->empty_cart();
						}else{
							$this->msg['class'] = 'woocommerce-error';
							$this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
							$order->add_order_note('Transaction ERROR. Status Code: '. $code);
						}
					}
				}

				wp_redirect( $redirect_url );
                exit;
			}

		}
		
		function genie_get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				if ($indent) {
                	$has_parent = $page->post_parent;
                	while($has_parent) {
                    	$prefix .=  ' - ';
                    	$next_page = get_post($has_parent);
                    	$has_parent = $next_page->post_parent;
                	}
            	}
            	$page_list[$page->ID] = $prefix . $page->post_title;
        	}
        	return $page_list;
		}
	}
	
	function woocommerce_add_gateway_genie_gateway($methods) {
		$methods[] = 'WC_Gateway_Genie';
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_genie_gateway' );
	
}

add_filter( 'plugin_action_links', 'genie_add_action_plugin', 10, 5 );
function genie_add_action_plugin( $actions, $plugin_file ) {
	static $plugin;
	if (!isset($plugin)){
		$plugin = plugin_basename(__FILE__);
		if ($plugin == $plugin_file) {
			$settings = array('settings' => '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_gateway_genie">' . __('Settings') . '</a>');
			$actions = array_merge($settings, $actions);
		}
		return $actions;
	}
}
