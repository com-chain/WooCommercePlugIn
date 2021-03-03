<?php
/**
 * Plugin Name:       WooCommerce ComChain Payment Gateway
 * Plugin URI:        https://com-chain.org/
 * Description:       Take card payments on your store
 * Version:           1.0.0
 * Author:            Com'Chain
 * Author URI:        https://com-chain.org/
 */
 
$public_key_file ='comchainwebhook_rsa.pub.pem';
 
// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

add_filter( 'woocommerce_payment_gateways', 'comchain_add_gateway_class' );
function comchain_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_ComChain_Gateway'; // your class name is here
	return $gateways;
}

add_action( 'plugins_loaded', 'comchain_init_gateway_class' );

function comchain_init_gateway_class() {
    class WC_ComChain_Gateway extends WC_Payment_Gateway {
    
        public function __construct() {
     
	        $this->id = 'comchain_gateway'; // the unique ID for this gateway
	        
	        $this->icon = ''; // the link to the image displayed next to the method’s title on the checkout page — it's optional  TODO get the currency logo
	        
	        $this->has_fields = false; // in case you need a custom credit card form
	        $this->method_title = 'ComChain Gateway'; //the title of the payment method for the admin page
	        $this->method_description = 'Payement en '. $this->get_option( 'VendorCurrency' ); // will be displayed on the options page  
         
	        // gateways can support subscriptions, refunds, saved payment methods... restrict to simple payments
	        $this->supports = array('products');
         
	        // Method with all the options fields
	        $this->init_form_fields();
         
	        // Load the settings.
	        $this->init_settings();
	        $this->title = $this->get_option( 'title' );
	        $this->description = $this->get_option( 'description' );
	        $this->enabled = $this->get_option( 'enabled' );
	        
	        $this->VendorComChainId = $this->get_option( 'VendorComChainId' );
	        $this->VendorWallet = $this->get_option( 'VendorWallet' );
	        $this->VendorCurrency = $this->get_option( 'VendorCurrency' );
	        $this->VendorLogoURL = $this->get_option( 'VendorLogoURL' );
	        $this->ComChainPublicKey = $this->get_option( 'ComChainPublicKey' );
	        
	        
	        $this->testmode = false;
	            
	        // This action hook saves the settings
	        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
         
	        // Register the webhook
	        add_action( 'woocommerce_api_comchain', array( $this, 'webhook' ) );
         }
    
    
        /**
 		 * Plugin options
 		 */
 		public function init_form_fields(){
 
	        $this->form_fields = array(
		        'enabled' => array(
			        'title'       => 'Enable/Disable',
			        'label'       => 'Enable Com\'Chain Gateway',
			        'type'        => 'checkbox',
			        'description' => '',
			        'default'     => 'no'
		        ),
		        'VendorComChainId' => array(
			        'title'       => 'Identify the store (Id provided by Com\'Chain)',
			        'type'        => 'text',
			        'description' => 'When you registered your store to use web-hook, you where assigned a unique identifier, you should enter it here.',
			        'default'     => '',
		        ),
		        'VendorCurrency' => array(
			        'title'       => 'Identify the currency the customer can pay with',
			        'type'        => 'text',
			        'description' => 'This is the name of your local currency. Check the spelling: when you registered your store to use web-hook, you recieve the exact spelling along with the unique identifier of your store.',
			        'default'     => '',
		        ),
		        'VendorWallet' => array(
			        'title'       => 'Wallet to which the payments has to be sent',
			        'type'        => 'text',
			        'description' => 'Your (active) wallet public adress (0x123...), double chek it!',
			        'default'     => '',
		        ),
		        
		        'VendorLogoURL' => array(
			        'title'       => 'Your logo URL',
			        'type'        => 'text',
			        'description' => 'Your logo URL to be added to the getway page (if blank will be skipped)',
			        'default'     => '',
		        ),
		        
		        
		        'PreferedWebApp' => array(
			        'title'       => 'Web app url for in-browser payement',
			        'type'        => 'text',
			        'description' => 'The web app suggested to the user (match your monney web-app if available)',
			        'default'     => 'https://wallet.cchosting.org',
		        ),
		        
		       'ComChainPublicKey' => array(
			        'title'       => 'Com\'Chain public key',
			        'type'        => 'textarea',
			        'description' => 'This key is used to validate the signature of the payement confirmation\' webhook.',
			        'default'     => '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArX7XahYFP5MxMRw6I+c1
Omrh1Ay6XcROfmBJi7HnVe+E8fo8kqWtW03uzGUCT9wKGZNr+mz6pFWor0Tcr/oe
ykd/6WvDLzkL4E+iwCp7i7+J7v9qYKqzubkHSXG6hpS6bsbzEXDwzqaLg9eRCnlL
0TMN6IcNTD/J6f7XlFUZbZASv4ql8nY4LF7ewn6QOUU2YBSLusczNS1xxjsMRuRx
xMP/2Udlux8FuMTfeED8vnLbcQHDEU0KCMPaQFDsnlajGBEV6xJ+TbX3umBCLiM3
geNLQFuoOiZV97CJgZbSOk//SNn5ihUHFYW6QlH9ZB3hiNOYnjg+pcMlY56lG5B+
5QIDAQAB
-----END PUBLIC KEY-----',
		        ),
		        
		        'title' => array(
			        'title'       => 'User Title',
			        'type'        => 'text',
			        'description' => 'This controls the title which the user sees during checkout.',
			        'default'     => 'Payer en Léman',
			        'desc_tip'    => true,
		        ),
		        'description' => array(
			        'title'       => 'User Description',
			        'type'        => 'textarea',
			        'description' => 'This controls the description which the user sees during checkout.',
			        'default'     => 'Payer en e-Léman à l\'aide de l\'app (ou de la webapp) Biletujo.',
		        ),
		       
	        );
 
	 	}
 
		
        
        
        function process_payment( $order_id ) {
            global $woocommerce;
            $order = new WC_Order( $order_id );

            // Mark as on-hold (we're awaiting the cheque)
            $order->update_status('on-hold', __( 'Awaiting Com\'Chain payment', 'woocommerce' ));

            // Remove cart
            $woocommerce->cart->empty_cart();

            
            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => 'https://com-chain.org/pay/comchain_webhook_getway.php?ShopId='.$this->VendorComChainId. '&TargetWallet='. $this->VendorWallet. '&ServerName='.$this->VendorCurrency.'&Total='.$order->get_total().'&TrnId='.$order_id.'&ReturnURL='.$this->get_return_url($order).'&logoURL='.$this->VendorLogoURL
            );
        }
        
        
        
		public function webhook() {
		if (isset($_POST['resources'])){
		    $txId = $_POST['resources']['reference'];
            $order =  new WC_Order( $txId );
            
            // 1) check message signature 
            // 1.a) get the hash of the message
            $json_message = json_encode($_POST);
            $hash = crc32($json_message);
            
            // 1.b) decode the signature
            $signed = $_SERVER['HTTP_COMCHAIN_TRANSMISSION_SIG'];
            $signature = base64_decode($signed);
   
            // 1.c) get the public key
            $pub_key_id = openssl_pkey_get_public($this->ComChainPublicKey);
   
            // 1.d) check signature
            if (1 == openssl_verify ( $hash , $signature ,  $pub_key_id  )){
                // 2) Get the address the payment has been made to
                $address = $_POST['resources']['addr_to'];
                // 2.a) Check the address is the expected one: 
                if (strtolower($address)==$this->VendorWallet) {
                    // 3) everything OK: record the payment.
                    $amount = intval($_POST['resources']['amount']['sent'])/100;
               
                    
                    if ($order->get_total() == $amount) {
                        $order->payment_complete();
	                    $order->reduce_order_stock();
                    } else {
		                $order->add_order_note( __('WARNING: Recieved a message with wrong amount sent', 'woothemes') );
                        throw new Exception('WARNING: Recieved a message with wrong amount sent: '.$json_message);  
                    }
                } else {
		          $order->add_order_note( __('WARNING: Recieved a message with wrong destinary', 'woothemes') );
                  throw new Exception('WARNING: Recieved a message with wrong destinary: '.$json_message);  
                }
            } else {
		        $order->add_order_note( __('WARNING: Recieved a message with wrong signature', 'woothemes') );
                throw new Exception('WARNING: Recieved a message with wrong signature: '.$_POST['json']. ' Signature: '.$signed);
            }
          } 
	    }
    }
}
