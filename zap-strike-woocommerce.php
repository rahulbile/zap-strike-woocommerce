<?php
/**
 * Plugin Name: WooCommerce Strike Payment Gateway
 * Plugin URI: https://github.com/rahulbile/zap-strike-woocommerce
 * Description: Accept Bitcoin lightning / onchain Payments on WooCommerce website
 * Author: rahulbile
 * Author URI: https://github.com/rahulbile
 * Version: 0.1
*/

add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_strike_gateway' );

// add a custom field to capture the strike invoiceId
add_action( 'woocommerce_after_order_notes', 'add_strike_invoice_tracking_field' );

// Saving the hidden field value in the order metadata
add_action( 'woocommerce_checkout_update_order_meta', 'capture_strike_invoice_tracking_field' );

// Displaying "Invoice ID" in customer order
add_action( 'woocommerce_order_details_after_customer_details', 'display_strike_invoice_id_in_customer_order', 10 );

// Display "Invoice ID" on Admin order edit page
add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_strike_invoice_id_in_admin_order_meta', 10, 1 );

// Displaying "Invoice ID" on email notifications
add_action('woocommerce_email_customer_details','add_strike_invoice_id_to_emails_notifications', 15, 4 );

/*
 * Registers as a WooCommerce payment gateway
 */
