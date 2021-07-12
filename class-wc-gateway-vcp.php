<?php
/**
 * Plugin Name: VCashPay for Woocommerce
 * Plugin URI: https://github.com/lucidddreams/VCP-Woocomerce-Plugin
 * Author Name: RFlora214
 * Author URI: https://fb.com/rflora214/
 * Description: This plugin allows for virtual payment systems.
 * Version: 1.1.0
 * License: 1.1.0
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: vcp-pay-woo
 * Class WC_Gateway_VCP file.
 *  
 * @package WooCommerce\VCP  
 */ 
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
 
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;
 
add_action( 'plugins_loaded', 'vcashpay_init', 11 );
 
function vcashpay_init() {
    if( class_exists( 'WC_Payment_Gateway' ) ) {
			require  plugin_dir_path(__FILE__). ('/includes/class-wc-payment-vcp.php');
			require  plugin_dir_path(__FILE__). ('/includes/vcp-order-statuses.php');
			require  plugin_dir_path(__FILE__). ('/includes/vcp-checkout-description-fields.php');
	}
} 
 
add_filter( 'woocommerce_payment_gateways', 'add_to_vcp_gateway'); 
 
function add_to_vcp_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_VCP';
    return $gateways;
}  

function vcp_payments(WP_REST_Request $request ){	
			$data 				= $request->get_params(); 			
			$order_id			= intval( $data['order_id'] );			
			return processOrder( $order_id );		 
}
 

function vcp_sweep(WP_REST_Request $request ){	
			$data 				= $request->get_params(); 			
			$order_id			= intval( $data['order_id'] );			
			return do_sweep( $order_id );			 
}
 

function do_sweep( $order_id ){			
			$order 				= wc_get_order( $order_id ); 			
			$post 				= get_post( $order_id ); 			
			$details 			= json_decode( $post->post_content ); 			 
			$balance			= 0;   
			if(  $order->get_status()!=='fordeposit' ){  			 
					$d = json_decode( $post->post_content, true); 
					$json_xxx			= get_vcp_response(array( 
											'requestType' 				=> 'getGuaranteedBalance', 
											'account' 					=> $details->accountRS,
											'numberOfConfirmations' 	=> WC()->payment_gateways->payment_gateways()['vcp']->get_option('total_network_confirmation_required')  
											));   					 
					$json_not_assoc 	= json_decode( $json_xxx );  
						if( !isset($json_not_assoc->errorDescription) )								
							$balance 			=  ( (isset($json_not_assoc->guaranteedBalanceNQT) and floatval( $json_not_assoc->guaranteedBalanceNQT )>0 )? (floatval( $json_not_assoc->guaranteedBalanceNQT ) / 100000000):0 ); 
						if( $balance > 0){ 
										$recipient			=  WC()->payment_gateways->payment_gateways()['vcp']->get_option('payment_address') ;  
										$transfer_json		= get_vcp_response(array( 
																							'requestType' 		=> 'sendMoney', 
																							'recipient' 		=> $recipient, 
																							'secretPhrase' 		=> getCovertPassPhrase( $details->secretPhrase ), 
																							'publicKey' 		=> $details->publicKey, 
																							'amountNQT' 		=> $json_not_assoc->guaranteedBalanceNQT-getNetworkFEE() , 
																							'feeNQT' 			=> getNetworkFEE(), 
																							'deadline' 			=> 60 
																					));  
										$t = json_decode( $transfer_json);  
										 if( isset( $t->signatureHash) and $t->signatureHash !=''){ 
												$d['sweep_details'][] = array(
																				'date'		=>date("F j, Y, g:i a"),
																				'details'	=> json_decode( $transfer_json, true)
																				);  
										 } 
									writeText("sweep order#".$order->get_id()." ". json_encode( $d ) ); 
									wp_update_post( array('ID' => $order->get_id(),'post_content' => json_encode( $d ) , 'post_mime_type'=>''));  
							return json_encode( array(
													'status' => 'sweep' 
													));			 
						}else{
							return json_encode( array(
													'status' => 'nobalance' 
													));
						} 
			}else{ 
				$fff			= get_vcp_response(array( 
											'requestType' 				=> 'getBalance', 
											'account' 					=> $details->accountRS 
											));   
				$s = json_decode( $fff );							
				$b = ( (isset($s->balanceNQT) and floatval( $s->balanceNQT )>0 )? (floatval( $s->balanceNQT ) / 100000000):0 ); 
				return json_encode( array(
													'status' => 'active',
													'message' => 'Unconfirmed balance is <b>'.number_format($b,2). ' VCP </b>'
											));
			}
}






