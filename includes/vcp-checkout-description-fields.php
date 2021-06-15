<?php

add_filter( 'woocommerce_gateway_description', 'vcp_description_fields', 20, 2,  );
add_action( 'woocommerce_checkout_process', 'vcp_description_fields_validation' );
add_action( 'woocommerce_checkout_update_order_meta', 'checkout_update_order_meta', 10, 1 );
add_action( 'woocommerce_admin_order_data_after_billing_address', 'order_data_after_billing_address', 10, 1 );
add_action( 'woocommerce_order_item_meta_end', 'order_item_meta_end', 10, 3 );

function vcp_description_fields( $description, $payment_id  ) {

    if ( 'vcp' !== $payment_id ) {
        return $description;
    }
    
    ob_start();
 
	
    echo '<div style="display: block;   height:auto;">';
    echo '<img style="display: block; width:30px; height:auto;" src="' . plugins_url('../assets/icon.png', __FILE__ ) . '">';
	 
    $vcp_amount = getVCPValue( );
	if( $vcp_amount and  $vcp_amount > 0   ){
		
		echo '<div style="display: block;   height:auto;"> <b> '.number_format( $vcp_amount ,2).' VCP</b></div>';
		
		// echo getPassPhrase($num = 12);
		    /*
			woocommerce_form_field(
				'card_code_number',
				array(
					'type' => 'password',
					'label' =>__( 'Enter Passphrase.', 'vcp-payments-woo' ),
					'class' => array( 'form-row', 'form-row-wide' ),
					'required' => true,
					'value'=>'####-####-####-####',
					'data-mask'=>'####-####-####-####',
				)
			); 
			woocommerce_form_field(
				'reference_number',
				array(
					'type' => 'hidden', 
				)
			); */
			
			
	}else{
		echo "<b>Not available</b>" ;
	}
	
 
    echo '</div>';

    $description .= ob_get_clean();

    return $description;
}


function vcp_description_fields_validation() {
	if( 'vcpx' === $_POST['payment_method']){
		if( 'vcp' === $_POST['payment_method'] && ! isset( $_POST['card_code_number'] )  || empty( $_POST['card_code_number'] ) ) {
			wc_add_notice( 'Please enter your passphrase to continue', 'error' );
		}
	} 
}

function checkout_update_order_meta( $order_id ) {
	//if( 'vcp' === $_POST['payment_method']){
	//	if( isset( $_POST['card_code_number'] ) || ! empty( $_POST['card_code_number'] ) ) {
		   //update_post_meta( $order_id, 'card_code_number', $_POST['card_code_number'] );
		   //update_post_meta( $order_id, 'reference_number', 'xxxxxxxxxx--');
	//	}
	//}
}

function order_data_after_billing_address( $order ) { 
	if( 'vcp' === $order->get_payment_method()){
		
			$post = get_post( $order->get_id() );
					$echo ='';
					if( isset( $post->post_content) and $post->post_content !=''){ 
						$details = json_decode($post->post_content); 
						 
						//echo '<textarea>'.$details->transfer_details->amountNQT.'</textarea><br>' ;
						echo ( (isset($details->accountRS) and $details->accountRS !="")?  'VCP account : <b>'.$details->accountRS.'</b> <br>':'');
						echo ( (isset($details->vcp) and $details->vcp !="")?  'Total : <b>'.number_format($details->vcp, 2).' VCP</b> <br>':'');
						echo 'Amount received : '.( (isset($details->transfer_details->transactionJSON->amountNQT) and $details->transfer_details->transactionJSON->amountNQT !="")?  '<b>'.number_format(($details->transfer_details->transactionJSON->amountNQT/100000000), 2).' VCP</b> <br>':'<b> 0.00 VCP</b> <br>');
						 
						echo sweepDetails($post->post_content);
						
						echo '<span id="sweep_span"></span><br>';
						echo '<a href="#" class="woocommerce-button button view sweep" data-id="'.$order->get_id().'">Sweep</a>';
						echo sweepJS();
						//echo  $post->post_content;
						
						
					}
	}
}


function  sweepDetails($post_content){
	 
	if(isset( json_decode($post_content)->sweep_details)){
		
	$sweeps = json_decode($post_content)->sweep_details;
	$li 	= '';
	foreach($sweeps as $sw){
		$li .='<li> Amount sweep : <b>'.number_format( ($sw->details->transactionJSON->amountNQT/100000000), 2)." VCP</b>"."<br>
					Date: <b>".$sw->date.'</b></li>';
	}
	return "<h3>Sweep details:</h3><ol>".$li."</ol>";
	}
}


function order_item_meta_end( $item_id, $item, $order ) {
//   echo '<p><strong>' . __( 'Card code number:', 'vcp-payments-woo' ) . '</strong><br>' . get_post_meta( $order->get_id(), 'card_code_number', true ) . '</p>';
}
 
 
function getVCPValue( ){
			global $woocommerce; 
			if( ! getUSD_to_VCPRate() ) return false;
			if( ! getBaseCurrency( get_woocommerce_currency() ) ) return false;
			
			return   ( getBaseCurrency( get_woocommerce_currency() ) * WC()->cart->total) / getUSD_to_VCPRate()  ;// ."|".WC()->cart->total	."|". $usd_per_cuur."|". getVCPRate();
}
 
function getBaseCurrency( $target ){ 
		$json = WC()->payment_gateways->payment_gateways()['vcp']->get_option('usd_to_other_currency_json');
		$rate = json_decode($json, true);  
		if(json_last_error() === JSON_ERROR_NONE) 
			return 1/$rate['rates'][$target]  ; 
		return false;
}


function getUSD_to_VCPRate(){ 
		$usd_to_vcp_rate = WC()->payment_gateways->payment_gateways()['vcp']->get_option('usd_to_vcp_rate');
		if( is_numeric( $usd_to_vcp_rate ) )  
			return 1/$usd_to_vcp_rate  ; 
		return false; 
}
 
 
 function sweepJS(){
	 return "<script>
					
					jQuery(document).ready(function($) {
						 
						$('.sweep').click(function(){ 
						 // alert('xxxxxxxxxxx');
						  var orderid = $(this).data('id');
						  
										$.ajax({
												 type: 'POST',
												 url: '".home_url("/wp-json/vcp/v1/vcp_sweep")."',
												 data: { 
													order_id:  orderid  //add to your request data
												 },
												 dataType: 'json',
												 beforeSend: function() {
													 
														//console.log( 'orderid=' + orderid );
														//console.log( '".home_url("/wp-json/vcp/v1/vcp_sweep")."' );
														$('#sweep_span').html('');
														$('.sweep').html('checking...').prop('disabled', true);
														
													},
												 success: function(textStatus){ 
													
														//console.log(  textStatus );
													var json =	jQuery.parseJSON(textStatus);
													
														if( json.status == 'nobalance' ){
															$('#sweep_span').text( 'No outstanding balance' );
															$('.sweep').html('Sweep').prop('disabled', false);
														}
														
														if( json.status == 'sweep' ){
															location.reload();
														}
														
														if( json.status == 'active' ){
															$('#sweep_span').html(  json.message );
															$('.sweep').html('Sweep').prop('disabled', false);
														}
														
												 },
												error: function(MLHttpRequest, textStatus, errorThrown){ 
													  console.log( textStatus ); 
												}
											
											}).done( function(){  } );
															 
							return false;							 
						});



							  
								
							///*******************	
					}); ///

				</script>";
 
 }
 
 