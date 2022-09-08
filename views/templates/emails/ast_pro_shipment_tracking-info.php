<?php

if ( $tracking_items ) :

	$ast_customizer = Ast_Customizer::get_instance();

	$border_color              = $ast->get_option_value_from_array( 'tracking_info_settings', 'fluid_table_border_color', $ast_customizer->defaults['fluid_table_border_color'] );
	$border_radius             = $ast->get_option_value_from_array( 'tracking_info_settings', 'fluid_table_border_radius', $ast_customizer->defaults['fluid_table_border_radius'] );
	$background_color          = $ast->get_option_value_from_array( 'tracking_info_settings', 'fluid_table_background_color', $ast_customizer->defaults['fluid_table_background_color'] );
	$table_padding             = $ast->get_option_value_from_array( 'tracking_info_settings', 'fluid_table_padding', $ast_customizer->defaults['fluid_table_padding'] );
	$fluid_hide_provider_image = $ast->get_checkbox_option_value_from_array( 'tracking_info_settings', 'fluid_hide_provider_image', $ast_customizer->defaults['fluid_hide_provider_image'] );
	$fluid_hide_shipping_date  = $ast->get_checkbox_option_value_from_array( 'tracking_info_settings', 'fluid_hide_shipping_date', $ast_customizer->defaults['fluid_hide_shipping_date'] );
	$button_background_color   = $ast->get_option_value_from_array( 'tracking_info_settings', 'fluid_button_background_color', $ast_customizer->defaults['fluid_button_background_color'] );
	$button_font_color         = $ast->get_option_value_from_array( 'tracking_info_settings', 'fluid_button_font_color', $ast_customizer->defaults['fluid_button_font_color'] );

	$button_radius     = $ast->get_option_value_from_array( 'tracking_info_settings', 'fluid_button_radius', $ast_customizer->defaults['fluid_button_radius'] );
	$button_expand     = $ast->get_checkbox_option_value_from_array( 'tracking_info_settings', 'fluid_button_expand', $ast_customizer->defaults['fluid_button_expand'] );
	$fluid_button_text = $ast->get_option_value_from_array( 'tracking_info_settings', 'fluid_button_text', $ast_customizer->defaults['fluid_button_text'] );

	$fluid_button_size             = $ast->get_checkbox_option_value_from_array( 'tracking_info_settings', 'fluid_button_size', $ast_customizer->defaults['fluid_button_size'] );
	$button_font_size              = ( 'large' == $fluid_button_size ) ? 16 : 14;
	$button_padding                = ( 'large' == $fluid_button_size ) ? '12px 25px' : '10px 20px';
	$shipment_tracking_header      = $ast->get_option_value_from_array( 'tracking_info_settings', 'header_text_change', 'Tracking Information' );
	$shipment_tracking_header_text = $ast->get_option_value_from_array( 'tracking_info_settings', 'additional_header_text', '' );
	$hide_trackig_header           = $ast->get_checkbox_option_value_from_array( 'tracking_info_settings', 'hide_trackig_header', '' );
	$fluid_tracker_type            = $ast->get_option_value_from_array( 'tracking_info_settings', 'fluid_tracker_type', $ast_customizer->defaults['fluid_tracker_type'] );

	$order_details = wc_get_order( $order_id );

	$ast_preview = ( isset( $_REQUEST['action'] ) && 'email_preview' === $_REQUEST['action'] ) ? true : false;
	$text_align  = is_rtl() ? 'right' : 'left';
	?>