function confirmationCron(){
	global $wpdb;
	$posts 					= $wpdb->get_results("SELECT id, post_content FROM $wpdb->posts WHERE post_type = 'shop_order' AND post_mime_type='waiting-confirmation' "); 
	  foreach($posts as $obj){ 
			$details 		= json_decode( $obj->post_content );  
			$order 			= wc_get_order( $obj->id );  
		    processOrder( $obj->id  ); 
	  }  
}



function processOrder( $order_id ){  
			$order 				= wc_get_order( $order_id );  
			$post 				= get_post( $order_id );  
			$details 			= json_decode( $post->post_content );  
			$balance			= 0;  
			if(  $order->get_status()=='fordeposit' and  ( intval($details->deadline) - time())<= 0  ){  
											$d = json_decode( $post->post_content, true); 
											$json_xxx			= get_vcp_response(array( 
																	'requestType' 				=> 'getGuaranteedBalance', 
																	'account' 					=> $details->accountRS,
																	'numberOfConfirmations' 	=> WC()->payment_gateways->payment_gateways()['vcp']->get_option('total_network_confirmation_required')  
																	));    
											$json_not_assoc 	= json_decode( $json_xxx );  
											if( !isset($json_not_assoc->errorDescription) ) 
												$balance 			=  ( (isset($json_not_assoc->guaranteedBalanceNQT) and floatval( $json_not_assoc->guaranteedBalanceNQT )>0 )? (floatval( $json_not_assoc->guaranteedBalanceNQT ) / 100000000):0 ); 
											if( $balance > 0){ 
															$recipient			=  WC()->payment_gateways->payment_gateways()['vcp']->get_option('payment_address') ;  
															$transfer_json		= get_vcp_response(array( 
																												'requestType' 		=> 'sendMoney', 
																												'recipient' 		=> $recipient, 
																												'secretPhrase' 		=> getCovertPassPhrase( $details->secretPhrase ), 
																												'publicKey' 		=> $details->publicKey, 
																												'amountNQT' 		=> $json_not_assoc->guaranteedBalanceNQT-getNetworkFEE(), 
																												'feeNQT' 			=> getNetworkFEE(), 
																												'deadline' 			=> 60 
																										));  
															$t = json_decode( $transfer_json);  
															 if( isset( $t->signatureHash) and $t->signatureHash !=''){  
																	$d['transfer_details'] = json_decode( $transfer_json, true);  
															 }
											writeText("cancelled payment order#".$order->get_id()." ". json_encode( $d ) ); 
											$order->update_status(  'wc-partiallypaid' );  
											}else{ 
											$order->update_status(  'wc-cancelled' );
											} 
											wp_update_post( array('ID' => $order->get_id(),'post_content' => json_encode( $d ) , 'post_mime_type'=>'')); 
												 
				return json_encode( array( 'status' => 'cancelled'  ) ) ;
			}
			
			
			/*  */
			if( isset($details->transfer_details) and $details->transfer_details != '' and $order->get_status()=='processing' ){  
							$transaction 			= $details->transfer_details->transaction; 
							$transaction_json		= get_vcp_response(array( 
																				'requestType' 		=> 'getTransaction', 
																				'transaction' 		=> $transaction 
																		));  
							$transaction_json 		= json_decode( $transaction_json ); 
									if( isset($transaction_json->confirmations) and intval($transaction_json->confirmations) >= WC()->payment_gateways->payment_gateways()['vcp']->get_option('total_network_confirmation_required')) { 
										if(WC()->payment_gateways->payment_gateways()['vcp']->get_option('auto_release') ==='yes'){
										 	$order->update_status(  'wc-completed' );
										  	$order->payment_complete(); 
										}else{
											$order->update_status(  'wc-torelease' );
										}  
										wp_update_post( array('ID' => $order->get_id() , 'post_mime_type'=>'')); 
										return json_encode( array(
																'status' 		=> 'complete',
																'textstatus'	=> "Success"
																) ) ;
																
									}else{
										return json_encode( array(
																'status' 		=> 'confirmation',
																'textstatus'	=> "Waiting for network confirmation" ,
																'html' 			=> 'Blockchain confirmation:<b>  '.( intval(WC()->payment_gateways->payment_gateways()['vcp']->get_option('total_network_confirmation_required')) - (isset($transaction_json->confirmations) and intval( $transaction_json->confirmations)>0?intval( $transaction_json->confirmations):0) ).' Remaining </b>' 																
																) ) ; 
									} 
			}else{ 
					$json_xxx			= get_vcp_response(array( 
																	'requestType' 				=> 'getGuaranteedBalance', 
																	'account' 					=> $details->accountRS,
																	'numberOfConfirmations' 	=> WC()->payment_gateways->payment_gateways()['vcp']->get_option('total_network_confirmation_required') 
																	));    
					 
					$json_not_assoc 	= json_decode( $json_xxx );   
					$topay				= round(floatval($details->vcp), 2); 
					$balance 			= 0; 
					$balance 			= (( !isset($json_not_assoc->errorDescription) and isset($json_not_assoc->guaranteedBalanceNQT) and intval( $json_not_assoc->guaranteedBalanceNQT )>0)? (intval( $json_not_assoc->guaranteedBalanceNQT ) / 100000000):0 );
				 	$balance 			= round( floatval($balance),2 );  
								
								if( $balance >= $topay  ){ 
														$recipient			=  WC()->payment_gateways->payment_gateways()['vcp']->get_option('payment_address') ;   
														$transfer_json		= get_vcp_response(array( 
																											'requestType' 		=> 'sendMoney', 
																											'recipient' 		=> $recipient, 
																											'secretPhrase' 		=> getCovertPassPhrase( $details->secretPhrase ), 
																											'publicKey' 		=> $details->publicKey, 
																											'amountNQT' 		=> $json_not_assoc->guaranteedBalanceNQT-getNetworkFEE(), 
																											'feeNQT' 			=> getNetworkFEE(), 
																											'deadline' 			=> 60 
																									));  
														$t = json_decode( $transfer_json);  
														 if( isset( $t->signatureHash) and $t->signatureHash !=''){ 
																$d = json_decode( $post->post_content, true); 
																$d['transfer_details'] = json_decode( $transfer_json, true);  
																writeText("payment details order#".$order->get_id()." ". json_encode( $d ) ); 
																wp_update_post( array('ID' => $order->get_id(),'post_content' => json_encode( $d ) ));  
																$order->update_status(  'wc-processing' ); 
																return json_encode( array(
																					'status' 		=> 'balance',
																					'html' 			=> 'Filled' 
																					) ) ; 
														 }  
																return json_encode( array(
																					'status' 		=> 'balance',
																					'html' 			=> 'Filled' 
																					) ) ;
									
								}else{ 
														return json_encode( array(
																					'status' 		=> 'balance',
																					'html' 			=> ' '.number_format( round(  $topay - $balance, 2 ), 2) . ' VCP ',
																					'vcpamount'		=> round(  $topay - $balance, 2 ),
																					'textstatus'	=> ((($topay - $balance) < $topay ) ?"Partially paid ":"Waiting for fund transfer") ,//."|".( $topay - $balance) ,
																					'timeleft'		=> $details->deadline."|".time()."[".($details->deadline- time())."]"
																					) ) ;
																
								} 
			}
			
}


