<?php

include('PortalSDK/api.php');

// In order to prevent direct access to the plugin

 defined('ABSPATH') or die("No access please!");
if(!isset($_SESSION)){
    session_start(); 
}


// Plugin header- notifies wordpress of the existence of the plugin


/* Plugin Name: Payment Gateway for M-PESA Open API on Woocommerce Free

* Plugin URI: https://demkitech.com/

* Description: Payment Gateway for M-PESA Open API on Woocommerce Free

* Version: 1.0.0 

* Author: Demkitech Solutions

* Author URI:

* Licence: GPL2 

* WC requires at least: 2.2

* WC tested up to: 6.5

*/



add_action('plugins_loaded', 'woompesa_openapi_payment_gateway_init');


//defining the classclass



/**

 * M-PESA Payment Gateway

 *

 * @class          WC_Gateway_Mpesa_OpenAPI

 * @extends        WC_Payment_Gateway

 * @version        1.0.0

 */

 

 function woompesa_openapi_adds_to_the_head() {

 

   wp_enqueue_script('OpenAPICallbacks', plugin_dir_url(__FILE__) . 'trxcheck_openapi.js', array('jquery'));

   wp_enqueue_style( 'Responses', plugin_dir_url(__FILE__) . '/display.css',false,'1.1','all');

 

}

//Add the css and js files to the header.

add_action( 'wp_enqueue_scripts', 'woompesa_openapi_adds_to_the_head' );

//Calls the woompesa_openapi_install function during plugin activation which creates table that records transactions.

register_activation_hook(__FILE__,'woompesa_openapi_install');

//Request payment function start//


add_action( 'init', function() {

    /** Add a custom path and set a custom query argument. */

    add_rewrite_rule( '^/payment/?([^/]*)/?', 'index.php?payment_action_openapi=1', 'top' );

} );



add_filter( 'query_vars', function( $query_vars ) {

    /** Make sure WordPress knows about this custom action. */

    $query_vars []= 'payment_action_openapi';

    return $query_vars;

} );



add_action( 'wp', function() {

    /** This is an call for our custom action. */

    if ( get_query_var( 'payment_action_openapi' ) ) {

        // your code here

		woompesa_openapi_request_payment();

    }

} );
//Add validations for input
add_action( 'woocommerce_after_checkout_validation', 'mpesa_openapi_phone', 10, 2);

//Request payment function end//

//Callback scanner function start

add_action( 'init', function() {  

    add_rewrite_rule( '^/scanner/?([^/]*)/?', 'index.php?scanner_action_openapi=1', 'top' );

} );



add_filter( 'query_vars', function( $query_vars ) {   

    $query_vars []= 'scanner_action_openapi';

    return $query_vars;

} );



add_action( 'wp', function() { 

    if ( get_query_var( 'scanner_action_openapi' ) ) {

        // invoke scanner function

		woompesa_openapi_scan_transactions();

    }

} );

//Callback scanner function end

function woompesa_openapi_payment_gateway_init() {


    if( !class_exists( 'WC_Payment_Gateway' )) return;


class WC_Gateway_Mpesa_OpenAPI extends WC_Payment_Gateway {





/**

*  Plugin constructor for the class

*/

public function __construct(){		

		

		if(!isset($_SESSION)){			        session_start(); 				}

        // Basic settings

		$this->id                 = 'mpesa_open_api';

		$this->icon               = plugin_dir_url(__FILE__) . 'mpesa_open_logo.png';

        $this->has_fields         = false;

        $this->method_title       = __( 'M-PESA Open API', 'woocommerce' );

        $this->method_description = __( 'Enable customers to make payments to your business merchant code' );

       

        // load the settings

        $this->init_form_fields();

        $this->init_settings();



        // Define variables set by the user in the admin section

        $this->title            = sanitize_text_field($this->get_option( 'title' ));

        $this->description      = sanitize_textarea_field($this->get_option( 'description' ));

        $this->instructions     = sanitize_textarea_field($this->get_option( 'instructions', $this->description ));

        $this->mer              = sanitize_text_field($this->get_option( 'mer' ));	
		
		$_SESSION['openapi_order_status']	= sanitize_text_field($this->get_option('openapi_order_status'));	
		
		$_SESSION['country']				= sanitize_text_field($this->get_option('country'));	

		$_SESSION['get_session_endpoint']   = sanitize_url($this->get_option( 'get_session_endpoint' )); 

		$_SESSION['c2b_payments_endpoint']   	= sanitize_url($this->get_option( 'c2b_payments_endpoint' )); 

		$_SESSION['api_key']      			= $this->get_option( 'api_key' ); 

		$_SESSION['public_key']      		= $this->get_option( 'public_key' ); 

		$_SESSION['currency']   			= sanitize_text_field($this->get_option( 'currency' ));
		
		$_SESSION['service_provider_code']  = $this->get_option('service_provider_code'); 

				



        //Save the admin options

        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {



            add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( $this, 'process_admin_options' ) );



        } else {



            add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );



        }



        add_action( 'woocommerce_receipt_mpesa_open_api', array( $this, 'receipt_page_mpesa_openapi' ));

		



    }


