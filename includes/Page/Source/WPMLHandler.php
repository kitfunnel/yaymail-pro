<?php
namespace YayMail\Page\Source;

defined( 'ABSPATH' ) || exit;
/**
 * Plugin activate/deactivate logic
 */
class WPMLHandler {


	public static function wpml_get_template_meta_query( $order = null ) {
		$language_query = array();
		if ( null !== $order ) {
			$order_id               = $order->get_id();
			$current_order_language = self::wpml_get_current_language_multilingual( $order_id );
		} else {
			$current_order_language = ICL_LANGUAGE_CODE;
		}
		if ( 'en' !== $current_order_language ) {
			$language_query = array(
				'key'     => '_yaymail_template_language',
				'value'   => $current_order_language,
				'compare' => '=',
			);
		} else {
			$language_query = array(
				'key'     => '_yaymail_template_language',
				'compare' => 'NOT EXISTS',
				'value'   => '',
			);
		}
		return $language_query;
	}

	public static function wpml_get_email_template( $args, $order = null ) {
		$default = array(
			'post_type'        => 'yaymail_template',
			'post_status'      => array( 'publish', 'pending', 'future' ),
			'meta_query'       => array(
				'relation' => 'AND',
				array(
					'key'     => '_yaymail_template',
					'value'   => $args['email_template'],
					'compare' => '=',
				),
				self::wpml_get_template_meta_query( $order ),
			),
			'suppress_filters' => true,
		);
		$posts   = new \WP_Query( $default );
		if ( $posts->have_posts() ) {
			return $posts->post->ID;
		}
		return false;
	}

	public static function wpml_update_meta_for_dupplicate( $post_id ) {
		if ( 'en' !== ICL_LANGUAGE_CODE ) {
			update_post_meta( $post_id, '_yaymail_template_language', ICL_LANGUAGE_CODE );
		}
	}

	public static function wpml_get_list_template( $posts, $getPostID ) {
		$template_export = array();
		if ( count( $posts ) > 0 ) {
			$list_use_temp = array();
			foreach ( $posts as $key => $post ) {
				$template          = get_post_meta( $post->ID, '_yaymail_template', true );
				$template_language = get_post_meta( $post->ID, '_yaymail_template_language', true );
				if ( isset( $list_use_temp[ $template ][ $template_language ] )
					&& isset( $list_use_temp[ $template ][ $template_language ]['prev_id'] ) ) {
					wp_delete_post( $post->ID );
				} else {
					$list_use_temp[ $template ][ $template_language ]['prev_id'] = $post->ID;
					if ( ICL_LANGUAGE_CODE === get_post_meta( $post->ID, '_yaymail_template_language', true ) || ( 'en' === ICL_LANGUAGE_CODE && '' === get_post_meta( $post->ID, '_yaymail_template_language', true ) ) ) {
						$template = get_post_meta( $post->ID, '_yaymail_template', true );
						if ( $getPostID ) {
							$template_export[ $template ]['post_id']         = $post->ID;
							$template_export[ $template ]['_yaymail_status'] = get_post_meta( $post->ID, '_yaymail_status', true );
						} else {
							$template_export[ $template ] = get_post_meta( $post->ID, '_yaymail_status', true );
						}
					}
				}
			}
		}
		return $template_export;
	}

	public static function wpml_get_cookie_domain() {
		if ( defined( 'COOKIE_DOMAIN' ) ) {
			$cookie_domain = COOKIE_DOMAIN;
		} else {
			$host = '';
			if ( isset( $_SERVER['HTTP_HOST'] ) ) {
				$host = sanitize_text_field( $_SERVER['HTTP_HOST'] );
			} elseif ( isset( $_SERVER['SERVER_NAME'] ) ) {
				$host = sanitize_text_field( $_SERVER['SERVER_NAME'] ) . self::wpml_get_port();
				// Removes standard ports 443 (80 should be already omitted in all cases)
				$host = preg_replace( '@:[443]+([/]?)@', '$1', $host );
			}
			$cookie_domain = $host;
		}
		return $cookie_domain;
	}

	public static function wpml_get_port() {
		return isset( $_SERVER['SERVER_PORT'] ) && ! in_array( $_SERVER['SERVER_PORT'], array( 80, 443 ) )
			? ':' . sanitize_text_field( $_SERVER['SERVER_PORT'] )
			: '';
	}

	public static function wpml_get_cookie_name() {
		return 'wp-wpml_current_admin_language_' . md5( self::wpml_get_cookie_domain() );
	}

	public static function wpml_switch_language( $language ) {
		$cookie_name = self::wpml_get_cookie_name();
		setcookie( $cookie_name, $language, time() + 86400, defined( 'COOKIEPATH' ) ? COOKIEPATH : '/' );
	}

	public static function wpml_turn_off_dupplicate_post_type() {
		global $sitepress_settings, $sitepress;
		$custom_posts_sync                     = $sitepress_settings['custom_posts_sync_option'];
		$custom_posts_sync['yaymail_template'] = 0;
		$sitepress->set_setting( 'custom_posts_sync_option', $custom_posts_sync, true );
	}

	public static function wpml_turn_on_admin_edit_language() {
		$user_id = get_current_user_id();
		update_user_meta( $user_id, 'icl_admin_language_for_edit', 1 );
	}
	public static function wpml_get_current_language_multilingual( $order_id ) {
		global $wpdb;
		$has_wpml_language              = get_post_meta( $order_id, 'wpml_language', true );
		$has_yaymail_multigual_language = get_post_meta( $order_id, 'yaymail_multigual_language', true );
		if ( $has_yaymail_multigual_language ) {
			$current_order_language = $has_yaymail_multigual_language;
		} else {
			if ( $has_wpml_language ) {
				$current_order_language = $has_wpml_language;
			} else {
				$item_result            = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = %s AND order_item_type = 'line_item' ", $order_id ), ARRAY_A );
				$order_item_id          = $item_result[0]['order_item_id'];
				$product_result         = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = %s AND meta_key = '_product_id' ", $order_item_id ), ARRAY_A );
				$edit_product_id        = $product_result[0]['meta_value'];
				$order_language_results = $wpdb->get_results( $wpdb->prepare( "SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_type = 'post_product' AND element_id = %s ", $edit_product_id ) );
				if ( count( $order_language_results ) ) {
					$current_order_language = $order_language_results[0]->language_code;
				} else {
					$current_order_language = 'en';
				}
			}
			update_post_meta( $order_id, 'yaymail_multigual_language', $current_order_language );
		}
		return $current_order_language;
	}

}
