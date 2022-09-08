<?php
namespace YayMail;

use YayMail\Helper\LicenseHandler;
use YayMail\Helper\EDD_SL_Plugin_Updater;
defined( 'ABSPATH' ) || exit;

/**
 * Class for create License Handle
 *
 * @class License
 */
class License {

	/**
	 * Single instance of class
	 *
	 * @var License
	 */
	protected static $instance = null;

	/**
	 * Slug of YayMail Pro License tab
	 *
	 * @var string
	 */
	protected $current_tab = null;

	/**
	 * Variable to check is this License expired or not
	 *
	 * @var boolean
	 */
	public $is_expired = false;

	/**
	 * YayMail plugin file
	 *
	 * @var string
	 */
	protected $file = null;

	/**
	 * Cache key of license info data, save in options
	 *
	 * @var string
	 */
	protected $license_info_cache_key = null;

	/**
	 * Version info
	 *
	 * @var object
	 */
	protected $version_info = null;

	/**
	 * Cache key of version info data, save in options
	 *
	 * @var string
	 */
	protected $version_info_cache_key = null;

	/**
	 * License key
	 *
	 * @var string
	 */
	protected $license_key = null;

	/**
	 * License expired date
	 *
	 * @var string
	 */
	protected $license_expires = null;

	/**
	 * Is Yaycommerce server error.
	 *
	 * @var boolean
	 */
	protected $server_error = false;

	/**
	 * Function ensure only one instance created
	 *
	 * @param object $file file input.
	 *
	 * @return License
	 */
	public static function get_instance( $file ) {

		if ( null === self::$instance ) {
			self::$instance = new self( $file );
		}
		return self::$instance;

	}