/**

*Initialize form fields that will be displayed in the admin section.

*/



public function init_form_fields() {



    $this->form_fields = array(

        'enabled' => array(

            'title'   => __( 'Enable/Disable', 'woocommerce' ),

            'type'    => 'checkbox',

            'label'   => __( 'Enable Mpesa Open API Payments Gateway', 'woocommerce' ),

            'default' => 'yes'

            ),

        'title' => array(

            'title'       => __( 'Title', 'woocommerce' ),

            'type'        => 'text',

            'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),

            'default'     => __( 'M-PESA Open API', 'woocommerce' ),

            'desc_tip'    => true,

            ),

        'description' => array(

            'title'       => __( 'Description', 'woocommerce' ),

            'type'        => 'textarea',

            'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),

            'default'     => __( 'Place order and pay using M-PESA.'),

            'desc_tip'    => true,

            ),

        'instructions' => array(

            'title'       => __( 'Instructions', 'woocommerce' ),

            'type'        => 'textarea',

            'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),

            'default'     => __( 'Place order and pay using M-PESA.', 'woocommerce' ),

                // 'css'         => 'textarea { read-only};',

            'desc_tip'    => true,

            ),

        'mer' => array(

            'title'       => __( 'Merchant Name', 'woocommerce' ),

            'description' => __( 'Company name', 'woocommerce' ),

            'type'        => 'text',

            'default'     => __( 'Company Name', 'woocommerce'),

            'desc_tip'    => false,

            ),
			
			///Give option to choose order status
			
			'openapi_order_status' => array( 
			'title'       => __( 'Successful Payment Status', 'woocommerce' ),
			'type'        => 'select',	
			'options' => array(		
			1 => __( 'On Hold', 'woocommerce' ),	
			2 => __( 'Processing', 'woocommerce' ),	
			3 => __( 'Completed', 'woocommerce' )	
			),					
			'description' => __( 'Payment status for the order after successful M-PESA payment.', 'woocommerce' ),	
			'desc_tip'    => false,	
			),
						
			'country' => array(

			'title'       =>  __( 'Country', 'woocommerce' ),
			'default'     => __( '', 'woocommerce'),
			
			'description' => __( 'Country for Go Live', 'woocommerce' ),

			'type'        => 'text',

		),
		'currency' => array(

			'title'       =>  __( 'Currency', 'woocommerce' ),
			'default'     => __( '', 'woocommerce'),
			
			'description' => __( 'Currency of the country', 'woocommerce' ),

			'type'        => 'text',

		),
		
			
		'get_session_endpoint' => array(

			'title'       =>  __( 'Get Session Endpoint(Sandbox/Production)', 'woocommerce' ),

			'default'     => __( 'https://openapi.m-pesa.com/sandbox/ipg/v2/vodafoneGHA/getSession/', 'woocommerce'),
			
			'description' => __( 'The endpoint for sandbox and live environment are different', 'woocommerce' ),

			'type'        => 'text',
			

		),				

		'c2b_payments_endpoint' => array(

			'title'       =>  __( 'C2B Endpoint(Sandbox/Production)', 'woocommerce' ),

			'default'     => __( 'https://openapi.m-pesa.com/sandbox/ipg/v2/vodafoneGHA/c2bPayment/singleStage/', 'woocommerce'),
			
			'description' => __( 'The endpoint for sandbox and live environment are different', 'woocommerce' ),

			'type'        => 'text',

		),

		'api_key' => array(

			'title'       =>  __( 'APIKey', 'woocommerce' ),

			 'default'     => __( '', 'woocommerce'),

			'type'        => 'password',

		),

		

		'public_key' => array(

			'title'       =>  __( 'Public Key', 'woocommerce' ),

			 'default'     => __( '', 'woocommerce'),

			'type'        => 'password',

		),
		'service_provider_code' => array(
			'title'       =>  __( 'Service Provider Code', 'woocommerce' ),
			'default'     => __( '', 'woocommerce'),
			'description' => __( 'Code for the service provider'),
			'type'        => 'text',

		)

		);

}