<div class="fluid_container">
	<table class="fluid_table fluid_table_2cl">
		<tbody class="fluid_tbody_2cl">
		<?php
		foreach ( $tracking_items as $key => $tracking_item ) {

			if ( '' != $tracking_item['formatted_tracking_provider'] ) {
				$ast_provider_title = apply_filters( 'ast_provider_title', esc_html( $tracking_item['formatted_tracking_provider'] ) );
			} else {
				$ast_provider_title = apply_filters( 'ast_provider_title', esc_html( $tracking_item['tracking_provider'] ) );
			}
			?>
				<tr class="fluid_2cl_tr">
					<td class="fluid_2cl_td_image" style="vertical-align: middle;">
						<div class="fluid_provider">
							<?php
							if ( $ast_preview ) {
								$fluid_provider_img_class = ( $fluid_hide_provider_image ) ? 'hide' : '';
								?>
								<div class="fluid_provider_img <?php esc_html_e( $fluid_provider_img_class ); ?>">
									<img src="<?php echo esc_url( $tracking_item['tracking_provider_image'] ); ?>"></img>
								</div>									
								<?php
							} elseif ( ! $fluid_hide_provider_image ) {
								?>
								<div class="fluid_provider_img">
									<img src="<?php echo esc_url( $tracking_item['tracking_provider_image'] ); ?>"></img>
								</div>
								<?php
							}
							?>
							<div class="provider_name">
								<div>
									<span class="tracking_provider"><?php esc_html_e( $ast_provider_title ); ?></span>
									<a class="tracking_number" href="<?php echo esc_url( $tracking_item['ast_tracking_link'] ); ?>"><?php esc_html_e( $tracking_item['tracking_number'] ); ?></a>
								</div>								
							</div>		
							
							<div class="mobile_tracker_img">
								<div class="order_status">
									<h2 class="shipped_label"><?php esc_html_e( 'Shipped', 'ast-pro' ); ?></h2>
									<?php
									if ( $ast_preview ) {
										$hide_shipping_date_class = ( $fluid_hide_shipping_date ) ? 'hide' : '';
										echo '<p style="margin: 0;"><span class="shipped_on ' . esc_attr( $hide_shipping_date_class ) . '">';
										esc_html_e( 'Shipped on', 'ast-pro' );
										echo ': <b>';
										echo esc_html( date_i18n( get_option( 'date_format' ), $tracking_item['date_shipped'] ) );
										echo '</b>';
										echo '</span></p>';
									} elseif ( ! $fluid_hide_shipping_date ) {
										echo '<p style="margin: 0;"><span class="shipped_on">';
										esc_html_e( 'Shipped on', 'ast-pro' );
										echo ': <b>';
										echo esc_html( date_i18n( get_option( 'date_format' ), $tracking_item['date_shipped'] ) );
										echo '</b>';
										echo '</span></p>';
									}
									?>
								</div>
								
								<?php
								if ( $ast_preview ) {
									$fluid_tracker_class = ( 'hide' == $fluid_tracker_type ) ? 'hide' : '';
									?>
									<img class="<?php esc_html_e( $fluid_tracker_class ); ?> mobile_tracker_image" src="<?php echo esc_url( ast_pro()->plugin_dir_url() ); ?>assets/images/mobile_<?php esc_html_e( $fluid_tracker_type ); ?>.png"></img>
									<?php
								} else {
									if ( 'hide' != $fluid_tracker_type ) {
										?>
											
										<img class="mobile_tracker_image" src="<?php echo esc_url( ast_pro()->plugin_dir_url() ); ?>assets/images/mobile_<?php esc_html_e( $fluid_tracker_type ); ?>.png"></img>						
										<?php
									}
								}
								?>

							</div>
							<?php do_action( 'ast_fluid_left_cl_end', $tracking_item, $order_id ); ?>
						</div>		
					</td>
					<td class="fluid_2cl_td_action">
						<a href="<?php echo esc_url( $tracking_item['ast_tracking_link'] ); ?>" class="track-button"><?php esc_html_e( $fluid_button_text ); ?></a>
					</td>
				</tr>
				<tr class="desktop_tracker_img">
					<td colspan="2" style="padding-top:0px !important;">
						<div class="order_status">
							<h2 class="shipped_label"><?php esc_html_e( 'Shipped', 'ast-pro' ); ?></h2>
							<?php
							if ( $ast_preview ) {
								$hide_shipping_date_class = ( $fluid_hide_shipping_date ) ? 'hide' : '';
								echo '<p style="margin: 0;"><span class="shipped_on ' . esc_attr( $hide_shipping_date_class ) . '">';
								esc_html_e( 'Shipped on', 'ast-pro' );
								echo ': <b>';
								echo esc_html( date_i18n( get_option( 'date_format' ), $tracking_item['date_shipped'] ) );
								echo '</b>';
								echo '</span></p>';
							} elseif ( ! $fluid_hide_shipping_date ) {
								echo '<p style="margin: 0;"><span class="shipped_on">';
								esc_html_e( 'Shipped on', 'ast-pro' );
								echo ': <b>';
								echo esc_html( date_i18n( get_option( 'date_format' ), $tracking_item['date_shipped'] ) );
								echo '</b>';
								echo '</span></p>';
							}
							?>
						</div>		
					</td>						
				</tr>
				<?php
				if ( $ast_preview ) {
					$fluid_tracker_class = ( 'hide' == $fluid_tracker_type ) ? 'hide' : '';
					?>
					<tr class="desktop_tracker_img tracker_tr <?php esc_html_e( $fluid_tracker_class ); ?>">
						<td colspan="2" class="fluid_2cl_td_image" style="padding-top:5px !important;">
							<img class="tracker_image " style="width:100%;" src="<?php echo esc_url( ast_pro()->plugin_dir_url() ); ?>assets/images/<?php esc_html_e( $fluid_tracker_type ); ?>.png"></img>
						</td>	
					</tr>	
					<?php
				} else {
					if ( 'hide' != $fluid_tracker_type ) {
						?>
							
						<tr class="desktop_tracker_img tracker_tr">
							<td colspan="2" class="fluid_2cl_td_image <?php esc_html_e( $fluid_tracker_type ); ?>" style="padding-top:5px !important;">
								<img class="tracker_image" style="width:100%;" src="<?php echo esc_url( ast_pro()->plugin_dir_url() ); ?>assets/images/<?php esc_html_e( $fluid_tracker_type ); ?>.png"></img>
							</td>					
						</tr>						
						<?php
					}
				}
		}
		?>
		</tbody>
	</table>
