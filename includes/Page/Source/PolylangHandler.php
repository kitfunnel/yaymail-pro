<?php
namespace YayMail\Page\Source;

defined( 'ABSPATH' ) || exit;
/**
 * Plugin activate/deactivate logic
 */
class PolylangHandler {

	public static function polylang_get_template_meta_query() {
		$language_query = array();
		$language       = null;
		if ( isset( $_COOKIE['pll_language'] ) && 'en' !== sanitize_text_field( $_COOKIE['pll_language'] ) ) {
			$language = sanitize_text_field( $_COOKIE['pll_language'] );
		} else {
			if ( class_exists('PLL') && isset(PLL()->curlang ) ) {
				$language = 'en' === PLL()->curlang->slug ? null : PLL()->curlang->slug;
			}
		}

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

	public static function polylang_get_email_template( $args ) {
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
				self::polylang_get_template_meta_query(),
			),
		);
		$posts   = new \WP_Query( $default );

		if ( $posts->have_posts() ) {
			return $posts->post->ID;
		}
		return false;

	}

	public static function polylang_switch_language_front_end( $language ) {
		setcookie( 'pll_language', $language, time() + 86400, defined( 'COOKIEPATH' ) ? COOKIEPATH : '/' );
		do_action( 'wpml_switch_language', $language, true );
	}

	public static function polylang_update_meta_for_dupplicate( $post_id ) {
		if ( isset( $_COOKIE['pll_language'] ) && 'en' !== sanitize_text_field( $_COOKIE['pll_language'] ) ) {
			update_post_meta( $post_id, '_yaymail_template_language', sanitize_text_field( $_COOKIE['pll_language'] ) );
		}
	}

	public static function polylang_get_list_template( $posts, $getPostID ) {
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

	public static function polylang_turn_off_dupplicate_post_type() {
		$polylang_options = get_option( 'polylang' );
		if ( isset( $polylang_options['post_types'] ) ) {
			$yaymail_template_position = array_search( 'yaymail_template', $polylang_options['post_types'] );
			if ( false !== $yaymail_template_position ) {
				$polylang_options['post_types'] = array_splice( $polylang_options['post_types'], $yaymail_template_position + 1, 1 );
				update_option( 'polylang', $polylang_options );
			}
		}
	}

}