//Add input validations
//Validate the api_key supplied
public function validate_public_key_field( $key, $value ) {
    if ( isset( $value ) && 1000 < strlen( $value ) ) {
        WC_Admin_Settings::add_error( esc_html__( 'Invalid API Key field, please ensure it is not longer than 1000 characters', 'woocommerce-integration-openapi' ) );
    }

    return wp_kses_post($value);
    return wp_kses_post($value);
}
//Validate the api_key supplied
public function validate_api_key_field( $key, $value ) {
    if ( isset( $value ) && 1000 < strlen( $value ) ) {
        WC_Admin_Settings::add_error( esc_html__( 'Invalid Public Key field, please ensure it is not longer than 1000 characters', 'woocommerce-integration-openapi' ) );
    }

    return wp_kses_post($value);
}

//Validate the currency
public function validate_currency_field( $key, $value) {
	
    if (!preg_match('/^[a-zA-Z]{1,3}$/', $value)) {
        WC_Admin_Settings::add_error( esc_html__( 'Invalid Currency value, please update to a correct value', 'woocommerce-integration-openapi' ) );
    }

    return wp_kses_post($value);
}

//Validate service provider code
public function validate_service_provider_code_field( $key, $value) {
    if (!preg_match('/^([0-9A-Za-z]{4,12})$/', $value)) {
        WC_Admin_Settings::add_error( esc_html__( 'Invalid Service Provider Code value, should be 4 to 12 characters in lenght, please update to a correct value', 'woocommerce-integration-openapi' ) );
    }

    return wp_kses_post($value);
}

//Validate country
public function validate_country_field( $key, $value) {
    if (!preg_match('/^[a-zA-Z]{1,5}$/', $value)) {
        WC_Admin_Settings::add_error( esc_html__( 'Invalid Country value, please update to a correct value', 'woocommerce-integration-openapi' ) );
    }

    return wp_kses_post($value);
}

//End in validations



/**

 * Generates the HTML for admin settings page

 */

public function admin_options(){

    /*

     *The heading and paragraph below are the ones that appear on the backend M-PESA settings page

     */

    echo wp_kses_post('<h3>' . 'M-PESA Payments Gateway' . '</h3>');

    

    echo wp_kses_post('<p>' . 'Payments Made Simple' . '</p>');

    

    echo wp_kses_post('<table class="form-table">');

    

    $this->generate_settings_html( );

    

    echo wp_kses_post('</table>');

}



/**

 * Receipt Page

 **/

public function receipt_page_mpesa_openapi( $order_id ) {

    echo wp_kses_post($this->woompesa_openapi_generate_iframe( $order_id ));

}



/**

 * Function that posts the params to mpesa and generates the html for the page

 */