/* */
function writeText($txt){
	
	 $plugindir = plugin_dir_path( __FILE__ );
 
	 makeDir($plugindir."/logs") ;
	

    $file = fopen( $plugindir."/logs/vcp_logs.txt","a");
 	fwrite($file, date("l jS \of F Y h:i:s A")."\n " .$txt."\n\n");
	fclose($file); 
}

function makeDir($path)
	{
		 return is_dir($path) || mkdir($path);
	} 

add_shortcode('vcp_confirmationCron', 'confirmationCron');  

add_filter( 'cron_schedules', 'vcp_add_cron_intervals' );

function vcp_add_cron_intervals( $schedules ) {

   $schedules['240seconds'] = array( // Provide the programmatic name to be used in code
									  'interval' => 900 , // Intervals are listed in seconds
									  'display' => __('Every 240 Seconds') // Easy to read display name
								   );
   return $schedules; // Do not forget to give back the list of schedules!
   
}

add_action( 'vcp_bl_cron_hook', 'confirmationCron' );

if( !wp_next_scheduled( 'vcp_bl_cron_hook' ) ) {
   wp_schedule_event( time(), '240seconds', 'vcp_bl_cron_hook' );
} 
add_action('rest_api_init', function() {
	
	register_rest_route('vcp/v1', 'posts', [
		'methods' => 'POST',
		'callback' => 'vcp_payments',
	]); 
});


