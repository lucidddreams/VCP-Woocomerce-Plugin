<?php
 
/**
 * Virtual Cash Payment mode.
 *
 * Provides a Cash on Delivery Payment Gateway.
 *
 * @class       WC_Gateway_VCP
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     vcp-payments-woo/Classes/Payment
 */
class WC_Gateway_VCP extends WC_Payment_Gateway {
			 

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        // Setup general properties.
        $this->setup_properties();

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Get settings.
        $this->title              			= $this->get_option( 'title' );
        $this->icon 			  			= apply_filters( 'woocommerce_vcashpay_icon', plugins_url('../assets/icon.png', __FILE__ ) );
        $this->merchant_id        			= $this->get_option( 'merchant_id' );
		$this->network     					= $this->get_option( 'network' );
        $this->description        			= $this->get_option( 'description' );
        $this->instructions       			= $this->get_option( 'instructions' );
        $this->enable_for_methods 			= $this->get_option( 'enable_for_methods', array() ); 
		$this->order_states       			= $this->get_option('order_states');

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) ); 
     
        // Customer Emails.
        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

 
	
    /**
     * Setup general properties for the gateway.
     */
    protected function setup_properties() {
        $this->id                 = 'vcp';
        $this->icon 			  = apply_filters( 'woocommerce_vcashpay_icon', plugins_url('/assets/icon.png', __FILE__ ) ); 
        $this->network     		  = __( 'Enter network', 'vcp-payments-woo' );
        $this->method_title       = __( 'VCashPay - VCP', 'vcp-payments-woo' );
        $this->method_description = __( 'VCP is a decentralized, sustainable, and secure digital money focused on addressing the inefficiencies present in existing financial systems. Get VCP from trusted partners listed at <a href="https://vcashpay.com/">vcashpay.com</a>', 'vcp-payments-woo' );
        $this->has_fields         = false;
		
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {
        $this->form_fields = array(
		 
            'enabled'            => array(
                'title'       => __( 'Enable/Disable', 'vcp-payments-woo' ),
                'label'       => __( 'Enable VCashPay option', 'vcp-payments-woo' ),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ), 
			 
            'title'              => array(
                'title'       => __( 'Title', 'vcp-payments-woo' ),
                'type'        => 'text',
                'description' => __( 'VCP is a decentralized, sustainable, and secure digital money focused on addressing the inefficiencies present in existing financial systems. Get VCP from trusted partners listed at <a href="https://vcashpay.com/">vcashpay.com</a>', 'vcp-payments-woo' ),
                'default'     => __( 'VCashPay - VCP', 'vcp-payments-woo' ),
                'desc_tip'    => true,
            ),
			
            'description'        => array(
                'title'       => __( 'Description', 'vcp-payments-woo' ),
                'type'        => 'textarea',
                'description' => __( 'VCP is a decentralized, sustainable, and secure digital money focused on addressing the inefficiencies present in existing financial systems. Get VCP from trusted partners listed at <a href="https://vcashpay.com/">vcashpay.com</a>', 'vcp-payments-woo' ),
                'default'     => __( 'VCashPay - VCP', 'vcp-payments-woo' ),
                'desc_tip'    => true,
            ),
			
            'instructions'       => array(
                'title'       => __( 'Instructions', 'vcp-payments-woo' ),
                'type'        => 'textarea',
                'description' => __( 'Instructions that will be added to the thank you page.', 'vcp-payments-woo' ),
                'default'     => __( 'VCashPay - VCP', 'vcp-payments-woo' ),
                'desc_tip'    => true,
            ), 
			
            'test_mode'            => array(
                'title'       => __( 'Enable/Disable', 'vcp-payments-woo' ),
                'label'       => __( 'Enable TEST MODE', 'vcp-payments-woo' ),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ), 
			 
			'payment_address' => array(
                'title'   => __( 'Receiving VCP Address', 'vcp-payments-woo' ),
                'label'   => __( 'Receiving VCP Address', 'vcp-payments-woo' ),
                'type'    => 'text', 
            ),
			
			'usd_to_vcp_rate' => array(
                'title'   => __( 'USD to VCP Rate', 'vcp-payments-woo' ),
                'label'   => __( 'USD to VCP Rate', 'vcp-payments-woo' ),
                'type'    => 'text', 
            ),
			
			'usd_to_other_currency_json' => array(
                'title'   => __( 'USD to other_currency_json', 'vcp-payments-woo' ),
                'label'   => __( 'USD to other_currency_json', 'vcp-payments-woo' ),
                'type'    => 'text', 
            ),
			
			'time_limit' => array(
                'title'   => __( 'Time limit', 'vcp-payments-woo' ),
                'label'   => __( 'Time limit', 'vcp-payments-woo' ),
				'description' => __( 'Time limit in hours Example: 8 ', 'vcp-payments-woo' ),
                'type'    => 'select',
                'options'    => array('1'=>'1 Hour','2'=>'2 Hours','3'=>'3 Hours','4'=>'4 Hours','5'=>'5 Hours','6'=>'6 Hours','7'=>'7 Hours','8'=>'8 Hours','9'=>'9 Hours','10'=>'10 Hours','11'=>'11 Hours','12'=>'12 Hours' ),
				'desc_tip'    => true,				
            ),
			
			'passphrase_source' => array(
                'title'   => __( 'Passphrase source', 'vcp-payments-woo' ),
                'label'   => __( 'Passphrase source', 'vcp-payments-woo' ),
				'description' => __( '50 to 100 random words separate by space', 'vcp-payments-woo' ),
                'type'    => 'textarea',
				'desc_tip'    => true,				
            ),
			
			'total_network_confirmation_required' => array(
                'title'   => __( 'Total required network confirmations', 'vcp-payments-woo' ),
                'label'   => __( 'Total required network confirmations', 'vcp-payments-woo' ),
				'description' => __( 'Enter 1 to 10 ', 'vcp-payments-woo' ),
                'type'    => 'select',
                'options'    => array('1'=>'1 Network Confirmation','2'=>'2 Network Confirmations','3'=>'3 Network Confirmations','4'=>'4 Network Confirmations','5'=>'5 Network Confirmations','6'=>'6 Network Confirmations','7'=>'7 Network Confirmations','8'=>'8 Network Confirmations','9'=>'9 Network Confirmations','10'=>'10 Network Confirmations' ),
				'desc_tip'    => true,				
            ),
			
			'auto_release' => array(
                'title'   => __( 'Auto release', 'vcp-payments-woo' ),
                'label'   => __( 'Auto release', 'vcp-payments-woo' ),
                'type'    => 'select',
                'options'    => array('yes'=>'Enabled','no'=>'Disabled' ), 
				'default' => 'no',
            ), 
        );
    }

//
  


    /**
     * Check If The Gateway Is Available For Use.
     *
     * @return bool
     */
    public function is_available() {
        $order          = null;
        $needs_shipping = false;

        // Test if shipping is needed first.
        if ( WC()->cart && WC()->cart->needs_shipping() ) {
            $needs_shipping = true;
        } elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
            $order_id = absint( get_query_var( 'order-pay' ) );
            $order    = wc_get_order( $order_id ); 
            if ( 0 < count( $order->get_items() ) ) {
                foreach ( $order->get_items() as $item ) {
                    $_product = $item->get_product();
                    if ( $_product && $_product->needs_shipping() ) {
                        $needs_shipping = true;
                        break;
                    }
                }
            }
        }

        $needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );
 
        // Only apply if all packages are being shipped via chosen method, or order is virtual.
        if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {
            $order_shipping_items            = is_object( $order ) ? $order->get_shipping_methods() : false;
            $chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

            if ( $order_shipping_items ) {
                $canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids( $order_shipping_items );
            } else {
                $canonical_rate_ids = $this->get_canonical_package_rate_ids( $chosen_shipping_methods_session );
            }

            if ( ! count( $this->get_matching_rates( $canonical_rate_ids ) ) ) {
                return false;
            }
        }

        return parent::is_available();
    }

    /**
     * Checks to see whether or not the admin settings are being accessed by the current request.
     *
     * @return bool
     */
    private function is_accessing_settings() {
        if ( is_admin() ) {
            // phpcs:disable WordPress.Security.NonceVerification
            if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
                return false;
            }
            if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
                return false;
            }
            if ( ! isset( $_REQUEST['section'] ) || 'cod' !== $_REQUEST['section'] ) {
                return false;
            }
            // phpcs:enable WordPress.Security.NonceVerification

            return true;
        }
 
        return false;
    }

    /**
     * Loads all of the shipping method options for the enable_for_methods field.
     *
     * @return array
     */
    private function load_shipping_method_options() {
        // Since this is expensive, we only want to do it if we're actually on the settings page.
        if ( ! $this->is_accessing_settings() ) {
            return array();
        }

        $data_store = WC_Data_Store::load( 'shipping-zone' );
        $raw_zones  = $data_store->get_zones();

        foreach ( $raw_zones as $raw_zone ) {
            $zones[] = new WC_Shipping_Zone( $raw_zone );
        }

        $zones[] = new WC_Shipping_Zone( 0 );

        $options = array();
        foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

            $options[ $method->get_method_title() ] = array();

            // Translators: %1$s shipping method name.
            $options[ $method->get_method_title() ][ $method->id ] = sprintf( __( 'Any &quot;%1$s&quot; method', 'vcp-payments-woo' ), $method->get_method_title() );

            foreach ( $zones as $zone ) {

                $shipping_method_instances = $zone->get_shipping_methods();

                foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

                    if ( $shipping_method_instance->id !== $method->id ) {
                        continue;
                    }

                    $option_id = $shipping_method_instance->get_rate_id();

                    // Translators: %1$s shipping method title, %2$s shipping method id.
                    $option_instance_title = sprintf( __( '%1$s (#%2$s)', 'vcp-payments-woo' ), $shipping_method_instance->get_title(), $shipping_method_instance_id );

                    // Translators: %1$s zone name, %2$s shipping method instance name.
                    $option_title = sprintf( __( '%1$s &ndash; %2$s', 'vcp-payments-woo' ), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'vcp-payments-woo' ), $option_instance_title );

                    $options[ $method->get_method_title() ][ $option_id ] = $option_title;
                }
            }
        } 
        return $options;
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * @since  3.4.0
     *
     * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
     * @return array $canonical_rate_ids    Rate IDs in a canonical format.
     */
    private function get_canonical_order_shipping_item_rate_ids( $order_shipping_items ) { 
        $canonical_rate_ids = array(); 
        foreach ( $order_shipping_items as $order_shipping_item ) {
            $canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
        } 
        return $canonical_rate_ids;
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * @since  3.4.0
     *
     * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
     * @return array $canonical_rate_ids  Rate IDs in a canonical format.
     */
    private function get_canonical_package_rate_ids( $chosen_package_rate_ids ) {

        $shipping_packages  = WC()->shipping()->get_packages();
        $canonical_rate_ids = array(); 
        if ( ! empty( $chosen_package_rate_ids ) && is_array( $chosen_package_rate_ids ) ) {
            foreach ( $chosen_package_rate_ids as $package_key => $chosen_package_rate_id ) {
                if ( ! empty( $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ] ) ) {
                    $chosen_rate          = $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ];
                    $canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
                }
            }
        }

        return $canonical_rate_ids;
    }

    /**
     * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
     *
     * @since  3.4.0
     *
     * @param array $rate_ids Rate ids to check.
     * @return boolean
     */
    private function get_matching_rates( $rate_ids ) {
        // First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
        return array_unique( array_merge( array_intersect( $this->enable_for_methods, $rate_ids ), array_intersect( $this->enable_for_methods, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment( $order_id ) { 
        $order = wc_get_order( $order_id ); 
        if ( $order->get_total() > 0 ) { 
          $rpayment =  $this->vcp_payment_processing( $order ); 
        }   
        if($rpayment['status']==='waiting-confirmation'){  
				writeText( "order#".$order_id." ".json_encode( $rpayment['json_response'] ) );  
				WC()->cart->empty_cart(); 
				$order->update_status( 'wc-fordeposit' ); 
			  return array( 
			 		'result'   => 'success',
					'redirect' => home_url('/vcp/?orderid='.$order_id) 
		 	 	);
        }else{
            wc_add_notice( __('Payment failed: ', 'woothemes') . "Error try again." , 'error' );
            return;
        } 
    }

 




    private function vcp_payment_processing( $order){
        global  $woocommerce;
       
        $currency       	= get_woocommerce_currency(); 
        $request_token  	= md5(uniqid(rand(), true));   
		$recipient			= WC()->payment_gateways->payment_gateways()['vcp']->get_option('payment_address') ; 
		$timelimit			= WC()->payment_gateways->payment_gateways()['vcp']->get_option('time_limit') ;  
		$amountNQT			= getVCPValue();  
		 if( $amountNQT and $amountNQT > 0 ){ 
				$passphrase						= getPassPhrase($num = 12); 
				$json_r							= get_vcp_response(array( 
																	'requestType' 		=> 'getAccountId', 
																	'secretPhrase' 		=> getCovertPassPhrase($passphrase )
															)); 
				$json_response 					= json_decode($json_r); 
				if( isset($json_response->accountRS) and $json_response->accountRS !=''){ 
					$json_response 					= json_decode($json_r, true); 
					$json_response['secretPhrase'] 	= $passphrase ;
					$json_response['vcp'] 			= getVCPValue();
					$json_response['deadline'] 		= date("U", strtotime('+'.$timelimit.' hours')); 
					wp_update_post( array('ID' => $order->get_id(),'post_content' => json_encode( $json_response ), 'post_mime_type'=>'waiting-confirmation'));  
					return array( 
												"status" 			=> 	"waiting-confirmation",
												"json_response" 	=>   json_encode( $json_response )
								); 
				}else{ 
					return array(
									"hash"		=>   '' ,
									"status" 	=> 	"incorrect-passphrase"  ,
									"json_response" 	=>  $json_r
								) ; 
				}
		}else{
			return array(
										"hash"		=>   '' ,
										"status" 	=> 	"incorrect-passphrase"  
									) ; 
		} 
    }
 



    /**
     * Output for the order received page.
     */
    public function thankyou_page() {
        if ( $this->instructions ) {
            echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
        }
    }

    /**
     * Change payment complete order status to completed for COD orders.
     *
     * @since  3.1.0
     * @param  string         $status Current order status.
     * @param  int            $order_id Order ID.
     * @param  WC_Order|false $order Order object.
     * @return string
     */
    public function change_payment_complete_order_status( $status, $order_id = 0, $order = false ) {
        if ( $order && 'cod' === $order->get_payment_method() ) {
            $status = 'completed';
        }
        return $status;
    }

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order Order object.
     * @param bool     $sent_to_admin  Sent to admin.
     * @param bool     $plain_text Email format: plain text or HTML.
     */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
        if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
            echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
        }
    }
	
	
	function log($txt){
			$file 		= fopen("debug.txt", "a") or die("Unable to open file!");
			fwrite($file, date("l jS \of F Y h:i:s A")."\n " .$txt."\n\n");
			fclose($file); 
		}
	
}



function get_vcp_response( $data , $method='POST'){ 
	  $response = wp_safe_remote_post( getNetworkURL() , array( 
													'method' 		=> $method,
													'timeout' 		=> 45,
													'redirection' 	=> 5,
													'httpversion' 	=> '1.0',
													'blocking' 		=> true,
													'headers' 		=> array(),
													'cookies' 		=> array(),
													'body' 			=> $data
												) ); 
		 if( !is_wp_error( $response ) ) {		
				if( $response['response']['code'] == 200 )
					return $response['body'];
		 }else{ 
			return false;
		 }
}

 
 function getPassPhrase($num){ 
	 $source 	= explode(" ", WC()->payment_gateways->payment_gateways()['vcp']->get_option('passphrase_source') ); 
	 $tmp		= array();
	 $i			= 0; 
			while(	$i<=$num ){ 
				$n =  rand(0, count($source)-1); 
				if( !in_array( $n, $tmp)){ 
					$tmp[] = $n; 
					$i++;
				}
			} 
	 return trim( implode(" ",$tmp)); 
 }
 
 
  function getCovertPassPhrase($num){
	 $source 	= explode(" ", WC()->payment_gateways->payment_gateways()['vcp']->get_option('passphrase_source') ); 
	 $tmp		= array();
	 $i			= 0; 
			$nums = explode(" ",$num);
			foreach($nums as $n){
				$tmp[] = $source[ $n ]; 
			} 
	 return trim( implode(" ",$tmp)); 
 }
 
 
 function getNetworkURL(){
	 if( WC()->payment_gateways->payment_gateways()['vcp']->get_option('test_mode') =='yes' ){ 
				return "https://testnet.vcashpay.com/nxt?";  
		}else{
				return "https://coin.vcashpay.com/nxt?"; 
		}
 } 
 
 function getNetworkFEE(){
	 if( WC()->payment_gateways->payment_gateways()['vcp']->get_option('test_mode')=='yes' ){ 
				return 10000000;  
		}else{
				return 100000000; 
		}
 } 
 
 
 function isOnTestMode(){
	 if( WC()->payment_gateways->payment_gateways()['vcp']->get_option('test_mode')=='yes' ){ 
				return '<div style="color:red">Warning: Test mode is Active!</div>';  
		} 
 }