public function woompesa_openapi_generate_iframe( $order_id ) {



    global $woocommerce;

    $order = new WC_Order ( $order_id );
	
	//Validate the order ID
	if(preg_match('/^\d+$/', $order->get_id())) {
		$_SESSION["openapi_order_id"] = sanitize_text_field($order->get_id());  
	
    }else{
		echo wp_kses_post('<p>' . 'Invalid Order ID, please check and rectify.' .$order->get_id(). '</p>');
		exit();
	}

	//Validate order total amount
	if(preg_match('/^\d*\.?\d+$/', $order->get_total())) {
		$_SESSION['order_total'] = sanitize_text_field($order->get_total());
	
    }else{
		echo wp_kses_post('<p>' . 'Invalid Order Amount, please check and try again.' . '</p>');
		exit();
	}
    
	
	$tel = $order->get_billing_phone();
    //sanitize the phone number and remove unecessary symbols

    $tel = str_replace("-", "", $tel);

    $tel = str_replace( array(' ', '<', '>', '&', '{', '}', '*', "+", '!', '@', '#', "$", '%', '^', '&'), "", $tel );

	//Validate the phone number
	if(preg_match('/^[0-9]{12,14}$/', $tel)) {
		$_SESSION['billing_tel'] = sanitize_text_field($tel);
	
    }else{
		echo wp_kses_post('<p>' . 'Invalid Phone Number, size should be between 12 to 14 digits.' . '</p>');
		exit();
	}
	

/**

 * Make the payment here by clicking on pay button and confirm by clicking on complete order button

 */

if ($_GET['transactionType']=='checkout_open_api') {

	
	echo wp_kses_post("<h4>Payment Instructions:</h4>");

    echo wp_kses_post("

		  1. Click on the <b>Pay</b> button in order to initiate the M-PESA payment.<br/>

		  2. Check your mobile phone for a prompt asking to enter M-PESA pin.<br/>

    	  3. Enter your <b>M-PESA PIN</b> and the amount specified on the 

    	  	notification will be deducted from your M-PESA account when you press send.<br/>

    	  4. When you enter the pin and click on send, you will receive an M-PESA payment confirmation message on your mobile phone.<br/>     	

    	  5. Once the payment is successful, the web page will be redirected to Order Received page.<br/>");

    echo wp_kses_post("<br/>");?>

	
	<input type="hidden" value="" id="txid"/>	

	<div id="commonname_openapi"></div>

	<button onClick="pay_mpesa_openapi()" id="pay_btn_mpesa_openapi">Pay</button>
	
	<button onClick="complete_mpesa_openapi()" id="complete_btn_mpesa_openapi">Complete Order</button>
    <?php	

    echo wp_kses_post("<br/>");



}



}



/**

* Process the payment field and redirect to checkout/pay page.

*

*

*

*/



public function process_payment( $order_id ) {



		$order = new WC_Order( $order_id );		  		

       // Redirect to checkout/pay page

        $checkout_url = $order->get_checkout_payment_url(true);

        $checkout_edited_url = $checkout_url."&transactionType=checkout_open_api";

        return array(

            'result' => 'success',

            'redirect' => add_query_arg('order', $order->get_id(),

                add_query_arg('key', $order->get_order_key(), $checkout_edited_url))

            ); 

}



}

}

/**

 * Telling woocommerce that mpesa payments gateway class exists

 * Filtering woocommerce_payment_gateways

 * Add the Gateway to WooCommerce

 **/



function woompesa_openapi_add_gateway_class( $methods ) {



    $methods[] = 'WC_Gateway_Mpesa_OpenAPI';



    return $methods;



}



if(!add_filter( 'woocommerce_payment_gateways', 'woompesa_openapi_add_gateway_class' )){

    die;

}

function woompesa_openapi_install() {
	create_openapi_trx_table();
}
//Create table for transactions

function create_openapi_trx_table(){


	global $wpdb;

	global $trx_db_version;

	$trx_db_version = '1.0';



	$table_name = $wpdb->prefix .'openapi_mpesa_trx';

	

	$charset_collate = $wpdb->get_charset_collate();



	$sql = "CREATE TABLE IF NOT EXISTS $table_name (

		id mediumint(9) NOT NULL AUTO_INCREMENT,
		
		order_complete_status varchar(20) DEFAULT '3' NULL,

		order_id varchar(150) DEFAULT '' NULL,

		phone_number varchar(150) DEFAULT '' NULL,

		trx_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,

		merchant_request_id varchar(150) DEFAULT '' NULL,

		checkout_request_id varchar(150) DEFAULT '' NULL,

		resultcode varchar(150) DEFAULT '' NULL,

		resultdesc varchar(150) DEFAULT '' NULL,

		processing_status varchar(20) DEFAULT '0' NULL,

		PRIMARY KEY  (id)

	) $charset_collate;";
		
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	add_option( 'trx_db_version', $trx_db_version );
		
}

//Payments start

