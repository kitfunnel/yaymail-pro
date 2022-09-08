<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;
$table_name = $wpdb->prefix . 'dpd_orders_switzerland';
$order_id   = $order->get_id();
$parcels    = $wpdb->get_results( "Select parcel_number FROM $table_name WHERE order_id = $order_id AND status_label = 1" );
if ( count( $parcels ) ) {
	$parc_num                = $parcels[0]->parcel_number;
	$shipping_date           = $wpdb->get_row( "Select shipping_date FROM $table_name WHERE parcel_number = '$parc_num' ORDER BY id DESC" );
	$formatted_shiiping_date = gmdate( 'd.m.Y', absint( $shipping_date->shipping_date ) );
	printf(
		/* translators: %s: Formatted shipping date */
		esc_html__( 'Die Bestellung wird am %s mit DPD verschickt. Hier der Link um die Sendung zu verfolgen: ', 'dpd-shipping-label-switzerland' ),
		esc_html( $formatted_shiiping_date )
	);
	foreach ( $parcels as $parcel ) {
		echo '<a href="https://www.dpdgroup.com/ch/mydpd/tmp/basicsearch?parcel_id=' . $parcel->parcel_number . '" target="_blank">' . $parcel->parcel_number . '</a>';
		echo '<br /><br />';
	}
}

