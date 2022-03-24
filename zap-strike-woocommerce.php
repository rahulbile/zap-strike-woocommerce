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

// Add proxy end points to Strike API for invoice / quote and status request

// request a invoice generation;
add_action('rest_api_init', function() {
  register_rest_route('strikeapi/v1', 'invoices', array(
    'methods' => WP_REST_SERVER::CREATABLE,
    'callback' => 'woocommerce_strike_create_invoice',
    'args' => array(),
    'permission_callback' => '__return_true',
  ));
});

// Get quote for invoice
add_action('rest_api_init', function() {
  register_rest_route('strikeapi/v1', 'invoices/(?P<invoice_id>[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}+)/quote', array(
    'methods' => WP_REST_SERVER::CREATABLE,
    'callback' => 'woocommerce_strike_create_invoice_quote',
    'args' => array(),
    'permission_callback' => '__return_true',
  ));
});

// get invoice status by invoice Id;
add_action( 'rest_api_init', function() {
	register_rest_route( 'strikeapi/v1', 'invoices/(?P<invoice_id>[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}+)', [
		'method'   => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback' => 'woocommerce_strike_get_invoice_status',
	] );
} );


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
			$this->strikeApiUrl = $this->get_option( 'apiurl' );
		//	$this->strikeUsername = $this->get_option( 'username' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->strikeCurrency = $this->get_option( 'currency' );
    	add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
	  }

 		public function init_form_fields() {

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
				'apiurl' => array(
					'title'       => 'API Request URL',
					'type'        => 'text',
					'description' => 'This url is used to call the API SDK. Set to <yourSiteBaseURl>/wp-json/strikeapi/v1 to proxy calls',
					'default'     => 'https://api.next.strike.me/v1',
					'desc_tip'    => true,
					'required'    => true,
				),
				// 'username' => array(
				// 	'title'       => 'Strike Account Username',
				// 	'type'        => 'text',
				// 	'description' => 'Strike account Username.',
				// 	'desc_tip'    => true,
				// 	'required'    => true,
				// ),
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
      if ( !empty($this->description) ) {
        $this->description  = trim( $this->description );
      }

			echo	'<div id="strikeInvoiceCard" class="strike-invoice-card" data-order-total="' . $amountUsd . '">
        <div class="strike-invoice-card-message">'.
        wpautop( wp_kses_post( $this->description ) ) . '</div></div>';


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
			wp_enqueue_style( 'zap-strike-woocommerce', plugins_url( '/css/strike.css', __FILE__), array(), date("h:i:s"));
			wp_register_script( 'zap-strike-woocommerce-strikejs', plugins_url( '/js/strike.min.js', __FILE__ ), array(), date("h:i:s") );
			wp_register_script( 'zap-strike-woocommerce-custom', plugins_url( '/js/strike.js', __FILE__ ), array(), date("h:i:s") );

			// Set key to null if proxy endpoint is set to wordpress api
			$apiKey = $this->strikeApiKey;
			if(strpos($this->strikeApiUrl, 'wp-json/strikeapi/v1') !== false) {
				$apiKey = "";
			}
      $plugin_data = get_plugin_data( __FILE__ );
      $plugin_version = $plugin_data['Version'];

      wp_localize_script( 'zap-strike-woocommerce-custom', 'strike_params', array(
      	'strikeApiKey' => $apiKey,
      	'strikeApiUrl' => $this->strikeApiUrl,
      	'strikeCurrency' => $this->strikeCurrency,
      	'displayMode' => $this->displayMode,
      	'totalAmount' => $amountUsd,
        'pluginVersion' => $plugin_version
      ) );

			wp_enqueue_script( 'zap-strike-woocommerce-custom' );
			wp_enqueue_script( 'zap-strike-woocommerce-strikejs' );
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
	 $strikeInvoiceId = get_post_meta( $order_id, '_strike_invoice_id', true );

	 if ( !empty($strikeInvoiceId) )
			 $output .= '<div><strong>' . __( "Strike Invoice ID:", "woocommerce" ) . '</strong> <span class="text">' . $strikeInvoiceId . '</span></div>';

	 echo $output;
}

