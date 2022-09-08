<?php
namespace YayMail\Helper;

use stdClass;

defined( 'ABSPATH' ) || exit;

/**
 * Class for handling license key to activate YayMail plugin and auto upload
 *
 * @class LicenseHandler
 */
class LicenseHandler {

	/**
	 * Send API request to check validation of license
	 *
	 * @param string $api_url API end point url.
	 *
	 * @param int    $item_id Item ID of YayMail product.
	 *
	 * @param string $license The license key.
	 *
	 * @return array
	 */
	public static function check_license( $api_url, $item_id, $license ) {
		try {
			$response = wp_remote_get( $api_url . '?edd_action=check_license&item_id=' . $item_id . '&license=' . $license . '&url=' . home_url() );

			$response_body = json_decode( $response['body'] );
	
			$results = array(
				'status' => false,
			);
	
			if ( true === $response_body->success ) {
				if ( 'valid' === $response_body->license || 'expired' === $response_body->license ) {
					$results['status']      = true;
					$results['expires']     = $response_body->expires;
					$results['license_key'] = $license;
				}
			}
			return $results;
		} catch ( \Error $error) {
			$results['status'] = true;
			return $results;
		}
	}

	/**
	 * Send API request to get version information of YayMail product
	 *
	 * @param string $api_url API end point url.
	 *
	 * @param int    $item_id Item ID of YayMail product.
	 *
	 * @return array
	 */
	public static function get_version( $api_url, $item_id ) {
		try {
			$response      = wp_remote_get( $api_url . '?edd_action=get_version&item_id=' . $item_id );
			$response_body = json_decode( $response['body'] );
			return $response_body;
		} catch ( \Error $error ) {
			$result              = new stdClass();
			$result->new_version = false;
			return $result;
		}

	}

	/**
	 * Function to activate license for YayMail plugin
	 *
	 * @param string $api_url API end point url.
	 *
	 * @param int    $item_id Item ID of YayMail product.
	 *
	 * @param string $license The license key.
	 *
	 * @return array
	 */
	public static function activate_license( $api_url, $item_id, $license ) {

		try {
			$response = wp_remote_get( $api_url . '?edd_action=activate_license&item_id=' . $item_id . '&license=' . $license . '&url=' . home_url() );

			$response_body = json_decode( $response['body'] );

			$result = array( 'status' => true );

			if ( ! $response_body->success ) {
				$result['status']  = false;
				$result['message'] = $response_body->error;
			} else {
				$result['expires']       = $response_body->expires;
				$result['license_limit'] = $response_body->license_limit;
				$result['customer_name'] = $response_body->customer_name;
			}
			return $result;
		} catch ( \Error $error ) {
			$result            = array();
			$result['status']  = true;
			$result['message'] = 'server_error';
			return $result;
		}

	}

	/**
	 * Add text to the message depend on the message
	 *
	 * @param string $message The message get from API request (error code).
	 *
	 * @return string
	 */
	public static function add_error( $message ) {
		$result = '';
		switch ( $message ) {
			case 'site_inactivate':
				$result = __( 'Your site is not activate this license.', 'yaymail' );
				break;
			case 'expired':
				$result = __( 'Your license is expired', 'yaymail' );
				break;
			case 'no_activations_left':
				$result = __( 'Your license could not be activated, there is no activations left for this License key.', 'yaymail' );
				break;
			case 'missing':
				$result = __( 'Your license could not be activated, please check your license key.', 'yaymail' );
				break;
			case 'disabled':
				$result = __( 'Your license could not be activated, the license key is now disabled.', 'yaymail' );
				break;
			case 'server_error':
				$result = __( 'Your license could not be activated because of server error.', 'yaymail' );
				break;
			default:
				$result = __( 'Your license could not be activated.', 'yaymail' );
				break;
		}
		return $result;
	}
}
