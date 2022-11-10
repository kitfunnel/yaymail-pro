<?php
namespace YayMail\Page\Source;

defined( 'ABSPATH' ) || exit;
/**
 * Plugin activate/deactivate logic
 */
class WeglotHandler {

	public static function weglot_get_template_meta_query() {
		$language_query       = array();
		$translate_services   = \weglot_get_service( 'Translate_Service_Weglot' );
		$language_services    = weglot_get_service( 'Language_Service_Weglot' );
		$request_url_services = weglot_get_service( 'Request_Url_Service_Weglot' );
		if ( is_admin() ) {
			if ( isset( $_COOKIE['pll_language'] ) && 'en' !== sanitize_text_field( $_COOKIE['pll_language'] ) ) {
				$language = sanitize_text_field( $_COOKIE['pll_language'] );
			} else {
				$language = 'en';
			}
			$current_language = $language_services->get_language_from_internal( $language );
		} else {
			$current_language = $request_url_services->get_current_language();
			$language         = $current_language->getInternalCode();
		}

		update_option( 'yaymail_direction', $current_language->isRtl() ? 'rtl' : 'ltr' );
		$language = 'en' === $language ? null : $language;
		if ( $language ) {
			$language_query = array(
				'key'     => '_yaymail_template_language',
				'value'   => sanitize_text_field( $language ),
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

	public static function weglot_get_email_template( $args ) {
		$default = array(
			'post_type'   => 'yaymail_template',
			'post_status' => array( 'publish', 'pending', 'future' ),
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'     => '_yaymail_template',
					'value'   => $args['email_template'],
					'compare' => '=',
				),
				self::weglot_get_template_meta_query(),
			),
		);
		$posts   = new \WP_Query( $default );

		if ( $posts->have_posts() ) {
			return $posts->post->ID;
		}
		return false;

	}

	public static function weglot_switch_language( $language ) {
		setcookie( 'pll_language', $language, time() + 86400, defined( 'COOKIEPATH' ) ? COOKIEPATH : '/' );
	}

	public static function weglot_update_meta_for_dupplicate( $post_id ) {
		if ( isset( $_COOKIE['pll_language'] ) && 'en' !== sanitize_text_field( $_COOKIE['pll_language'] ) ) {
			update_post_meta( $post_id, '_yaymail_template_language', sanitize_text_field( $_COOKIE['pll_language'] ) );
		}
	}

	public static function weglot_get_list_template( $posts, $getPostID ) {
		$template_export = array();
		$cur_lang        = isset( $_COOKIE['pll_language'] ) ? sanitize_text_field( $_COOKIE['pll_language'] ) : 'en';
		if ( count( $posts ) > 0 ) {
			$list_use_temp = array();
			foreach ( $posts as $key => $post ) {
				if ( get_post_meta( $post->ID, '_yaymail_template_language', true ) === $cur_lang || ( 'en' === $cur_lang && '' === get_post_meta( $post->ID, '_yaymail_template_language', true ) ) ) {
					$template          = get_post_meta( $post->ID, '_yaymail_template', true );
					$template_language = get_post_meta( $post->ID, '_yaymail_template_language', true );
					if ( isset( $list_use_temp[ $template ][ $template_language ] )
					&& isset( $list_use_temp[ $template ][ $template_language ]['prev_id'] ) ) {
						wp_delete_post( $post->ID );
					} else {
						$list_use_temp[ $template ][ $template_language ]['prev_id'] = $post->ID;
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

}