function woocommerce_add_strike_gateway( $gateways ) {
	$gateways[] = 'WC_Strike_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'strike_init_gateway' );

function strike_init_gateway() {

  if (!class_exists('WC_Payment_Gateway')) {
      return;
  }

	class WC_Strike_Gateway extends WC_Payment_Gateway {

 		public function __construct() {

      $this->id = 'strike';
    	$this->icon = plugins_url('zap-strike-woocommerce/img', dirname(__FILE__)).'/logo.png';
    	$this->has_fields = false;
    	$this->method_title = 'Bitcoin and Lightning Payments';
    	$this->method_description = 'Accept bitcoin payments via strike gateway';
			$this->order_button_text = 'Pay with Bitcoin';

    	// Method with all the options fields
    	$this->init_form_fields();

    	// Load the settings.
    	$this->init_settings();
    	$this->title = $this->get_option( 'title' );
    	$this->description = $this->get_option( 'description' );
			$this->displayMode = $this->get_option( 'displaymode' );
			$this->strikeApiKey = $this->get_option( 'apikey' );
			$this->strikeUsername = $this->get_option( 'username' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->strikeCurrency = $this->get_option( 'currency' );

    	add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	  }

 		public function init_form_fields() {
			wp_register_script( 'zap-strike-woocommerce-settings', plugins_url( '/js/strike.settings.js', __FILE__ ), array(), date("h:i:s") );
			wp_enqueue_script( 'zap-strike-woocommerce-settings' );

      $this->form_fields = array(
    		'enabled' => array(
    			'title'       => 'Enable/Disable',
    			'label'       => 'Enable Strike Gateway',
    			'type'        => 'checkbox',
    			'description' => '',
    			'default'     => 'no',
    		),
    		'title' => array(
    			'title'       => 'Title',
    			'type'        => 'text',
    			'description' => 'This controls the title which the user sees during checkout.',
    			'default'     => 'Bitcoin',
    			'desc_tip'    => true,
    		),
    		'description' => array(
    			'title'       => 'Description',
    			'type'        => 'textarea',
    			'description' => 'This controls the description which the user sees during checkout.',
    			'default'     => 'Pay with your bitcoin / lightning wallet.',
    		),
				'displaymode' => array(
					'title'       => 'Display mode',
					'label'       => 'Select display mode',
					'type'     		=> 'select',
					'options'  		=> array(
						'dark'          => __( 'Dark', 'dark' ),
						'light'     	 	=> __( 'Light', 'light' ),
					),
					'description' => 'Select the widget display mode for payment.',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'apikey' => array(
    			'title'       => 'Strike account API Key',
    			'type'        => 'text',
    			'description' => 'This is used to request the invoice as the specified account.',
    			'desc_tip'    => true,
					'required'    => true,
    		),
				'username' => array(
					'title'       => 'Strike Account Username',
					'type'        => 'text',
					'description' => 'Strike account Username.',
					'desc_tip'    => true,
					'required'    => true,
				),
				'currency' => array(
					'title'       => 'Preferred Currency',
					'label'       => 'Select Preferred Currency',
					'type'     		=> 'select',
					'options'  		=> array(
						'USD'          => __( 'USD', 'USD' ),
						'USDT'     	 	=> __( 'USDT', 'USDT' ),
					),
					'description' => 'Select the currency for orders payments.',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
    	);

	 	}

		/**
		 * Payment section to show the QR code to scan and pay
		 */
		public function payment_fields() {
		  global $woocommerce;
		  $amountUsd = $woocommerce->cart->total ? $woocommerce->cart->total : 0;

		  // Add this action hook if you want your custom payment gateway to support it
		  do_action( 'woocommerce_strike_qrcode_start', $this->id );


			echo	'
				<div id="QrCodeLoader" class="overlay"></div>
		    <div id="QrSlider" class="qrCode QrCodesSlider"  data-order-total="' . $amountUsd . '">
					<ul>
						<li>
							<span id="lnQrcodeAmount"></span>
				      <a id="lnQrcodeLink" href="#">
								<span id="lnQrcode" class="lnInvoice"></span>
							</a>
						</li>
						<li>
							<span id="onChainQrcodeAmount"></span>
							<a id="onChainQrcodeLink" href="#">
								<span id="onChainQrcode" class="onchainUrl"></span>
							</a>
						</li>
					</ul>
		    </div>';
			echo '<div id="paymentInfo">Expires in <span id="expirySecond"></span> seconds</div>';
			echo '<div class="strike-btn strike-btn-' . $this->displayMode . '" id="paymentRequestRefresh">Refresh</div>';
			echo '<div class="strike-btn strike-btn-' . $this->displayMode . '" id="paymentRequestInvoiceCopy" data-clipboard-text="">Copy</div>';
			if ( !empty($this->description) ) {
				$this->description  = trim( $this->description );
				// display the description with <p> tags etc.
				echo wpautop( wp_kses_post( $this->description ) );
			}

		  do_action( 'woocommerce_strike_qrcode_end', $this->id );
		}

		/*
		 * Custom CSS and JS
		 */
	 	public function payment_scripts() {
			// we need JavaScript to process a token only on cart/checkout pages, right?
			if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
				return;
			}

			// if our payment gateway is disabled, we do not have to enqueue JS too
			if ( 'no' === $this->enabled ) {
				return;
			}

			global $woocommerce;
			$amountUsd = $woocommerce->cart->total ? $woocommerce->cart->total : 0;

			wp_enqueue_style( 'strike', plugins_url( '/css/strike.css', __FILE__, '5.6.9' ));
			wp_enqueue_style( 'unislider', plugins_url( '/css/unslider.css', __FILE__ ));
			wp_enqueue_style( 'unislider-dots', plugins_url( '/css/unslider-dots.css', __FILE__ ));

			wp_register_script( 'zap-strike-woocommerce-custom', plugins_url( '/js/strike.js', __FILE__ ), array(), date("h:i:s") );
			wp_register_script( 'zap-strike-woocommerce-qrcode', plugins_url( '/js/easy.qrcode.min.js' , __FILE__ ), array( 'jquery' ), '3.6.0', true );
			wp_register_script( 'zap-strike-woocommerce-unislider', plugins_url( '/js/unslider-min.js' , __FILE__ ), array( 'jquery' ), '3.6.0', true );
			wp_register_script( 'zap-strike-woocommerce-clipboard', plugins_url( '/js/clipboard.min.js' , __FILE__ ), array( 'jquery' ), '3.6.0', true );

			wp_localize_script( 'zap-strike-woocommerce-custom', 'strike_params', array(
				'strikeApiKey' => $this->strikeApiKey,
				'strikeCurrency' => $this->strikeCurrency,
				'displayMode' => $this->displayMode,
				'totalAmount' => $amountUsd,
			) );
			wp_enqueue_script( 'zap-strike-woocommerce-custom' );
			wp_enqueue_script( 'zap-strike-woocommerce-qrcode' );
			wp_enqueue_script( 'zap-strike-woocommerce-unislider' );
			wp_enqueue_script( 'zap-strike-woocommerce-clipboard' );
	 	}

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
			if ( empty($_POST['strike_invoice_id']) && 0 === wc_notice_count( 'error' ) ) {
		 		// Generate the QR Code and submit via Script
				wc_add_notice( 'Please scan the QR code and pay.', 'notice' );
		 	} else {
				global $woocommerce;
				$order = wc_get_order( $order_id );
				$order->payment_complete();
				$order->reduce_order_stock();

				$order->add_order_note( 'Hey, your order is paid with strike! Invoice Id:  Thank you!', true );

				// Empty cart
				$woocommerce->cart->empty_cart();

				return array(
					'result' => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			}
	 	}
 	}
}

function add_strike_invoice_id_to_emails_notifications( $order, $sent_to_admin, $plain_text, $email ) {
	 // compatibility with WC +3
	 $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;

	 $output = '';
	 $strikInvoiceId = get_post_meta( $order_id, '_strike_invoice_id', true );

	 if ( !empty($strikInvoiceId) )
			 $output .= '<div><strong>' . __( "Strike Invoice ID:", "woocommerce" ) . '</strong> <span class="text">' . $strikInvoiceId . '</span></div>';

	 echo $output;
}

function add_strike_invoice_tracking_field( $checkout ) {
	// Output the hidden field
	echo '<div id="strikeInvoiceIdCaptureField">
					<input type="hidden" class="input-hidden" name="strike_invoice_id" id="strikInvoiceId" value="">
	</div>';
}

function capture_strike_invoice_tracking_field( $order_id ) {
		if ( ! empty( $_POST['strike_invoice_id'] ) ) {
				update_post_meta( $order_id, '_strike_invoice_id', sanitize_text_field( $_POST['strike_invoice_id'] ) );
		}
}

function display_strike_invoice_id_in_customer_order( $order ) {
	// compatibility with WC +3
	$order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;

	echo '<p class="strike-invoice-id"><strong>'.__('Strike Invoice ID', 'woocommerce') . ':</strong> ' . get_post_meta( $order_id, '_strike_invoice_id', true ) .'</p>';
}

function display_strike_invoice_id_in_admin_order_meta( $order ) {
 // compatibility with WC +3
 $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
 echo '<p><strong>'.__('Strike Invoice ID', 'woocommerce').':</strong> ' . get_post_meta( $order_id, '_strike_invoice_id', true ) . '</p>';
}


function strike_plugin_add_settings_link( $links ) {
  $settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=strike' ) . '">' . __( 'Settings', 'zap-stripe-woocommerce' ) . '</a>';
  array_unshift( $links, $settings_link );
  return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'strike_plugin_add_settings_link' );