function woompesa_openapi_request_payment(){

		if(!isset($_SESSION)){
			session_start(); 
			}

		global $wpdb; 
	
	// This is to ensure browser does not timeout after 30 seconds
	
ini_set('max_execution_time', 300);
set_time_limit(300);

// Create Context with API to request a SessionKey
$context = new APIContext();

// Api key
$context->set_api_key($_SESSION['api_key']);
// Public key
$context->set_public_key($_SESSION['public_key']);
// Use ssl/https
$context->set_ssl(true);
// Method type (can be GET/POST/PUT)
$context->set_method_type(APIMethodType::GET);
// API address
$context->set_address('openapi.m-pesa.com');

$context->set_endpoint(sanitize_url($_SESSION['get_session_endpoint']));
// Add/update headers
$context->add_header('Origin', '*');

// Parameters can be added to the call as well that on POST will be in JSON format and on GET will be URL parameters
// context->add_parameter('key', 'value');

// Create a request object
$request = new APIRequest($context);

// Do the API call and put result in a response packet
$response = null;
try {
	$response = $request->execute();
} catch(exception $e) {
	echo wp_kses_post(json_encode(array("rescode" => "9990", "resmsg" => "Failed to initiate payment session request, please try again.")));
	exit();
}
if ($response->get_body() == null) {
	echo wp_kses_post(json_encode(array("rescode" => "9991", "resmsg" => "Failed to initiate payment session request, please try again.")));
	exit();
}

// Display results

//End Open API Token Generation


///If the session value is available, start the payment process
// Decode JSON packet
$decoded = json_decode($response->get_body());

// The above call issued a sessionID which can be used as the API key in calls that needs the sessionID
$context = new APIContext();
$context->set_api_key($decoded->output_SessionID);
$context->set_public_key(validate_sanitize_public_key_session($_SESSION['public_key']));
$context->set_ssl(true);
$context->set_method_type(APIMethodType::POST);
$context->set_address('openapi.m-pesa.com');
$context->set_endpoint(sanitize_url($_SESSION['c2b_payments_endpoint']));


$context->add_header('Origin', '*');
$timestamp = date("YmdHis");

$context->add_parameter('input_Amount', sanitize_text_field($_SESSION['order_total']));
$context->add_parameter('input_Country', validate_sanitize_country_session($_SESSION['country']));
$context->add_parameter('input_Currency', validate_sanitize_currency_session($_SESSION['currency']));
$context->add_parameter('input_CustomerMSISDN', sanitize_text_field($_SESSION['billing_tel']));
$context->add_parameter('input_ServiceProviderCode', validate_sanitize_service_provider_code_session($_SESSION['service_provider_code']));
$context->add_parameter('input_ThirdPartyConversationID', sanitize_text_field($_SESSION['billing_tel']).$timestamp);
$context->add_parameter('input_TransactionReference', sanitize_text_field($_SESSION["openapi_order_id"]));
$context->add_parameter('input_PurchasedItemsDesc', 'Payment for Order Number '.sanitize_text_field($_SESSION["openapi_order_id"]));

$request = new APIRequest($context);

$response = null;

try {
	$response = $request->execute();
} catch(exception $e) {
	echo wp_kses_post(json_encode(array("rescode" => "9992", "resmsg" => "Failed to initiate payment request, please try again.")));
	exit();	
}

if ($response->get_body() == null) {	
	echo wp_kses_post(json_encode(array("rescode" => "9993", "resmsg" => "Failed to initiate payment request, please try again.")));
	exit();
}

else{

$response_body_array = json_decode($response->get_body());

		if($response_body_array->output_ResponseCode == "INS-0"){

			echo wp_kses_post(json_encode(array("rescode" => "0", "resmsg" => "Request accepted for processing, check your phone to enter M-PESA pin")));	

		}
        else if($response_body_array->output_ResponseCode == "INS-990"){

			echo wp_kses_post(json_encode(array("rescode" => "INS-990", "resmsg" => "Customer Transaction Value Limit Breached")));

						

		}

		 else if($response_body_array->output_ResponseCode == "INS-991"){

			echo wp_kses_post(json_encode(array("rescode" => "INS-991", "resmsg" => "Customer Transaction Count Limit Breached")));

			

		}

		else if($response_body_array->output_ResponseCode == "INS-998"){

			

			echo wp_kses_post(json_encode(array("rescode" => "INS-998", "resmsg" => "Invalid Market Configured For The Transaction.")));

		}

		else if($response_body_array->output_ResponseCode == "INS-2006"){

			

			echo wp_kses_post(json_encode(array("rescode" => "INS-2006", "resmsg" => "The Balance Is Insufficient For The Transaction.")));

		}	
		else if($response_body_array->output_ResponseCode == "INS-2051"){

			

			echo wp_kses_post(json_encode(array("rescode" => "INS-2051", "resmsg" => "Mobile Number Is Invalid.")));

		}		

		else{

			echo wp_kses_post(json_encode(array("rescode" => $response_body_array->output_ResponseCode, "resmsg" => "Error encountered during payment request")));

		}

}
//Insert the details before exit
woompesa_openapi_insert_transaction($_SESSION['billing_tel'].$timestamp, $response_body_array->output_ResponseCode, $response_body_array->output_ResponseDesc);
exit();

}

