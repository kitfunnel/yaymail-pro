<?php

namespace YayMail\Helper;

use YayMail\Page\Source\CustomPostType;
use YayMail\Page\Source\DefaultElement;
use YayMail\Templates\Templates;

defined( 'ABSPATH' ) || exit;
/**
 * Plugin activate/deactivate logic
 */
class ActivePlugin {

	protected static $instance = null;
	public static function getInstance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		if ( function_exists( 'WC' ) ) {
			$this->activePlugin();
		}
		$this->addDefaultSetting();
	}

	public function activePlugin() {
		$templateEmail = Templates::getInstance();
		$templates     = $templateEmail::getList();

		foreach ( $templates as $key => $template ) {
			if ( ! CustomPostType::postIDByTemplate( $key ) ) {
				$arr    = array(
					'mess'                            => '',
					'post_date'                       => current_time( 'Y-m-d H:i:s' ),
					'post_type'                       => 'yaymail_template',
					'post_status'                     => 'publish',
					'_yaymail_template'               => $key,
					'_email_backgroundColor_settings' => 'rgb(236, 236, 236)',
					'_yaymail_elements'               => json_decode( $template['elements'], true ),
					'_yaymail_email_textLinkColor_settings' => '#7f54b3',
					'_yaymail_email_order_item_title' => array(
						'order_title'                   => '',
						'product_title'                 => __( 'Product', 'yaymail' ),
						'quantity_title'                => __( 'Quantity', 'yaymail' ),
						'price_title'                   => __( 'Price', 'yaymail' ),
						'subtoltal_title'               => __( 'Subtotal:', 'yaymail' ),
						'discount_title'                => __( 'Discount:', 'yaymail' ),
						'shipping_title'                => __( 'Shipping:', 'yaymail' ),
						'payment_method_title'          => __( 'Payment method:', 'yaymail' ),
						'fully_refunded'                => __( 'Order fully refunded.', 'yaymail' ),
						'total_title'                   => __( 'Total:', 'yaymail' ),
						'subscript_id'                  => __( 'ID', 'yaymail' ),
						'subscript_start_date'          => __( 'Start date', 'yaymail' ),
						'subscript_end_date'            => __( 'End date', 'yaymail' ),
						'subscript_recurring_total'     => __( 'Recurring total', 'yaymail' ),
						'subscript_subscription'        => __( 'Subscription', 'yaymail' ),
						'subscript_price'               => __( 'Price', 'yaymail' ),
						'subscript_last_order_date'     => __( 'Last Order Date', 'yaymail' ),
						'subscript_end_of_prepaid_term' => __( 'End of Prepaid Term', 'yaymail' ),
						'subscript_date_suspended'      => __( 'Date Suspended', 'yaymail' ),
					),
					'_yaymail_email_order_item_download_title' => array(
						'items_download_header_title'   => __( 'Downloads', 'yaymail' ),
						'items_download_product_title'  => __( 'Product', 'yaymail' ),
						'items_download_expires_title'  => __( 'Expires', 'yaymail' ),
						'items_download_download_title' => __( 'Download', 'yaymail' ),
					),
				);
				$insert = CustomPostType::insert( $arr );
			} else {
				if ( ! metadata_exists( 'post', CustomPostType::postIDByTemplate( $key ), '_yaymail_email_textLinkColor_settings' ) ) {
					update_post_meta( CustomPostType::postIDByTemplate( $key ), '_yaymail_email_textLinkColor_settings', '#7f54b3' );
				}

				if ( ! metadata_exists( 'post', CustomPostType::postIDByTemplate( $key ), '_yaymail_email_order_item_title' ) ) {
					$orderTitle = array(
						'order_title'                   => '',
						'product_title'                 => __( 'Product', 'yaymail' ),
						'quantity_title'                => __( 'Quantity', 'yaymail' ),
						'price_title'                   => __( 'Price', 'yaymail' ),
						'subtoltal_title'               => __( 'Subtotal:', 'yaymail' ),
						'discount_title'                => __( 'Discount:', 'yaymail' ),
						'shipping_title'                => __( 'Shipping:', 'yaymail' ),
						'payment_method_title'          => __( 'Payment method:', 'yaymail' ),
						'fully_refunded'                => __( 'Order fully refunded.', 'yaymail' ),
						'total_title'                   => __( 'Total:', 'yaymail' ),
						'subscript_id'                  => __( 'ID', 'yaymail' ),
						'subscript_start_date'          => __( 'Start date', 'yaymail' ),
						'subscript_end_date'            => __( 'End date', 'yaymail' ),
						'subscript_recurring_total'     => __( 'Recurring total', 'yaymail' ),
						'subscript_subscription'        => __( 'Subscription', 'yaymail' ),
						'subscript_price'               => __( 'Price', 'yaymail' ),
						'subscript_last_order_date'     => __( 'Last Order Date', 'yaymail' ),
						'subscript_end_of_prepaid_term' => __( 'End of Prepaid Term', 'yaymail' ),
						'subscript_date_suspended'      => __( 'Date Suspended', 'yaymail' ),
					);
					update_post_meta( CustomPostType::postIDByTemplate( $key ), '_yaymail_email_order_item_title', $orderTitle );
				}
				if ( ! metadata_exists( 'post', CustomPostType::postIDByTemplate( $key ), '_yaymail_email_order_item_download_title' ) ) {
					$orderItemsDownloadTitle = array(
						'items_download_header_title'   => __( 'Downloads', 'yaymail' ),
						'items_download_product_title'  => __( 'Product', 'yaymail' ),
						'items_download_expires_title'  => __( 'Expires', 'yaymail' ),
						'items_download_download_title' => __( 'Download', 'yaymail' ),
					);
					update_post_meta( CustomPostType::postIDByTemplate( $key ), '_yaymail_email_order_item_download_title', $orderItemsDownloadTitle );
				}
			}
		}

		if ( ! get_option( 'yaymail_version' ) ) {
			update_option( 'yaymail_version', YAYMAIL_VERSION );
		}
		if ( ! get_option( 'yaymail_direction' ) ) {
			update_option( 'yaymail_direction', 'ltr' );
		}

		/*
		 @@@@check key in settingRow whether or not it exists.
		 @@@@note: case when add setting row for element.
		*/
		$versionCurrent = YAYMAIL_VERSION;
		$versionOld     = get_option( 'yaymail_version' );

		if ( $versionCurrent != $versionOld ) {
			$posts = CustomPostType::getListPostTemplate();
			if ( count( $posts ) > 0 ) {
				foreach ( $posts as $post ) {
					$elements = get_post_meta( $post->ID, '_yaymail_elements', true );

					$defaultElement      = new DefaultElement();
					$defaultDataElements = $defaultElement->defaultDataElement;

					foreach ( $defaultDataElements as $defaultelement ) {

						foreach ( $elements as $keyEl => $element ) {

							if ( $defaultelement['type'] == $element['type'] ) {
								/*
								@@@ add key default for element
								*/
								$keyEleDefaus = array();
								$keyEleDefaus = array_diff_key( $defaultelement, $element );
								if ( count( $keyEleDefaus ) > 0 ) {
									$elements[ $keyEl ] = array_merge( $element, $keyEleDefaus );
								}

								/*
								@@@ add key default for setting row.
								@@@ note: when add a field in setting row
								*/
								$propSettings    = array();
								$propSettings    = array_diff_key( $defaultelement['settingRow'], $element['settingRow'] );
								$lenPropSettings = count( $propSettings );
								if ( $lenPropSettings > 0 ) {
										$result                           = array();
										$result                           = array_merge( $element['settingRow'], $propSettings );
										$elements[ $keyEl ]['settingRow'] = $result;
								}

								/*
								@@@ remove Key not needed for setting row
								@@@ note: when deleting a field in setting row
								*/
							}
						}
					}
					update_post_meta( $post->ID, '_yaymail_elements', $elements );
				}
			}
		}

		$settingDefault = CustomPostType::templateEnableDisable();
		$listTemplates  = ! empty( $settingDefault ) ? array_keys( $settingDefault ) : array();
		$settingCurrent = array(
			'new_order'                 => 1,
			'cancelled_order'           => 1,
			'failed_order'              => 1,
			'customer_on_hold_order'    => 1,
			'customer_processing_order' => 1,
			'customer_completed_order'  => 1,
			'customer_refunded_order'   => 1,
			'customer_invoice'          => 0,
			'customer_note'             => 0,
			'customer_reset_password'   => 0,
			'customer_new_account'      => 0,
		);

		if ( ! empty( $listTemplates ) ) {
			foreach ( $settingCurrent as $key => $value ) {
				if ( in_array( $key, $listTemplates )
					&& ! metadata_exists( 'post', $settingDefault[ $key ]['post_id'], '_yaymail_status' )
				) {
					update_post_meta( $settingDefault[ $key ]['post_id'], '_yaymail_status', $value );
				}
			}
		}
	}
	public function addDefaultSetting() {
		if ( ! get_option( 'yaymail_settings' ) ) {
			$settings = array(
				'payment'                      => 2,
				'product_image'                => 0,
				'image_size'                   => 'thumbnail',
				'image_width'                  => '30px',
				'image_height'                 => '30px',
				'product_sku'                  => 1,
				'product_des'                  => 0,
				'product_hyper_links'          => 0,
				'product_regular_price'        => 0,
				'background_color_table_items' => '#e5e5e5',
				'content_items_color'          => '#636363',
				'title_items_color'            => '#7f54b3',
				'container_width'              => '605px',
				'order_url'                    => '',
				'custom_css'                   => '',
				'enable_css_custom'            => 'no',
				'direction_rtl'                => 'ltr',
			);
			update_option( 'yaymail_settings', $settings );
		}
	}
}
