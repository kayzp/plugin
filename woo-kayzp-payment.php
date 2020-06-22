<?php
/*
 * Plugin Name: WooCommerce kayzp Payment Gateway
 * Plugin URI: https://freelancehunt.com/freelancer/sapronov.html
 * Description: Custom Payment Gateway plugin 
 * Author: Oleksandr Kiiashko
 * Author URI: https://freelancehunt.com/freelancer/sapronov.html
 * Version: 1.0
 *

 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'kayzp_add_gateway_class' );
function kayzp_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_kayzp_Gateway'; // your class name is here
	return $gateways;
}
 
/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'kayzp_init_gateway_class' );
function kayzp_init_gateway_class() {

	add_shortcode( 'kayzp_payment' , array( WC_kayzp_Gateway, 'kayzp_payment_shortcode' ));
 
	class WC_kayzp_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor
 		 */
 		public function __construct() {
 
		 	$this->id = 'kayzp'; // payment gateway plugin ID
			$this->icon = '/wp-content/plugins/woo-kayzp-payment/pic.png'; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->method_title = 'Kayzp Gateway';
			$this->method_description = 'This is custom gateway plugin for medguard.shop'; // will be displayed on the options page
		 
			// gateways can support subscriptions, refunds, saved payment methods. We use only simple products
			$this->supports = array(
				'products'
			);
		 
			// Method with all the options fields
			$this->init_form_fields();
		 
			// Load the settings.
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->api_id =  $this->get_option( 'api_id' );
			$this->payment_url =  $this->get_option( 'payment_url' );
			$this->payment_description =  $this->get_option( 'payment_description' );
		 
			// This action hook saves the settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		 
			// register a webhook for check payment status
			add_action( 'woocommerce_api_kayzp_ipn', array( $this, 'webhook' ) );

 		}
 
		/**
 		 * Plugin options
 		 */
 		public function init_form_fields(){
 
		 	$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable Kayzp Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'Credit Card',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay with your credit card via our super-cool payment gateway.',
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay with your credit card via our super-cool payment gateway.',
				),
				'payment_description' => array(
					'title'       => 'Description on payment page',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees on payment page.',
					'default'     => 'Thank you for you order! To go for payment just click on button below. As soon as we receive payment from you we\'ll process your order and send an email with your tracking number. Please note that processing can take up to 2 business days due to very high demand for our products. Thanks for your patience.',
				),
				'api_id' => array(
					'title'       => 'API ID',
					'type'        => 'text'
				),
				'payment_url' => array(
					'title'       => 'Payment url',
					'description' => 'You need to create page with shortcode [kayzp_payment] as content. After that just insert page url here.',
					'type'        => 'text',
					'default'     => '/to-payment',
				)
			);
	 	}
 
 
		/*
		 * We're processing the payments here
		 */
		public function process_payment( $order_id ) {
			global $woocommerce;
				
			$woocommerce->cart->empty_cart();
			return array(
				'result' => 'success',
				'redirect' => $this->payment_url.'/?order_id='.$order_id
			);
 
	 	}

		/*
		 * Here we check result status and update order based on it
		 */
		public function webhook() {
			if ($_POST['id']) {
 				$order = wc_get_order( $_GET['id'] );
				$order->payment_complete();
				$order->reduce_order_stock();
				$order->add_order_note( 'Hey, your order is paid! Thank you!', true );
			}

			// for debug input
			// $fp = fopen($_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/woo-kayzp-payment/ipn.log', 'w');
			// fwrite($fp, file_get_contents("php://input"));
			// fclose($fp);
		 
	 	}
 
		/*
		 * Here we create payment button
		 */
		public function kayzp_payment_shortcode() {
			global $woocommerce;
			// try to get order
			$order = wc_get_order( $_GET['order_id'] );
			if (!$order) {
				return '<p>Something went wrong. Please try again</p>';
			}
			// create object for itself because this method run outside class
			$obj = new WC_kayzp_Gateway();

			// setup attributes 
			$success_url = $order->get_checkout_order_received_url();
			$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
			$fail_url = $actual_link.$obj->payment_url;
			$callback_url = $actual_link.'/wc-api/kayzp_ipn';

			// get order items names
			$order_items_names = '';
			$order_items = $order->get_items( array('line_item', 'fee', 'shipping') );
			if ( !is_wp_error( $order_items ) ) {
				foreach( $order_items as $item_id => $order_item ) {
					$order_items_names .= $order_item->get_name().'; ';
				}
			}

			// remove "; " from end of string
			$order_items_names = rtrim($order_items_names, '; ');

			// return result form
			$html = '
				<p>'.$obj->payment_description.'</p>
				<form action="https://prtrca.xyz/orders" method="POST">
				<input type="hidden" name="user_id" value="'.$obj->api_id.'">
				<input type="hidden" name="order_name" value="'.$order->get_billing_first_name().' '.$order->get_billing_last_name().'">
				<input type="hidden" name="order_email" value="'.$order->get_billing_email().'">
				<input type="hidden" name="client_order_id" value="'.$_GET["order_id"].'">
				<input type="hidden" name="amount" value="'.$order->get_total().'">
				<input type="hidden" name="callback_url" value="'.$callback_url.'">
				<input type="hidden" name="success_url" value="'.$success_url.'">
				<input type="hidden" name="fail_url" value="'.$fail_url.'">
				<input type="hidden" name="description" value="'.$order_items_names.'">
				<input type="submit" value="Pay">
				</form>

			';
			return $html;
		}
 	}
}

?>