//Insert Transactions//
function woompesa_openapi_insert_transaction( $merchant_id, $response_code, $response_message ) {

	if(!isset($_SESSION)){session_start();}

  global $wpdb; 

  $table_name = $wpdb->prefix . 'openapi_mpesa_trx';

  $wpdb->insert( $table_name, array(
      'order_complete_status' => sanitize_text_field($_SESSION['openapi_order_status']),
     'order_id' => sanitize_text_field($_SESSION["openapi_order_id"]),
	 'phone_number' => sanitize_text_field($_SESSION['billing_tel']),
    'merchant_request_id' => sanitize_text_field($merchant_id),
	'trx_time' => date("Y-m-d H:i:s"),
	'resultcode' => sanitize_text_field($response_code),
	'resultdesc' => sanitize_text_field($response_message),
	'processing_status' =>	'0'	

  ) );
}
//End Insert Transactions

/////Scanner start

function woompesa_openapi_scan_transactions(){
//The code below is invoked after customer clicks on the Confirm Order button
echo wp_kses_post(json_encode(array("rescode" => "76", "resmsg" => "Callback processing has been disabled, please request for the Pro Version of the plugin.")));

exit();
}

////Scanner end

//Phone Number Field Validator
function mpesa_openapi_phone( $fields, $errors ){

    if(!preg_match('/^[0-9]{12,14}$/', $fields[ 'billing_phone' ])) {
	$errors->add( 'validation', 'Invalid Phone Number, size should be between 12 to 14 digits.' );
    }

}

		
//Add session variables validations
//Validate the api_key supplied
function validate_sanitize_public_key_session($session_value) {
    if ( isset( $session_value ) && 1000 < strlen( $session_value ) ) {
		echo wp_kses_post(json_encode(array("rescode" => "9994", "resmsg" => "Invalid input provided, please rectify and try again.")));
		exit();
	}
    return sanitize_textarea_field($session_value);
}
//Validate the api_key supplied
function validate_api_key_session($session_value) {
    if ( isset( $session_value ) && 1000 < strlen( $session_value ) ) {
        echo wp_kses_post(json_encode(array("rescode" => "9995", "resmsg" => "Invalid input provided, please rectify and try again.")));
		exit();
    }

    return sanitize_textarea_field($session_value);
}

//Validate the currency
function validate_sanitize_currency_session($session_value) {
	
    if (!preg_match('/^[a-zA-Z]{1,3}$/', $session_value)) {
        echo wp_kses_post(json_encode(array("rescode" => "9996", "resmsg" => "Invalid input provided, please rectify and try again.")));
		exit();
    }

    return sanitize_text_field($session_value);
}

//Validate service provider code
function validate_sanitize_service_provider_code_session($session_value) {
    if (!preg_match('/^([0-9A-Za-z]{4,12})$/', $session_value)) {
        echo wp_kses_post(json_encode(array("rescode" => "9997", "resmsg" => "Invalid input provided, please rectify and try again.")));
		exit();
    }

    return sanitize_text_field($session_value);
}

//Validate country
function validate_sanitize_country_session($session_value) {
    if (!preg_match('/^[a-zA-Z]{1,5}$/', $session_value)) {
        echo wp_kses_post(json_encode(array("rescode" => "9998", "resmsg" => "Invalid input provided, please rectify and try again.")));
		exit();
    }

    return sanitize_text_field($session_value);
}

//End in validations

?>
