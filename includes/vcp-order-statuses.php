<?php

/**
 * Add new Invoiced status for woocommerce
 */
   
  
 // New order status AFTER woo 2.2
add_action( 'init', 'register_my_new_order_statusess' );

function register_my_new_order_statusess() {
	
	
    register_post_status( 'wc-confirmation', array(
        'label'                     => _x( 'Waiting Blockchain Confirmation', 'Waiting Blockchain Confirmation', 'vcp-payments-woo' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Waiting Blockchain Confirmation <span class="count">(%s)</span>', 'Waiting Blockchain Confirmation<span class="count">(%s)</span>', 'woocommerce' )
    ) );
	
	
	register_post_status( 'wc-fordeposit', array(
        'label'                     => _x( 'Waiting for fund transfer', 'Waiting for fund transfer', 'vcp-payments-woo' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Waiting for fund transfer <span class="count">(%s)</span>', 'Waiting for fund transfer<span class="count">(%s)</span>', 'woocommerce' )
    ) );
	
	
	register_post_status( 'wc-torelease', array(
        'label'                     => _x( 'Waiting for Release', 'Waiting for Release', 'vcp-payments-woo' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Waiting for Release <span class="count">(%s)</span>', 'Waiting for Release<span class="count">(%s)</span>', 'woocommerce' )
    ) );
	
	
	
	register_post_status( 'wc-partiallypaid', array(
        'label'                     => _x( 'Partially paid', 'Partially paid', 'vcp-payments-woo' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Partially paid <span class="count">(%s)</span>', 'Partially paid<span class="count">(%s)</span>', 'woocommerce' )
    ) ); 
}

add_filter( 'wc_order_statuses', 'my_new_wc_order_statusesz' );

// Register in wc_order_statuses.
function my_new_wc_order_statusesz( $order_statuses ) {
    $order_statuses['wc-confirmation'] = _x( 'Waiting Blockchain Confirmation', 'Order status', 'vcp-payments-woo' );
	$order_statuses['wc-torelease'] = _x( 'Waiting for Release', 'Order status', 'vcp-payments-woo' );
	$order_statuses['wc-fordeposit'] = _x( 'Waiting for fund transfer', 'Order status', 'vcp-payments-woo' );
	$order_statuses['wc-partiallypaid'] = _x( 'Partially paid', 'Order status', 'vcp-payments-woo' );
    return $order_statuses;
}

  