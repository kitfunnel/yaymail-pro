<?php
namespace YayMail\Page\Source;

defined( 'ABSPATH' ) || exit;
/**
 * Plugin activate/deactivate logic
 */
class LocoHandler {

	public static function loco_get_template_meta_query() {
		$language_query = array();
		if ( 'en' !== get_user_locale() && 'en_US' !== get_user_locale() ) {
			$language_query = array(
				'key'     => '_yaymail_template_language',
				'value'   => get_user_locale(),
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

	public static function loco_get_email_template( $args ) {
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
				self::loco_get_template_meta_query(),
			),
		);
		$posts   = new \WP_Query( $default );

		if ( $posts->have_posts() ) {
			return $posts->post->ID;
		}
		return false;

	}

	public static function loco_switch_language( $language ) {
		update_user_meta( get_current_user_id(), 'locale', $language );
	}

	public static function loco_update_meta_for_dupplicate( $post_id ) {
		if ( 'en_US' !== get_user_locale() ) {
			update_post_meta( $post_id, '_yaymail_template_language', get_user_locale() );
		}
	}

	public static function loco_get_list_template( $posts, $getPostID ) {
		$template_export = array();
		$cur_lang        = get_user_locale();
		if ( count( $posts ) > 0 ) {
			$list_use_temp = array();
			foreach ( $posts as $key => $post ) {
				if ( get_post_meta( $post->ID, '_yaymail_template_language', true ) === $cur_lang || ( 'en_US' === $cur_lang && '' === get_post_meta( $post->ID, '_yaymail_template_language', true ) ) ) {
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