</div>

<div class="clearfix"></div>

<style>
.clearfix{
	display: block;
	content: '';
	clear: both;
}
.fluid_container{
	width: 100%;
	display: block;
}
.fluid_table_2cl{
	width: 100%;	
	margin: 10px 0 !important;
	border: 1px solid <?php esc_html_e( $border_color ); ?> !important;
	border-radius: <?php esc_html_e( $border_radius ); ?>px !important;    
	background: <?php esc_html_e( $background_color ); ?> !important;	
	border-spacing: 0 !important;	
}
.fluid_table_2cl .fluid_2cl_tr td{
	vertical-align: top;
	border-bottom: 1px solid #e0e0e0;
}
.fluid_table_2cl .fluid_2cl_tr td.fluid_2cl_td_action{	
	text-align: right;
}

.fluid_table td{
	padding: <?php esc_html_e( $table_padding ); ?>px !important;
}

.fluid_provider_img {    
	display: inline-block;
	vertical-align: middle;
}
.fluid_provider_img img{
	width:100%;
	max-width: 40px;
	border-radius: 5px;
	margin-right: 10px;
}
.provider_name{
	display: inline-block;
	vertical-align: middle;
}
.tracking_provider{
	word-break: break-word;
	margin-right: 5px;	
	font-size: 14px;
	display: block;
}
.tracking_number{
	color: #03a9f4;
	text-decoration: none;    
	font-size: 14px;
	line-height: 19px;
	display: block;
	margin-top: 4px;
}
.order_status{
	font-size: 12px;    
	margin: 8px 0 0;	
}
.shipped_label{	
	font-size: 24px !important;
	margin: 10px 0 !important;
	display: inline-block;
	color: #333;
	vertical-align: middle;
	font-weight:500;
	line-height: 100%;
}
span.shipped_on{
	margin-top: 5px;
	display: inline-block;
	font-size: 14px;
}
.order_status span{
	vertical-align: middle;
}
a.track-button {
	background: <?php esc_html_e( $button_background_color ); ?>;
	color: <?php esc_html_e( $button_font_color ); ?> !important;
	padding: <?php esc_html_e( $button_padding ); ?>;
	text-decoration: none;
	display: inline-block;
	border-radius: <?php esc_html_e( $button_radius ); ?>px;
	margin-top: 0;
	font-size: <?php esc_html_e( $button_font_size ); ?>px !important;
	text-align: center;
	min-height: 10px;
	white-space: nowrap;
}
.mobile_tracker_img img{
	padding: 15px 0 0;	
	width: 100%;
	max-width: 500px;
}

@media screen and (max-width: 460px) {
	.mobile_tracker_img{
		display:block;
	}
	.desktop_tracker_img{
		display: none;
	}
	.fluid_table_2cl .fluid_2cl_tr td{
		border-bottom: 0 !important;
	}
}
@media screen and (min-width: 461px) {
	.desktop_tracker_img{
		display: table-row;
	}
	.mobile_tracker_img{
		display:none;
	}
}
@media screen and (max-width: 460px) {
	.fluid_table td{
		display: block;
		flex: 1;
	}
	.fluid_table_2cl .fluid_2cl_tr td.fluid_2cl_td_action{	
		text-align: left !important;
	}
	.fluid_2cl_td_action{
		padding-top: 0 !important;
	}
	.fluid_2cl_tr{
		width: 100% !important;
		display: block !important;
	}

	.fluid_table_2cl{
		display:block !important;
	}
	.fluid_table_2cl .fluid_tbody_2cl{
		display: block !important;
	}
	a.track-button{
		display: block !important;
	}
}
</style>

	<?php
endif;
