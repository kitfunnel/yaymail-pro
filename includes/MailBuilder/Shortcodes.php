<?php

namespace YayMail\MailBuilder;

use YayMail\Helper\Helper;
use YayMail\Page\Source\CustomPostType;
use YayMail\Page\Source\UpdateElement;
use YayMail\Templates\Templates;

defined( 'ABSPATH' ) || exit;
global $woocommerce, $wpdb, $current_user, $order;

class Shortcodes {

	protected static $instance = null;
	public $order_id           = false;
	public $args_email         = false;
	public $order;
	public $sent_to_admin = false;
	public $order_data;
	public $template          = false;
	public $customer_note     = false;
	public $yaymail_states    = null;
	public $yaymail_countries = null;
	public $shipping_address  = null;
	public $billing_address   = null;
	// public $array_content_template = false;
	public $shortcodes_lists;
	public static function getInstance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct( $template = false, $checkOrder = '' ) {
		$this->yaymail_states    = include plugin_dir_path( __FILE__ ) . '/languages/states.php';
		$this->yaymail_countries = include plugin_dir_path( __FILE__ ) . '/languages/countries.php';
		if ( $template ) {
			$this->template = $template;
			if ( 'sampleOrder' === $checkOrder ) {
				$this->shortCodesOrderSample();
			} else {
				$this->shortCodesOrderDefined();
			}
			// style css
			add_filter( 'woocommerce_email_styles', array( $this, 'customCss' ) );

			add_filter( 'safe_style_css', array( $this, 'filter_safe_style_css' ), 10, 1 );

			// Order Details
			$order_details_list = array(
				'items_downloadable_title',
				'items_downloadable_product',
				'items_border',
				'items',
				'items_products_quantity_price',
				'order_date',
				'order_fee',
				'order_id',
				'order_link',
				'order_link_string',
				'order_number',
				'order_refund',
				'order_sub_total',
				'order_discount',
				'order_total',
				'order_total_numbers',
				'orders_count',
				'orders_count_double',
				'order_tn',
				'items_border_before',
				'items_border_after',
				'items_border_title',
				'items_border_content',
				'get_heading',
				'items_downloadable_product',
				'items_downloadable_title',
				'shipment_tracking_title',
				'shipment_tracking_items',
				'ph_shipment_tracking',
				'shipping_tax_shipment_tracking',
				'chitchats_shipping_shipments',
				'fooevents_ticket_details',
				'yith_barcode',
				'software_add_on',
				'woocommerce_email_order_meta',
				'woocommerce_email_order_details',
				'woocommerce_email_after_order_table',
				'woocommerce_email_before_order_table',
				'woocommerce_email_sozlesmeler',
				'dpdch_tracking_number',
			);

			// Payments
			$payments_list = array(
				'order_payment_method',
				'order_payment_url',
				'order_payment_url_string',
				'payment_instruction',
				'payment_method',
				'transaction_id',
			);

			// Shippings
			$shippings_list = array(
				'order_shipping',
				'shipping_address',
				'shipping_address_1',
				'shipping_address_2',
				'shipping_city',
				'shipping_company',
				'shipping_country',
				'shipping_first_name',
				'shipping_last_name',
				'shipping_method',
				'shipping_postcode',
				'shipping_state',
				'shipping_phone',
			);

			// Billings
			$billings_list = array(
				'billing_address',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_company',
				'billing_country',
				'billing_email',
				'billing_first_name',
				'billing_last_name',
				'billing_phone',
				'billing_postcode',
				'billing_state',
			);

			// Reset Password
			$reset_password_list = array( 'password_reset_url', 'password_reset_url_string', 'wp_password_reset_url' );

			// New Users
			$new_users_list = array( 'user_new_password', 'user_activation_link', 'set_password_url_string' );

			// General
			$general_list = array(
				'customer_note',
				'customer_notes',
				'customer_provided_note',
				'site_name',
				'site_url',
				'site_url_string',
				'user_email',
				'user_id',
				'user_name',
				'customer_username',
				'customer_roles',
				'additional_content',
				'customer_name',
				'customer_first_name',
				'customer_last_name',
				'view_order_url',
				'view_order_url_string',
				'billing_shipping_address',
				'domain',
				'user_account_url',
				'user_account_url_string',
				// new
				'billing_shipping_address_title',
				'billing_shipping_address_content',
				'check_billing_shipping_address',
				'order_status',
				'order_status_from',
				'notifier_product_name',
				'notifier_product_id',
				'notifier_product_link',
				'notifier_shopname',
				'notifier_email_id',
				'notifier_subscriber_email',
				'notifier_subscriber_name',
				'notifier_cart_link',
				'notifier_only_product_name',
				'notifier_only_product_sku',
				'notifier_only_product_image',
				'order_coupon_codes',
				'pagarme_banking_ticket_url',
				'pagarme_credit_card_brand',
				'pagarme_credit_card_installments',
				// user meta
				'user_meta_fpso',
				'user_meta_employeeid',
			);

			if ( class_exists( 'WC_Shipment_Tracking_Actions' ) || class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) || class_exists( 'AST_PRO_Install' ) ) {
				$shipment_tracking_list = array( 'order_meta:_wc_shipment_tracking_items', 'order_meta:shipment_carriers', 'order_meta:shipment_tracking_link', 'order_meta:shipment_tracking_numbers' );
				$order_details_list     = array_merge( $order_details_list, $shipment_tracking_list );
			}

			if ( class_exists( 'WC_Admin_Custom_Order_Fields' ) ) {
				$admin_custom_order_fields = array( 'order_meta:_wc_additional_order_details' );
				$order_details_list        = array_merge( $order_details_list, $admin_custom_order_fields );
			}

			if ( class_exists( 'EventON' ) ) {
				$event_on           = array( 'order_meta:_event_on_list' );
				$order_details_list = array_merge( $order_details_list, $event_on );
			}

			if ( class_exists( 'YITH_WooCommerce_Order_Tracking_Premium' ) ) {
				$tracking           = array( 'order_carrier_name', 'order_pickup_date', 'order_track_code', 'order_tracking_link' );
				$order_details_list = array_merge( $order_details_list, $tracking );
			}

			// Additional Order Meta.
			$order = CustomPostType::getListOrders();

			/* Define Shortcodes */
			$shortcodes_lists = array();
			$shortcodes_lists = array_merge( $shortcodes_lists, apply_filters( 'yaymail_shortcodes', $shortcodes_lists ) );
			$shortcodes_lists = array_merge( $shortcodes_lists, $order_details_list );
			// $shortcodes_lists       = array_merge( $shortcodes_lists, $order_subscription_list );
			$shortcodes_lists       = array_merge( $shortcodes_lists, $payments_list );
			$shortcodes_lists       = array_merge( $shortcodes_lists, $shippings_list );
			$shortcodes_lists       = array_merge( $shortcodes_lists, $billings_list );
			$shortcodes_lists       = array_merge( $shortcodes_lists, $reset_password_list );
			$shortcodes_lists       = array_merge( $shortcodes_lists, $new_users_list );
			$shortcodes_lists       = array_merge( $shortcodes_lists, $general_list );
			$this->shortcodes_lists = $shortcodes_lists;
			foreach ( $this->shortcodes_lists as $key => $shortcode_name ) {
				if ( 'woocommerce_email_before_order_table' == $shortcode_name || 'woocommerce_email_after_order_table' == $shortcode_name || 'woocommerce_email_order_details' == $shortcode_name || 'woocommerce_email_order_meta' == $shortcode_name ) {
					$function_name = $this->parseShortCodeToFunctionName( 'woocomme' . $shortcode_name );
					if ( method_exists( $this, $function_name ) ) {
						add_shortcode(
							$shortcode_name,
							function ( $atts, $content, $tag ) {
								$function_name = $this->parseShortCodeToFunctionName( 'woocomme' . $tag );
								return $this->$function_name( $atts, $this->order, $this->sent_to_admin, $this->args_email );
							}
						);
					}
				} else {
					$function_name = $this->parseShortCodeToFunctionName( 'yaymail_' . $shortcode_name );
					if ( method_exists( $this, $function_name ) ) {
						add_shortcode(
							'yaymail_' . $shortcode_name,
							function ( $atts, $content, $tag ) {
								$function_name = $this->parseShortCodeToFunctionName( $tag );
								return $this->$function_name( $atts, $this->order, $this->sent_to_admin, $this->args_email );
							}
						);
					} elseif ( strpos( $shortcode_name, 'addon' ) !== false ) {
						add_shortcode(
							$shortcode_name,
							function ( $atts, $content, $tag ) {
								return $this->order_data[ '[' . $tag . ']' ];
							}
						);
					} else {
						add_shortcode( 'yaymail_' . $shortcode_name, array( $this, 'shortcodeCallBack' ) );
					}
				}
			}
		}
	}

	public function parseShortCodeToFunctionName( $shortcode_name ) {
		$function_name = substr( $shortcode_name, 8 );
		$offset        = 0;
		while ( false !== strpos( $function_name, '_', $offset ) ) {
			$position                       = strpos( $function_name, '_', $offset );
			$function_name[ $position + 1 ] = strtoupper( $function_name[ $position + 1 ] );
			$offset                         = $position + 1;
		}
		$function_name = str_replace( '_', '', $function_name );
		if ( 'yaymail_order_meta:_wc_shipment_tracking_items' == $shortcode_name
			|| 'yaymail_order_meta:_wc_additional_order_details' == $shortcode_name
			|| 'yaymail_order_meta:_event_on_list' === $shortcode_name
		) {
			$function_name = str_replace( ':', '', $function_name );
		}
		return $function_name;
	}

	public function applyCSSFormat( $defaultsCss = '' ) {
		$templateEmail = \YayMail\Templates\Templates::getInstance();
		$css           = $templateEmail::getCssFortmat();
		$cssDirection  = '';
		$cssDirection .= 'td{direction: rtl}';
		$cssDirection .= 'td, th, td{text-align:right;}';

		$css .= get_option( 'yaymail_direction' ) && get_option( 'yaymail_direction' ) === 'rtl' ? $cssDirection : '';
		$css .= $defaultsCss;
		$css .= '.td { color: inherit; }';
		return $css;
	}
	public function customCss( $css = '' ) {
		return $this->applyCSSFormat( $css );
	}
	public function filter_safe_style_css( $array ) {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'wp-social-reviews/wp-social-reviews.php' ) ) {
			$style_css = array(
				'background',
				'background-color',
				'border',
				'border-width',
				'border-color',
				'border-style',
				'border-right',
				'border-right-color',
				'border-right-style',
				'border-right-width',
				'border-bottom',
				'border-bottom-color',
				'border-bottom-style',
				'border-bottom-width',
				'border-left',
				'border-left-color',
				'border-left-style',
				'border-left-width',
				'border-top',
				'border-top-color',
				'border-top-style',
				'border-top-width',
				'border-spacing',
				'border-collapse',
				'caption-side',
				'color',
				'font',
				'font-family',
				'font-size',
				'font-style',
				'font-variant',
				'font-weight',
				'letter-spacing',
				'line-height',
				'text-decoration',
				'text-indent',
				'text-align',
				'height',
				'min-height',
				'max-height',
				'width',
				'min-width',
				'max-width',
				'margin',
				'margin-right',
				'margin-bottom',
				'margin-left',
				'margin-top',
				'padding',
				'padding-right',
				'padding-bottom',
				'padding-left',
				'padding-top',
				'clear',
				'cursor',
				'direction',
				'float',
				'overflow',
				'vertical-align',
				'list-style-type',
			);
			return $style_css;
		} else {
			return $array;
		}

	}
	public function setOrderId( $order_id = '', $sent_to_admin = '', $args = '' ) {
		$this->order_id      = $order_id;
		$this->args_email    = $args;
		$this->sent_to_admin = $sent_to_admin;
		// Additional Order Meta.
		$order_meta_list = array();
		if ( ! empty( $this->order_id ) ) {
			$order_metaArr = get_post_meta( $this->order_id );
			if ( is_array( $order_metaArr ) && count( $order_metaArr ) > 0 ) {
				foreach ( $order_metaArr as $k => $v ) {
					$nameField         = str_replace( ' ', '_', trim( $k ) );
					$order_meta_list[] = 'order_meta:' . $nameField;
				}
			}
		}
		if ( 0 == count( $order_meta_list ) ) {
			$order_meta_list[] = 'order_meta:_wc_shipment_tracking_items';
			$order_meta_list[] = 'order_meta:_wc_additional_order_details';
			$order_meta_list[] = 'order_meta:_event_on_list';
		}
		$shortcodes_lists      = array();
		$shortcodes_lists      = array_merge( $shortcodes_lists, $order_meta_list );
		$order                 = wc_get_order( $order_id );
		$shortcode_order_taxes = array();
		if ( ! empty( $order ) ) {
			foreach ( $order->get_items( 'tax' ) as $item_id => $item_tax ) {
				$tax_rate_id             = $item_tax->get_rate_id();
				$shortcode_order_taxes[] = 'order_taxes_' . $tax_rate_id;
			}
			$shortcodes_lists = array_merge( $shortcodes_lists, $shortcode_order_taxes );
		}
		foreach ( $shortcodes_lists as $key => $shortcode_name ) {
			if ( 'order_meta:_wc_shipment_tracking_items' == $shortcode_name ) {
				add_shortcode(
					'yaymail_' . $shortcode_name,
					function ( $atts, $content, $tag ) {
						return $this->orderMetaWcShipmentTrackingItems( $atts, $this->order, $this->sent_to_admin, $this->args_email );
					}
				);
			} elseif ( 'order_meta:_wc_additional_order_details' == $shortcode_name ) {
				add_shortcode(
					'yaymail_' . $shortcode_name,
					function ( $atts, $content, $tag ) {
						return $this->orderMetaWcAdditionalOrderDetails( $atts, $this->order, $this->sent_to_admin, $this->args_email );
					}
				);
			} elseif ( 'order_meta:_event_on_list' === $shortcode_name ) {
				add_shortcode(
					'yaymail_' . $shortcode_name,
					function ( $atts, $content, $tag ) {
						return $this->eventOnList( $atts, $this->order, $this->sent_to_admin, $this->args_email );
					}
				);
			} else {
				add_shortcode( 'yaymail_' . $shortcode_name, array( $this, 'shortcodeCallBack' ) );
			}
		}

	}

	protected function _shortcode_atts( $defaults = array(), $atts = array() ) {
		if ( isset( $atts['class'] ) ) {
			$atts['classname'] = $atts['class'];
		}

		return \shortcode_atts( $defaults, $atts );
	}

	// short Codes Order when select SampleOrder
	public function shortCodesOrderSample( $sent_to_admin = '' ) {
		$user  = wp_get_current_user();
		$useId = get_current_user_id();
		$this->defaultSampleOrderData( $sent_to_admin );
	}

	public function shortCodesOrderDefined( $sent_to_admin = '', $args = array() ) {
		if ( false !== $this->order_id && ! empty( $this->order_id ) && class_exists( 'WC_Order' ) ) {
			$this->order = new \WC_Order( $this->order_id );
			$this->collectOrderData( $sent_to_admin, $args );
		}
		if ( ! function_exists( 'get_user_by' ) ) {
			return false;
		}
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';
		if ( empty( $this->order_id ) || ! $this->order_id ) {
			$shortcode = $this->order_data;
			if ( isset( $args['additional_content'] ) ) {
				$shortcode['[yaymail_additional_content]'] = wp_kses_post( wpautop( wptexturize( $args['additional_content'] ) ) );
			}
			if ( isset( $_REQUEST['billing_email'] ) ) {
				$shortcode['[yaymail_user_email]'] = sanitize_email( $_REQUEST['billing_email'] );
				$user                              = get_user_by( 'email', sanitize_email( $_REQUEST['billing_email'] ) );
				if ( ! empty( $user ) ) {
					$shortcode['[yaymail_customer_username]']   = $user->user_login;
					$shortcode['[yaymail_customer_name]']       = get_user_meta( $user->ID, 'first_name', true ) . ' ' . get_user_meta( $user->ID, 'last_name', true );
					$shortcode['[yaymail_customer_first_name]'] = get_user_meta( $user->ID, 'first_name', true );
					$shortcode['[yaymail_customer_last_name]']  = get_user_meta( $user->ID, 'last_name', true );
					$shortcode['[yaymail_user_id]']             = $user->ID;
				}
			}
			if ( empty( $shortcode['[yaymail_customer_username]'] ) ) {
				if ( isset( $_REQUEST['user_email'] ) ) {
					$user = get_user_by( 'email', sanitize_email( $_REQUEST['user_email'] ) );
					if ( isset( $user->user_login ) ) {
						$shortcode['[yaymail_customer_username]'] = $user->user_login;
					}
					if ( isset( $user->ID ) ) {
						$shortcode['[yaymail_user_id]'] = $user->ID;
					}
				} elseif ( isset( $_REQUEST['email'] ) ) {
					$user = get_user_by( 'email', sanitize_email( $_REQUEST['email'] ) );
					if ( isset( $user->user_login ) ) {
						$shortcode['[yaymail_customer_username]'] = $user->user_login;
					}
					if ( isset( $user->ID ) ) {
						$shortcode['[yaymail_user_id]'] = $user->ID;
					}
				}
			}
			if ( empty( $shortcode['[yaymail_user_email]'] ) ) {
				if ( isset( $_REQUEST['user_email'] ) ) {
					$user = get_user_by( 'email', sanitize_email( $_REQUEST['user_email'] ) );
					if ( isset( $user->user_email ) ) {
						$shortcode['[yaymail_user_email]'] = $user->user_email;
					}
					if ( isset( $user->ID ) ) {
						$shortcode['[yaymail_user_id]'] = $user->ID;
					}
				} elseif ( isset( $_REQUEST['email'] ) ) {
					$user = get_user_by( 'email', sanitize_email( $_REQUEST['email'] ) );
					if ( isset( $user->user_email ) ) {
						$shortcode['[yaymail_user_email]'] = $user->user_email;
					}
					if ( isset( $user->ID ) ) {
						$shortcode['[yaymail_user_id]'] = $user->ID;
					}
				}
			}
			if ( empty( $shortcode['[yaymail_customer_name]'] ) ) {
				if ( isset( $_REQUEST['user_email'] ) ) {
					$user = get_user_by( 'email', sanitize_email( $_REQUEST['user_email'] ) );
					if ( isset( $user->user_email ) ) {
						$shortcode['[yaymail_customer_name]']       = get_user_meta( $user->ID, 'first_name', true ) . ' ' . get_user_meta( $user->ID, 'last_name', true );
						$shortcode['[yaymail_customer_first_name]'] = get_user_meta( $user->ID, 'first_name', true );
						$shortcode['[yaymail_customer_last_name]']  = get_user_meta( $user->ID, 'last_name', true );
					}
					if ( isset( $user->ID ) ) {
						$shortcode['[yaymail_user_id]'] = $user->ID;
					}
				} elseif ( isset( $_REQUEST['email'] ) ) {
					$user = get_user_by( 'email', sanitize_email( $_REQUEST['email'] ) );
					if ( isset( $user->user_email ) ) {
						if ( ! empty( get_user_meta( $user->ID, 'first_name', true ) ) && ! empty( get_user_meta( $user->ID, 'last_name', true ) ) ) {
							$shortcode['[yaymail_customer_name]']       = get_user_meta( $user->ID, 'first_name', true ) . ' ' . get_user_meta( $user->ID, 'last_name', true );
							$shortcode['[yaymail_customer_first_name]'] = get_user_meta( $user->ID, 'first_name', true );
							$shortcode['[yaymail_customer_last_name]']  = get_user_meta( $user->ID, 'last_name', true );
						} elseif ( isset( $_REQUEST['first_name'] ) && isset( $_REQUEST['last_name'] ) ) {
							$shortcode['[yaymail_customer_name]']       = sanitize_text_field( $_REQUEST['first_name'] ) . ' ' . sanitize_text_field( $_REQUEST['last_name'] );
							$shortcode['[yaymail_customer_first_name]'] = sanitize_text_field( $_REQUEST['first_name'] );
							$shortcode['[yaymail_customer_last_name]']  = sanitize_text_field( $_REQUEST['last_name'] );
						}
					}
					if ( isset( $user->ID ) ) {
						$shortcode['[yaymail_user_id]'] = $user->ID;
					}
				}
			}
			if ( ! empty( $args ) ) {
					$postID               = CustomPostType::postIDByTemplate( $this->template );
					$text_link_color      = get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) ? get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) : '#7f54b3';
					$yaymail_settings     = get_option( 'yaymail_settings' );
					$yaymail_informations = array(
						'post_id'          => $postID,
						'template'         => $this->template,
						'yaymail_elements' => get_post_meta( $postID, '_yaymail_elements', true ),
						'general_settings' => array(
							'tableWidth'           => $yaymail_settings['container_width'],
							'emailBackgroundColor' => get_post_meta( $postID, '_email_backgroundColor_settings', true ) ? get_post_meta( $postID, '_email_backgroundColor_settings', true ) : '#ECECEC',
							'textLinkColor'        => get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) ? get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) : '#7f54b3',
						),
					);
					if ( isset( $args['email'] ) || isset( $args['admin_email'] ) || isset( $args['user'] ) ) {

						if ( isset( $args['email']->id ) && 'customer_reset_password' == $args['email']->id ) {
							$shortcode['[yaymail_customer_username]']   = $args['email']->user_login;
							$user                                       = new \WP_User( intval( $args['email']->user_id ) );
							$shortcode['[yaymail_customer_name]']       = get_user_meta( $user->ID, 'first_name', true ) . ' ' . get_user_meta( $user->ID, 'last_name', true );
							$shortcode['[yaymail_customer_first_name]'] = get_user_meta( $user->ID, 'first_name', true );
							$shortcode['[yaymail_customer_last_name]']  = get_user_meta( $user->ID, 'last_name', true );
							$shortcode['[yaymail_user_email]']          = $args['email']->user_email;
							$shortcode['[yaymail_user_id]']             = $user->ID;
							if ( isset( $args['reset_key'] ) ) {
								$link_reset                                       = add_query_arg(
									array(
										'key' => $args['reset_key'],
										'id'  => $user->ID,
									),
									wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
								);
								$shortcode['[yaymail_password_reset_url]']        = '<a style="color: ' . esc_attr( $text_link_color ) . ';" href="' . esc_url( $link_reset ) . '">' . esc_html__( 'Click here to reset your password', 'woocommerce' ) . '</a>';
								$shortcode['[yaymail_password_reset_url_string]'] = esc_url( $link_reset );
							}

							// link reset password send by wp
							if ( isset( $args['email']->user_login ) && isset( $args['email']->user_data ) && isset( $args['email']->key ) ) {
								$locale     = get_user_locale( $args['email']->user_data );
								$key        = $args['email']->key;
								$user_login = $args['email']->user_login;
								$shortcode['[yaymail_wp_password_reset_url]'] = network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . '&wp_lang=' . $locale;
							}

							$shortcode['[yaymail_site_name]'] = esc_html( get_bloginfo( 'name' ) );
						}

						$shortcode['[yaymail_site_name]']               = esc_html( get_bloginfo( 'name' ) );
						$shortcode['[yaymail_site_url]']                = '<a href="' . esc_url( get_home_url() ) . '"> ' . esc_url( get_home_url() ) . ' </a>';
						$shortcode['[yaymail_site_url_string]']         = esc_url( get_home_url() );
						$shortcode['[yaymail_user_account_url]']        = '<a style="color:' . esc_attr( $text_link_color ) . '; font-weight: normal; text-decoration: underline;" href="' . wc_get_page_permalink( 'myaccount' ) . '">' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '</a>';
						$shortcode['[yaymail_user_account_url_string]'] = wc_get_page_permalink( 'myaccount' );
						if ( isset( $args['email']->user_pass ) && ! empty( $args['email']->user_pass ) ) {
							$shortcode['[yaymail_user_new_password]'] = $args['email']->user_pass;
						} else {
							if ( isset( $_REQUEST['pass1-text'] ) && '' != $_REQUEST['pass1-text'] ) {
								$shortcode['[yaymail_user_new_password]'] = sanitize_text_field( $_REQUEST['pass1-text'] );
							} elseif ( isset( $_REQUEST['pass1'] ) && '' != $_REQUEST['pass1'] ) {
								$shortcode['[yaymail_user_new_password]'] = sanitize_text_field( $_REQUEST['pass1-text'] );
							} else {
								$shortcode['[yaymail_user_new_password]'] = '';
							}
						}
						if ( isset( $args['set_password_url'] ) && ! empty( $args['set_password_url'] ) ) {
							$shortcode['[yaymail_set_password_url_string]'] = $args['set_password_url'];
						}
						if ( isset( $args['email']->user_login ) && ! empty( $args['email']->user_login ) ) {
							$shortcode['[yaymail_customer_username]'] = $args['email']->user_login;
							$user                                     = get_user_by( 'email', $args['email']->user_email );
							if ( ! empty( get_user_meta( $user->ID, 'first_name', true ) ) && ! empty( get_user_meta( $user->ID, 'last_name', true ) ) ) {
								$shortcode['[yaymail_customer_name]']       = get_user_meta( $user->ID, 'first_name', true ) . ' ' . get_user_meta( $user->ID, 'last_name', true );
								$shortcode['[yaymail_customer_first_name]'] = get_user_meta( $user->ID, 'first_name', true );
								$shortcode['[yaymail_customer_last_name]']  = get_user_meta( $user->ID, 'last_name', true );
							} elseif ( isset( $_REQUEST['first_name'] ) && isset( $_REQUEST['last_name'] ) ) {
								$shortcode['[yaymail_customer_name]']       = sanitize_text_field( $_REQUEST['first_name'] ) . ' ' . sanitize_text_field( $_REQUEST['last_name'] );
								$shortcode['[yaymail_customer_first_name]'] = sanitize_text_field( $_REQUEST['first_name'] );
								$shortcode['[yaymail_customer_last_name]']  = sanitize_text_field( $_REQUEST['last_name'] );
							}
						}
						if ( isset( $args['email']->user_email ) && ! empty( $args['email']->user_email ) ) {
							$shortcode['[yaymail_user_email]'] = $args['email']->user_email;
						}
						if ( isset( $args['email']->id ) && ( 'customer_new_account_activation' == $args['email']->id ) ) {
							if ( 'customer_new_account_activation' == $args['email']->id ) {
								if ( isset( $args['email']->user_activation_url ) && ! empty( $args['email']->user_activation_url ) ) {
									$shortcode['[yaymail_user_activation_link]'] = $args['email']->user_activation_url;
								}
							} else {
								if ( isset( $args['email']->user_login ) && ! empty( $args['email']->user_login ) ) {
									global $wpdb, $wp_hasher;
									$newHash = $wp_hasher;
									// Generate something random for a password reset key.
									$key = wp_generate_password( 20, false );

									/**
									 *
									 * This action is documented in wp-login.php
									 */
									do_action( 'retrieve_password_key', $args['email']->user_login, $key );

									// Now insert the key, hashed, into the DB.
									if ( empty( $wp_hasher ) ) {
										if ( ! class_exists( 'PasswordHash' ) ) {
											include_once ABSPATH . 'wp-includes/class-phpass.php';
										}
										$newHash = new \PasswordHash( 8, true );
									}
									$hashed = time() . ':' . $newHash->HashPassword( $key );
									$wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $args['email']->user_login ) );
									$activation_url                              = network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $args['email']->user_login ), 'login' );
									$shortcode['[yaymail_user_activation_link]'] = $activation_url;
								}
							}
						}

						// Define shortcode from plugin addon
						$shortcode = apply_filters( 'yaymail_do_shortcode', $shortcode, $yaymail_informations, $args );
					} else {
						// Define shortcode from plugin addon not have args[''email]
						$shortcode = apply_filters( 'yaymail_do_shortcode', $shortcode, $yaymail_informations, $args );
					}
			}
			// support plugin Support Back In Stock Notifier for WooCommerce
			if ( class_exists( 'CWG_Instock_API' ) && isset( $args['subscriber_id'] ) ) {
				$obj               = new \CWG_Instock_API();
				$product_name      = $obj->display_product_name( $args['subscriber_id'] );
				$only_product_name = $obj->display_only_product_name( $args['subscriber_id'] );
				$product_link      = $obj->display_product_link( $args['subscriber_id'] );
				$only_product_sku  = $obj->get_product_sku( $args['subscriber_id'] );
				$product_image     = $obj->get_product_image( $args['subscriber_id'] );
				$subscriber_name   = $obj->get_subscriber_name( $args['subscriber_id'] );
				$pid               = get_post_meta( $args['subscriber_id'], 'cwginstock_pid', true );
				$cart_url          = esc_url_raw( add_query_arg( 'add-to-cart', $pid, get_permalink( wc_get_page_id( 'cart' ) ) ) );
				$blogname          = esc_html( get_bloginfo( 'name' ) );

				$shortcode['[yaymail_notifier_product_name]']       = $product_name;
				$shortcode['[yaymail_notifier_product_id]']         = $pid;
				$shortcode['[yaymail_notifier_product_link]']       = $product_link;
				$shortcode['[yaymail_notifier_shopname]']           = $blogname;
				$shortcode['[yaymail_notifier_email_id]']           = get_post_meta( $args['subscriber_id'], 'cwginstock_subscriber_email', true );
				$shortcode['[yaymail_notifier_subscriber_email]']   = get_post_meta( $args['subscriber_id'], 'cwginstock_subscriber_email', true );
				$shortcode['[yaymail_notifier_subscriber_name]']    = $subscriber_name;
				$shortcode['[yaymail_notifier_cart_link]']          = '<a href="' . esc_url( $cart_url ) . '"> ' . esc_url( $cart_url ) . ' </a>';
				$shortcode['[yaymail_notifier_only_product_name]']  = $only_product_name;
				$shortcode['[yaymail_notifier_only_product_sku]']   = $only_product_sku;
				$shortcode['[yaymail_notifier_only_product_image]'] = $product_image;
				$shortcode['[yaymail_site_name]']                   = esc_html( get_bloginfo( 'name' ) );
			}
			$this->order_data = $shortcode;
		}
	}
	public function shortcodeCallBack( $atts, $content, $tag ) {

		return isset( $this->order_data[ '[' . $tag . ']' ] ) ? $this->order_data[ '[' . $tag . ']' ] : false;

	}

	public function templateParser() {
		// Helper::checkNonce();
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'email-nonce' ) ) {
			wp_send_json_error( array( 'mess' => __( 'Nonce is invalid', 'yaymail' ) ) );
		} else {
			if ( class_exists( 'Loco_Locale' ) ) {
				$cur_lang = isset( $_COOKIE['pll_language'] ) ? sanitize_text_field( $_COOKIE['pll_language'] ) : null;
				if ( $cur_lang ) {
					switch_to_locale( $cur_lang );
				} else {
					switch_to_locale( 'en_US' );
					setcookie( 'pll_language', 'en', time() + 86400, defined( 'COOKIEPATH' ) ? COOKIEPATH : '/' );
				}
			}
			$request             = $_POST;
			$request['order_id'] = sanitize_text_field( $request['order_id'] );
			$request['template'] = sanitize_text_field( $request['template'] );
			$this->order_id      = false;
			if ( isset( $request['order_id'] ) ) {
				$order_id = sanitize_text_field( $request['order_id'] );
				if ( 'sampleOrder' !== $order_id ) {
					$order_id = intval( $order_id );
				}
				if ( ! $order_id ) {
					$order_id = '';
				}

				$this->template = isset( $request['template'] ) ? $request['template'] : false;
				$this->order_id = $order_id;
			}

			if ( ! $this->order_id || ! $this->template ) {
				return false;
			}

			if ( 'sampleOrder' !== $order_id ) {
				$this->order = new \WC_Order( $this->order_id );
			}

			if ( 'sampleOrder' !== $order_id && ( is_null( $this->order ) || empty( $this->order ) || ! isset( $this->order ) ) ) {
				return false;
			}

			if ( 'sampleOrder' !== $order_id ) {
				$this->collectOrderData();
				$this->collectOrderDataHasFunction();
			} else {
				$this->defaultSampleOrderData();
			}

			$result             = (object) array();
			$result->order_id   = $this->order_id;
			$result->order_data = $this->order_data;

			$shortcode_order_meta        = array();
			$shortcode_order_custom_meta = array();
			$shortcode_order_taxes       = array();
			if ( 'sampleOrder' !== $order_id ) {
				$result->order        = $this->order;
				$result->order_items  = $result->order->get_items();
				$result->user_details = $result->order->get_user();

				/*
				@@@@ Get name field in custom field of order woocommerce.
				 */
				$order_metaArr = get_post_meta( $order_id );
				if ( is_array( $order_metaArr ) && count( $order_metaArr ) > 0 ) {
					$pattern = '/^_.*/i';
					$n       = 0;
					foreach ( $order_metaArr as $k => $v ) {
						// @@@ starts with the "_" character of the woo field.
						if ( ! preg_match( $pattern, $k ) ) {
							$nameField              = str_replace( ' ', '_', trim( $k ) );
							$nameShorcode           = '[yaymail_post_meta:' . $nameField . ']';
							$key_order_meta         = 'post_meta:' . $nameField . '_' . $n;
							$shortcode_order_meta[] = array(
								'key'         => $key_order_meta,
								$nameShorcode => 'Loads value of order meta key - ' . $nameField,
							);
							$n++;
						}
					}
				}
				if ( ! empty( $result->order ) ) {
					foreach ( $result->order->get_meta_data() as $meta ) {
						$nameField = str_replace( ' ', '_', trim( $meta->get_data()['key'] ) );
						if ( 'yaymail_multigual_language' != $nameField ) {
							$nameShorcode                  = '[yaymail_order_meta:' . $nameField . ']';
							$key_order_custom_meta         = 'order_meta:' . $nameField;
							$shortcode_order_custom_meta[] = array(
								'key'         => $key_order_custom_meta,
								$nameShorcode => 'Loads value of order custom meta key - ' . $nameField,
							);
						}
					}
					$shortcode_order_custom_meta[] = array(
						'key' => 'order_meta:shipment_carriers',
						'[yaymail_order_meta:shipment_carriers]' => 'Loads value of order custom meta key - shipment_carriers',
					);
					$shortcode_order_custom_meta[] = array(
						'key' => 'order_meta:shipment_tracking_numbers',
						'[yaymail_order_meta:shipment_tracking_numbers]' => 'Loads value of order custom meta key - shipment_tracking_numbers',
					);
					$shortcode_order_custom_meta[] = array(
						'key' => 'shipment_tracking_link',
						'[yaymail_order_meta:shipment_tracking_link]' => 'Loads value of order custom meta key - shipment_tracking_link',
					);

					foreach ( $result->order->get_items( 'tax' ) as $item_id => $item_tax ) {
						$tax_rate_id             = $item_tax->get_rate_id();
						$shortcode_order_taxes[] = array(
							'key'  => '[yaymail_order_taxes_' . $tax_rate_id . ']',
							'name' => $item_tax->get_label(),
						);
					}
				}
			} else {
				$result->order        = '';
				$result->order_items  = '';
				$result->user_details = '';
			}
			$real_postID = '';
			if ( isset( $request['template'] ) ) {
				if ( CustomPostType::postIDByTemplate( $this->template ) ) {
					$postID           = CustomPostType::postIDByTemplate( $this->template );
					$real_postID      = $postID;
					$emailTemplate    = get_post( $postID );
					$updateElement    = new UpdateElement();
					$yaymail_elements = get_post_meta( $postID, '_yaymail_elements', true );
					$list_elements    = Helper::preventXSS( $yaymail_elements );

					$current_language = get_post_meta( $postID, '_yaymail_template_language', true );
					$current_language = ( false === $current_language || '' === $current_language ) ? 'en' : $current_language;

					$yaymailSettingsDefaultLogo   = get_option( 'yaymail_settings_default_logo_' . $current_language );
					$set_default_logo             = false != $yaymailSettingsDefaultLogo ? $yaymailSettingsDefaultLogo['set_default'] : '0';
					$yaymailSettingsDefaultFooter = get_option( 'yaymail_settings_default_footer_' . $current_language );
					$set_default_footer           = false != $yaymailSettingsDefaultFooter ? $yaymailSettingsDefaultFooter['set_default'] : '0';

					if ( YAYMAIL_PRO ) {
						$reviewYayMail = boolval( get_option( 'yaymail_review' ) );
					} else {
						$reviewYayMail = boolval( 1 );
					}

					$make_args['order'] = $this->order;
					$array_element      = array();
					foreach ( $list_elements as $key => $element ) {
						if ( has_filter( 'yaymail_addon_for_conditional_logic' ) && ! empty( $make_args['order'] ) && isset( $element['settingRow']['arrConditionLogic'] ) && ! empty( $element['settingRow']['arrConditionLogic'] ) ) {
							$conditional_Logic = apply_filters( 'yaymail_addon_for_conditional_logic', false, $make_args, $element['settingRow'] );

							if ( $conditional_Logic ) {
								$array_element[] = $element;
							}
						} else {
							$array_element[] = $element;
						}
					}

					$result->elements                    = Helper::unsanitize_array( $updateElement->merge_new_props_to_elements( $array_element ) );
					$result->emailBackgroundColor        = get_post_meta( $postID, '_email_backgroundColor_settings', true ) ? get_post_meta( $postID, '_email_backgroundColor_settings', true ) : 'rgb(236, 236, 236)';
					$result->emailTextLinkColor          = get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) ? get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) : '#7f54b3';
					$result->titleShipping               = get_post_meta( $postID, '_email_title_shipping', true ) ? get_post_meta( $postID, '_email_title_shipping', true ) : __( 'Shipping Address', 'woocommerce' );
					$result->titleBilling                = get_post_meta( $postID, '_email_title_billing', true ) ? get_post_meta( $postID, '_email_title_billing', true ) : __( 'Billing Address', 'woocommerce' );
					$result->orderTitle                  = get_post_meta( $postID, '_yaymail_email_order_item_title', true );
					$result->customCSS                   = $this->applyCSSFormat();
					$result->shortcode_order_meta        = $shortcode_order_meta;
					$result->shortcode_order_custom_meta = $shortcode_order_custom_meta;
					$result->shortcode_order_taxes       = $shortcode_order_taxes;
					$result->set_default_logo            = $set_default_logo;
					$result->set_default_footer          = $set_default_footer;
					$result->review_yaymail              = $reviewYayMail;
				}
			}
			$result->yaymailAddonTemps = apply_filters( 'yaymail_addon_templates', array(), $result->order, $real_postID );

			echo json_encode( $result );
			die();
		}
	}

	public function collectOrderDataHasFunction( $sent_to_admin = '', $args = array() ) {
		$order = $this->order;
		if ( empty( $this->order_id ) || empty( $order ) ) {
			return false;
		}
		// Link Downloadable Product
		$shortcode['[yaymail_items_downloadable_title]']   = $this->itemsDownloadableTitle( '', $this->order, $sent_to_admin, '' ); // done
		$shortcode['[yaymail_items_downloadable_product]'] = $this->itemsDownloadableProduct( '', $this->order, $sent_to_admin, '' ); // done
		// CUSTOM SHORTCODE
		if ( class_exists( 'Puc_v4_Factory' ) ) {
			$shortcode['[yaymail_dpdch_tracking_number]'] = $this->dpdchTrackingNumber( '', $this->order, $sent_to_admin );
		}

		// ORDER DETAILS
		$shortcode['[yaymail_items_border]']         = $this->itemsBorder( '', $this->order, $sent_to_admin ); // done
		$shortcode['[yaymail_items_border_before]']  = $this->itemsBorderBefore( '', $this->order, $sent_to_admin ); // done
		$shortcode['[yaymail_items_border_after]']   = $this->itemsBorderAfter( '', $this->order, $sent_to_admin ); // done
		$shortcode['[yaymail_items_border_title]']   = $this->itemsBorderTitle( '', $this->order, $sent_to_admin ); // done
		$shortcode['[yaymail_items_border_content]'] = $this->itemsBorderContent( '', $this->order, $sent_to_admin ); // done
		$shortcode['[yaymail_get_heading]']          = $this->getEmailHeading( $args, $this->order, $sent_to_admin );

		// WC HOOK
		$shortcode['[woocommerce_email_order_meta]']          = $this->woocommerceEmailOrderMeta( $args, $sent_to_admin ); // not Changed
		$shortcode['[woocommerce_email_order_details]']       = $this->woocommerceEmailOrderDetails( $args, $sent_to_admin ); // not Changed
		$shortcode['[woocommerce_email_after_order_table]']   = $this->woocommerceEmailAfterOrderTable( $args, $sent_to_admin ); // not Changed
		$shortcode['[woocommerce_email_before_order_table]']  = $this->woocommerceEmailBeforeOrderTable( $args, $sent_to_admin ); // not Changed
		$shortcode['[yaymail_woocommerce_email_sozlesmeler]'] = $this->woocommerceEmailSozlesmeler( '', $this->order, $sent_to_admin ); // not Changed

		$shortcode['[yaymail_payment_instruction]'] = $this->paymentInstructions( $this->order, $sent_to_admin );

		$shortcode['[yaymail_billing_shipping_address]'] = $this->billingShippingAddress( '', $this->order ); // done

		$shortcode['[yaymail_billing_shipping_address_title]']   = $this->billingShippingAddressTitle( '', $this->order ); // done
		$shortcode['[yaymail_billing_shipping_address_content]'] = $this->billingShippingAddressContent( '', $this->order ); // done
		$shortcode['[yaymail_check_billing_shipping_address]']   = $this->checkBillingShippingAddress( '', $this->order );

		$shortcode['[yaymail_shipment_tracking_title]'] = $this->shipmentTrackingTitle( '', $this->order );
		$shortcode['[yaymail_shipment_tracking_items]'] = $this->shipmentTrackingItems( '', $this->order );

		if ( class_exists( 'PH_Shipment_Tracking_API_Manager' ) ) {
			$shortcode['[yaymail_ph_shipment_tracking]'] = $this->phShipmentTracking( '', $this->order );
		}

		if ( class_exists( 'WC_Connect_Loader' ) ) {
			$shortcode['[yaymail_shipping_tax_shipment_tracking]'] = $this->shippingTaxShipmentTracking( '', $this->order );
		}

		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'wc-chitchats-shipping-pro/wc-chitchats-shipping-pro.php' ) ) {
			$shortcode['[yaymail_chitchats_shipping_shipments]'] = $this->chitchatsShippingShipments( '', $this->order );
		}

		if ( class_exists( 'FooEvents' ) ) {
			$shortcode['[yaymail_fooevents_ticket_details]'] = $this->fooeventsTicketDetails( '', $this->order );
		}

		if ( class_exists( 'YITH_Barcode' ) ) {
			$shortcode['[yaymail_yith_barcode]'] = $this->yithBarcode( '', $this->order );
		}

		if ( class_exists( 'WC_Software' ) ) {
			$shortcode['[yaymail_software_add_on]'] = $this->softwareAddOn( '', $this->order );
		}

		if ( class_exists( 'YITH_WooCommerce_Order_Tracking_Premium' ) ) {
			$shortcode['[yaymail_order_carrier_name]']  = $this->orderCarrierName( '', $this->order );
			$shortcode['[yaymail_order_pickup_date]']   = $this->orderPickupDate( '', $this->order );
			$shortcode['[yaymail_order_track_code]']    = $this->orderTrackCode( '', $this->order );
			$shortcode['[yaymail_order_tracking_link]'] = $this->orderTrackingLink( '', $this->order );
		}
		$shortcode['[yaymail_order_coupon_codes]'] = $this->orderCouponCodes( $this->order );

		$this->order_data = array_merge( $this->order_data, $shortcode );

	}


	public function collectOrderData( $sent_to_admin = '', $args = array() ) {
		$order = $this->order;
		if ( empty( $this->order_id ) || empty( $order ) ) {
			return false;
		}

		// Getting Fee & Refunds:
		$fee    = 0;
		$refund = 0;
		$totals = $order->get_order_item_totals();
		foreach ( $totals as $index => $value ) {
			if ( strpos( $index, 'fee' ) !== false ) {
				$fees = $order->get_fees();
				foreach ( $fees as $feeVal ) {
					if ( method_exists( $feeVal, 'get_amount' ) ) {
						$fee += $feeVal->get_amount();
					}
				}
			}
			if ( strpos( $index, 'refund' ) !== false ) {
				$refund = $order->get_total_refunded();
			}
		}
		// User Info
		$user_data        = $order->get_user();
		$created_date     = $order->get_date_created();
		$items            = $order->get_items();
		$yaymail_settings = get_option( 'yaymail_settings' );
		$order_url        = $order->get_edit_order_url();
		add_filter(
			'woocommerce_formatted_address_replacements',
			function( $info, $args ) {
				$file = __FILE__;
				if ( 'Shortcodes.php' === basename( $file ) ) {
					if ( isset( $this->yaymail_states[ $args['country'] ][ $args['state'] ] ) ) {
						$info['{state}'] = $this->yaymail_states[ $args['country'] ][ $args['state'] ];
					}
					if ( isset( $this->yaymail_countries[ $args['country'] ] ) ) {
						$info['{country}'] = $this->yaymail_countries[ $args['country'] ];
					}
					$info['{state_upper}']   = wc_strtoupper( $info['{state}'] );
					$info['{country_upper}'] = wc_strtoupper( $info['{country}'] );
				}
				return $info;
			},
			100,
			2
		);
		$this->shipping_address = $order->get_formatted_shipping_address();
		$this->billing_address  = $order->get_formatted_billing_address();
		$shipping_address       = class_exists( 'Flexible_Checkout_Fields_Disaplay_Options' ) ? $this->shipping_address : $order->get_formatted_shipping_address();
		$billing_address        = class_exists( 'Flexible_Checkout_Fields_Disaplay_Options' ) ? $this->billing_address : $order->get_formatted_billing_address();
		$postID                 = CustomPostType::postIDByTemplate( $this->template );
		$yaymail_informations   = array(
			'post_id'          => $postID,
			'template'         => $this->template,
			'order'            => $order,
			'yaymail_elements' => get_post_meta( $postID, '_yaymail_elements', true ),
			'general_settings' => array(
				'tableWidth'           => $yaymail_settings['container_width'],
				'emailBackgroundColor' => get_post_meta( $postID, '_email_backgroundColor_settings', true ) ? get_post_meta( $postID, '_email_backgroundColor_settings', true ) : '#ECECEC',
				'textLinkColor'        => get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) ? get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) : '#7f54b3',
			),
		);
		$text_link_color        = get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) ? get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) : '#7f54b3';
		if ( $order->get_billing_phone() ) {
			$billing_address .= "<br/> <a href='tel:" . esc_html( $order->get_billing_phone() ) . "' style='color:" . esc_attr( $text_link_color ) . "; font-weight: normal; text-decoration: underline;'>" . esc_html( $order->get_billing_phone() ) . '</a>';
		}
		if ( $order->get_billing_email() ) {
			$billing_address .= "<br/><a href='mailto:" . esc_html( $order->get_billing_email() ) . "' style='color:" . esc_attr( $text_link_color ) . ";font-weight: normal; text-decoration: underline;'>" . esc_html( $order->get_billing_email() ) . '</a>';
		}
		$customerNotes        = $order->get_customer_order_notes();
		$customerNoteHtmlList = '';
		$customerNoteHtml     = $customerNoteHtmlList;
		if ( ! empty( $customerNotes ) && count( $customerNotes ) ) {
			$customerNoteHtmlList  = $this->getOrderCustomerNotes( $customerNotes );
			$customerNote_single[] = $customerNotes[0];
			$customerNoteHtml      = $this->getOrderCustomerNotes( $customerNote_single );
		}

		$resetURL = '';
		if ( isset( $args['email']->reset_key ) && ! empty( $args['email']->reset_key )
			&& isset( $args['email']->user_login ) && ! empty( $args['email']->user_login )
		) {
			$user      = new \WP_User( intval( $args['email']->user_id ) );
			$reset_key = get_password_reset_key( $user );
			$resetURL  = esc_url(
				add_query_arg(
					array(
						'key'   => $reset_key,
						'login' => rawurlencode( $args['email']->user_login ),
					),
					wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
				)
			);
		}

		// User meta
		if ( $user_data ) {
			$shortcode['[yaymail_user_meta_fpso]']       = get_user_meta( $user_data->ID, 'fpso', true );
			$shortcode['[yaymail_user_meta_employeeid]'] = get_user_meta( $user_data->ID, 'employeeid', true );
		}

		// support plugin Support Back In Stock Notifier for WooCommerce
		if ( class_exists( 'CWG_Instock_Notifier' ) ) {
			$shortcode['[yaymail_notifier_product_name]']       = __( 'YayMail', 'yaymail' );
			$shortcode['[yaymail_notifier_product_id]']         = __( '1', 'yaymail' );
			$shortcode['[yaymail_notifier_product_link]']       = '<a href="' . esc_url( get_site_url() ) . '"> ' . esc_url( get_site_url() ) . ' </a>';
			$shortcode['[yaymail_notifier_shopname]']           = esc_html( get_bloginfo( 'name' ) );
			$shortcode['[yaymail_notifier_email_id]']           = __( 'yaymail@gmail.com', 'yaymail' );
			$shortcode['[yaymail_notifier_subscriber_email]']   = __( 'yaymail@gmail.com', 'yaymail' );
			$shortcode['[yaymail_notifier_subscriber_name]']    = __( 'YayMail', 'yaymail' );
			$shortcode['[yaymail_notifier_cart_link]']          = '<a href="' . esc_url( get_site_url() ) . '"> ' . esc_url( get_site_url() ) . ' </a>';
			$shortcode['[yaymail_notifier_only_product_name]']  = __( 'YayMail', 'yaymail' );
			$shortcode['[yaymail_notifier_only_product_sku]']   = __( '1', 'yaymail' );
			$shortcode['[yaymail_notifier_only_product_image]'] = '<a href="' . esc_url( get_site_url() ) . '"> ' . esc_url( get_site_url() ) . ' </a>';
		}
		// Define shortcode from plugin addon
		$shortcode = apply_filters( 'yaymail_do_shortcode', $shortcode, $yaymail_informations, $this->args_email );

		if ( null != $created_date ) {
			$shortcode['[yaymail_order_date]'] = $order->get_date_created()->date_i18n( wc_date_format() );
		} else {
			$shortcode['[yaymail_order_date]'] = '';
		}
		$shortcode['[yaymail_order_fee]'] = $fee;
		if ( ! empty( $order->get_id() ) ) {
			$shortcode['[yaymail_order_id]'] = $order->get_id();
		} else {
			$shortcode['[yaymail_order_id]'] = '';
		}
		$shortcode['[yaymail_order_link]']        = '<a href="' . esc_url( $order_url ) . '" style="color:' . esc_attr( $text_link_color ) . ';">' . esc_html__( 'Order', 'yaymail' ) . '</a>';
		$shortcode['[yaymail_order_link]']        = str_replace( '[yaymail_order_id]', $order->get_id(), $shortcode['[yaymail_order_link]'] );
		$shortcode['[yaymail_order_link_string]'] = esc_url( $order_url );
		if ( ! empty( $order->get_order_number() ) ) {
			$shortcode['[yaymail_order_number]'] = $order->get_order_number();
		} else {
			$shortcode['[yaymail_order_number]'] = '';
		}
		$shortcode['[yaymail_order_refund]'] = $refund;
		if ( isset( $totals['cart_subtotal']['value'] ) ) {
			$shortcode['[yaymail_order_sub_total]'] = $totals['cart_subtotal']['value'];
		} else {
			$shortcode['[yaymail_order_sub_total]'] = '';
		}

		if ( isset( $totals['discount']['value'] ) ) {
			$shortcode['[yaymail_order_discount]'] = $totals['discount']['value'];
		}

		$shortcode['[yaymail_order_total]']         = wc_price( $order->get_total() );
		$shortcode['[yaymail_order_total_numbers]'] = $order->get_total();
		$shortcode['[yaymail_orders_count]']        = count( $order->get_items() );
		$shortcode['[yaymail_orders_count_double]'] = count( $order->get_items() ) * 2;

		// PAYMENTS
		if ( isset( $totals['payment_method']['value'] ) ) {
			$shortcode['[yaymail_order_payment_method]'] = $totals['payment_method']['value'];
		} else {
			$shortcode['[yaymail_order_payment_method]'] = '';
		}
		$shortcode['[yaymail_order_payment_url]']        = '<a href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . esc_html__( 'Payment page', 'yaymail' ) . '</a>';
		$shortcode['[yaymail_order_payment_url_string]'] = esc_url( $order->get_checkout_payment_url() );

		foreach ( $order->get_items( 'tax' ) as $item_id => $item_tax ) {
			$tax_rate_id = $item_tax->get_rate_id();

			$shortcode_order_taxes = '[yaymail_order_taxes_' . $tax_rate_id . ']';
			$tax_amount_total      = $item_tax->get_tax_total(); // Tax rate total
			$tax_shipping_total    = $item_tax->get_shipping_tax_total(); // Tax shipping total
			$totals_taxes          = $tax_amount_total + $tax_shipping_total;

			$shortcode[ $shortcode_order_taxes ] = wc_price( $totals_taxes );
		}

		if ( ! empty( $order->get_payment_method_title() ) ) {
			$shortcode['[yaymail_payment_method]'] = $order->get_payment_method_title();
		} else {
			$shortcode['[yaymail_payment_method]'] = '';
		}
		if ( ! empty( $order->get_transaction_id() ) ) {
			$shortcode['[yaymail_transaction_id]'] = $order->get_transaction_id();
		} else {
			$shortcode['[yaymail_transaction_id]'] = '';
		}

		// SHIPPINGS
		if ( ! empty( $order->calculate_shipping() ) ) {
			$shortcode['[yaymail_order_shipping]'] = $order->calculate_shipping();
		} else {
			$shortcode['[yaymail_order_shipping]'] = 0;
		}
		$shortcode['[yaymail_shipping_address]'] = $shipping_address;
		if ( ! empty( $order->get_shipping_address_1() ) ) {
			$shortcode['[yaymail_shipping_address_1]'] = $order->get_shipping_address_1();
		} else {
			$shortcode['[yaymail_shipping_address_1]'] = '';
		}
		if ( ! empty( $order->get_shipping_address_2() ) ) {
			$shortcode['[yaymail_shipping_address_2]'] = $order->get_shipping_address_2();
		} else {
			$shortcode['[yaymail_shipping_address_2]'] = '';
		}
		if ( ! empty( $order->get_shipping_city() ) ) {
			$shortcode['[yaymail_shipping_city]'] = $order->get_shipping_city();
		} else {
			$shortcode['[yaymail_shipping_city]'] = '';
		}
		if ( ! empty( $order->get_shipping_company() ) ) {
			$shortcode['[yaymail_shipping_company]'] = $order->get_shipping_company();
		} else {
			$shortcode['[yaymail_shipping_company]'] = '';
		}
		if ( ! empty( $order->get_shipping_country() ) ) {
			$country_code_bym                        = $order->get_shipping_country();
			$wc_countries                            = WC()->countries;
			$shipping_country_name_bym               = $wc_countries->countries[ $country_code_bym ];
			$shortcode['[yaymail_shipping_country]'] = $shipping_country_name_bym;
		} else {
			$shortcode['[yaymail_shipping_country]'] = '';
		}
		if ( ! empty( $order->get_shipping_first_name() ) ) {
			$shortcode['[yaymail_shipping_first_name]'] = $order->get_shipping_first_name();
		} else {
			$shortcode['[yaymail_shipping_first_name]'] = '';

		}
		if ( ! empty( $order->get_shipping_last_name() ) ) {
			$shortcode['[yaymail_shipping_last_name]'] = $order->get_shipping_last_name();
		} else {
			$shortcode['[yaymail_shipping_last_name]'] = '';

		}
		if ( ! empty( $order->get_shipping_method() ) ) {
			$shortcode['[yaymail_shipping_method]'] = $order->get_shipping_method();
		} else {
			$shortcode['[yaymail_shipping_method]'] = '';
		}
		if ( ! empty( $order->get_shipping_postcode() ) ) {
			$shortcode['[yaymail_shipping_postcode]'] = $order->get_shipping_postcode();
		} else {
			$shortcode['[yaymail_shipping_postcode]'] = '';
		}
		if ( ! empty( $order->get_shipping_state() ) ) {
			$shortcode['[yaymail_shipping_state]'] = $order->get_shipping_state();
		} else {
			$shortcode['[yaymail_shipping_state]'] = '';
		}
		if ( method_exists( $order, 'get_shipping_phone' ) && ! empty( $order->get_shipping_phone() ) ) {
			$shortcode['[yaymail_shipping_phone]'] = $order->get_shipping_phone();
		} else {
			$shortcode['[yaymail_shipping_phone]'] = '';
		}

		// BILLINGS
		$shortcode['[yaymail_billing_address]'] = $billing_address;
		if ( ! empty( $order->get_billing_address_1() ) ) {
			$shortcode['[yaymail_billing_address_1]'] = $order->get_billing_address_1();
		} else {
			$shortcode['[yaymail_billing_address_1]'] = '';
		}
		if ( ! empty( $order->get_billing_address_2() ) ) {
			$shortcode['[yaymail_billing_address_2]'] = $order->get_billing_address_2();
		} else {
			$shortcode['[yaymail_billing_address_2]'] = '';
		}
		if ( ! empty( $order->get_billing_city() ) ) {
			$shortcode['[yaymail_billing_city]'] = $order->get_billing_city();
		} else {
			$shortcode['[yaymail_billing_city]'] = $order->get_billing_city();
		}
		if ( ! empty( $order->get_billing_company() ) ) {
			$shortcode['[yaymail_billing_company]'] = $order->get_billing_company();
		} else {
			$shortcode['[yaymail_billing_company]'] = '';
		}
		if ( ! empty( $order->get_billing_country() ) ) {
			$country_code_bym                       = $order->get_billing_country();
			$wc_countries                           = WC()->countries;
			$billing_country_name_bym               = $wc_countries->countries[ $country_code_bym ];
			$shortcode['[yaymail_billing_country]'] = $billing_country_name_bym;
		} else {
			$shortcode['[yaymail_billing_country]'] = '';
		}
		if ( ! empty( $order->get_billing_email() ) ) {
			$shortcode['[yaymail_billing_email]'] = '<a style="color: inherit" href="mailto:' . esc_html( $order->get_billing_email() ) . '">' . esc_html( $order->get_billing_email() ) . '</a>';
		} else {
			$shortcode['[yaymail_billing_email]'] = '';
		}
		if ( ! empty( $order->get_billing_first_name() ) ) {
			$shortcode['[yaymail_billing_first_name]'] = $order->get_billing_first_name();
		} else {
			$shortcode['[yaymail_billing_first_name]'] = '';
		}
		if ( ! empty( $order->get_billing_last_name() ) ) {
			$shortcode['[yaymail_billing_last_name]'] = $order->get_billing_last_name();
		} else {
			$shortcode['[yaymail_billing_last_name]'] = '';
		}
		if ( ! empty( $order->get_billing_phone() ) ) {
			$shortcode['[yaymail_billing_phone]'] = $order->get_billing_phone();
		} else {
			$shortcode['[yaymail_billing_phone]'] = '';
		}
		if ( ! empty( $order->get_billing_postcode() ) ) {
			$shortcode['[yaymail_billing_postcode]'] = $order->get_billing_postcode();
		} else {
			$shortcode['[yaymail_billing_postcode]'] = '';
		}
		if ( ! empty( $order->get_billing_state() ) ) {
			$shortcode['[yaymail_billing_state]'] = $order->get_billing_state();
		} else {
			$shortcode['[yaymail_billing_state]'] = '';
		}

		// Reset Passwords
		$shortcode['[yaymail_password_reset_url]']        = '<a style="color: ' . esc_attr( $text_link_color ) . ';" href="' . esc_url( $resetURL ) . '">' . esc_html__( 'Click here to reset your password', 'woocommerce' ) . '</a>';
		$shortcode['[yaymail_password_reset_url_string]'] = esc_url( $resetURL );

		// New Users
		if ( isset( $args['email']->user_pass ) && ! empty( $args['email']->user_pass ) ) {
			$shortcode['[yaymail_user_new_password]'] = $args['email']->user_pass;
		} else {
			if ( isset( $_REQUEST['pass1-text'] ) && '' != $_REQUEST['pass1-text'] ) {
				$shortcode['[yaymail_user_new_password]'] = sanitize_text_field( $_REQUEST['pass1-text'] );
			} elseif ( isset( $_REQUEST['pass1'] ) && '' != $_REQUEST['pass1'] ) {
				$shortcode['[yaymail_user_new_password]'] = sanitize_text_field( $_REQUEST['pass1-text'] );
			} else {
				$shortcode['[yaymail_user_new_password]'] = '';
			}
		}
		// Review this code ??
		if ( isset( $args['email']->user_activation_url ) && ! empty( $args['email']->user_activation_url ) ) {
			$shortcode['[yaymail_user_activation_link]'] = $args['email']->user_activation_url;
		} else {
			$shortcode['[yaymail_user_activation_link]'] = '';
		}

		// GENERALS
		$shortcode['[yaymail_customer_note]']  = ( $customerNoteHtml ); // add strip_tags() to remove link
		$shortcode['[yaymail_customer_notes]'] = $customerNoteHtmlList;
		if ( ! empty( $order->get_customer_note() ) ) {
			$shortcode['[yaymail_customer_provided_note]'] = $order->get_customer_note();
		} else {
			$shortcode['[yaymail_customer_provided_note]'] = '';
		}
		$shortcode['[yaymail_site_name]']       = esc_html( get_bloginfo( 'name' ) );
		$shortcode['[yaymail_site_url]']        = '<a href="' . esc_url( get_home_url() ) . '"> ' . esc_url( get_home_url() ) . ' </a>';
		$shortcode['[yaymail_site_url_string]'] = esc_url( get_home_url() );
		if ( isset( $user_data->user_email ) ) {
			$shortcode['[yaymail_user_email]'] = $user_data->user_email;
		} else {
			$shortcode['[yaymail_user_email]'] = $order->get_billing_email();
		}
		if ( isset( $shortcode['[yaymail_user_email]'] ) && '' != $shortcode['[yaymail_user_email]'] ) {
			$user                           = get_user_by( 'email', $shortcode['[yaymail_user_email]'] );
			$shortcode['[yaymail_user_id]'] = ( isset( $user->ID ) ) ? $user->ID : '';
		}
		if ( isset( $user_data->user_login ) && ! empty( $user_data->user_login ) ) {
			$shortcode['[yaymail_customer_username]'] = $user_data->user_login;
		} elseif ( isset( $user_data->user_nicename ) ) {
			$shortcode['[yaymail_customer_username]'] = $user_data->user_nicename;
		} else {
			$shortcode['[yaymail_customer_username]'] = $order->get_billing_first_name();
		}
		if ( isset( $user_data->roles ) && ! empty( $user_data->roles ) ) {
			$shortcode['[yaymail_customer_roles]'] = implode( ', ', $user_data->roles );
		}
		if ( isset( $user->ID ) && ! empty( $user->ID ) ) {
			$shortcode['[yaymail_customer_name]']       = get_user_meta( $user->ID, 'first_name', true ) . ' ' . get_user_meta( $user->ID, 'last_name', true );
			$shortcode['[yaymail_customer_first_name]'] = get_user_meta( $user->ID, 'first_name', true );
			$shortcode['[yaymail_customer_last_name]']  = get_user_meta( $user->ID, 'last_name', true );
		} elseif ( isset( $user_data->user_nicename ) ) {
			$shortcode['[yaymail_customer_name]'] = $user_data->user_nicename;
		} else {
			$shortcode['[yaymail_customer_name]'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		}
		if ( ! empty( $order->get_view_order_url() ) ) {
			$text_your_order                              = esc_html__( 'Your Order', 'yaymail' );
			$shortcode['[yaymail_view_order_url]']        = '<a href="' . esc_url( $order->get_view_order_url() ) . '" style="color:' . esc_attr( $text_link_color ) . ';">' . esc_html( $text_your_order ) . '</a>';
			$shortcode['[yaymail_view_order_url_string]'] = $order->get_view_order_url();
		} else {
			$shortcode['[yaymail_view_order_url]'] = '';
		}

		$shortcode['[yaymail_order_status]']      = strtolower( wc_get_order_status_name( $this->order->get_status() ) );
		$shortcode['[yaymail_order_status_from]'] = strtolower( wc_get_order_status_name( isset( $args['status_from'] ) ? $args['status_from'] : $this->order->get_status() ) );

		if ( ! empty( parse_url( esc_url( get_site_url() ) )['host'] ) ) {
			$shortcode['[yaymail_domain]'] = parse_url( esc_url( get_site_url() ) )['host'];
		} else {
			$shortcode['[yaymail_domain]'] = '';
		}

		$shortcode['[yaymail_user_account_url]']        = '<a style="color:' . esc_attr( $text_link_color ) . '; font-weight: normal; text-decoration: underline;" href="' . wc_get_page_permalink( 'myaccount' ) . '">' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '</a>';
		$shortcode['[yaymail_user_account_url_string]'] = wc_get_page_permalink( 'myaccount' );

		if ( class_exists( 'WC_Pagarme' ) ) {
			$pagarme_data                                      = get_post_meta( $this->order->id, '_wc_pagarme_transaction_data', true );
			$shortcode['[yaymail_pagarme_banking_ticket_url]'] = '<a style="color:' . esc_attr( $text_link_color ) . '; font-weight: normal; text-decoration: underline;" href="' . esc_url( $pagarme_data['boleto_url'] ? $pagarme_data['boleto_url'] : '#' ) . '">' . esc_html__( 'Pay the banking ticket', 'woocommerce-pagarme' ) . '</a>';
			$shortcode['[yaymail_pagarme_credit_card_brand]']  = $pagarme_data['card_brand'] ? $pagarme_data['card_brand'] : '';
			$shortcode['[yaymail_pagarme_credit_card_installments]'] = $pagarme_data['installments'] ? $pagarme_data['installments'] : '';
		}
		// ADDITIONAL ORDER META:
		$order_metaArr = get_post_meta( $this->order_id );
		if ( is_array( $order_metaArr ) && count( $order_metaArr ) > 0 ) {
			foreach ( $order_metaArr as $k => $v ) {
				$nameField    = str_replace( ' ', '_', trim( $k ) );
				$nameShorcode = '[yaymail_post_meta:' . $nameField . ']';

				// when array $v has tow value ???
				if ( is_array( $v ) && count( $v ) > 0 ) {
					$shortcode[ $nameShorcode ] = trim( $v[0] );
				} else {
					$shortcode[ $nameShorcode ] = trim( $v );
				}
			}
		}

		/*
		To get custom fields support Checkout Field Editor for WooCommerce */
		if ( ! empty( $order ) ) {
			foreach ( $order->get_meta_data() as $meta ) {
				$nameField    = str_replace( ' ', '_', trim( $meta->get_data()['key'] ) );
				$nameShorcode = '[yaymail_order_meta:' . $nameField . ']';
				if ( '_wc_shipment_tracking_items' == $nameField ) {
					$shortcode[ $nameShorcode ] = $this->orderMetaWcShipmentTrackingItems( '', $this->order );
				} elseif ( '_local_pickup_time_select' == $nameField ) {
					$plugin                     = \Local_Pickup_Time::get_instance();
					$shortcode[ $nameShorcode ] = $plugin->pickup_time_select_translatable( $meta->get_data()['value'] );
				} else {
					if ( is_array( $meta->get_data()['value'] ) ) {
						$checkNestedArray = false;
						foreach ( $meta->get_data()['value'] as $value ) {
							if ( is_object( $value ) || is_array( $value ) ) {
								$checkNestedArray = true;
								break;
							}
						}
						if ( false == $checkNestedArray ) {
							$shortcode[ $nameShorcode ] = implode( ', ', $meta->get_data()['value'] );
						} else {
							$shortcode[ $nameShorcode ] = '';
							if ( class_exists( 'WCPFC_Country' ) ) {
								$arr_values = \wcpfc_get_value_if_set( $meta->get_data(), array( 'value', 'value' ), '' );
								if ( is_object( $arr_values ) || is_array( $arr_values ) ) {
									$shortcode[ $nameShorcode ] = implode( ',', $arr_values );
								} else {
									$shortcode[ $nameShorcode ] = $arr_values;
								}
							}
						}
					} else {
						if ( is_string( $meta->get_data()['value'] ) ) {
							$shortcode[ $nameShorcode ] = nl2br( $meta->get_data()['value'] );
						} else {
							$shortcode[ $nameShorcode ] = $meta->get_data()['value'];
						}
					}
				}
			}
		}

		$shortcode['[yaymail_items]']                         = $this->orderItems( $items, $sent_to_admin, '', true );
		$shortcode['[yaymail_items_products_quantity_price]'] = $this->orderItems( $items, $sent_to_admin, '', false );

		if ( class_exists( 'WC_Admin_Custom_Order_Fields' ) ) {
			$shortcode['[yaymail_order_meta:_wc_additional_order_details]'] = $this->orderMetaWcAdditionalOrderDetails( '', $this->order );
		}

		if ( class_exists( 'EventON' ) ) {
			$shortcode['[yaymail_order_meta:_event_on_list]'] = $this->eventOnList( '', $this->order );
		}

		$shortcode['[yaymail_order_meta:shipment_carriers]']         = $this->orderMetaWcShipmentTrackingCarriers( '', $this->order );
		$shortcode['[yaymail_order_meta:shipment_tracking_numbers]'] = $this->orderMetaWcShipmentTrackingNumbers( '', $this->order );
		$shortcode['[yaymail_order_meta:shipment_tracking_link]']    = $this->orderMetaWcShipmentTrackingLink( '', $this->order );

		$this->order_data = $shortcode;
	}

	public function defaultSampleOrderData( $sent_to_admin = '' ) {
		$current_user         = wp_get_current_user();
		$postID               = CustomPostType::postIDByTemplate( $this->template );
		$text_link_color      = get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) ? get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) : '#7f54b3';
		$billing_address      = "John Doe<br/>YayCommerce<br/>7400 Edwards Rd<br/>Edwards Rd<br/><a href='tel:+18587433828' style='color: " . esc_attr( $text_link_color ) . "; font-weight: normal; text-decoration: underline;'>(910) 529-1147</a><br/>";
		$shipping_address     = "John Doe<br/>YayCommerce<br/>755 E North Grove Rd<br/>Mayville, Michigan<br/><a href='tel:+18587433828' style='color: " . esc_attr( $text_link_color ) . "; font-weight: normal; text-decoration: underline;'>(910) 529-1147</a><br/>";
		$user_id              = get_current_user_id();
		$yaymail_settings     = get_option( 'yaymail_settings' );
		$yaymail_informations = array(
			'post_id'          => $postID,
			'yaymail_elements' => get_post_meta( $postID, '_yaymail_elements', true ),
			'template'         => $this->template,
			'general_settings' => array(
				'tableWidth'           => $yaymail_settings['container_width'],
				'emailBackgroundColor' => get_post_meta( $postID, '_email_backgroundColor_settings', true ) ? get_post_meta( $postID, '_email_backgroundColor_settings', true ) : '#ECECEC',
				'textLinkColor'        => get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) ? get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) : '#7f54b3',
			),
		);

		// Link Downloadable Product
		$shortcode['[yaymail_items_downloadable_title]']   = $this->itemsDownloadableTitle( '', $this->order, $sent_to_admin, '' ); // done
		$shortcode['[yaymail_items_downloadable_product]'] = $this->itemsDownloadableProduct( '', $this->order, $sent_to_admin, '' ); // done

		// CUSTOM SHORTCODE,
		if ( class_exists( 'Puc_v4_Factory' ) ) {
			$shortcode['[yaymail_dpdch_tracking_number]'] = $this->dpdchTrackingNumber( '', $this->order, $sent_to_admin );
		}

		// ORDER DETAILS
		$shortcode['[yaymail_items_border]'] = $this->itemsBorder( '', $this->order, $sent_to_admin ); // done

		$shortcode['[yaymail_items_border_before]']  = $this->itemsBorderBefore( '', $this->order, $sent_to_admin ); // done
		$shortcode['[yaymail_items_border_after]']   = $this->itemsBorderAfter( '', $this->order, $sent_to_admin ); // done
		$shortcode['[yaymail_items_border_title]']   = $this->itemsBorderTitle( '', $this->order, $sent_to_admin ); // done
		$shortcode['[yaymail_items_border_content]'] = $this->itemsBorderContent( '', $this->order, $sent_to_admin ); // done
		$shortcode['[yaymail_get_heading]']          = $this->getEmailHeading( '', $this->order, $sent_to_admin );

		// WC HOOK
		$shortcode['[woocommerce_email_order_meta]']          = $this->woocommerceEmailOrderMeta( array(), $sent_to_admin, 'sampleOrder' ); // not Changed
		$shortcode['[woocommerce_email_order_details]']       = $this->woocommerceEmailOrderDetails( array(), $sent_to_admin, 'sampleOrder' ); // not Changed
		$shortcode['[woocommerce_email_after_order_table]']   = $this->woocommerceEmailAfterOrderTable( array(), $sent_to_admin, 'sampleOrder' ); // not changed
		$shortcode['[woocommerce_email_before_order_table]']  = $this->woocommerceEmailBeforeOrderTable( array(), $sent_to_admin, 'sampleOrder' ); // not changed
		$shortcode['[yaymail_woocommerce_email_sozlesmeler]'] = $this->woocommerceEmailSozlesmeler( '', $this->order, $sent_to_admin ); // not Changed

		// support plugin Support Back In Stock Notifier for WooCommerce
		if ( class_exists( 'CWG_Instock_Notifier' ) ) {
			$shortcode['[yaymail_notifier_product_name]']       = __( 'YayMail', 'yaymail' );
			$shortcode['[yaymail_notifier_product_id]']         = __( '1', 'yaymail' );
			$shortcode['[yaymail_notifier_product_link]']       = '<a href="' . esc_url( get_site_url() ) . '"> ' . esc_url( get_site_url() ) . ' </a>';
			$shortcode['[yaymail_notifier_shopname]']           = esc_html( get_bloginfo( 'name' ) );
			$shortcode['[yaymail_notifier_email_id]']           = __( 'yaymail@gmail.com', 'yaymail' );
			$shortcode['[yaymail_notifier_subscriber_email]']   = __( 'yaymail@gmail.com', 'yaymail' );
			$shortcode['[yaymail_notifier_subscriber_name]']    = __( 'YayMail', 'yaymail' );
			$shortcode['[yaymail_notifier_cart_link]']          = '<a href="' . esc_url( get_site_url() ) . '"> ' . esc_url( get_site_url() ) . ' </a>';
			$shortcode['[yaymail_notifier_only_product_name]']  = __( 'YayMail', 'yaymail' );
			$shortcode['[yaymail_notifier_only_product_sku]']   = __( '1', 'yaymail' );
			$shortcode['[yaymail_notifier_only_product_image]'] = '<a href="' . esc_url( get_site_url() ) . '"> ' . esc_url( get_site_url() ) . ' </a>';
		}

		// Define shortcode from plugin addon
		$shortcode = apply_filters( 'yaymail_do_shortcode', $shortcode, $yaymail_informations, '' );

		$shortcode['[yaymail_items]']                         = $this->orderItems( array(), $sent_to_admin, 'sampleOrder', true );
		$shortcode['[yaymail_items_products_quantity_price]'] = $this->orderItems( array(), $sent_to_admin, 'sampleOrder', false );
		$shortcode['[yaymail_order_date]']                    = date_i18n( wc_date_format() );
		$shortcode['[yaymail_order_fee]']                     = 0;
		$shortcode['[yaymail_order_id]']                      = 1;
		$shortcode['[yaymail_order_link]']                    = '<a href="" style="color:' . esc_attr( $text_link_color ) . ';">' . esc_html__( 'Order', 'yaymail' ) . '</a>';
		$shortcode['[yaymail_order_link_string]']             = esc_url( get_home_url() );
		$shortcode['[yaymail_order_number]']                  = __( '1', 'yaymail' );
		$shortcode['[yaymail_order_refund]']                  = 0;
		$shortcode['[yaymail_order_sub_total]']               = wc_price( '18.00' );
		$shortcode['[yaymail_order_discount]']                = wc_price( '18.00' );
		$shortcode['[yaymail_order_total]']                   = wc_price( '18.00' );
		$shortcode['[yaymail_order_total_numbers]']           = '18.00';
		$shortcode['[yaymail_orders_count]']                  = '1';
		$shortcode['[yaymail_orders_count_double]']           = '2';

		// PAYMENTS
		$shortcode['[yaymail_order_payment_method]']     = __( 'Direct bank transfer', 'yaymail' );
		$shortcode['[yaymail_order_payment_url]']        = '<a href="">' . esc_html__( 'Payment page', 'yaymail' ) . '</a>';
		$shortcode['[yaymail_order_payment_url_string]'] = '';
		$shortcode['[yaymail_payment_instruction]']      = __( 'Payment Instructions', 'yaymail' );
		$shortcode['[yaymail_payment_method]']           = __( 'Check payments', 'yaymail' );
		$shortcode['[yaymail_transaction_id]']           = 1;

		// SHIPPINGS
		$shortcode['[yaymail_order_shipping]']      = __( '333', 'yaymail' );
		$shortcode['[yaymail_shipping_address]']    = $shipping_address;
		$shortcode['[yaymail_shipping_address_1]']  = __( '755 E North Grove Rd', 'yaymail' );
		$shortcode['[yaymail_shipping_address_2]']  = __( '755 E North Grove Rd', 'yaymail' );
		$shortcode['[yaymail_shipping_city]']       = __( 'Mayville, Michigan', 'yaymail' );
		$shortcode['[yaymail_shipping_company]']    = __( 'YayCommerce', 'yaymail' );
		$shortcode['[yaymail_shipping_country]']    = '';
		$shortcode['[yaymail_shipping_first_name]'] = __( 'John', 'yaymail' );
		$shortcode['[yaymail_shipping_last_name]']  = __( 'Doe', 'yaymail' );
		$shortcode['[yaymail_shipping_method]']     = '';
		$shortcode['[yaymail_shipping_postcode]']   = __( '48744', 'yaymail' );
		$shortcode['[yaymail_shipping_state]']      = __( 'Random', 'yaymail' );
		$shortcode['[yaymail_shipping_phone]']      = __( '(910) 529-1147', 'yaymail' );

		// BILLING
		$shortcode['[yaymail_billing_address]']    = $billing_address;
		$shortcode['[yaymail_billing_address_1]']  = __( '7400 Edwards Rd', 'yaymail' );
		$shortcode['[yaymail_billing_address_2]']  = __( '7400 Edwards Rd', 'yaymail' );
		$shortcode['[yaymail_billing_city]']       = __( 'Edwards Rd', 'yaymail' );
		$shortcode['[yaymail_billing_company]']    = __( 'YayCommerce', 'yaymail' );
		$shortcode['[yaymail_billing_country]']    = '';
		$shortcode['[yaymail_billing_email]']      = __( 'johndoe@gmail.com', 'yaymail' );
		$shortcode['[yaymail_billing_first_name]'] = __( 'John', 'yaymail' );
		$shortcode['[yaymail_billing_last_name]']  = __( 'Doe', 'yaymail' );
		$shortcode['[yaymail_billing_phone]']      = __( '(910) 529-1147', 'yaymail' );
		$shortcode['[yaymail_billing_postcode]']   = __( '48744', 'yaymail' );
		$shortcode['[yaymail_billing_state]']      = __( 'Random', 'yaymail' );

		// RESET PASSWORD:
		$shortcode['[yaymail_password_reset_url]']        = '<a style="color:' . esc_attr( $text_link_color ) . ';" href="">' . esc_html__( 'Click here to reset your password', 'woocommerce' ) . '</a>';
		$shortcode['[yaymail_password_reset_url_string]'] = esc_url( get_home_url() ) . '/my-account/lost-password/?login';
		$shortcode['[yaymail_wp_password_reset_url]']     = esc_url( get_home_url() ) . '/my-account/lost-password/?login';

		// NEW USERS:
		$shortcode['[yaymail_user_new_password]']       = __( 'G(UAM1(eIX#G', 'yaymail' );
		$shortcode['[yaymail_set_password_url_string]'] = esc_url( get_home_url() ) . '/my-account/set-password/';

		$shortcode['[yaymail_user_activation_link]'] = '';

		// GENERALS
		$shortcode['[yaymail_customer_note]']          = __( 'note', 'yaymail' );
		$shortcode['[yaymail_customer_notes]']         = __( 'notes', 'yaymail' );
		$shortcode['[yaymail_customer_provided_note]'] = __( 'provided note', 'yaymail' );
		$shortcode['[yaymail_site_name]']              = esc_html( get_bloginfo( 'name' ) );
		$shortcode['[yaymail_site_url]']               = '<a href="' . esc_url( get_home_url() ) . '"> ' . esc_url( get_home_url() ) . ' </a>';
		$shortcode['[yaymail_site_url_string]']        = esc_url( get_home_url() );
		$shortcode['[yaymail_additional_content]']     = __( 'Additional content', 'yaymail' );
		$shortcode['[yaymail_user_email]']             = $current_user->data->user_email;
		$shortcode['[yaymail_user_id]']                = $user_id;
		$shortcode['[yaymail_customer_username]']      = $current_user->data->user_login;
		$shortcode['[yaymail_customer_roles]']         = implode( ', ', $current_user->roles );

		$shortcode['[yaymail_customer_name]']            = get_user_meta( $current_user->data->ID, 'first_name', true ) . ' ' . get_user_meta( $current_user->data->ID, 'last_name', true );
		$shortcode['[yaymail_view_order_url]']           = '';
		$shortcode['[yaymail_view_order_url_string]']    = '';
		$shortcode['[yaymail_billing_shipping_address]'] = $this->billingShippingAddress( '', $this->order ); // done

		$shortcode['[yaymail_billing_shipping_address_title]']   = $this->billingShippingAddressTitle( '', $this->order ); // done
		$shortcode['[yaymail_billing_shipping_address_content]'] = $this->billingShippingAddressContent( '', $this->order ); // done
		$shortcode['[yaymail_check_billing_shipping_address]']   = $this->checkBillingShippingAddress( '', $this->order ); // done
		$shortcode['[yaymail_order_status]']                     = __( 'sample status', 'yaymail' ); // done
		$shortcode['[yaymail_order_status_from]']                = __( 'sample status', 'yaymail' ); // done

		$shortcode['[yaymail_domain]']                  = parse_url( esc_url( get_site_url() ) )['host'];
		$shortcode['[yaymail_user_account_url]']        = '<a style="color:' . esc_attr( $text_link_color ) . '; font-weight: normal; text-decoration: underline;" href="' . wc_get_page_permalink( 'myaccount' ) . '">' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '</a>';
		$shortcode['[yaymail_user_account_url_string]'] = wc_get_page_permalink( 'myaccount' );
		$shortcode['[yaymail_order_coupon_codes]']      = __( 'yaymail_code', 'yaymail' );

		$shortcode['[yaymail_order_meta:_wc_shipment_tracking_items]'] = $this->orderMetaWcShipmentTrackingItems( '', $this->order );
		$shortcode['[yaymail_order_meta:shipment_carriers]']           = $this->orderMetaWcShipmentTrackingCarriers( '', $this->order );
		$shortcode['[yaymail_order_meta:tracking_numbers]']            = $this->orderMetaWcShipmentTrackingNumbers( '', $this->order );
		$shortcode['[yaymail_shipment_tracking_title]']                = $this->shipmentTrackingTitle( '', $this->order );
		$shortcode['[yaymail_shipment_tracking_items]']                = $this->shipmentTrackingItems( '', $this->order );
		$shortcode['[yaymail_order_meta:shipment_tracking_link]']      = $this->orderMetaWcShipmentTrackingLink( '', $this->order );

		if ( class_exists( 'PH_Shipment_Tracking_API_Manager' ) ) {
			$shortcode['[yaymail_ph_shipment_tracking]'] = $this->phShipmentTracking( '', $this->order );
		}

		if ( class_exists( 'WC_Connect_Loader' ) ) {
			$shortcode['[yaymail_shipping_tax_shipment_tracking]'] = $this->shippingTaxShipmentTracking( '', $this->order );
		}

		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'wc-chitchats-shipping-pro/wc-chitchats-shipping-pro.php' ) ) {
			$shortcode['[yaymail_chitchats_shipping_shipments]'] = $this->chitchatsShippingShipments( '', $this->order );
		}

		if ( class_exists( 'FooEvents' ) ) {
			$shortcode['[yaymail_fooevents_ticket_details]'] = $this->fooeventsTicketDetails( '', $this->order );
		}

		if ( class_exists( 'YITH_Barcode' ) ) {
			$shortcode['[yaymail_yith_barcode]'] = $this->yithBarcode( '', $this->order );
		}

		if ( class_exists( 'WC_Software' ) ) {
			$shortcode['[yaymail_software_add_on]'] = $this->softwareAddOn( '', $this->order );
		}

		if ( class_exists( 'WC_Admin_Custom_Order_Fields' ) ) {
			$shortcode['[yaymail_order_meta:_wc_additional_order_details]'] = $this->orderMetaWcAdditionalOrderDetails( '', $this->order );
		}
		if ( class_exists( 'EventON' ) ) {
			$shortcode['[yaymail_order_meta:_event_on_list]'] = $this->eventOnList( '', $this->order );
		}
		if ( class_exists( 'YITH_WooCommerce_Order_Tracking_Premium' ) ) {
			$shortcode['[yaymail_order_carrier_name]']  = $this->orderCarrierName( '', $this->order );
			$shortcode['[yaymail_order_pickup_date]']   = $this->orderPickupDate( '', $this->order );
			$shortcode['[yaymail_order_track_code]']    = $this->orderTrackCode( '', $this->order );
			$shortcode['[yaymail_order_tracking_link]'] = $this->orderTrackingLink( '', $this->order );
		}
		if ( class_exists( 'WC_Pagarme' ) ) {
			$shortcode['[yaymail_pagarme_banking_ticket_url]']       = '<a style="color:' . esc_attr( $text_link_color ) . '; font-weight: normal; text-decoration: underline;" href="#">' . esc_html__( 'Pay the banking ticket', 'woocommerce-pagarme' ) . '</a>';
			$shortcode['[yaymail_pagarme_credit_card_brand]']        = 'yaymail_pagarme_credit_card_brand';
			$shortcode['[yaymail_pagarme_credit_card_installments]'] = 'yaymail_pagarme_credit_card_installments';
		}

		// ADDITIONAL ORDER META:
		$order         = CustomPostType::getListOrders();
		$order_metaArr = get_post_meta( isset( $order[0]['id'] ) ? $order[0]['id'] : '' );
		if ( is_array( $order_metaArr ) && count( $order_metaArr ) > 0 ) {
			foreach ( $order_metaArr as $k => $v ) {
				$nameField    = str_replace( ' ', '_', trim( $k ) );
				$nameShorcode = '[yaymail_post_meta:' . $nameField . ']';

				// when array $v has tow value ???
				if ( is_array( $v ) && count( $v ) > 0 ) {
					$shortcode[ $nameShorcode ] = trim( $v[0] );
				} else {
					$shortcode[ $nameShorcode ] = trim( $v );
				}
			}
		}

		$this->order_data = $shortcode;
	}

	public function ordetItemTables( $order, $default_args ) {
		$postID           = CustomPostType::postIDByTemplate( $this->template );
		$text_link_color  = get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) ? get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) : '#7f54b3';
		$items            = $order->get_items();
		$path             = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/email-order-items.php';
		$yaymail_settings = get_option( 'yaymail_settings' );

		$show_product_image            = isset( $yaymail_settings['product_image'] ) ? $yaymail_settings['product_image'] : 0;
		$show_product_sku              = isset( $yaymail_settings['product_sku'] ) ? $yaymail_settings['product_sku'] : 0;
		$show_product_des              = isset( $yaymail_settings['product_des'] ) ? $yaymail_settings['product_des'] : 0;
		$default_args['image_size'][0] = isset( $yaymail_settings['image_width'] ) ? $yaymail_settings['image_width'] : 32;
		$default_args['image_size'][1] = isset( $yaymail_settings['image_height'] ) ? $yaymail_settings['image_height'] : 32;
		$default_args['image_size'][2] = isset( $yaymail_settings['image_size'] ) ? $yaymail_settings['image_size'] : 'thumbnail';

		$args = array(
			'order'                         => $order,
			'items'                         => $order->get_items(),
			'show_download_links'           => $order->is_download_permitted() && ! $default_args['sent_to_admin'],
			'show_sku'                      => $show_product_sku,
			'show_des'                      => $show_product_des,
			'show_purchase_note'            => $order->is_paid() && ! $default_args['sent_to_admin'],
			'show_image'                    => $show_product_image,
			'image_size'                    => $default_args['image_size'],
			'plain_text'                    => $default_args['plain_text'],
			'sent_to_admin'                 => $default_args['sent_to_admin'],
			'order_item_table_border_color' => isset( $yaymail_settings['background_color_table_items'] ) ? $yaymail_settings['background_color_table_items'] : '#dddddd',
			'text_link_color'               => $text_link_color,
		);
		include $path;
	}
	public function itemsBorder( $atts, $order, $sent_to_admin = '' ) {
		if ( null === $order ) {
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/sampleOrder/email-order-details-border.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		} else {
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/email-order-details-border.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}

	}

	/* Link Downloadable Product - start */
	public function itemsDownloadableTitle( $atts, $order, $sent_to_admin = '', $items = array() ) {
		if ( null !== $order ) {
			$items     = $order->get_items();
			$downloads = $order->get_downloadable_items();
		}
		ob_start();
		$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/email-order-item-download-title.php';
		include $path;
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}
	public function itemsDownloadableProduct( $atts, $order, $sent_to_admin = '', $items = array() ) {
		if ( null === $order ) {
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/sampleOrder/email-order-item-download.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		} else {
			if ( class_exists( 'WC_Subscription' ) ) {
				add_filter(
					'woocommerce_order_is_download_permitted',
					function( $value ) {
						return true;
					},
					100
				);
			}
			$items = $order->get_items();
			ob_start();
			$downloads = $order->get_downloadable_items();
			$path      = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/email-order-item-download.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}
	}

	public function dpdchTrackingNumber( $atts, $order, $sent_to_admin = '' ) {
		if ( null === $order ) {
			return '[yaymail_dpdch_tracking_number]';
		} else {
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/dpdch_tracking_number.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}

	}
	/* Order items border - start */
	public function itemsBorderBefore( $atts, $order, $sent_to_admin = '' ) {
		if ( null === $order ) {
			return '';
		} else {
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/email-order-details-border-before.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}
	}

	public function itemsBorderAfter( $atts, $order, $sent_to_admin = '' ) {
		if ( null === $order ) {
			return '';
		} else {
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/email-order-details-border-after.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}
	}

	public function itemsBorderTitle( $atts, $order, $sent_to_admin = '' ) {
		if ( null === $order ) {
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/sampleOrder/email-order-details-border-title.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		} else {
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/email-order-details-border-title.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}
	}

	public function itemsBorderContent( $atts, $order, $sent_to_admin = '' ) {
		if ( null === $order ) {
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/sampleOrder/email-order-details-border-content.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		} else {
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/email-order-details-border-content.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}
	}
	/* Order items border - end */

	public function billingShippingAddress( $atts, $order ) {
		$postID          = CustomPostType::postIDByTemplate( $this->template );
		$text_link_color = get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) ? get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) : '#7f54b3';
		if ( null !== $order ) {
			$shipping_address = class_exists( 'Flexible_Checkout_Fields_Disaplay_Options' ) ? $this->shipping_address : $order->get_formatted_shipping_address();
			$billing_address  = class_exists( 'Flexible_Checkout_Fields_Disaplay_Options' ) ? $this->billing_address : $order->get_formatted_billing_address();
			if ( $order->get_billing_phone() ) {
				$billing_address .= "<br/> <a href='tel:" . esc_html( $order->get_billing_phone() ) . "' style='color:" . esc_attr( $text_link_color ) . "; font-weight: normal; text-decoration: underline;'>" . esc_html( $order->get_billing_phone() ) . '</a>';
			}
			if ( $order->get_billing_email() ) {
				$billing_address .= "<br/><a href='mailto:" . esc_html( $order->get_billing_email() ) . "' style='color:" . esc_attr( $text_link_color ) . ";font-weight: normal; text-decoration: underline;'>" . esc_html( $order->get_billing_email() ) . '</a>';
			}
		} else {
			$billing_address  = "John Doe<br/>YayCommerce<br/>7400 Edwards Rd<br/>Edwards Rd<br/><a href='tel:+18587433828' style='color: " . esc_attr( $text_link_color ) . "; font-weight: normal; text-decoration: underline;'>(910) 529-1147</a><br/>";
			$shipping_address = "John Doe<br/>YayCommerce<br/>755 E North Grove Rd<br/>Mayville, Michigan<br/><a href='tel:+18587433828' style='color: " . esc_attr( $text_link_color ) . "; font-weight: normal; text-decoration: underline;'>(910) 529-1147</a><br/>";
		}
		ob_start();
		$path  = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/email-billing-shipping-address.php';
		$order = $this->order;
		include $path;
		$html = ob_get_contents();
		ob_end_clean();
		return $html;

	}

	/* Billing Shipping Address - start */
	public function billingShippingAddressTitle( $atts, $order ) {
		$postID          = CustomPostType::postIDByTemplate( $this->template );
		$text_link_color = get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) ? get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) : '#7f54b3';
		if ( null !== $order ) {
			$shipping_address = class_exists( 'Flexible_Checkout_Fields_Disaplay_Options' ) ? $this->shipping_address : $order->get_formatted_shipping_address();
			$billing_address  = class_exists( 'Flexible_Checkout_Fields_Disaplay_Options' ) ? $this->billing_address : $order->get_formatted_billing_address();
		} else {
			$billing_address  = "John Doe<br/>YayCommerce<br/>7400 Edwards Rd<br/>Edwards Rd<br/><a href='tel:+18587433828' style='color: " . esc_attr( $text_link_color ) . "; font-weight: normal; text-decoration: underline;'>(910) 529-1147</a><br/>";
			$shipping_address = "John Doe<br/>YayCommerce<br/>755 E North Grove Rd<br/>Mayville, Michigan<br/><a href='tel:+18587433828' style='color: " . esc_attr( $text_link_color ) . "; font-weight: normal; text-decoration: underline;'>(910) 529-1147</a><br/>";
		}
		ob_start();
		$path  = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/email-billing-shipping-address-title.php';
		$order = $this->order;
		include $path;
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}

	public function checkBillingShippingAddress( $atts, $order_id ) {
		$isShippingAddress = false;
		$isBillingAddress  = false;

		if ( ! empty( $billing_address ) ) {
			$isBillingAddress = true;
		}
		if ( ! empty( $shipping_address ) ) {
			$isShippingAddress = true;
		}

		$args = array(
			'isShippingAddress' => $isShippingAddress,
			'isBillingAddress'  => $isBillingAddress,
		);

		return 'Checking_here';
	}

	public function billingShippingAddressContent( $atts, $order ) {
		$postID          = CustomPostType::postIDByTemplate( $this->template );
		$text_link_color = get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) ? get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) : '#7f54b3';
		if ( null !== $order ) {
			$shipping_address = class_exists( 'Flexible_Checkout_Fields_Disaplay_Options' ) ? $this->shipping_address : $order->get_formatted_shipping_address();
			$billing_address  = class_exists( 'Flexible_Checkout_Fields_Disaplay_Options' ) ? $this->billing_address : $order->get_formatted_billing_address();
			if ( $order->get_billing_phone() ) {
				$billing_address .= "<br/> <a href='tel:" . esc_html( $order->get_billing_phone() ) . "' style='color:" . esc_attr( $text_link_color ) . "; font-weight: normal; text-decoration: underline;'>" . esc_html( $order->get_billing_phone() ) . '</a>';
			}
			if ( $order->get_billing_email() ) {
				$billing_address .= "<br/><a href='mailto:" . esc_html( $order->get_billing_email() ) . "' style='color:" . esc_attr( $text_link_color ) . ";font-weight: normal; text-decoration: underline;'>" . esc_html( $order->get_billing_email() ) . '</a>';
			}
			if ( method_exists( $order, 'get_shipping_phone' ) && ! empty( $order->get_shipping_phone() ) ) {
				if ( ! str_contains( $shipping_address, $order->get_shipping_phone() ) ) {
					$shipping_address .= "<br/> <a href='tel:" . esc_html( $order->get_shipping_phone() ) . "' style='color:" . esc_attr( $text_link_color ) . "; font-weight: normal; text-decoration: underline;'>" . esc_html( $order->get_shipping_phone() ) . '</a>';
				}
			}
			if ( metadata_exists( 'post', $order->get_id(), '_shipping_email' ) ) {
				if ( ! str_contains( $shipping_address, get_post_meta( $order->get_id(), '_shipping_email', true ) ) ) {
					$shipping_address .= "<br/><a href='mailto:" . esc_html( get_post_meta( $order->get_id(), '_shipping_email', true ) ) . "' style='color:" . esc_attr( $text_link_color ) . ";font-weight: normal; text-decoration: underline;'>" . esc_html( get_post_meta( $order->get_id(), '_shipping_email', true ) ) . '</a>';
				}
			}
		} else {
			$billing_address  = "John Doe<br/>YayCommerce<br/>7400 Edwards Rd<br/>Edwards Rd<br/><a href='tel:+18587433828' style='color: " . esc_attr( $text_link_color ) . "; font-weight: normal; text-decoration: underline;'>(910) 529-1147</a><br/>";
			$shipping_address = "John Doe<br/>YayCommerce<br/>755 E North Grove Rd<br/>Mayville, Michigan<br/><a href='tel:+18587433828' style='color: " . esc_attr( $text_link_color ) . "; font-weight: normal; text-decoration: underline;'>(910) 529-1147</a><br/>";
		}
		ob_start();
		$path  = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/email-billing-shipping-address-content.php';
		$order = $this->order;
		include $path;
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}
	/* Billing Shipping Address - end */

	public function orderItems( $items, $sent_to_admin = '', $checkOrder = '', $is_display = true ) {
		if ( 'sampleOrder' === $checkOrder ) {
			ob_start();
			$path  = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/sampleOrder/email-order-details.php';
			$order = $this->order;
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;

		} else {
			ob_start();
			$path  = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/email-order-details.php';
			$order = $this->order;
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}

	}
	public function getOrderCustomerNotes( $customerNotes ) {
		ob_start();
		foreach ( $customerNotes as $customerNote ) {
			?>
				<?php echo wp_kses_post( wpautop( wptexturize( make_clickable( $customerNote->comment_content ) ) ) ); ?>
			<?php
		}
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}

	/** Admin custom fields */
	public function orderMetaWcAdditionalOrderDetails( $atts, $order, $sent_to_admin = '' ) {
		if ( ! $order ) {
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/sampleOrder/additional-order-details.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
		} else {
			$order_id            = $order->get_id();
			$order_fields        = array();
			$order               = $order_id ? wc_get_order( $order_id ) : null;
			$custom_order_fields = get_option( 'wc_admin_custom_order_fields' );
			if ( ! is_array( $custom_order_fields ) ) {
				$custom_order_fields = array();
			}
			foreach ( $custom_order_fields as $key => $field ) {
				$order_field = new \WC_Custom_Order_Field( $key, $field );
				$have_value  = false;
				if ( $order instanceof \WC_Order ) {
					$set_value = false;
					$value     = '';
					if ( metadata_exists( 'post', $order_id, $order_field->get_meta_key() ) ) {
						$set_value = true;
						$value     = $order->get_meta( $order_field->get_meta_key() );
					}
					if ( $set_value ) {
						$order_field->set_value( $value );
						$have_value = true;
					}
				}
				if ( $have_value ) {
					$order_fields[ $key ] = $order_field;
				}
			}
			if ( ! empty( $order_fields ) ) {
				ob_start();
				$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/additional-order-details.php';
				include $path;
				$html = ob_get_contents();
				ob_end_clean();
			} else {
				$html = '';
			}
		}
		return $html;
	}
	/** Event On */
	public function eventOnList( $atts, $order, $sent_to_admin = '' ) {
		ob_start();
		$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/event-on-list.php';
		include $path;
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}

	/* Woo Shipment Tracking - Start */
	public function orderMetaWcShipmentTrackingItems( $atts, $order, $sent_to_admin = '' ) {
		ob_start();
		$order = $this->order;
		if ( ( ! class_exists( 'WC_Shipment_Tracking_Actions' ) && ! class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) && ! class_exists( 'AST_PRO_Install' ) ) ) {
			ob_end_clean();
			return null;
		}
		$setClassAvtive = null;

		if ( ! $order ) {
			if ( class_exists( 'WC_Shipment_Tracking_Actions' ) && ! class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
				$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/sampleOrder/wc_shipment_tracking-info.php';
			}
			if ( class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
				$ast  = new \WC_Advanced_Shipment_Tracking_Actions();
				$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/sampleOrder/wc_advanced_shipment_tracking-info.php';
			}
			if ( class_exists( 'AST_PRO_Install' ) ) {
				$ast  = \AST_Pro_Actions::get_instance();
				$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/sampleOrder/ast_pro_shipment_tracking-info.php';
			}

			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}

		$order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
		if ( class_exists( 'WC_Shipment_Tracking_Actions' ) && ! class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
			$sta            = \WC_Shipment_Tracking_Actions::get_instance();
			$tracking_items = $sta->get_tracking_items( $order_id, true );
			if ( $tracking_items ) {
				$setClassAvtive = 'WC_Shipment_Tracking_Actions';
				$path           = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/wc_shipment_tracking-info.php';
				include $path;
				$html = ob_get_contents();
				ob_end_clean();
				return $html;
			} else {
				ob_end_clean();
				return null;
			}
		}
		if ( class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
			$ast            = new \WC_Advanced_Shipment_Tracking_Actions();
			$order_id       = $ast->get_formated_order_id( $order_id );
			$tracking_items = $ast->get_tracking_items( $order_id, true );
			if ( $tracking_items ) {
				$setClassAvtive = 'WC_Advanced_Shipment_Tracking_Actions';
				$path           = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/wc_advanced_shipment_tracking-info.php';
				include $path;
				$html = ob_get_contents();
				ob_end_clean();
				return $html;
			} else {
				ob_end_clean();
				return null;
			}
		}

		if ( class_exists( 'AST_PRO_Install' ) ) {
			$ast            = \AST_Pro_Actions::get_instance();
			$order_id       = $ast->get_formated_order_id( $order_id );
			$tracking_items = $ast->get_tracking_items( $order_id, true );
			if ( $tracking_items ) {
				$setClassAvtive = 'AST_Pro_Actions';
				$path           = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/ast_pro_shipment_tracking-info.php';
				include $path;
				$html = ob_get_contents();
				ob_end_clean();
				return $html;
			} else {
				ob_end_clean();
				return null;
			}
		}

	}

	public function orderMetaWcShipmentTrackingCarriers( $atts, $order, $sent_to_admin = '' ) {
		if ( ! $order ) {
			return 'yaymail provider';
		}
		$order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
		$html     = '';
		if ( class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
			$ast            = \WC_Advanced_Shipment_Tracking_Actions::get_instance();
			$order_id       = $ast->get_formated_order_id( $order_id );
			$tracking_items = $ast->get_tracking_items( $order_id, true );
			foreach ( $tracking_items as $key => $tracking_item ) {
				if ( '' != $tracking_item['formatted_tracking_provider'] ) {
					$ast_provider_title = apply_filters( 'ast_provider_title', esc_html( $tracking_item['formatted_tracking_provider'] ) );
				} else {
					$ast_provider_title = apply_filters( 'ast_provider_title', esc_html( $tracking_item['tracking_provider'] ) );
				}
				if ( 0 !== $key ) {
					$html .= ', ';
				}
				$html .= $ast_provider_title;
			}
		}
		if ( class_exists( 'WC_Shipment_Tracking_Actions' ) && ! class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
			global $wpdb;
			$sta            = \WC_Shipment_Tracking_Actions::get_instance();
			$tracking_items = $sta->get_tracking_items( $order_id, true );
			foreach ( $tracking_items as $key => $tracking_item ) {
				$tracking_provider = isset( $tracking_item['tracking_provider'] ) ? $tracking_item['tracking_provider'] : $tracking_item['custom_tracking_provider'];
				if ( $tracking_provider ) {
					$tracking_provider = apply_filters( 'convert_provider_name_to_slug', $tracking_provider );

					$results = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woo_shippment_provider WHERE ts_slug = %s", $tracking_provider ) );

					$provider_name = apply_filters( 'get_ast_provider_name', $tracking_provider, $results );
				} else {
					$provider_name = $tracking_item['custom_tracking_provider'];
				}
				if ( 0 !== $key ) {
					$html .= ', ';
				}
				$html .= $provider_name;

			}
		}
		if ( class_exists( 'AST_PRO_Install' ) ) {
			$ast            = \AST_Pro_Actions::get_instance();
			$order_id       = $ast->get_formated_order_id( $order_id );
			$tracking_items = $ast->get_tracking_items( $order_id, true );
			foreach ( $tracking_items as $key => $tracking_item ) {
				if ( '' !== $tracking_item['formatted_tracking_provider'] ) {
					$provider_name = $tracking_item['formatted_tracking_provider'];
				} else {
					$provider_name = $tracking_item['tracking_provider'];
				}
				if ( 0 !== $key ) {
					$html .= ', ';
				}
				$html .= $provider_name;
			}
		}
		if ( '' === $html ) {
			return '[yaymail_order_meta:shipment_carriers]';
		}
		return wp_kses_post( $html );

	}

	public function orderMetaWcShipmentTrackingLink( $atts, $order, $sent_to_admin = '' ) {
		if ( ! $order ) {
			return 'https://shipment_tracking_link.com';
		}
		$order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
		$html     = '';
		if ( class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
			$ast            = \WC_Advanced_Shipment_Tracking_Actions::get_instance();
			$order_id       = $ast->get_formated_order_id( $order_id );
			$tracking_items = $ast->get_tracking_items( $order_id, true );
			foreach ( $tracking_items as $key => $tracking_item ) {

				$tracking_link = isset( $tracking_item['ast_tracking_link'] ) ? $tracking_item['ast_tracking_link'] : '';

				if ( 0 !== $key ) {
					$html .= ', ';
				}
				$html .= $tracking_link;
			}
		}
		if ( class_exists( 'WC_Shipment_Tracking_Actions' ) && ! class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
			global $wpdb;
			$sta            = \WC_Shipment_Tracking_Actions::get_instance();
			$tracking_items = $sta->get_tracking_items( $order_id, true );
			foreach ( $tracking_items as $key => $tracking_item ) {

				$tracking_link = isset( $tracking_item['formatted_tracking_link'] ) ? $tracking_item['formatted_tracking_link'] : '';

				if ( 0 !== $key ) {
					$html .= ', ';
				}
				$html .= $tracking_link;

			}
		}
		if ( class_exists( 'AST_PRO_Install' ) ) {
			$ast            = \AST_Pro_Actions::get_instance();
			$order_id       = $ast->get_formated_order_id( $order_id );
			$tracking_items = $ast->get_tracking_items( $order_id, true );
			foreach ( $tracking_items as $key => $tracking_item ) {

				$tracking_link = isset( $tracking_item['ast_tracking_link'] ) ? $tracking_item['ast_tracking_link'] : '';

				if ( 0 !== $key ) {
					$html .= ', ';
				}
				$html .= $tracking_link;
			}
		}
		if ( '' === $html ) {
			return '[yaymail_order_meta:shipment_tracking_link]';
		}
		return wp_kses_post( $html );

	}
	public function orderMetaWcShipmentTrackingNumbers( $atts, $order, $sent_to_admin = '' ) {
		if ( ! $order ) {
			return 'yaymail provider';
		}
		$order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
		$html     = '';
		if ( class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
			$ast            = \WC_Advanced_Shipment_Tracking_Actions::get_instance();
			$order_id       = $ast->get_formated_order_id( $order_id );
			$tracking_items = $ast->get_tracking_items( $order_id, true );
			foreach ( $tracking_items as $key => $tracking_item ) {
				if ( isset( $tracking_item['tracking_number'] ) ) {
					$tracking_number = $tracking_item['tracking_number'];
					if ( 0 !== $key ) {
						$html .= ', ';
					}
				}
				$html .= $tracking_number;
			}
		}
		if ( class_exists( 'WC_Shipment_Tracking_Actions' ) && ! class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
			$sta            = \WC_Shipment_Tracking_Actions::get_instance();
			$tracking_items = $sta->get_tracking_items( $order_id, true );
			foreach ( $tracking_items as $key => $tracking_item ) {
				if ( isset( $tracking_item['tracking_number'] ) ) {
					$tracking_number = $tracking_item['tracking_number'];
					if ( 0 !== $key ) {
						$html .= ', ';
					}
				}
				$html .= $tracking_number;
			}
		}
		if ( class_exists( 'AST_PRO_Install' ) ) {
			$ast            = \AST_Pro_Actions::get_instance();
			$order_id       = $ast->get_formated_order_id( $order_id );
			$tracking_items = $ast->get_tracking_items( $order_id, true );
			foreach ( $tracking_items as $key => $tracking_item ) {
				if ( isset( $tracking_item['tracking_number'] ) ) {
					$tracking_number = $tracking_item['tracking_number'];
					if ( 0 !== $key ) {
						$html .= ', ';
					}
				}
				$html .= $tracking_number;
			}
		}
		if ( '' === $html ) {
			return '[yaymail_order_meta:tracking_numbers]';
		}
		return wp_kses_post( $html );

	}

	public function shipmentTrackingItems( $atts, $order, $sent_to_admin = '' ) {
		ob_start();
		$order = $this->order;
		if ( ( ! class_exists( 'AST_PRO_Install' ) ) ) {
			ob_end_clean();
			return null;
		}

		if ( ! $order ) {
			if ( class_exists( 'AST_PRO_Install' ) ) {
				$ast                          = \AST_Pro_Actions::get_instance();
				$shipped_settings             = \Ast_Customizer::get_instance();
				$display_shippment_item_price = $ast->get_checkbox_option_value_from_array( 'woocommerce_customer_shipped_order_settings', 'display_shippment_item_price', $shipped_settings->defaults['display_shippment_item_price'] );
				$display_product_images       = $ast->get_checkbox_option_value_from_array( 'woocommerce_customer_shipped_order_settings', 'display_product_images', $shipped_settings->defaults['display_product_images'] );
				$shipping_items_heading       = $ast->get_option_value_from_array( 'woocommerce_customer_shipped_order_settings', 'shipping_items_heading', $shipped_settings->defaults['shipping_items_heading'] );

				$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/sampleOrder/ast-pro-email-order-details.php';
			}
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}

		$order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
		if ( class_exists( 'AST_PRO_Install' ) ) {
			$ast              = \AST_Pro_Actions::get_instance();
			$shipped_settings = \Ast_Customizer::get_instance();
			$order_id         = $ast->get_formated_order_id( $order_id );
			$tracking_items   = $ast->get_tracking_items( $order_id, true );
			$plain_text       = '';
			if ( $tracking_items ) {
				$tpi_order                    = ast_pro()->ast_tpi->check_if_tpi_order( $tracking_items, $order );
				$display_shippment_item_price = $ast->get_checkbox_option_value_from_array( 'woocommerce_customer_shipped_order_settings', 'display_shippment_item_price', $shipped_settings->defaults['display_shippment_item_price'] );
				$display_product_images       = $ast->get_checkbox_option_value_from_array( 'woocommerce_customer_shipped_order_settings', 'display_product_images', $shipped_settings->defaults['display_product_images'] );
				$shipping_items_heading       = $ast->get_option_value_from_array( 'woocommerce_customer_shipped_order_settings', 'shipping_items_heading', $shipped_settings->defaults['shipping_items_heading'] );

				if ( $tpi_order ) {
					$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/ast-pro-tpi-email-order-details.php';
				} else {
					$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/ast-pro-email-order-details.php';
				}
				include $path;
				$html = ob_get_contents();
				ob_end_clean();
				return $html;
			} else {
				ob_end_clean();
				return null;
			}
		}
	}

	public function shipmentTrackingTitle( $atts, $order, $sent_to_admin = '' ) {
		ob_start();
		$order = $this->order;
		if ( ( ! class_exists( 'WC_Shipment_Tracking_Actions' ) && ! class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) && ! class_exists( 'AST_PRO_Install' ) ) ) {
			ob_end_clean();
			return null;
		}
		if ( class_exists( 'WC_Shipment_Tracking_Actions' ) && ! class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/wc_shipment_tracking-title.php';
		}
		if ( class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
			$ast  = \WC_Advanced_Shipment_Tracking_Actions::get_instance();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/wc_advanced_shipment_tracking-title.php';
		}
		if ( class_exists( 'AST_PRO_Install' ) ) {
			$ast            = \AST_Pro_Actions::get_instance();
			$order_id       = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
			$order_id       = $ast->get_formated_order_id( $order_id );
			$tracking_items = $ast->get_tracking_items( $order_id, true );

			if ( ! $order ) {
				$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/ast_pro_shipment_tracking-title.php';
			} else {
				if ( ! $tracking_items ) {
					ob_end_clean();
					return '';
				}
				$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/ast_pro_shipment_tracking-title.php';
			}
		}
		include $path;
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}


	public function phShipmentTracking( $atts, $order, $sent_to_admin = '' ) {
		if ( class_exists( 'PH_Shipment_Tracking_API_Manager' ) ) {
			$order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
			if ( ! class_exists( 'WF_Tracking_Admin' ) ) {
				include_once plugin_dir_url( __FILE__ ) . '/includes/class-wf-tracking-admin.php';
			}
			$tracking_admin_obj   = new \WF_Tracking_Admin();
			$shipment_source_data = $tracking_admin_obj->get_shipment_source_data( $order_id );
			if ( ! empty( $shipment_source_data['shipment_id_cs'] ) || ! empty( $shipment_source_data['shipping_service'] ) ) {
				ob_start();
				$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/ph_shipment_tracking_api_manager.php';
				include $path;
				$html = ob_get_contents();
				ob_end_clean();
				return $html;
			} else {
				ob_start();
				$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/sampleOrder/ph_shipment_tracking_api_manager.php';
				include $path;
				$html = ob_get_contents();
				ob_end_clean();
				return $html;
			}
		}
	}
	public function shippingTaxShipmentTracking( $atts, $order, $sent_to_admin = '' ) {
		if ( class_exists( 'WC_Connect_Loader' ) && isset( $order ) ) {
			$id     = \WC_Connect_Compatibility::instance()->get_order_id( $order );
			$labels = get_post_meta( (int) $id, 'wc_connect_labels', true );
			if ( empty( $labels ) ) {
				return;
			}
			foreach ( $labels as $label ) {
				$carrier         = $label['carrier_id'];
				$carrier_service = $this->get_service_schemas( $carrier );
				$carrier_label   = ( ! $carrier_service || empty( $carrier_service->carrier_name ) ) ? strtoupper( $carrier ) : $carrier_service->carrier_name;
				$tracking        = $label['tracking'];
				$error           = array_key_exists( 'error', $label );
				$refunded        = array_key_exists( 'refund', $label );

				// If the label has an error or is refunded, move to the next label.
				if ( $error || $refunded ) {
					continue;
				}

				if ( $plain_text ) {
					// Should look like '- USPS: 9405536897846173912345' in plain text mode.
					$markup .= '- ' . $carrier_label . ': ' . $tracking . "\n";
					continue;
				}

				$markup .= '<tr>';
				$markup .= '<td class="td" scope="col">' . esc_html( $carrier_label ) . '</td>';

				switch ( $carrier ) {
					case 'fedex':
						$tracking_url = 'https://www.fedex.com/apps/fedextrack/?action=track&tracknumbers=' . $tracking;
						break;
					case 'usps':
						$tracking_url = 'https://tools.usps.com/go/TrackConfirmAction.action?tLabels=' . $tracking;
						break;
					case 'ups':
						$tracking_url = 'https://www.ups.com/track?tracknum=' . $tracking;
						break;
					case 'dhlexpress':
						$tracking_url = 'https://www.dhl.com/en/express/tracking.html?AWB=' . $tracking . '&brand=DHL';
						break;
				}

				$markup .= '<td class="td" scope="col">';
				$markup .= '<a href="' . esc_url( $tracking_url ) . '" style="color: ' . esc_attr( $link_color ) . '">' . esc_html( $tracking ) . '</a>';
				$markup .= '</td>';
				$markup .= '</tr>';
			}

			// Abort if all labels are refunded.
			if ( empty( $markup ) ) {
				return;
			}
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/shipping_tax_shipment_tracking.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		} else {
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/sampleOrder/shipping_tax_shipment_tracking.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}
	}

	public function get_service_schemas( $carrier ) {
		$service_schemas = \WC_Connect_Options::get_option( 'services', null );
		if ( ! is_object( $service_schemas ) ) {
			return null;
		}

		foreach ( $service_schemas as $service_type => $service_type_service_schemas ) {
			$matches = wp_filter_object_list( $service_type_service_schemas, array( 'id' => $carrier ) );
			if ( $matches ) {
				return array_shift( $matches );
			}
		}
	}

	public function chitchatsShippingShipments( $atts, $order, $sent_to_admin = '' ) {
		if ( $order && $order->get_meta( 'wc-chitchats-shipping_shipments' ) ) {
			$shipments = $order->get_meta( 'wc-chitchats-shipping_shipments' );
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/chitchats_shipping_shipments.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		} else {
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/sampleOrder/chitchats_shipping_shipments.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}
	}
	/*  Woo Shipment Tracking - End */

	/*  Woocommerce Hook - End */

	public function woocommerceEmailOrderMeta( $args, $sent_to_admin = '', $checkOrder = '' ) {
		if ( 'sampleOrder' === $checkOrder ) {
			return '[woocommerce_email_order_meta]';
		} else {
			$order = $this->order;
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/wc-email-order-meta.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}
	}

	public function woocommerceEmailOrderDetails( $args, $sent_to_admin = '', $checkOrder = '' ) {
		if ( 'sampleOrder' === $checkOrder ) {
			return '[woocommerce_email_order_details]';
		} else {
			$order = $this->order;
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/wc-email-order-detail.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}
	}
	public function woocommerceEmailBeforeOrderTable( $args, $sent_to_admin = '', $checkOrder = '' ) {
		if ( 'sampleOrder' === $checkOrder ) {
			return '[woocommerce_email_before_order_table]';
		} else {
			$order = $this->order;
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/wc-email-before.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}
	}
	public function woocommerceEmailAfterOrderTable( $args, $sent_to_admin = '', $checkOrder = '' ) {
		if ( 'sampleOrder' === $checkOrder ) {
			return '[woocommerce_email_after_order_table]';
		} else {
			$order = $this->order;
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/wc-email-after.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}
	}
	public function woocommerceEmailSozlesmeler( $atts, $order, $sent_to_admin = '' ) {
		if ( function_exists( 'woocontracts_maile_ekle' ) ) {
			$order = $this->order;
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/wc-email-sozlesmeler.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		} else {
			return '[yaymail_woocommerce_email_sozlesmeler]';
		}

	}

	/** Yith tracking order */
	public function orderCarrierName( $atts, $order ) {
		if ( null !== $order ) {
			$data         = \YITH_Tracking_Data::get( $order );
			$carriers     = \Carriers::get_instance()->get_carrier_list();
			$carrier_id   = $data->get_carrier_id();
			$carrier_name = isset( $carriers[ $carrier_id ] ) ? $carriers[ $carrier_id ]['name'] : '';
			$html         = $carrier_name;
		} else {
			$html = get_option( 'ywot_carrier_default_name' );
		}
		return $html;
	}
	public function orderPickupDate( $atts, $order ) {
		if ( null !== $order ) {
			$data = \YITH_Tracking_Data::get( $order );
			$html = date_i18n( get_option( 'date_format' ), strtotime( $data->get_pickup_date() ) );
		} else {
			$html = date_i18n( get_option( 'date_format' ), strtotime( gmdate( 'm-d-y' ) ) );
		}
		return $html;
	}
	public function orderTrackCode( $atts, $order ) {
		if ( null !== $order ) {
			$data          = \YITH_Tracking_Data::get( $order );
			$tracking_code = $data->get_tracking_code();
			if ( strpos( $tracking_code, '{' ) !== false ) {
				preg_match_all( '/{(.*?)}/', $tracking_code, $words );
				$tracking_code = implode( ' ', $words[1] );
			}
			$html = $tracking_code;
		} else {
			$html = 'SAMPLE_TRACKING_CODE';
		}
		return $html;
	}
	public function orderTrackingLink( $atts, $order ) {
		$postID          = CustomPostType::postIDByTemplate( $this->template );
		$text_link_color = get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) ? get_post_meta( $postID, '_yaymail_email_textLinkColor_settings', true ) : '#7f54b3';
		if ( null !== $order ) {
			$html = '';
			$data = \YITH_Tracking_Data::get( $order );
			if ( $data->is_pickedup() ) {
				$order_tracking_code     = $data->get_tracking_code();
				$order_tracking_postcode = $data->get_tracking_postcode();
				$order_carrier_id        = $data->get_carrier_id();
				$carriers                = \Carriers::get_instance()->get_carrier_list();
				if ( ! isset( $carriers[ $order_carrier_id ] ) ) {
					return '';
				}

				$carrier_object = $carriers[ $order_carrier_id ];

				// Check if tracking code is single or multiple
				if ( strpos( $order_tracking_code, '{' ) !== false ) {
					$order_track_url = $carrier_object['track_url'];

					preg_match_all( '/{(.*?)}/', $order_tracking_code, $words );
					$length_word = count( $words[1] );
					for ( $i = 0; $i < $length_word; $i++ ) {
						$order_track_url = str_replace( '[TRACK_CODE][' . $i . ']', $words[1][ $i ], $order_track_url );
					}
				} else {

					$text            = array( '[TRACK_CODE]', '[TRACK_POSTCODE]' );
					$codes           = array( $order_tracking_code, $order_tracking_postcode );
					$order_track_url = str_replace( $text, $codes, $carrier_object['track_url'] );
				}

				if ( strpos( $order_track_url, '[TRACK_YEAR]' ) !== false || strpos( $order_track_url, '[TRACK_MONTH]' ) !== false || strpos( $order_track_url, '[TRACK_DAY]' ) !== false ) {
					$date            = $data->get_pickup_date();
					$array_date      = explode( '-', $date );
					$order_track_url = str_replace( '[TRACK_YEAR]', $array_date[0], $order_track_url );
					$order_track_url = str_replace( '[TRACK_MONTH]', $array_date[1], $order_track_url );
					$order_track_url = str_replace( '[TRACK_DAY]', $array_date[2], $order_track_url );
				}
				$html = "<a style='color: " . esc_attr( $text_link_color ) . "' href='" . esc_url( $order_track_url ) . "'>" . __( 'Live track your order', 'yith-woocommerce-order-tracking' ) . '</a>';
			}
		} else {
			$html = "<a style='color: " . esc_attr( $text_link_color ) . "' href='#'>" . __( 'Live track your order', 'yith-woocommerce-order-tracking' ) . '</a>';
		}
		return $html;
	}

	public function getEmailHeading( $args, $order, $sent_to_admin ) {
		if ( isset( $args['email_heading'] ) && ! empty( $args ) ) {
			$email_heading = $args['email_heading'];
		} else {
			if ( isset( $order ) ) {
				$orderID       = $order->get_id();
				$email_heading = 'Order Refunded: ' . $order->get_id();
			} else {
				$email_heading = 'Order Refunded: 1';
			}
		}
		return $email_heading;
	}

	public function orderCouponCodes( $order ) {
		if ( isset( $order ) && method_exists( $order, 'get_coupon_codes' ) && ! empty( $order->get_coupon_codes() ) ) {
			$coupon_codes = $order->get_coupon_codes();
			ob_start();
			foreach ( $coupon_codes as $key => $value ) {
				?>
					<?php echo wp_kses_post( $value ); ?>
				<?php
			}
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}
	}


	public function softwareAddOn( $atts, $order, $sent_to_admin = '' ) {
		global $wpdb;
		if ( ! empty( $order ) ) {
			$order_id = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
			$keys     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->wc_software_licenses} WHERE order_id = %s", $order_id ) );

			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/software_add_on.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		} else {
			return '';
		}

	}

	public function fooeventsTicketDetails( $atts, $order, $sent_to_admin = '' ) {
		if ( ! empty( $order ) ) {

			$WooCommerceEventsOrderTickets = get_post_meta( $order->get_id(), 'WooCommerceEventsOrderTickets', true );
			$WooCommerceEventsOrderTickets = $this->process_order_tickets_for_display( $WooCommerceEventsOrderTickets );
			if ( ! empty( $WooCommerceEventsOrderTickets ) ) {
				ob_start();
				$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/fooevents_ticket_details.php';
				include $path;
				$html = ob_get_contents();
				ob_end_clean();
				return $html;
			} else {
				return '[yaymail_fooevents_ticket_details]';
			}
		} else {
				return '[yaymail_fooevents_ticket_details]';
		}

	}

	public function process_order_tickets_for_display( $woocommerce_events_order_tickets ) {

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {

			require_once ABSPATH . '/wp-admin/includes/plugin.php';

		}
		$config = new \FooEvents_Config();
		require_once $config->class_path . 'class-fooevents-zoom-api-helper.php';
		$zoom_api_helper = new \FooEvents_Zoom_API_Helper( $config );

		$processed_event_tickets = array();

		foreach ( $woocommerce_events_order_tickets as $event_tickets ) {

			$x = 0;
			foreach ( $event_tickets as $ticket ) {

				$event = get_post( $ticket['WooCommerceEventsProductID'] );

				if ( empty( $processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ] ) ) {

					$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ] = array();

					$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['WooCommerceEventsProductID'] = $ticket['WooCommerceEventsProductID'];
					$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['WooCommerceEventsName']      = $event->post_title;
					$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['WooCommerceEventsURL']       = get_permalink( $event->ID );
					$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['WooCommerceEventsType']      = get_post_meta( $event->ID, 'WooCommerceEventsType', true );

					$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['WooCommerceEventsDate']      = get_post_meta( $event->ID, 'WooCommerceEventsDate', true );
					$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['WooCommerceEventsStartTime'] = get_post_meta( $event->ID, 'WooCommerceEventsHour', true ) . ':' . get_post_meta( $event->ID, 'WooCommerceEventsMinutes', true ) . ' ' . get_post_meta( $event->ID, 'WooCommerceEventsPeriod', true );
					$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['WooCommerceEventsEndTime']   = get_post_meta( $event->ID, 'WooCommerceEventsHourEnd', true ) . ':' . get_post_meta( $event->ID, 'WooCommerceEventsMinutesEnd', true ) . ' ' . get_post_meta( $event->ID, 'WooCommerceEventsEndPeriod', true );

					$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['WooCommerceEventsLocation']       = get_post_meta( $event->ID, 'WooCommerceEventsLocation', true );
					$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['WooCommerceEventsGPS']            = get_post_meta( $event->ID, 'WooCommerceEventsGPS', true );
					$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['WooCommerceEventsSupportContact'] = get_post_meta( $event->ID, 'WooCommerceEventsSupportContact', true );
					$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['WooCommerceEventsEmail']          = get_post_meta( $event->ID, 'WooCommerceEventsEmail', true );

					if ( empty( $ticket['WooCommerceEventsBookingOptions'] ) ) {

						$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['WooCommerceEventsZoomText'] = $zoom_api_helper->get_ticket_text( array( 'WooCommerceEventsProductID' => $event->ID ), 'admin' );

					}
				}

				if ( ! empty( $ticket['WooCommerceEventsVariations'] ) ) {

					$ticket_vars = array();
					foreach ( $ticket['WooCommerceEventsVariations'] as $variation_name => $variation_value ) {

						$variation_name_output = str_replace( 'attribute_', '', $variation_name );
						$variation_name_output = str_replace( 'pa_', '', $variation_name_output );
						$variation_name_output = str_replace( '_', ' ', $variation_name_output );
						$variation_name_output = str_replace( '-', ' ', $variation_name_output );
						$variation_name_output = str_replace( 'Pa_', '', $variation_name_output );
						$variation_name_output = ucwords( $variation_name_output );

						$variation_value_output = str_replace( '_', ' ', $variation_value );
						$variation_value_output = str_replace( '-', ' ', $variation_value_output );
						$variation_value_output = ucwords( $variation_value_output );

						$ticket_vars[ $variation_name_output ] = $variation_value_output;

					}

					$ticket['WooCommerceEventsVariations'] = $ticket_vars;

				}

				if ( ! empty( $ticket['WooCommerceEventsCustomAttendeeFields'] ) ) {

					if ( is_plugin_active( 'fooevents_custom_attendee_fields/fooevents-custom-attendee-fields.php' ) || is_plugin_active_for_network( 'fooevents_custom_attendee_fields/fooevents-custom-attendee-fields.php' ) ) {

						$fooevents_custom_attendee_fields = new \Fooevents_Custom_Attendee_Fields();
						$ticket_cust                      = $fooevents_custom_attendee_fields->fetch_attendee_details_for_order( $ticket['WooCommerceEventsProductID'], $ticket['WooCommerceEventsCustomAttendeeFields'] );

					}

					$ticket['WooCommerceEventsCustomAttendeeFields'] = $ticket_cust;

				}

				$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['tickets'][ $x ] = $ticket;

				if ( is_plugin_active( 'fooevents_bookings/fooevents-bookings.php' ) || is_plugin_active_for_network( 'fooevents_bookings/fooevents-bookings.php' ) ) {

						$fooevents_bookings = new \Fooevents_Bookings();

					if ( ! empty( $ticket['WooCommerceEventsBookingOptions'] ) ) {

						$woocommerce_events_booking_fields = $fooevents_bookings->process_capture_booking( $ticket['WooCommerceEventsProductID'], $ticket['WooCommerceEventsBookingOptions'], '' );

					}

					$bookings_date_term = get_post_meta( $ticket['WooCommerceEventsProductID'], 'WooCommerceEventsBookingsDateOverride', true );
					$bookings_slot_term = get_post_meta( $ticket['WooCommerceEventsProductID'], 'WooCommerceEventsBookingsSlotOverride', true );

					$slot_label = '';
					if ( empty( $bookings_slot_term ) ) {

						$slot_label = __( 'Slot', 'fooevents-bookings' );

					} else {

						$slot_label = $bookings_slot_term;

					}

					$date_label = '';
					if ( empty( $bookings_date_term ) ) {

						$date_label = __( 'Date', 'fooevents-bookings' );

					} else {

						$date_label = $bookings_date_term;

					}

					if ( ! empty( $woocommerce_events_booking_fields ) ) {

						$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['tickets'][ $x ]['WooCommerceEventsBookingOptions']['slot']      = $woocommerce_events_booking_fields['slot'];
						$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['tickets'][ $x ]['WooCommerceEventsBookingOptions']['slot_term'] = $slot_label;
						$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['tickets'][ $x ]['WooCommerceEventsBookingOptions']['date']      = $woocommerce_events_booking_fields['date'];
						$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['tickets'][ $x ]['WooCommerceEventsBookingOptions']['date_term'] = $date_label;

						$ticket_text_options = array_merge( array( 'WooCommerceEventsProductID' => $event->ID ), $woocommerce_events_booking_fields );

						$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['tickets'][ $x ]['WooCommerceEventsBookingOptions']['WooCommerceEventsZoomText'] = $zoom_api_helper->get_ticket_text( $ticket_text_options, 'admin' );

					}
				}

				if ( is_plugin_active( 'fooevents_multi_day/fooevents-multi-day.php' ) || is_plugin_active_for_network( 'fooevents_multi_day/fooevents-multi-day.php' ) ) {

					$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['WooCommerceEventsEndDate']    = get_post_meta( $event->ID, 'WooCommerceEventsEndDate', true );
					$processed_event_tickets[ $ticket['WooCommerceEventsProductID'] ]['WooCommerceEventsSelectDate'] = get_post_meta( $event->ID, 'WooCommerceEventsSelectDate', true );

				}

				$x++;

			}
		}

		return $processed_event_tickets;
	}

	public function yithBarcode( $atts, $order, $sent_to_admin = '' ) {
		if ( class_exists( 'YITH_Barcode' ) ) {
			if ( ! empty( $order ) ) {
				$obj             = yit_get_prop( $order, 'id' );
				$barcode         = is_numeric( $obj ) ? new \YITH_Barcode( $obj ) : $obj;
				$hide_if_missing = true;
				if ( true ) {
					ob_start();
					$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/yith_barcode.php';
					include $path;
					$html = ob_get_contents();
					ob_end_clean();
					return $html;
				}
			} else {
				return 'yaymail_yith_barcode';
			}
		}
		return '';
	}

	public function render_barcode( $barcode, $show_value = false, $layout = '' ) {
		$barcode_src = apply_filters(
			'yith_ywbc_barcode_src',
			$barcode->image_filename ? $this->get_public_file_path( $barcode ) : 'data:image/png;base64,' . $barcode->image,
			$barcode->get_display_value(),
			$barcode->get_protocol()
		);

		$barcode_value = $barcode->get_display_value();

		$barcode_image_tag = apply_filters( 'yith_ywbc_barcode_image_tag', '<img class="ywbc-barcode-image" src="' . esc_attr( $barcode_src ) . '">', $barcode, $barcode_value, $barcode_src );

		if ( empty( $layout ) ) {
			// Format the image src:  data:{mime};base64,{data};
			$src = $barcode_image_tag;

			if ( $show_value ) {
				$src .= '<div class="ywbc-barcode-display-container"><span class="ywbc-barcode-display-value">' . $barcode_value . '</span></div>';
			}
		} else {
			$finds    = array(
				'{barcode_image}',
				'{barcode_code}',
			);
			$replaces = array(
				$barcode_image_tag,
				$barcode_value,
			);

			$src = str_replace( $finds, $replaces, $layout );
		}

		return apply_filters( 'yith_ywbc_render_barcode_html', $src, $barcode_src, $barcode_value, $barcode, $show_value, $layout );
	}

	public function get_public_file_path( $barcode ) {

		$pos  = strrpos( $barcode->image_filename, '/' );
		$pos2 = strrpos( $barcode->image_filename, '\\' );

		$pos  = ( false === $pos ) ? - 1 : $pos;
		$pos2 = ( false === $pos2 ) ? - 1 : $pos2;

		$last_index = ( $pos > $pos2 ) ? $pos : $pos2;

		if ( $last_index ) {
			return YITH_YWBC_UPLOAD_URL . substr( $barcode->image_filename, $last_index );
		}

		// Previous value
		return str_replace( YITH_YWBC_UPLOAD_DIR, YITH_YWBC_UPLOAD_URL, $barcode->image_filename );
	}

	public function paymentInstructions( $order, $sent_to_admin = false ) {
		if ( null === $order ) {
			return '';
		} else {
			ob_start();
			$path = YAYMAIL_PLUGIN_PATH . 'views/templates/emails/email-order-payment-gateways.php';
			include $path;
			$html = ob_get_contents();
			ob_end_clean();
			return $html;
		}
	}

}