	/**
	 * Constructor, defined default variable
	 *
	 * @param string $file The plugin file.
	 *
	 * @return void
	 */
	protected function __construct( $file ) {


		// remove license
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['_wpnonce'] ), 'woocommerce-settings' ) ) {
			if( isset( $_POST['yaymail_remove_license'] ) && 'true' == $_POST['yaymail_remove_license'] ) {
				delete_option('yaymail_version_info_cache');
				delete_option('yaymail_license_info');
				delete_option('yaymail_license_key');
			}
		}
		
		// Create construct variables.
		$this->file        = $file;
		$this->current_tab = 'yaymail_pro_license';
		$this->license_key = $this->yaymail_get_license_key();

		// Call do hooks function.
		$this->do_hooks();
		add_action( 'yaymail_license_cron_hook', array( $this,'yaymail_license_cron_hook_run') );
		if ( ! wp_next_scheduled( 'yaymail_license_cron_hook' ) ) {
			wp_schedule_event( time(), 'weekly', 'yaymail_license_cron_hook' );
		};

		// Create setup hooks.
		// add_action( 'wp_ajax_nopriv_yaymail-deactivate-license', array( $this, 'deactivate_license_webhook' ) );
		// add_action( 'wp_ajax_nopriv_yaymail-extend-license', array( $this, 'extend_license_webhook' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_license_scripts' ) );
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'yaymail_pro_license_setting_tab' ), 100, 1 );
		add_action( 'woocommerce_settings_' . $this->current_tab, array( $this, 'yaymail_pro_license_settings' ) );
	}

	public function yaymail_license_cron_hook_run() {
		if ( !get_option('yaymail_license_key') ) {
			return;
		}
		$results_check_license = LicenseHandler::check_license( YAYMAIL_SELLER_API_URL, YAYMAIL_ITEM_ID, $this->license_key );

		if ( $results_check_license['status'] ) {
			$license_info          = $this->yaymail_get_cache_license_info();
			if ( isset($results_check_license['expires'])) {
				$license_info->expires = $results_check_license['expires'];
			}	
			$this->yaymail_set_cache_license_info( $license_info );
		} else {
			delete_option('yaymail_version_info_cache');
			delete_option('yaymail_license_info');
			delete_option('yaymail_license_key');
		}
	}

	/**
	 * Create webhook when this license is deactivated from admin
	 *
	 * @return void
	 */
	public function deactivate_license_webhook() {
		$this->deactivate_license();
		wp_send_json_success( array( 'success' => true ), 200 );
	}

	/**
	 * Handling deactivate license
	 *
	 * @return void
	 */
	public function deactivate_license() {
		$this->yaymail_remove_cache_license_info();
		$this->yaymail_update_license_key( false );
	}

	/**
	 * Create webhook when this license is extended from admin
	 *
	 * @return void
	 */
	public function extend_license_webhook() {
		$this->extend_license();
		wp_send_json_success( array( 'success' => true ), 200 );
	}

	/**
	 * Handling extend license
	 *
	 * @return void
	 */
	public function extend_license() {
		$authorization_key = sanitize_text_field( $_REQUEST['authorization_key'] );
		$new_expiration    = sanitize_text_field( $_REQUEST['new_expiration'] );
		if ( ! empty( $new_expiration ) && ! empty( $authorization_key ) ) {
			if ( $authorization_key !== md5( 'yaymail-license-' . md5( $new_expiration ) ) ) {
				return;
			}
			$license_info          = $this->yaymail_get_cache_license_info();
			$license_info->expires = date( 'Y-m-d H:i:s', $new_expiration );
			$this->yaymail_set_cache_license_info( $license_info );
		}
	}

	/**
	 * Do things when the class is created
	 *
	 * @return void
	 */
	protected function do_hooks() {
		global $pagenow;

		$this->yaymail_check_expired();

		// Handling activate plugin.
		$this->yaymail_license_do_activate();

		// Handling auto update.
		$this->yaymail_auto_update();

		// Handling show notification in plugins page.
		if ( ! $this->license_key || ( $this->license_key && $this->is_expired ) ) {
			if ( $this->is_expired ) {
				add_action( 'admin_notices', array( $this, 'yaymail_license_addmin_error_notice' ) );
			}
			if ( 'plugins.php' === $pagenow ) {
				$this->set_version_info_cache_key();
				$version_info = $this->yaymail_get_cache_version_info();
				if ( false === $version_info ) {
					$version_checker = LicenseHandler::get_version( YAYMAIL_SELLER_API_URL, YAYMAIL_ITEM_ID );
					$this->yaymail_set_cache_version_info( $version_checker );
				}
				$this->yaymail_show_update_notification();
				add_filter(
					'plugin_auto_update_setting_html',
					function( $html, $plugin_file, $plugin_data ) {
						if ( YAYMAIL_PLUGIN_BASENAME === $plugin_file ) {
							return '<span class="label">' . __( 'Auto-updates unavailable', 'yaymail' ) . '</span>';
						}
						return $html;
					},
					100,
					3
				);
				add_filter(
					'plugin_action_links_' . YAYMAIL_PLUGIN_BASENAME,
					function( $links ) {
						$action_links = array(
							'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=yaymail_pro_license' ) . '" aria-label="' . esc_attr__( 'View Yaymail license settings', 'yaymail' ) . '">' . esc_html__( 'Enter license key', 'yaymail' ) . '</a>',
						);
						return array_merge( $action_links, $links );
					}
				);
			}
			if ( 'plugin-install.php' === $pagenow ) {
				add_filter( 'plugins_api', array( $this, 'yaymail_api_filters' ), 21, 3 );
			}
		}
	}

	public function yaymail_get_renewal_url() {
		return YAYMAIL_SELLER_API_URL . 'checkout/?edd_license_key=' . $this->license_key . '&download_id=' . YAYMAIL_ITEM_ID;
		// return YAYMAIL_SELLER_API_URL . 'checkout';
	}

	/**
	 * Add notice on admin page when license is expired
	 *
	 * @return void
	 */
	public function yaymail_license_addmin_error_notice() {
		$class      = 'notice notice-error';
		$message    = __( 'Your license key for YayMail Pro - WooCommerce Email Customizer has expired.', 'yaymail' );
		$renew_text = __( 'Please click here to renew your license key and continue receiving automatic updates.', 'yaymail' );
		printf( '<div class="%1$s"><p>%2$s <a href="%3$s" target="_blank">%4$s</a></p></div>', esc_attr( $class ), esc_html( $message ), esc_url( $this->yaymail_get_renewal_url() ), esc_html( $renew_text ) );
	}

	/**
	 * Get this license key
	 *
	 * @return string
	 */
	public function get_license_key() {
		return $this->license_key;
	}

	/**
	 * Set cache key of version info data
	 *
	 * @return void
	 */
	protected function set_version_info_cache_key() {
		$this->version_info_cache_key = 'yaymail_vi_cache_' . md5( serialize( YAYMAIL_PLUGIN_BASENAME ) );
	}

	/**
	 * Get version info cache saved in options
	 *
	 * @return object
	 */
	protected function yaymail_get_cache_version_info() {

		$cache = get_option( 'yaymail_version_info_cache' );
		if ( empty( $cache['timeout'] ) || time() > $cache['timeout'] ) {
			return false;
		}

		// $cache          = array();
		// $cache['value'] = json_encode( LicenseHandler::get_version( YAYMAIL_SELLER_API_URL, YAYMAIL_ITEM_ID ) );
		// $cache['value'] = json_decode( $cache['value'] );
		$cache['value'] = json_decode( $cache['value'] );
		return $cache['value'];
	}

	/**
	 * Set version info cache
	 *
	 * @param object $version_info The version info get from api request.
	 *
	 * @return void
	 */
	protected function yaymail_set_cache_version_info( $version_info ) {
		if ( false === $version_info->new_version ) {
			$this->server_error = true;
		} else {
			$this->server_error = false;
		}
		$data = array(
			'timeout' => strtotime( '+3 hours', time() ),
			'value'   => json_encode( $version_info ),
		);
		update_option( 'yaymail_version_info_cache', $data );
		$this->version_info = $data['value'];
	}

	/**
	 * Set cache key of license info data
	 *
	 * @param string $license_key The license key.
	 *
	 * @return void
	 */
	protected function set_license_info_cache_key( $license_key ) {
		$this->license_info_cache_key = 'yaymail_li_cache_' . md5( serialize( YAYMAIL_PLUGIN_BASENAME . $license_key ) );
	}

	/**
	 * Get license info cache saved in options
	 *
	 * @return object
	 */
	protected function yaymail_get_cache_license_info() {
		$result = get_option( 'yaymail_license_info' );
		return json_decode( $result );
	}

	/**
	 * Set license info cache
	 *
	 * @param object $license_info The license info get from api request.
	 *
	 * @return void
	 */
	protected function yaymail_set_cache_license_info( $license_info ) {
		update_option( 'yaymail_license_info', json_encode( $license_info ) );
	}

	/**
	 * Remove license info cache
	 *
	 * @return void
	 */
	protected function yaymail_remove_cache_license_info() {
		delete_option( 'yaymail_license_info' );
	}

	/**
	 * Enqueue script for license page
	 *
	 * @return void
	 */
	public function enqueue_license_scripts() {
		wp_enqueue_style( 'yaymail-license', YAYMAIL_PLUGIN_URL . 'assets/admin/css/license.css', array(), YAYMAIL_VERSION );
	}

	/**
	 * Function to add YayMail Pro license tab to WooCommerce settings
	 *
	 * @param string $settings_array license_key.
	 *
	 * @return array
	 */
	public function yaymail_pro_license_setting_tab( $settings_array ) {
		$settings_array = array_merge( $settings_array, array( 'yaymail_pro_license' => __( 'YayMail Pro License', 'yaymail' ) ) );
		return $settings_array;
	}

	/**
	 * Function to add YayMail Pro license tab to WooCommerce settings
	 *
	 * @return void
	 */
	public function yaymail_pro_license_settings() {

		// Get current License status ( status, expired date, license key ).
		?>
		<div class="yaymail-license-wrap">
			<div id="yaymail-license-root">
				<div class="yaymail-license-layout">
					<div class="yaymail-license-layout-primary">
						<div class="yaymail-license-layout-main">
							<div class="yaymail-license-settings">
							<?php if ( ! $this->license_key ) : ?>
								<!-- Form input license key -->
								<div class="yaymail-license-card">
									<div class="yaymail-license-card-header">
										<div class="yaymail-license-card-title-wrapper">
											<h3 class="yaymail-license-card-title yaymail-license-card-header-item">
												<?php echo esc_html( __( 'YayMail Pro Activation', 'yaymail' ) ); ?>
											</h3>
										</div>
									</div>
									<div class="yaymail-license-card-body"> 
										<div class="yaymail-license-control">
											<div class="yaymail-license-text" for="inspector-select-control-text"><?php echo esc_html( __( 'Enter a license key', 'yaymail' ) ); ?></div>
											<div class="yaymail-license-base-control">
												<div class="yaymail-license-base-control-field">
													<input name="yaymail_license_key"  class="yaymail-license-text-control-input" type="password" id="yaymail_license_key" value="">
												</div>
												<p><?php echo esc_html( __( 'To receive updates, please enter your valid YayMail Pro license key', 'yaymail' ) ); ?></p>
											</div>
											<div class="yaymail-license-text" for="inspector-select-control-text"><?php echo esc_html( __( 'By activating YayMail Pro, you\'ll have:', 'yaymail' ) ); ?></div>
											<ul class="yaymail-license-in-feature">
												<li><?php echo esc_html( __( 'Auto-update to the latest version', 'yaymail' ) ); ?></li>
												<li><?php echo esc_html( __( 'Premium Technical Support', 'yaymail' ) ); ?></li>
												<li><?php echo esc_html( __( 'Live Chat 1-1 on Facebook for any questions', 'yaymail' ) ); ?></li>
											</ul>
										</div>			
									</div>
								</div>
								<?php
								else :
									// Hide save button when License is activated.
									$GLOBALS['hide_save_button'] = true;
									add_action(
										'woocommerce_after_settings_' . $this->current_tab,
										function() {
											$GLOBALS['hide_save_button'] = '';
										}
									);
									$license_info = $this->yaymail_get_cache_license_info();

									?>
								<!-- Form show info license -->
								<div class="yaymail-license-card">
									<div class="yaymail-license-card-header">
										<div class="yaymail-license-card-title-wrapper">
											<h3 class="yaymail-license-card-title yaymail-license-card-header-item">
												<?php echo esc_html( __( 'Thanks for activating YayMail Pro!', 'yaymail' ) ); ?>
											</h3>
										</div>
									</div>
									<div class="yaymail-license-card-body"> 
										<table class="yaymail-license-table">
											<tbody>
												<tr class="yaymail-license-tr">
													<td class="yaymail-license-key-text" ><?php echo esc_html( __( 'Your License Key:', 'yaymail' ) ); ?></td>
													<td class="yaymail-license-text">
														<input type="text" disabled value="<?php echo esc_html( $this->yaymail_format_license_key( $this->license_key ) ); ?>">
													</td>
													<td>
														<a class="yaymail_remove_license" href="javascript:;" onclick="document.getElementById('mainform').submit()"><?php echo esc_html__( 'Remove', 'yaymail' ); ?></a>
														<input type="hidden" id="yaymail_remove_license" name="yaymail_remove_license" value="true">
														<?php wp_nonce_field(); ?>
													</td>
												</tr>
												<tr class="yaymail-license-tr">
													<td class="yaymail-license-key-text" ><?php echo esc_html( __( 'Expiration Date:', 'yaymail' ) ); ?></td>
													<?php
														$time_in_tz = strtotime( $license_info->expires );
													if ( 'lifetime' === $license_info->expires ) {
														$time_in_tz = strtotime( 'now +3000 year' );
													}
													?>
													<td class="yaymail-license-text">
														<?php echo esc_html( gmdate( 'F j, Y H:i:s', $time_in_tz ) ); ?>
														<?php
														if ( $this->is_expired ) {
															echo '<strong class="yaymail-license-expired-text">(' . esc_html__( 'Expired', 'yaymail' ) . ')</strong>';}
														?>
													</td>
												</tr>
											</tbody>
										</table>
										<p><?php echo esc_html( __( 'Need more licenses? ', 'yaymail' ) ); ?><a class="yaymail-license-buy-now" href="https://yaycommerce.com/yaymail-woocommerce-email-customizer/" target="_blank"><?php echo esc_html( __( 'Buy Now', 'yaymail' ) ); ?></a></p>	
										<?php
										if ( $this->is_expired ) {
											echo '<p><strong class="yaymail-license-expired-text">' . esc_html__( 'Your license is expired! ', 'yaymail' ) . '<a href="' . esc_url( $this->yaymail_get_renewal_url() ) . '" class="yaymail-license-expired-text" target="_blank">' . esc_html__( 'Renew Now!', 'yaymail' ) . '</a>' . '</strong></p>';}
										?>
									</div>
								</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
								<?php
	}

	/**
	 * Take the license key from database
	 *
	 * @return string
	 */
	protected function yaymail_get_license_key() {
		$key = get_option( 'yaymail_license_key' );
		return $key;
	}

	/**
	 * Update license key to database
	 *
	 * @param string $license_key The license key.
	 *
	 * @return void
	 */
	protected function yaymail_update_license_key( $license_key ) {
		update_option( 'yaymail_license_key', $license_key );
		$this->license_key = $license_key;
	}


	/**
	 * Handling check license expiration
	 *
	 * @return array
	 */
	protected function yaymail_check_expired() {
		$license_key = $this->license_key;
		if ( $license_key ) {
			$license_info = $this->yaymail_get_cache_license_info();
			if ( 'lifetime' !== $license_info->expires ) {
				if ( strtotime( $license_info->expires ) < time() ) {
					$this->is_expired = true;
					return;
				}
			}
		}
		$this->is_expired = false;
	}

	/**
	 * Format license key
	 *
	 * @param string $license_key The license key.
	 *
	 * @param number $number_seperate The number of each group of digit.
	 *
	 * @param char   $seperate Characters of the seperate.
	 *
	 * @param number $number_hidden The number of hidden character.
	 *
	 * @return string
	 */
	protected function yaymail_format_license_key( $license_key, $number_seperate = 8, $seperate = '-', $number_hidden = 20 ) {
		$license_key_length = strlen( $license_key );
		for ( $i = $license_key_length - $number_hidden; $i < $license_key_length; $i++ ) {
			$license_key[ $i ] = '*';
		}
		$formatted_license_key = '';
		for ( $i = 0; $i < $license_key_length; $i++ ) {
			$formatted_license_key .= $license_key[ $i ];
			if ( 0 === ( ( $i + 1 ) % $number_seperate ) && $i + 1 >= $number_seperate && $i !== $license_key_length - 1 ) {
				$formatted_license_key .= $seperate;
			}
		}
		return $formatted_license_key;
	}

	/**
	 *
	 * Check is a activation request and do activate
	 *
	 * @return void
	 */
	protected function yaymail_license_do_activate() {
		if ( ! $this->license_key ) {
			if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
				if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['_wpnonce'] ), 'woocommerce-settings' ) ) {
					if ( isset( $_REQUEST['yaymail_license_key'] ) && ! empty( $_REQUEST['yaymail_license_key'] ) ) {
						$yaymail_license_key = sanitize_text_field( $_REQUEST['yaymail_license_key'] );
						$activate_checker    = LicenseHandler::activate_license( YAYMAIL_SELLER_API_URL, YAYMAIL_ITEM_ID, $yaymail_license_key );
						if ( $activate_checker['status'] ) {
							$this->yaymail_set_cache_license_info( $activate_checker );
							$this->yaymail_update_license_key( $yaymail_license_key );
						} else {
							$message = LicenseHandler::add_error( $activate_checker['message'] );
							\WC_Admin_Settings::add_error( $message );
						}
					}
				}
			}
		}
	}

	/**
	 * Handling add action to show the update notification when the license is not activated or expired or have new version.
	 *
	 * @return void
	 */
	protected function yaymail_show_update_notification() {
		global $pagenow;
		if ( 'plugins.php' === $pagenow ) {
			$license_key  = $this->license_key;
			$version_info = $this->yaymail_get_cache_version_info();
			if ( YAYMAIL_VERSION < $version_info->new_version || $this->is_expired || ! $license_key ) {
				if ( ! $license_key || ( $license_key && $this->is_expired ) ) {
					add_action( 'after_plugin_row_' . YAYMAIL_PLUGIN_BASENAME, array( $this, 'yaymail_update_notification' ), 10, 2 );
				}
			}
		}
	}


	/**
	 * Show notification html view
	 *
	 * @param string $file The plugin file.
	 *
	 * @param string $plugin The plugin.
	 *
	 * @return void
	 */
	public function yaymail_update_notification( $file, $plugin ) {
		$wp_list_table = _get_list_table( 'WP_MS_Themes_List_Table' );
		$version_info  = $this->yaymail_get_cache_version_info();
		?>
		<script>
		var plugin_row_element = document.querySelector('tr[data-plugin="<?php echo esc_js( plugin_basename( $file ) ); ?>"]');
		plugin_row_element.classList.add('update');
		</script>
		<?php
		echo '<tr class="plugin-update-tr' . ( is_plugin_active( $file ) ? ' active' : '' ) . '"><td colspan="' . esc_attr( $wp_list_table->get_column_count() ) . '" class="plugin-update colspanchange" >';
		echo '<div class="update-message notice inline notice-warning notice-alt"><p>';
		if ( $this->server_error ) {
			$this->yaymail_server_error_text();
		}
		if ( YAYMAIL_VERSION < $version_info->new_version ) {
			$this->yaymail_update_text();
		}
		if ( ! $this->license_key ) {
			$this->yaymail_not_activate_text();
		}
		echo '</p>';
		if ( $this->license_key && $this->is_expired ) {
			$this->yaymail_expired_text();
		}
		echo '
				</div>
			</td>
			</tr>';
	}

	/**
	 * Html view when have new version
	 *
	 * @return void
	 */
	protected function yaymail_update_text() {
		$changelog_link = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . basename( $this->file, '.php' ) . '&section=changelog&TB_iframe=true&width=600&height=800' );
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data  = get_plugin_data( $this->file );
		$version_info = $this->yaymail_get_cache_version_info();
		?>
		<?php echo esc_html__( 'There is a new version of ' . $plugin_data['Name'] . ' available. ', 'yaymail' ); ?>
		<a target="_blank" class="thickbox open-plugin-details-modal" href="<?php echo esc_url( $changelog_link ); ?>"><?php echo esc_html__( 'View version ' . $version_info->new_version . ' details', 'yaymail' ); ?></a>.
		<?php
	}

	/**
	 * Html view when server is down
	 *
	 * @return void
	 */
	protected function yaymail_server_error_text() {
		echo esc_html__( 'Now cannot get plugin information due to server issue.', 'yaymail' );
	}

	/**
	 * Html view when the license is not activated.
	 *
	 * @return void
	 */
	protected function yaymail_not_activate_text() {
		?>
		<i><?php echo esc_html__( 'Automatic update is unavailable for this plugin. ', 'yaymail' ); ?></i>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=yaymail_pro_license' ) ); ?>"><?php echo esc_html__( 'Activate your license for automatic updates', 'yaymail' ); ?></a>.
		<?php
	}
	/**
	 * Html view when the license is not activated or expired
	 *
	 * @return void
	 */
	protected function yaymail_expired_text() {
		?>
		<p class="yaymail_expired_text">
			<span><?php echo esc_html__( 'Your license has expired, please ', 'yaymail' ); ?></span>
			<a target="_blank" href="<?php echo esc_url( $this->yaymail_get_renewal_url() ); ?>"><?php echo esc_html__( 'renew this license', 'yaymail' ); ?></a>
			<span><?php echo esc_html__( ' to download this update. ', 'yaymail' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Handling auto update when the license is activated and remove auto update when the license is not activated or expired
	 *
	 * @return void
	 */
	protected function yaymail_auto_update() {
		if ( $this->license_key && ! $this->is_expired && ! $this->server_error ) {
			add_action( 'admin_init', array( $this, 'auto_update' ) );
		} else {
			global $pagenow;
			if ( 'plugin-install.php' !== $pagenow ) {
				$site_transient_update_plugins = get_site_transient( 'update_plugins' );
				if ( isset( $site_transient_update_plugins->response[ YAYMAIL_PLUGIN_BASENAME ] )
				|| isset( $site_transient_update_plugins->no_update[ YAYMAIL_PLUGIN_BASENAME ] )
				) {
					if ( isset( $site_transient_update_plugins->response[ YAYMAIL_PLUGIN_BASENAME ] ) ) {
						unset( $site_transient_update_plugins->response[ YAYMAIL_PLUGIN_BASENAME ] );
					}
					if ( isset( $site_transient_update_plugins->no_update[ YAYMAIL_PLUGIN_BASENAME ] ) ) {
						unset( $site_transient_update_plugins->no_update[ YAYMAIL_PLUGIN_BASENAME ] );
					}
					set_site_transient( 'update_plugins', $site_transient_update_plugins );
				}
			}
			add_filter(
				'auto_update_plugin',
				function( $value, $plugin ) {
					if ( YAYMAIL_PLUGIN_BASENAME === $plugin->plugin ) {
						return false;
					}
				},
				100,
				2
			);
		}
	}

	/**
	 * Call to EDD SL Plugin Updater
	 *
	 * @return void
	 */
	public function auto_update() {
		$license_key = $this->license_key;
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data = get_plugin_data( $this->file );
		$doing_cron  = defined( 'DOING_CRON' ) && DOING_CRON;
		if ( ! current_user_can( 'manage_options' ) && ! $doing_cron ) {
			return;
		}
		$args        = array(
			'version' => YAYMAIL_VERSION,
			'license' => $license_key,
			'author'  => $plugin_data['AuthorName'],
			'item_id' => YAYMAIL_ITEM_ID,
		);
		$edd_updater = new EDD_SL_Plugin_Updater(
			YAYMAIL_SELLER_API_URL,
			$this->file,
			$args
		);
	}

	/**
	 * Change api filters for auto update plugin
	 *
	 * @param object $_data Plugin version info get from API request.
	 *
	 * @param string $_action Action of API filters.
	 *
	 * @param array  $_args Arguments.
	 *
	 * @return object
	 */
	public function yaymail_api_filters( $_data, $_action = '', $_args = null ) {
		$slug = basename( $this->file, '.php' );
		if ( $slug === $_args->slug ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin_data = get_plugin_data( $this->file );
			$_data       = $this->yaymail_get_cache_version_info();
			// $_data = json_decode( get_option( 'yaymail_version_info_cache' ) );
			$sites = get_site_transient( 'update_plugins' );
			if ( ! isset( $sites->response[ YAYMAIL_PLUGIN_BASENAME ] ) ) {
				$sites->response[ YAYMAIL_PLUGIN_BASENAME ] = $_data;
				set_site_transient( 'update_plugins', $sites );
			}
			$_data->version        = $_data->new_version;
			$_data->author         = $plugin_data['AuthorName'];
			$_data->author_profile = $plugin_data['AuthorURI'];
			$_data->download_link  = '#';

			add_action(
				'admin_head',
				function() {
					?>
			<script>
				jQuery(document).ready(function(){
					var update_a_tag = document.querySelector('a[data-plugin="<?php echo esc_js( YAYMAIL_PLUGIN_BASENAME ); ?>"]#plugin_update_from_iframe');
					update_a_tag.innerHTML = '<?php esc_html_e( 'Active your license to update', 'yaymail' ); ?>';
					update_a_tag.href = '<?php echo esc_url( admin_url() ) . 'admin.php?page=wc-settings&tab=yaymail_pro_license'; ?>';
					update_a_tag.id = 'yaymail-activate-license';
					update_a_tag.target = '_blank';
				})
			</script>
					<?php
				},
				100
			);

			if ( isset( $_data->sections ) && ! is_array( $_data->sections ) ) {
				$_data->sections = maybe_unserialize( $_data->sections );
			}

			if ( isset( $_data->banners ) && ! is_array( $_data->banners ) ) {
				$_data->banners = maybe_unserialize( $_data->banners );
			}

			if ( isset( $_data->icons ) && ! is_array( $_data->icons ) ) {
				$_data->icons = maybe_unserialize( $_data->icons );
			}

			if ( isset( $_data->contributors ) && ! is_array( $_data->contributors ) ) {
				$_data->contributors = $this->convert_object_to_array( $_data->contributors );
			}

			if ( ! isset( $_data->plugin ) ) {
				$_data->plugin = YAYMAIL_PLUGIN_BASENAME;
			}
			return $_data;
		}
		return $_data;
	}

	/**
	 * Convert object to array
	 *
	 * @param object $data data.
	 */
	private function convert_object_to_array( $data ) {
		$new_data = array();
		foreach ( $data as $key => $value ) {
			$new_data[ $key ] = is_object( $value ) ? $this->convert_object_to_array( $value ) : $value;
		}

		return $new_data;
	}
}