add_action('rest_api_init', function() {
	
	register_rest_route('vcp/v1', 'vcp_sweep', [
		'methods' 	=> 'POST',
		'callback' 	=> 'vcp_sweep',
	]);


});
 
 
// register shortcode
add_shortcode('vcp_waiting_confirmation', 'tutsplus_add_script_wp_footer'); 


function tutsplus_add_script_wp_footer() { 
	if(isset( $_GET['orderid'] ) ){
	 
		$orderid	= intval( $_GET['orderid'] );
		
		$order 		= wc_get_order( $orderid );
 
					 if( $order ){ 
								if( $order->get_payment_method()=='vcp' and ( $order->get_status()=== 'fordeposit' or $order->get_status()=== 'confirmation' ) ){
	 
								$post 			= get_post( $order->get_id() );
								$echo 			='';
								$details 		= json_decode($post->post_content); 

								$difference_in_seconds = gmdate("Y-m-d\TH:i:s\Z", $details->deadline);

								$show_tick  	= ($order->get_status()=== 'fordeposit'? 'tick();':'');
								$timer  		= ($order->get_status()=== 'fordeposit' ? '<span id="timerxx"> Time Left : <b id="timeleft" data-time="'.$difference_in_seconds.'" > computing </b> </span><br>':''); 
								 
								$echo .= '<h2> VCP Payment details:</h2>
											<b id="tvcp" style="display:none">'. round( $details->vcp , 2 )  .' </b>
											VCP Address 	: <b id="accountRS">'.$details->accountRS.'</b> <a href="#" class="cpylink " data-data="'.$details->accountRS.'">copy</a><br>
											VCP Public Key 	: <b id="publicKey">'.$details->publicKey.'</b> <a href="#" class="cpylink" data-data="'.$details->publicKey.'">copy</a><br>
											<span id="confirmation">
													<span id="balance">
															Amount to Pay : <b  id="checking">'. round( $details->vcp , 2 )  .' VCP </b>
													</span>	
														<a href="#" id="remainingvcp" class="cpylink" data-data="'. round( $details->vcp , 2 )  .'">copy</a> 
													</span>
															<br>  
												Status : <b id="orderstatus">Processing</b> <br>
												'.$timer.'
												
												Make sure to send enough to cover any coin transaction fees!
												<br>
												<br> 
												 <style>
												 .cpylink{ 
															text-decoration: none;
														}
												</style>
										
									
								';  
							 
								return isOnTestMode() .$echo." 
									<script>
														
														jQuery(document).ready(function($) {
															var i=0; interval = 30000, orderid =".$orderid."; 
															setTimeout( check , 1000 );
															 
															function checking ( ){  
																setTimeout( check , interval );
															} 
															function check(){ 
															  
																								$.ajax({
																										 type: 'POST',
																										 url: '".home_url("/wp-json/vcp/v1/posts")."',
																										 data: { 
																											order_id:  orderid  //add to your request data
																										 },
																										 dataType: 'json',
																										 beforeSend: function() {
																												//$('#checking').html( 'checking' ); 
																											},
																										 success: function(textStatus){
																											 
																											//console.log( textStatus );
																											textStatus 	=	jQuery.parseJSON(textStatus);
																											
																											
																											if( textStatus.status=='complete'){ 
																												window.open('".home_url("/checkout/order-received/". $orderid."/?key=".$order->get_order_key() )."','_self')
																											}
																											
																											if( textStatus.status=='cancelled'){ 
																												window.open('".home_url("/my-account/view-order/". $orderid )."','_self')
																											}
																											
																											if( textStatus.status=='balance'){ 
																												$('#orderstatus').html( textStatus.textstatus );
																												$('#checking').html( textStatus.html );
																												$('#remainingvcp').data('data', textStatus.vcpamount  );
																											}
																											  
																											if( textStatus.status=='confirmation'){ 
																												$('#orderstatus').html( textStatus.textstatus );
																												$('.cpylink').hide(); 
																												$('#timerxx').hide(); 
																												$('#confirmation').html( textStatus.html ); 
																											} 
																											  
																											//setTimeout( check , interval );
																										 },
																										error: function(MLHttpRequest, textStatus, errorThrown){
																											/// alert('errorThrown');
																											  console.log( textStatus );
																											 // setTimeout( check , interval );
																										}
																									
																									}).done( checking );
																								 
																							 
															}


																
															$(\".cpylink\").click(function(){ 
																	copyToClipboard( $(this).data(\"data\") );
																	return false;
																})
											
														function copyToClipboard(element) { 
																	  var temp = $(\"<input>\");
																	  $(\"body\").append(temp);
																	  temp.val( element ).select();
																	  document.execCommand(\"copy\");
																	  temp.remove();
																	}
																	 
																					".$show_tick." 
																				  var start = new Date( $('#timeleft').data('time') ) ;
																				//  start.setHours(23, 0, 0); // 11pm

																				  function pad(num) {
																					return (\"0\" + parseInt(num)).substr(-2);
																				  }

																				  function tick() {
																					var now = new Date;
																					//if (now > start) { // too late, go to tomorrow
																					//  start.setDate(start.getDate() + 1);
																					//}
																					var remain = ((start - now) / 1000);
																					var hh = pad((remain / 60 / 60) % 60);
																					var mm = pad((remain / 60) % 60);
																					var ss = pad(remain % 60);
																					document.getElementById('timeleft').innerHTML = hh + \":\" + mm + \":\" + ss;
																					
																					//console.log(now +' '+ start );
																					if (now > start){
																						document.getElementById('timeleft').innerHTML = 'Time is up';
																						//window.open('".home_url("/my-account/view-order/". $orderid )."','_self')
																						setTimeout(check, 4000);
																					}else{
																						setTimeout(tick, 1000);
																					}
																				  }
 
																  
																	
																///*******************	
														}); ///

													</script>
													";
					 }else{
						 
						 	if( $order->get_payment_method()=='vcp' and $order->get_status()=== 'torelease' ){
								
								return  "<script>window.open('".home_url("/checkout/order-received/". $orderid."/?key=".$order->get_order_key() )."','_self')</script>";
							
							}
							
							
						 	if( $order->get_payment_method()=='vcp' and $order->get_status()=== 'cancelled' ){
							
								return  "<script>window.open('".home_url("/my-account/view-order/". $orderid )."','_self')</script>";
							
							}
					 }
				}
		}
}

 
 
			

   



add_filter( 'cron_schedules', 'vcp_add_cron_intervals_2' );

function vcp_add_cron_intervals_2( $schedules ) {

   $schedules['18000seconds'] = array( // Provide the programmatic name to be used in code
									  'interval' => 18000 , // Intervals are listed in seconds
									  'display' => __('Every 18000 Seconds') // Easy to read display name
								   );
   return $schedules; // Do not forget to give back the list of schedules!
   
}

add_action( 'vcp_bl_cron_hook_update_settings', 'updateVCPSettings' );

if( !wp_next_scheduled( 'vcp_bl_cron_hook_update_settings' ) ) {
   wp_schedule_event( time(), '18000seconds', 'vcp_bl_cron_hook_update_settings' );
}

function updateVCPSettings(){
	getUpdateBaseCurrency();
	getUpdateVCPRate();
}




function getUpdateBaseCurrency(){
	
		 $url = 'https://openexchangerates.org/api/latest.json?app_id=85d1251c592546b29c9db83659d92d8f&base=USD'; 
		 $response = wp_safe_remote_post( $url, array( 
													'method' 		=> 'POST',
													'timeout' 		=> 45,
													'redirection' 	=> 5,
													'httpversion' 	=> '1.0',
													'blocking' 		=> true,
													'headers' 		=> array(),
													'cookies' 		=> array()
												) );
	 
		 if( !is_wp_error( $response ) ) {		
				if( $response['response']['code'] == 200 ){
					$rate = json_decode($json = $response['body'], true); 
					if(  $rate !='' ){
						$vcp =  WC()->payment_gateways->payment_gateways()['vcp'] ;
						$vcp->update_option('usd_to_other_currency_json', json_encode( $rate ) );
					} 
				} 
		 }  
}




function getUpdateVCPRate(){
		$url 		=  "https://vcashpay.com/request-exporttxt.txt"; 
		$response 	= wp_safe_remote_post( $url, array( 
													'method' 		=> 'POST',
													'timeout' 		=> 45,
													'redirection' 	=> 5,
													'httpversion' 	=> '1.0',
													'blocking' 		=> true,
													'headers' 		=> array(),
													'cookies' 		=> array()
												) ); 
		
		if( !is_wp_error( $response ) ) {		
				if( $response['response']['code'] == 200 ){ 
					$arr 		= explode("\n", $response['body'] ); 
					$rate 		= false;
					foreach( $arr as $r ){ 
						$k 		= explode(";", $r); 
						if( $k[0]==='USDT' and $k[1] === 'VcashPay'){
							$rate = $k[2]/$k[3];
						}
					}
					 
					if( is_numeric( $rate )){ 
						$vcp =  WC()->payment_gateways->payment_gateways()['vcp'] ;
						$vcp->update_option('usd_to_vcp_rate', 1/floatval($rate ) );
					} 
				} 
		 }
		 
}

function sv_add_my_account_order_actions( $actions, $order ) {
	if( $order->get_payment_method()=='vcp' and ( $order->get_status()=== 'fordeposit' or $order->get_status()=== 'confirmation' ) ){
			$actions['name'] = array(
				'url'  => home_url('/vcp/?orderid='.$order->get_id() ),
				'name' => 'Order status',
			);
			return $actions;
	} 
		return  $actions;
	 
}
add_filter( 'woocommerce_my_account_my_orders_actions', 'sv_add_my_account_order_actions', 10, 2 );