function add_strike_invoice_tracking_field( $checkout ) {
	// Output the hidden field
	echo '<div id="strikeInvoiceIdCaptureField">
					<input type="hidden" class="input-hidden" name="strike_invoice_id" id="strikeInvoiceId" value="">
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
  $settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=strike' ) . '">' . __( 'Settings', 'zap-strike-woocommerce' ) . '</a>';
  array_unshift( $links, $settings_link );
  return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'strike_plugin_add_settings_link' );



// Strike API proxry requests

// Create invoice
function woocommerce_strike_create_invoice( $request ) {
	$payParams   = $request->get_body();
	// Make API Request and get invoice generated
	$strikeApiKey = woocommerce_strike_get_settings('apikey');
	$strikeApiUrl = 'https://api.strike.me/v1';
	$args = array(
	  'timeout'    => 10,
	  // Add a couple of custom HTTP headers
	  'headers'    => array(
	     'Content-Type' => 'application/json; charset=utf-8',
	     'Authorization' => 'Bearer ' . $strikeApiKey,
	  ),
		'body' => $payParams,
	);

	$invoice = wp_remote_post( $strikeApiUrl . "/invoices", $args );

	if ( empty( $invoice ) ) {
		return new WP_REST_Response( [
			'message' => 'Invoice with specified Id was not found',
		], 400 );
	}
  nocache_headers();
	$invoiceResponse = json_decode(wp_remote_retrieve_body($invoice), true);
	return new WP_REST_Response( $invoiceResponse, 200 );
}

function woocommerce_strike_create_invoice_quote( $request ) {
	$invoiceId   = $request->get_param( 'invoice_id' );

	// generate a quote and pass result along with InvoiceId
	// Make API Request and get invoice generated
	$strikeApiKey = woocommerce_strike_get_settings('apikey');
	$strikeApiUrl = 'https://api.strike.me/v1';
	$args = array(
		'timeout'    => 10,
		// Add a couple of custom HTTP headers
		'headers'    => array(
			 'Content-Type' => 'application/json; charset=utf-8',
			 'Authorization' => 'Bearer ' . $strikeApiKey,
		),
	);

	$invoiceQuote = wp_remote_post( $strikeApiUrl . "/invoices/" . $invoiceId . "/quote" , $args );
	if ( empty( $invoiceId ) ) {
		return new WP_REST_Response( [
			'message' => 'Invoice with specified Id was not found',
		], 400 );
	}
  nocache_headers();
	$invoiceQuoteResponse = json_decode(wp_remote_retrieve_body($invoiceQuote), true);
	return new WP_REST_Response( $invoiceQuoteResponse, 200 );
}

// Get Invoice status
function woocommerce_strike_get_invoice_status( $request ) {
	$invoiceId   = $request->get_param( 'invoice_id' );
	$strikeApiKey = woocommerce_strike_get_settings('apikey');
	$strikeApiUrl = 'https://api.strike.me/v1';
	$args = array(
	  'timeout'    => 10,
	  // Add a couple of custom HTTP headers
	  'headers'    => array(
	     'Content-Type' => 'application/json; charset=utf-8',
	     'Authorization' => 'Bearer ' . $strikeApiKey
	  ),
	);

	$invoiceStatus = wp_remote_get( $strikeApiUrl . "/invoices/" . $invoiceId, $args );
	if ( empty( $invoiceStatus ) ) {
		return new WP_REST_Response( [
			'message' => 'Invoice id was not found.',
		], 400 );
	}
	// return the body
  nocache_headers();
	$invoiceStatusResponse = json_decode(wp_remote_retrieve_body($invoiceStatus), true);
	return new WP_REST_Response( $invoiceStatusResponse, 200 );
}

function woocommerce_strike_get_settings($option) {
	$strikeGateway = WC()->payment_gateways->payment_gateways()['strike'];
	return $strikeGateway->get_option($option);
}
