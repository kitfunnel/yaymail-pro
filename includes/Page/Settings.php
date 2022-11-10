<?php

namespace YayMail\Page;

use stdClass;
use YayMail\Ajax;
use YayMail\Page\Source\CustomPostType;
use YayMail\Page\Source\DefaultElement;
use YayMail\Templates\Templates;
use YayMail\Page\Source\PolylangHandler;
use YayMail\Page\Source\WPMLHandler;
use YayMail\I18n;
use YayMail\Helper\Products;

defined( 'ABSPATH' ) || exit;
/**
 * Settings Page
 */
class Settings {

	protected static $instance = null;

	public static function getInstance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
			self::$instance->doHooks();
		}

		return self::$instance;
	}

	private $email_customizer_hook_surfix = null;
	private $pageId                       = null;
	private $templateAccount;
	private $emails = null;
	public function doHooks() {
		$this->templateAccount = array( 'customer_new_account', 'customer_new_account_activation', 'customer_reset_password' );

		// Register Custom Post Type use Email Builder
		add_action( 'init', array( $this, 'registerCustomPostType' ) );

		// Register Menu
		add_action( 'admin_menu', array( $this, 'settingsMenu' ) );

		// Register Style & Script use for Menu Backend
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAdminScripts' ) );

		add_filter( 'plugin_action_links_' . YAYMAIL_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );

		add_filter( 'plugin_row_meta', array( $this, 'add_support_and_docs_links' ), 10, 2 );
		// Notice

		$optionNotice = get_option( 'yaymail_notice' );
		if ( time() >= (int) $optionNotice ) {
			add_action( 'admin_notices', array( $this, 'renderNotice' ) );
		}

		// Ajax display notive
		add_action( 'wp_ajax_yaymail_notice', array( $this, 'yaymailNotice' ) );

		// Add Woocommerce email setting columns
		add_filter( 'woocommerce_email_setting_columns', array( $this, 'yaymail_email_setting_columns' ) );
		add_action( 'woocommerce_email_setting_column_template', array( $this, 'column_template' ) );

		// Excute Ajax
		Ajax::getInstance();
	}
	public function __construct() {}

	public function renderNotice() {

		include YAYMAIL_PLUGIN_PATH . '/includes/Page/Source/DisplayAddonNotice.php';
	}

	public function yaymailNotice() {
		if ( isset( $_POST ) ) {
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : null;

			if ( ! wp_verify_nonce( $nonce, 'yaymail_nonce' ) ) {
				wp_send_json_error( array( 'status' => 'Wrong nonce validate!' ) );
				exit();
			}
			update_option( 'yaymail_notice', time() + 60 * 60 * 24 * 60 ); // After 60 days show
			wp_send_json_success();
		}
		wp_send_json_error( array( 'message' => 'Update fail!' ) );
	}

	public function yaymail_email_setting_columns( $array ) {
		if ( isset( $array['actions'] ) ) {
			unset( $array['actions'] );
			return array_merge(
				$array,
				array(
					'template' => '',
					'actions'  => '',
				)
			);
		}
		return $array;
	}
	public function column_template( $email ) {
		echo '<td class="wc-email-settings-table-template">
				<a class="button alignright" target="_blank" href="' . esc_attr( admin_url( 'admin.php?page=yaymail-settings' ) ) . '&template=' . esc_attr( $email->id ) . '">' . esc_html( __( 'Customize with YayMail', 'yaymail' ) ) . '</a></td>';
	}

	public function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=yaymail-settings' ) . '" aria-label="' . esc_attr__( 'View WooCommerce Email Builder', 'yaymail' ) . '">' . esc_html__( 'Start Customizing', 'yaymail' ) . '</a>',
		);
		return array_merge( $action_links, $links );
	}

	public function add_support_and_docs_links( $plugin_meta, $plugin_file ) {
		if ( YAYMAIL_PLUGIN_BASENAME === $plugin_file ) {
			$plugin_meta[] = '<a target="_blank" href="https://docs.yaycommerce.com/yaymail/getting-started/introduction">Docs</a>';
			$plugin_meta[] = '<a target="_blank" href="https://yaycommerce.com/support/">Support</a>';
		}
		return $plugin_meta;
	}

	public function registerCustomPostType() {
		$labels       = array(
			'name'               => __( 'Email Template', 'yaymail' ),
			'singular_name'      => __( 'Email Template', 'yaymail' ),
			'add_new'            => __( 'Add New Email Template', 'yaymail' ),
			'add_new_item'       => __( 'Add a new Email Template', 'yaymail' ),
			'edit_item'          => __( 'Edit Email Template', 'yaymail' ),
			'new_item'           => __( 'New Email Template', 'yaymail' ),
			'view_item'          => __( 'View Email Template', 'yaymail' ),
			'search_items'       => __( 'Search Email Template', 'yaymail' ),
			'not_found'          => __( 'No Email Template found', 'yaymail' ),
			'not_found_in_trash' => __( 'No Email Template currently trashed', 'yaymail' ),
			'parent_item_colon'  => '',
		);
		$capabilities = array();
		$args         = array(
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => false,
			'query_var'           => true,
			'rewrite'             => true,
			'capability_type'     => 'yaymail_template',
			'capabilities'        => $capabilities,
			'hierarchical'        => false,
			'menu_position'       => null,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'supports'            => array( 'title', 'author', 'thumbnail', 'revisions' ),
		);
		register_post_type( 'yaymail_template', $args );
	}
	public function settingsMenu() {
		$this->email_customizer_hook_surfix = add_submenu_page( 'woocommerce', __( 'Email Builder Settings', 'yaymail' ), __( 'Email Customizer', 'yaymail' ), 'manage_woocommerce', $this->getPageId(), array( $this, 'settingsPage' ) );
	}


	public function nitWebPluginRegisterButtons( $buttons ) {
		$buttons[] = 'table';
		$buttons[] = 'searchreplace';
		$buttons[] = 'visualblocks';
		$buttons[] = 'code';
		$buttons[] = 'insertdatetime';
		$buttons[] = 'autolink';
		$buttons[] = 'contextmenu';
		$buttons[] = 'advlist';
		return $buttons;
	}

	public function njtWebPluginRegisterPlugin( $plugin_array ) {
		$plugin_array['table']          = YAYMAIL_PLUGIN_URL . 'assets/tinymce/table/plugin.min.js';
		$plugin_array['searchreplace']  = YAYMAIL_PLUGIN_URL . 'assets/tinymce/searchreplace/plugin.min.js';
		$plugin_array['visualblocks']   = YAYMAIL_PLUGIN_URL . 'assets/tinymce/visualblocks/plugin.min.js';
		$plugin_array['code']           = YAYMAIL_PLUGIN_URL . 'assets/tinymce/code/plugin.min.js';
		$plugin_array['insertdatetime'] = YAYMAIL_PLUGIN_URL . 'assets/tinymce/insertdatetime/plugin.min.js';
		$plugin_array['autolink']       = YAYMAIL_PLUGIN_URL . 'assets/tinymce/autolink/plugin.min.js';
		$plugin_array['contextmenu']    = YAYMAIL_PLUGIN_URL . 'assets/tinymce/contextmenu/plugin.min.js';
		$plugin_array['advlist']        = YAYMAIL_PLUGIN_URL . 'assets/tinymce/advlist/plugin.min.js';
		return $plugin_array;
	}

	public function settingsPage() {
		// When load this page will not show adminbar
		?>
		<style type="text/css">
			#wpcontent, #footer {opacity: 0}
			#adminmenuback, #adminmenuwrap { display: none !important; }
		</style>
		<script type="text/javascript" id="yaymail-onload">
			jQuery(document).ready( function() {
				jQuery('#adminmenuback, #adminmenuwrap').remove();
			});
		</script>
		<?php
		// add new buttons
		add_filter( 'mce_buttons', array( $this, 'nitWebPluginRegisterButtons' ) );

		// Load the TinyMCE plugin
		add_filter( 'mce_external_plugins', array( $this, 'njtWebPluginRegisterPlugin' ) );
		$viewPath = YAYMAIL_PLUGIN_PATH . 'views/pages/html-settings.php';
		include_once $viewPath;
	}

	public function enqueueAdminScripts( $screenId ) {
		global $wpdb, $sitepress;

		if ( class_exists( 'SitePress' ) ) {
			add_filter(
				'woocommerce_order_get_items',
				function( $items, $order ) {
					$order_id = $order->get_id();
					WPMLHandler::wpml_get_current_language_multilingual( $order_id );
					return $items;
				},
				9,
				2
			);
		}
		if ( class_exists( 'SitePress' ) ) {
			WPMLHandler::wpml_turn_off_dupplicate_post_type();
			// WPMLHandler::wpml_turn_on_admin_edit_language();
			global $pagenow;
			if ( 'post.php' === $pagenow ) {
				if ( isset( $_REQUEST['post'] ) && ! empty( $_REQUEST['post'] ) && isset( $_REQUEST['action'] ) && 'edit' === $_REQUEST['action'] ) {
					$post_type = get_post_type( sanitize_text_field( $_REQUEST['post'] ) );
					if ( 'shop_order' === $post_type ) {
						$current_order_language = WPMLHandler::wpml_get_current_language_multilingual( sanitize_text_field( $_REQUEST['post'] ) );
						if ( ! class_exists( 'woocommerce_wpml' ) ) {
							$sitepress->switch_lang( $current_order_language, true );
						}
					}
				}
			}
		}
		if ( class_exists( 'Polylang' ) ) {
			PolylangHandler::polylang_turn_off_dupplicate_post_type();
			global $pagenow;
			if ( 'post.php' === $pagenow ) {
				if ( isset( $_REQUEST['post'] ) && ! empty( $_REQUEST['post'] ) && isset( $_REQUEST['action'] ) && 'edit' === $_REQUEST['action'] ) {
					$post_type = get_post_type( sanitize_text_field( $_REQUEST['post'] ) );
					if ( 'shop_order' === $post_type ) {
						$current_edit_order = new \WC_Order( sanitize_text_field( $_REQUEST['post'] ) );
						$edit_order_items   = $current_edit_order->get_items();
						foreach ( $edit_order_items as $key => $item ) {
							$edit_product_id = $item->get_product_id();
							break;
						}
						$current_order_language = pll_get_post_language( $edit_product_id );
						PolylangHandler::polylang_switch_language_front_end( $current_order_language );
					}
				}
			}
		}
		if ( strpos( $screenId, 'yaymail-settings' ) !== false && class_exists( 'WC_Emails' ) ) {
			// Filter to active tinymce
			add_filter( 'user_can_richedit', '__return_true', PHP_INT_MAX );
			// Get list template from Woo
			$wc_emails    = \WC_Emails::instance();
			$this->emails = (array) $wc_emails::instance()->emails;
			unset( $this->emails['WC_TrackShip_Email_Manager'] );
			if ( isset( $this->emails['WC_GZD_Email_Customer_Shipment'] ) ) {
				$partial_email              = new stdClass();
				$partial_email->id          = 'customer_partial_shipment';
				$partial_email->title       = __( 'Order partial shipped', 'yaymail' );
				$customer_shipment_position = array_search( 'WC_GZD_Email_Customer_Shipment', array_keys( $this->emails ) );
				$this->emails               = array_merge(
					array_slice( $this->emails, 0, $customer_shipment_position ),
					array( 'WC_GZD_Email_Customer_Partial_Shipment' => $partial_email ),
					array_slice( $this->emails, $customer_shipment_position )
				);
			}
			if ( class_exists( 'AW_Referrals_Plugin_Data' ) && ( is_plugin_active( 'yaymail-addon-for-automatewoo/yaymail-automatewoo.php' ) || is_plugin_active( 'email-customizer-automatewoo/yaymail-automatewoo.php' ) ) ) {
				$referrals_email        = new stdClass();
				$referrals_email->id    = 'AutomateWoo_Referrals_Email';
				$referrals_email->title = __( 'AutomateWoo Referrals Email', 'yaymail' );
				$this->emails           = array_merge( $this->emails, array( 'AutomateWoo_Referrals_Email' => $referrals_email ) );
			}
			if ( class_exists( 'CWG_Instock_Notifier' ) ) {
				$notifierInstockMail                             = array();
				$notifierInstockMail['WC_Notifier_Instock_Mail'] = (object) array(
					'id'    => 'notifier_instock_mail',
					'title' => 'Notifier Instock Mail',
				);

				$this->emails = array_merge( $this->emails, $notifierInstockMail );
			}
			if ( function_exists( 'alg_get_custom_order_statuses_from_cpt' ) ) {
				$custom_statuses_array = alg_get_custom_order_statuses_from_cpt();
				$array                 = array();
				foreach ( $custom_statuses_array as $key => $value ) {
					$array[ $key ] = (object) array(
						'id'    => $key,
						'title' => $value,
					);
					$this->emails  = array_merge( $this->emails, $array );
				}
			}

			if ( class_exists( 'CWG_Instock_Notifier' ) ) {
				$notifierSubscribeMail                               = array();
				$notifierSubscribeMail['WC_Notifier_Subscribe_Mail'] = (object) array(
					'id'    => 'notifier_subscribe_mail',
					'title' => 'Notifier Subscribe Mail',
				);
				$this->emails                                        = array_merge( $this->emails, $notifierSubscribeMail );
			}
			$this->emails = apply_filters( 'YaymailCreateGermanMarketTemplates', $this->emails );

			// Check active language
			$listLanguages       = array();
				$active_language = 'en';
			if ( class_exists( 'Loco_Locale' ) ) {
				$api                      = new \Loco_api_WordPressTranslations();
				$installed_language_codes = $api->getInstalledCore();
				$listLanguages            = array();
				foreach ( $installed_language_codes as $key => $code ) {
					$lang        = $api->getLocale( $code );
					$lang_active = get_user_locale() === $code ? true : false;
					if ( null == $lang ) {
						$listLanguages[] = array(
							'code'   => 'en_US',
							'lang'   => 'en_US',
							'name'   => 'en_US' === $code ? 'English' : $code,
							'icon'   => $code,
							'active' => $lang_active,
						);
					} else {
						$listLanguages[] = array(
							'code'   => $code,
							'lang'   => $lang->__get( 'lang' ),
							'name'   => $lang->getName(),
							'icon'   => $lang->getIcon(),
							'active' => $lang_active,
						);
					}
				}
				$active_language = get_user_locale();
			} elseif ( class_exists( 'SitePress' ) ) {
				foreach ( icl_get_languages() as $key => $lang ) {
					$listLanguages[] = array(
						'code'   => $lang['code'],
						'name'   => $lang['native_name'],
						'flag'   => $lang['country_flag_url'],
						'active' => $lang['active'],
					);
				}
				$active_language = defined( 'ICL_LANGUAGE_CODE' ) ? ICL_LANGUAGE_CODE : 'en';
			} elseif ( class_exists( 'Polylang' ) ) {
				foreach ( icl_get_languages() as $key => $lang ) {
					$listLanguages[] = array(
						'code'   => $lang['language_code'],
						'name'   => $lang['native_name'],
						'flag'   => $lang['country_flag_url'],
						'active' => $lang['active'],
					);
				}
				$active_language = isset( $_COOKIE['pll_language'] ) ? sanitize_text_field( $_COOKIE['pll_language'] ) : 'en';
			} elseif ( class_exists( 'TRP_Translate_Press' ) ) {
				$trp                 = \TRP_Translate_Press::get_trp_instance();
				$trp_languages       = $trp->get_component( 'languages' );
				$trp_settings        = $trp->get_component( 'settings' );
				$published_languages = $trp_languages->get_language_names( $trp_settings->get_settings()['publish-languages'] );
				$active_language     = isset( $_COOKIE['pll_language'] ) ? sanitize_text_field( $_COOKIE['pll_language'] ) : $trp_settings->get_settings()['default-language'];
				foreach ( $published_languages as $code => $lang_name ) {
					$flags_path = TRP_PLUGIN_URL . 'assets/images/flags/';
					$flags_path = apply_filters( 'trp_flags_path', $flags_path, $code );

					$flag_file_name = $code . '.png';
					$flag_file_name = apply_filters( 'trp_flag_file_name', $flag_file_name, $lang_name );

					$listLanguages[] = array(
						'code'   => $code,
						'name'   => $lang_name,
						'flag'   => $flags_path . $flag_file_name,
						'active' => $active_language,
					);
				}
			} elseif ( defined( 'WEGLOT_NAME' ) ) {
				wp_enqueue_style( 'new-flag-css', WEGLOT_DIRURL . 'app/styles/new-flags.css', array(), WEGLOT_VERSION );
				wp_enqueue_style( 'weglot-css', WEGLOT_URL_DIST . '/css/front-css.css', array(), WEGLOT_VERSION );
				$request_url_services = weglot_get_service( 'Request_Url_Service_Weglot' );
				$language_services    = weglot_get_service( 'Language_Service_Weglot' );
				$languages            = $language_services->get_original_and_destination_languages( $request_url_services->is_allowed_private() );
				if ( isset( $_COOKIE['pll_language'] ) && 'en' !== sanitize_text_field( $_COOKIE['pll_language'] ) ) {
					$active_language = sanitize_text_field( $_COOKIE['pll_language'] );
				} else {
					$active_language = 'en';
				}
				$current_language = $language_services->get_language_from_internal( $active_language );
				$listLanguages    = array_map(
					function( $lang ) use ( $current_language, $active_language ) {
						return array(
							'code'   => $lang->getInternalCode(),
							'name'   => $lang->getLocalName(),
							'flag'   => '',
							'active' => $active_language === $lang->getInternalCode(),
						);
					},
					$languages
				);
			}

			update_option( 'yaymail_customizer_page_language', $active_language );

			// Insert database all order template from Woo
			$templateEmail = Templates::getInstance();
			$templates     = $templateEmail::getList();

			foreach ( $templates as $key => $template ) {
				$postIDTemplate = CustomPostType::postIDByTemplate( $key );
				if ( ! $postIDTemplate ) {
					global $wpdb;
					$hold_post_id   = $wpdb->get_results( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_yaymail_template' AND meta_value = %s", $key ) );
					$hold_elements  = '';
					$hold_text_link = '';
					if ( count( $hold_post_id ) ) {
						$hold_elements  = get_post_meta( $hold_post_id[0]->post_id, '_yaymail_elements', true );
						$hold_text_link = get_post_meta( $hold_post_id[0]->post_id, '_yaymail_email_textLinkColor_settings', true );
					}
					$arr    = array(
						'mess'                            => '',
						'post_date'                       => current_time( 'Y-m-d H:i:s' ),
						'post_type'                       => 'yaymail_template',
						'post_status'                     => 'publish',
						'_yaymail_template'               => $key,
						'_email_backgroundColor_settings' => 'rgb(236, 236, 236)',
						'_yaymail_elements'               => '' === $hold_elements ? json_decode( $template['elements'], true ) : $hold_elements,
						'_yaymail_email_textLinkColor_settings' => '' === $hold_text_link ? '' : $hold_text_link,
					);
					$insert = CustomPostType::insert( $arr );
				}
			}

			/*
			@@@@ Enable Disable
			@@@@ note: Note the default value section is required when displaying in vue
			 */

			$settingDefaultEnableDisable = array(
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

			$settingEnableDisables = ( CustomPostType::templateEnableDisable( false ) ) ? CustomPostType::templateEnableDisable( false ) : $settingDefaultEnableDisable;

			foreach ( $this->emails as $key => $value ) {
				if ( 'ORDDD_Email_Delivery_Reminder' == $key ) {
					$value->id = 'orddd_delivery_reminder_customer';
				}
				if ( 'WCVendors_Admin_Notify_Approved' == $key ) {
					$value->id = 'admin_notify_approved';
				}
				if ( isset( $value->id ) ) {
					if ( ! array_key_exists( $value->id, $settingEnableDisables ) ) {
						$settingEnableDisables[ $value->id ] = '0';
					}
				} else {
					if ( ! array_key_exists( $value['id'], $settingEnableDisables ) ) {
						$settingEnableDisables[ $value['id'] ] = '0';
					}
				}
			}

			$this->emails          = apply_filters( 'YaymailCreateFollowUpTemplates', $this->emails );
			$settingEnableDisables = apply_filters( 'YaymailCreateSelectFollowUpTemplates', $settingEnableDisables );

			$this->emails          = apply_filters( 'YaymailCreateAutomateWooTemplates', $this->emails );
			$settingEnableDisables = apply_filters( 'YaymailCreateSelectAutomateWooTemplates', $settingEnableDisables );

			$this->emails          = apply_filters( 'YaymailCreateTrackShipWooTemplates', $this->emails );
			$settingEnableDisables = apply_filters( 'YaymailCreateSelectTrackShipWooTemplates', $settingEnableDisables );

			$this->emails          = apply_filters( 'YaymailCreateWCFMWooFMTemplates', $this->emails );
			$settingEnableDisables = apply_filters( 'YaymailCreateSelectWCFMWooFMTemplates', $settingEnableDisables );

			$settingDefaultGenerals = array(
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
				'image_position'               => 'Top',
			);
			$settingGenerals        = get_option( 'yaymail_settings' ) ? get_option( 'yaymail_settings' ) : $settingDefaultGenerals;
			foreach ( $settingDefaultEnableDisable as $keyDefaultEnableDisable => $settingDefaultEnableDisable ) {
				if ( ! array_key_exists( $keyDefaultEnableDisable, $settingEnableDisables ) ) {
					$settingEnableDisables[ $keyDefaultEnableDisable ] = $settingDefaultEnableDisable;
				};
			}
			$settings['enableDisable'] = $settingEnableDisables;

			/*
			@@@@ General
			@@@@ note: Note the default value section is required when displaying in vue
			 */

			$settingGenerals = get_option( 'yaymail_settings' ) ? get_option( 'yaymail_settings' ) : $settingDefaultGenerals;
			foreach ( $settingDefaultGenerals as $keyDefaultGeneral => $settingGeneral ) {
				if ( ! array_key_exists( $keyDefaultGeneral, $settingGenerals ) ) {
					$settingGenerals[ $keyDefaultGeneral ] = $settingDefaultGenerals[ $keyDefaultGeneral ];
				};
			}

			$settingGenerals['direction_rtl'] = get_option( 'yaymail_direction' ) ? get_option( 'yaymail_direction' ) : 'ltr';
			$settings['general']              = $settingGenerals;

			$scriptId = $this->getPageId();
			$order    = CustomPostType::getListOrders();
			wp_enqueue_script( 'vue', YAYMAIL_PLUGIN_URL . ( YAYMAIL_DEBUG ? 'assets/libs/vue.js' : 'assets/libs/vue.min.js' ), '', YAYMAIL_VERSION, true );
			wp_enqueue_script( 'vuex', YAYMAIL_PLUGIN_URL . 'assets/libs/vuex.js', '', YAYMAIL_VERSION, true );

			wp_enqueue_script( $scriptId, YAYMAIL_PLUGIN_URL . 'assets/dist/js/main.js', array( 'jquery' ), YAYMAIL_VERSION, true );
			wp_enqueue_style( $scriptId, YAYMAIL_PLUGIN_URL . 'assets/dist/css/main.css', array(), YAYMAIL_VERSION );

			wp_enqueue_script( $scriptId . '-script', YAYMAIL_PLUGIN_URL . 'assets/admin/js/script.js', '', YAYMAIL_VERSION, true );
			wp_enqueue_script( $scriptId . '-plugin-install', YAYMAIL_PLUGIN_URL . 'assets/admin/js/plugin-install.js', '', YAYMAIL_VERSION, true );
			$yaymailSettings = get_option( 'yaymail_settings' );

			// Load ACE Editor -Start
			if ( isset( $yaymailSettings['enable_css_custom'] ) && 'yes' == $yaymailSettings['enable_css_custom'] ) {
				wp_enqueue_script( $scriptId . 'ace-script', YAYMAIL_PLUGIN_URL . 'assets/aceeditor/ace.js', '', YAYMAIL_VERSION, true );
				wp_enqueue_script( $scriptId . 'ace1-script', YAYMAIL_PLUGIN_URL . 'assets/aceeditor/ext-language_tools.js', '', YAYMAIL_VERSION, true );
				wp_enqueue_script( $scriptId . 'ace2-script', YAYMAIL_PLUGIN_URL . 'assets/aceeditor/mode-css.js', '', YAYMAIL_VERSION, true );
				wp_enqueue_script( $scriptId . 'ace3-script', YAYMAIL_PLUGIN_URL . 'assets/aceeditor/theme-merbivore_soft.js', '', YAYMAIL_VERSION, true );
				wp_enqueue_script( $scriptId . 'ace4-script', YAYMAIL_PLUGIN_URL . 'assets/aceeditor/worker-css.js', '', YAYMAIL_VERSION, true );
				wp_enqueue_script( $scriptId . 'ace5-script', YAYMAIL_PLUGIN_URL . 'assets/aceeditor/snippets/css.js ', '', YAYMAIL_VERSION, true );
			} else {
				wp_dequeue_script( $scriptId . 'ace-script' );
				wp_dequeue_script( $scriptId . 'ace1-script' );
				wp_dequeue_script( $scriptId . 'ace2-script' );
				wp_dequeue_script( $scriptId . 'ace3-script' );
				wp_dequeue_script( $scriptId . 'ace4-script' );
				wp_dequeue_script( $scriptId . 'ace5-script' );
			}
			// Load ACE Editor -End
			// Css for page admin of WordPress.
			wp_enqueue_style( $scriptId . '-css', YAYMAIL_PLUGIN_URL . 'assets/admin/css/css.css', array(), YAYMAIL_VERSION );
			$current_user       = wp_get_current_user();
			$default_email_test = false != get_user_meta( get_current_user_id(), 'yaymail_default_email_test', true ) ? get_user_meta( get_current_user_id(), 'yaymail_default_email_test', true ) : $current_user->user_email;
			$element            = new DefaultElement();

			$yaymailSettingsDefaultLogo   = get_option( 'yaymail_settings_default_logo_' . $active_language );
			$setDefaultLogo               = false != $yaymailSettingsDefaultLogo ? $yaymailSettingsDefaultLogo['set_default'] : '0';
			$yaymailSettingsDefaultFooter = get_option( 'yaymail_settings_default_footer_' . $active_language );
			$setDefaultFooter             = false != $yaymailSettingsDefaultFooter ? $yaymailSettingsDefaultFooter['set_default'] : '0';
			if ( isset( $_GET['template'] ) || ! empty( $_GET['template'] ) ) {
				$req_template['id'] = sanitize_text_field( $_GET['template'] );
			} else {
				$req_template['id'] = 'new_order';
			}
			foreach ( $this->emails as $value ) {
				if ( $value->id == $req_template['id'] ) {
					$req_template['title'] = $value->title;
				}
			}

			// List email supported

			$list_email_supported = array(
				'WC_Subscription'                          => array(
					'plugin_name'   => 'WooCommerce Subscriptions',
					'template_name' => array(
						'cancelled_subscription',
						'expired_subscription',
						'suspended_subscription',
						'customer_completed_renewal_order',
						'customer_completed_switch_order',
						'customer_on_hold_renewal_order',
						'customer_renewal_invoice',
						'new_renewal_order',
						'new_switch_order',
						'customer_processing_renewal_order',
						'customer_payment_retry',
						'payment_retry',
						'_enr_customer_auto_renewal_reminder',
						'_enr_customer_expiry_reminder',
						'_enr_customer_manual_renewal_reminder',
						'_enr_customer_processing_shipping_fulfilment_order',
						'_enr_customer_shipping_frequency_notification',
						'_enr_customer_subscription_price_updated',
						'_enr_customer_trial_ending_reminder',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-woo-subscriptions',
				),
				'yith_wishlist_constructor'                => array(
					'plugin_name'   => 'YITH WooCommerce Wishlist Premium',
					'template_name' => array(
						'estimate_mail',
						'yith_wcwl_back_in_stock',
						'yith_wcwl_on_sale_item',
						'yith_wcwl_promotion_mail',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-wishlist',
				),
				'SUMO_Subscription'                        => array(
					'plugin_name'   => 'SUMO Subscription',
					'template_name' => array(
						'subscription_new_order',
						'subscription_new_order_old_subscribers',
						'subscription_processing_order',
						'subscription_completed_order',
						'subscription_pause_order',
						'subscription_invoice_order_manual',
						'subscription_expiry_reminder',
						'subscription_automatic_charging_reminder',
						'subscription_renewed_order_automatic',
						'auto_to_manual_subscription_renewal',
						'subscription_overdue_order_automatic',
						'subscription_overdue_order_manual',
						'subscription_suspended_order_automatic',
						'subscription_suspended_order_manual',
						'subscription_preapproval_access_revoked',
						'subscription_turnoff_automatic_payments_success',
						'subscription_pending_authorization',
						'subscription_cancel_order',
						'subscription_cancel_request_submitted',
						'subscription_cancel_request_revoked',
						'subscription_expired_order',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-sumo-subscriptions',
				),

				'YITH_Subscription'                        => array(
					'plugin_name'   => 'YITH WooCommerce Subscription Premium',
					'template_name' => array(
						'ywsbs_subscription_admin_mail',
						'ywsbs_customer_subscription_cancelled',
						'ywsbs_customer_subscription_suspended',
						'ywsbs_customer_subscription_expired',
						'ywsbs_customer_subscription_before_expired',
						'ywsbs_customer_subscription_paused',
						'ywsbs_customer_subscription_resumed',
						'ywsbs_customer_subscription_request_payment',
						'ywsbs_customer_subscription_renew_reminder',
						'ywsbs_customer_subscription_payment_done',
						'ywsbs_customer_subscription_payment_failed',
						'ywsbs_customer_subscription_delivery_schedules',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-subscription',
				),
				'woo-b2b'                                  => array(
					'plugin_name'   => 'WooCommerce B2B',
					'template_name' => array(
						'wcb2b_customer_onquote_order',
						'wcb2b_customer_quoted_order',
						'wcb2b_customer_status_notification',
						'wcb2b_new_quote',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-b2b-woocommerce',
				),
				'YITH_Vendors'                             => array(
					'plugin_name'   => 'YITH WooCommerce Multi Vendor Premium',
					'template_name' => array(
						'cancelled_order_to_vendor',
						'commissions_paid',
						'commissions_unpaid',
						'new_order_to_vendor',
						'new_vendor_registration',
						'product_set_in_pending_review',
						'vendor_commissions_bulk_action',
						'vendor_commissions_paid',
						'vendor_new_account',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-multivendor',
				),
				'Germanized_Pro'                           => array(
					'plugin_name'   => 'Germanized for WooCommerce',
					'template_name' => array(
						'sab_cancellation_invoice',
						'sab_document',
						'sab_document_admin',
						'sab_simple_invoice',
						'sab_packing_slip',
						'customer_paid_for_order',
						'customer_cancelled_order',
						'customer_order_confirmation',
						'customer_revocation',
						'customer_new_account_activation',
						'customer_shipment',
						'customer_partial_shipment',
						'customer_return_shipment',
						'customer_return_shipment_delivered',
						'new_return_shipment_request',
						'customer_trusted_shops',
						'customer_sepa_direct_debit_mandate',
						'customer_guest_return_shipment_request',
						'oss_delivery_threshold_email_notification',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-germanized',
				),
				'WC_Bookings'                              => array(
					'plugin_name'   => 'WooCommerce Bookings',
					'template_name' => array(
						'admin_booking_cancelled',
						'booking_cancelled',
						'booking_confirmed',
						'booking_notification',
						'booking_pending_confirmation',
						'booking_reminder',
						'new_booking',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-woo-bookings',
				),
				'WooCommerce_Waitlist'                     => array(
					'plugin_name'   => 'WooCommerce Waitlist',
					'template_name' => array(
						'woocommerce_waitlist_joined_email',
						'woocommerce_waitlist_left_email',
						'woocommerce_waitlist_mailout',
						'woocommerce_waitlist_signup_email',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-woo-waitlist',
				),
				'WooCommerce_Quotes'                       => array(
					'plugin_name'   => 'Quotes for WooCommerce',
					'template_name' => array(
						'qwc_req_new_quote',
						'qwc_request_sent',
						'qwc_send_quote',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-quotes-woocommerce',
				),
				'YITH_Pre_Order'                           => array(
					'plugin_name'   => 'YITH Pre-Order for WooCommerce Premium ',
					'template_name' => array(
						'yith_ywpo_date_end',
						'yith_ywpo_sale_date_changed',
						'yith_ywpo_is_for_sale',
						'yith_ywpo_out_of_stock',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-pre-order',
				),
				'WooCommerce_Appointments'                 => array(
					'plugin_name'   => 'WooCommerce Appointments',
					'template_name' => array(
						'admin_appointment_cancelled',
						'admin_new_appointment',
						'appointment_cancelled',
						'appointment_confirmed',
						'appointment_follow_up',
						'appointment_reminder',
						'admin_appointment_rescheduled',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-appointments-woocommerce',
				),
				'SG_Order_Approval'                        => array(
					'plugin_name'   => 'Sg Order Approval for Woocommerce',
					'template_name' => array(
						'wc_admin_order_new',
						'wc_customer_order_new',
						'wc_customer_order_approved',
						'wc_customer_order_rejected',
						'sgitsoa_wc_admin_order_new',
						'sgitsoa_wc_customer_order_new',
						'sgitsoa_wc_customer_order_approved',
						'sgitsoa_wc_customer_order_rejected',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-order-approval-woocommerce',
				),
				'YITH_Pre_Order'                           => array(
					'plugin_name'   => 'YITH Pre-Order for WooCommerce Premium ',
					'template_name' => array(
						'yith_ywpo_date_end',
						'yith_ywpo_sale_date_changed',
						'yith_ywpo_is_for_sale',
						'yith_ywpo_out_of_stock',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-pre-order',
				),
				'Follow_Up_Emails'                         => array(
					'plugin_name'   => 'Follow Up Emails for WooCommerce ',
					'template_name' => apply_filters( 'YaymailCreateListFollowUpNames', array() ),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-woo-follow-ups',
				),
				'Order_Delivery_Date'                      => array(
					'plugin_name'   => 'Order Delivery Date Pro for WooCommerce',
					'template_name' => array(
						'orddd_delivery_reminder',
						'orddd_delivery_reminder_customer',
						'orddd_update_date',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-order-delivery-date',
				),
				'WC_Email_Cancelled_Customer_Order'        => array(
					'plugin_name'   => 'Order Cancellation Email to Customer',
					'template_name' => array(
						'wc_customer_cancelled_order',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-order-cancellation-email-customer',
				),
				'WC_Smart_Coupons'                         => array(
					'plugin_name'   => 'WooCommerce Smart Coupons',
					'template_name' => array(
						'wc_sc_combined_email_coupon',
						'wc_sc_acknowledgement_email',
						'wc_sc_email_coupon',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-smart-coupons',
				),
				'Dokan'                                    => array(
					'plugin_name'   => 'Dokan',
					'template_name' => array(
						'dokan_new_seller',
						'dokan_email_vendor_disable',
						'dokan_email_vendor_enable',
						'dokan_contact_seller',
						'new_product',
						'new_product_pending',
						'pending_product_published',
						'updated_product_pending',
						'dokan_vendor_new_order',
						'dokan_vendor_completed_order',
						'dokan_vendor_withdraw_request',
						'dokan_vendor_withdraw_cancelled',
						'dokan_vendor_withdraw_approved',
						'dokan_refund_request',
						'dokan_vendor_refund',
						'dokan_announcement',
						'dokan_staff_new_order',
						'Dokan_Email_Wholesale_Register',
						'dokan_email_shipping_status_tracking',
						'dokan_email_subscription_invoice',
						'updates_for_store_followers',
						'vendor_new_store_follower',
						'dokan_product_enquiry_email',
						'dokan_report_abuse_admin_email',
						'Dokan_Send_Coupon_Email',
						'Dokan_Rma_Send_Warranty_Request',
						'dokan_new_support_ticket',
						'dokan_reply_to_store_support_ticket',
						'dokan_reply_to_user_support_ticket',
						'dokan_vendor_refund_canceled',
						'Dokan_Email_Booking_New',
						'Dokan_Email_Booking_Cancelled_NEW',
						'DokanNewSupportTicketForAdmin',
						'DokanReplyToAdminSupportTicket_vendor_customer',
						'reverse_withdrawal_invoice',
						'dokan_staff_password_update',
						'dokan_new_store_review',
						'Dokan_Subscription_Cancelled',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-dokan',
				),
				'Woocommerce_German_Market'                => array(
					'plugin_name'   => 'Woocommerce_German_Market',
					'template_name' => array(
						'wgm_confirm_order_email',
						'wgm_double_opt_in_customer_registration',
						'wgm_sepa',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-german-market',
				),
				'B2B_Wholesale_Suite'                      => array(
					'plugin_name'   => 'B2B & Wholesale Suite',
					'template_name' => array(
						'b2bwhs_new_customer_email',
						'b2bwhs_new_customer_requires_approval_email',
						'b2bwhs_new_message_email',
						'b2bwhs_new_quote_email',
						'b2bwhs_your_account_approved_email',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-b2b-wholesale-suite',
				),
				'WooCommerce_Deposits'                     => array(
					'plugin_name'   => 'WooCommerce Deposits',
					'template_name' => array(
						'customer_deposit_partially_paid',
						'customer_partially_paid',
						'customer_second_payment_reminder',
						'full_payment',
						'partial_payment',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-woocommerce-deposits',
				),
				'YITH_Booking'                             => array(
					'plugin_name'   => 'YITH Booking and Appointment for WooCommerce Premium',
					'template_name' => array(
						'yith_wcbk_admin_new_booking',
						'yith_wcbk_booking_status',
						'yith_wcbk_customer_booking_note',
						'yith_wcbk_customer_cancelled_booking',
						'yith_wcbk_customer_completed_booking',
						'yith_wcbk_customer_confirmed_booking',
						'yith_wcbk_customer_new_booking',
						'yith_wcbk_customer_paid_booking',
						'yith_wcbk_customer_unconfirmed_booking',
						'yith_wcbk_booking_status_vendor',
						'yith_wcbk_vendor_new_booking',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-booking',
				),
				'WooCommerce_Points_Rewards'               => array(
					'plugin_name'   => 'Points and Rewards for WooCommerce',
					'template_name' => array(
						'mwb_wpr_email_notification',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-points-and-rewards',
				),
				'PW_WooCommerce_Gift_Cards'                => array(
					'plugin_name'   => 'PW WooCommerce Gift Cards',
					'template_name' => array(
						'pwgc_email',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-pw-gift-cards',
				),
				'YITH_WooCommerce_Gift_Cards_Premium'      => array(
					'plugin_name'   => 'YITH WooCommerce Gift Cards Premium',
					'template_name' => array(
						'ywgc-email-delivered-gift-card',
						'ywgc-email-send-gift-card',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-gift-cards',
				),
				'YITH_WooCommerce_Membership_Premium'      => array(
					'plugin_name'   => 'YITH WooCommerce Membership Premium',
					'template_name' => array(
						'membership_cancelled',
						'membership_expired',
						'membership_expiring',
						'membership_welcome',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-membership',
				),
				'WooCommerce_Order_Delivery'               => array(
					'plugin_name'   => 'WooCommerce Order Delivery',
					'template_name' => array(
						'subscription_delivery_note',
						'order_delivery_note',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-themesquad-delivery-date',
				),
				'WooCommerce_Simple_Auction'               => array(
					'plugin_name'   => 'WooCommerce Simple Auction',
					'template_name' => array(
						'auction_buy_now',
						'auction_closing_soon',
						'auction_fail',
						'auction_finished',
						'auction_relist',
						'auction_relist_user',
						'auction_win',
						'bid_note',
						'customer_bid_note',
						'outbid_note',
						'remind_to_pay',
						'Reserve_fail',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-simple-auctions',
				),
				'vendors_marketplace'                      => array(
					'plugin_name'   => 'Vendors marketplace',
					'template_name' => array(
						'admin_notify_approved',
						'admin_notify_application',
						'admin_notify_product',
						'admin_notify_shipped',
						'customer_notify_shipped',
						'vendor_notify_application',
						'vendor_notify_approved',
						'vendor_notify_cancelled_order',
						'vendor_notify_denied',
						'vendor_notify_order',
						'vendor_application',
						'admin_new_vendor_product',
						'vendor_notify_shipped',
						'vendor_new_order',
						'customer-mark-received',
						'store_contact',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-wc-vendors',
				),
				'WooCommerce_Pre_Orders'                   => array(
					'plugin_name'   => 'WooCommerce Pre-Orders',
					'template_name' => array(
						'wc_pre_orders_new_pre_order',
						'wc_pre_orders_pre_order_available',
						'wc_pre_orders_pre_order_cancelled',
						'wc_pre_orders_pre_order_date_changed',
						'wc_pre_orders_pre_ordered',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-woo-pre-orders',
				),
				'WooCommerce_Split_Orders'                 => array(
					'plugin_name'   => 'WooCommerce Split Orders',
					'template_name' => array(
						'customer_order_split',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-split-orders',
				),
				'WP_Crowdfunding'                          => array(
					'plugin_name'   => 'WP Crowdfunding',
					'template_name' => array(
						'wp_crowdfunding_campaign_accept',
						'wp_crowdfunding_submit_campaign',
						'wp_crowdfunding_campaign_update_email',
						'wp_crowdfunding_new_backed',
						'wp_crowdfunding_new_user',
						'wp_crowdfunding_target_reached_email',
						'wp_crowdfunding_withdraw_request',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-wp-crowdfunding',
				),
				'WC_PIP_Loader'                            => array(
					'plugin_name'   => 'Woocommerce Print Invoices & Packing lists',
					'template_name' => array(
						'pip_email_invoice',
						'pip_email_packing_list',
						'pip_email_pick_list',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-print-invoices-packing-lists',
				),
				'LMFWC'                                    => array(
					'plugin_name'   => 'License Manager for WooCommerce',
					'template_name' => array(
						'lmfwc_email_customer_deliver_license_keys',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-license-manager-for-woo',
				),
				'WooCommerce_Account_Funds'                => array(
					'plugin_name'   => 'WooCommerce Account Funds',
					'template_name' => array(
						'wc_account_funds_increase',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-themesquad-account-funds',
				),
				'WooCommerce_Gift_Cards'                   => array(
					'plugin_name'   => 'WooCommerce Gift Cards',
					'template_name' => array(
						'gift_card_received',
						'gift_card_send_to_buyer',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-gift-cards-somewherewarm',
				),
				'AutomateWoo'                              => array(
					'plugin_name'   => 'AutomateWoo',
					'template_name' => apply_filters( 'YaymailCreateListAutomateWooNames', array() ),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-automatewoo',
				),
				'WooCommerce_Stripe_Gateway'               => array(
					'plugin_name'   => 'WooCommerce Stripe Gateway',
					'template_name' => array(
						'failed_authentication_requested',
						'failed_preorder_sca_authentication',
						'failed_renewal_authentication',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-stripe-payment-gateway',
				),
				'YITH_WCSTRIPE'                            => array(
					'plugin_name'   => 'YITH Woocommerce Stripe',
					'template_name' => array(
						'renew_needs_action',
						'expiring_card',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-woocommerce-stripe',
				),
				'WooCommerce_Multivendor_Marketplace'      => array(
					'plugin_name'   => 'WooCommerce Multivendor Marketplace',
					'template_name' => array(
						'new-enquiry',
						'store-new-order',
						'email-verification',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-wcfm-marketplace',
				),
				'WooCommerce_Memberships'                  => array(
					'plugin_name'   => 'WooCommerce Memberships',
					'template_name' => array(
						'WC_Memberships_User_Membership_Activated_Email',
						'WC_Memberships_User_Membership_Ended_Email',
						'WC_Memberships_User_Membership_Ending_Soon_Email',
						'WC_Memberships_User_Membership_Note_Email',
						'WC_Memberships_User_Membership_Renewal_Reminder_Email',
						'wc_memberships_for_teams_team_invitation',
						'wc_memberships_for_teams_team_membership_ended',
						'wc_memberships_for_teams_team_membership_ending_soon',
						'wc_memberships_for_teams_team_membership_renewal_reminder',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-woo-memberships',
				),
				'TrackShip_for_WooCommerce'                => array(
					'plugin_name'   => 'TrackShip for WooCommerce',
					'template_name' => apply_filters( 'YaymailCreateListTrackShipWooNames', array() ),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-trackship',
				),
				'AliDropship_Woo_Plugin'                   => array(
					'plugin_name'   => 'AliDropship Woo Plugin',
					'template_name' => array(
						'adsw_order_shipped_notification',
						'adsw_order_tracking_changed_notification',
						'adsw_update_notification',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-alidropship',
				),
				'YITH_WooCommerce_Review_For_Discounts_Premium' => array(
					'plugin_name'   => 'YITH WooCommerce Review For Discounts Premium',
					'template_name' => array(
						'yith-review-for-discounts',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-review-discounts',
				),
				'SUMO_Payment_Plans'                       => array(
					'plugin_name'   => 'SUMO Payment Plans',
					'template_name' => array(
						'_sumo_pp_deposit_balance_payment_auto_charge_reminder',
						'_sumo_pp_deposit_balance_payment_completed',
						'_sumo_pp_deposit_balance_payment_invoice',
						'_sumo_pp_deposit_balance_payment_overdue',
						'_sumo_pp_payment_awaiting_cancel',
						'_sumo_pp_payment_cancelled',
						'_sumo_pp_payment_pending_auth',
						'_sumo_pp_payment_plan_auto_charge_reminder',
						'_sumo_pp_payment_plan_completed',
						'_sumo_pp_payment_plan_invoice',
						'_sumo_pp_payment_plan_overdue',
						'_sumo_pp_payment_plan_success',
						'_sumo_pp_payment_schedule',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-sumo-payment-plans',
				),
				'TeraWallet'                               => array(
					'plugin_name'   => 'TeraWallet',
					'template_name' => array(
						'new_wallet_transaction',
						'low_wallet_balance',
						'woo_wallet_withdraw_approved',
						'woo_wallet_withdraw_rejected',
						'woo_wallet_withdrawal_request',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-terawallet',
				),
				'CustomFieldsforWooCommerce'               => array(
					'plugin_name'   => 'Custom Fields for WooCommerce by Addify',
					'template_name' => array(
						'af_email_admin_register_new_user',
						'af_email_approve_user_account',
						'af_email_declined_user_account',
						'af_email_register_new_account',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-custom-fields-addify',
				),
				'WooCommerceMultiLocationInventory'        => array(
					'plugin_name'   => 'WooCommerce MultiLocation Inventory & Order Routing',
					'template_name' => array(
						'wh_new_order',
						'wh_reassign',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-woo-multiwarehouse-order-routing',
				),
				'MultivendorMarketplaceSolutionWooCommerce' => array(
					'plugin_name'   => 'Multivendor Marketplace Solution for WooCommerce - WC Marketplace',
					'template_name' => array(
						'admin_added_new_product_to_vendor',
						'admin_change_order_status',
						'admin_new_question',
						'admin_new_vendor',
						'admin_widthdrawal_request',
						'approved_vendor_new_account',
						'customer_answer',
						'notify_shipped',
						'rejected_vendor_new_account',
						'wcmp_send_report_abuse',
						'suspend_vendor_new_account',
						'vendor_commissions_transaction',
						'vendor_contact_widget_email',
						'vendor_direct_bank',
						'vendor_followed',
						'vendor_new_account',
						'vendor_new_announcement',
						'admin_new_vendor_coupon',
						'vendor_new_order',
						'admin_new_vendor_product',
						'vendor_new_question',
						'vendor_orders_stats_report',
						'admin_vendor_product_rejected',
						'review_vendor_alert',
						'customer_order_refund_request',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-wcmp-marketplace',
				),
				'AffiliateForWooCommerce'                  => array(
					'plugin_name'   => 'Affiliate For WooCommerce',
					'template_name' => array(
						'afwc_commission_paid',
						'afwc_new_conversion',
						'afwc_new_registration',
						'afwc_welcome',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-affiliate-storeapps',
				),
				'WooCommerceProductVendors'                => array(
					'plugin_name'   => 'WooCommerce Product Vendors',
					'template_name' => array(
						'vendor_approval',
						'cancelled_order_email_to_vendor',
						'order_email_to_vendor',
						'order_fulfill_status_to_admin',
						'order_note_to_customer',
						'product_added_notice',
						'vendor_registration_to_admin',
						'vendor_registration_to_vendor',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-product-vendors/',
				),
				'WooCommerceBackInStockNotifications'      => array(
					'plugin_name'   => 'WooCommerce Back In Stock Notifications',
					'template_name' => array(
						'bis_notification_confirm',
						'bis_notification_received',
						'bis_notification_verify',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-back-in-stock-notifications',
				),
				'WooCommerceReturnandWarrrantyPro'         => array(
					'plugin_name'   => 'WooCommerce Return and Warrranty Pro',
					'template_name' => array(
						'WCRW_Send_Coupon_Email',
						'WCRW_Send_Message_Email',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-wc-return-warrranty',
				),
				'B2BKing'                                  => array(
					'plugin_name'   => 'B2BKing',
					'template_name' => array(
						'b2bking_new_customer_email',
						'b2bking_new_customer_requires_approval_email',
						'b2bking_new_message_email',
						'b2bking_new_offer_email',
						'b2bking_your_account_approved_email',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-b2bking',
				),
				'Domina_Shipping'                          => array(
					'plugin_name'   => 'Domina Shipping',
					'template_name' => array(
						'Domina_Email_Tracking',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-departamentos-ciudades-colombia',
				),
				'YITH_WooCommerce_Delivery_Date_Premium'   => array(
					'plugin_name'   => 'YITH WooCommerce Delivery Date Premium',
					'template_name' => array(
						'yith_advise_user_delivery_email',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-delivery-date',
				),
				'YITH_Advanced_Refund_System_for_WooCommerce_Premium' => array(
					'plugin_name'   => 'YITH Advanced Refund System for WooCommerce Premium',
					'template_name' => array(
						'yith_ywcars_approved_user_email',
						'yith_ywcars_coupon_user_email',
						'yith_ywcars_new_message_admin_email',
						'yith_ywcars_new_message_user_email',
						'yith_ywcars_new_request_admin_email',
						'yith_ywcars_new_request_user_email',
						'yith_ywcars_on_hold_user_email',
						'yith_ywcars_processing_user_email',
						'yith_ywcars_rejected_user_email',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-refund-system',
				),
				'YITH_WooCommerce_Affiliates_Premium'      => array(
					'plugin_name'   => 'YITH WooCommerce Affiliates Premium',
					'template_name' => array(
						'new_affiliate',
						'payment_sent',
						'pending_commission',
						'affiliate_ban',
						'affiliate_new_coupon',
						'customer_payment_sent',
						'customer_pending_commission',
						'affiliate_status_changed',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-affiliates',
				),
				'YITH_Auctions_for_WooCommerce_Premium'    => array(
					'plugin_name'   => 'YITH Auctions for WooCommerce Premium',
					'template_name' => array(
						'yith_wcact_email_auction_no_winner',
						'yith_wcact_email_auction_rescheduled_admin',
						'yith_wcact_email_auction_winner',
						'yith_wcact_email_auction_winner_reminder',
						'yith_wcact_email_better_bid',
						'yith_wcact_email_closed_buy_now',
						'yith_wcact_email_auction_delete_bid',
						'yith_wcact_email_delete_bid_admin',
						'yith_wcact_email_end_auction',
						'yith_wcact_email_new_bid',
						'yith_wcact_email_not_reached_reserve_price',
						'yith_wcact_email_not_reached_reserve_price_max_bidder',
						'yith_wcact_email_successfully_bid',
						'yith_wcact_email_successfully_bid_admin',
						'yith_wcact_email_successfully_follow',
						'yith_wcact_email_winner_admin',
						'yith_wcact_email_without_bid',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-auctions',
				),
				'RMA_Return_Refund_Exchange_for_WooCommerce_Pro' => array(
					'plugin_name'   => 'RMA Return Refund & Exchange for WooCommerce Pro',
					'template_name' => array(
						'wps_rma_cancel_request_email',
						'wps_rma_exchange_request_accept_email',
						'wps_rma_exchange_request_cancel_email',
						'wps_rma_exchange_request_email',
						'wps_rma_order_messages_email',
						'wps_rma_refund_email',
						'wps_rma_refund_request_accept_email',
						'wps_rma_refund_request_cancel_email',
						'wps_rma_refund_request_email',
						'wps_rma_returnship_email',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-woo-refund-and-exchange',
				),
				'YITH_WooCommerce_Points_and_Rewards_Premium' => array(
					'plugin_name'   => 'YITH WooCommerce Points and Rewards Premium',
					'template_name' => array(
						'ywpar_expiration',
						'ywpar_update_points',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-points-and-rewards',
				),
				'WooCommerce_PDF_Product_Vouchers'         => array(
					'plugin_name'   => 'WooCommerce PDF Product Vouchers',
					'template_name' => array(
						'wc_pdf_product_vouchers_voucher_purchaser',
						'wc_pdf_product_vouchers_voucher_recipient',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-pdf-product-vouchers',
				),
				'YITH_WooCommerce_Request_A_Quote_Premium' => array(
					'plugin_name'   => 'YITH WooCommerce Request A Quote Premium',
					'template_name' => array(
						'ywraq_quote_status',
						'ywraq_email',
						'ywraq_email_customer',
						'ywraq_send_quote',
						'ywraq_send_quote_reminder',
						'ywraq_send_quote_reminder_accept',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-yith-request-a-quote',
				),
				'Woo_Sell_Services'                        => array(
					'plugin_name'   => 'Woo Sell Services',
					'template_name' => array(
						'order_accepted',
						'order_conversation_ready',
						'order_ready',
						'order_rejected',
						'submit_requirement',
						'requirements_received',
					),
					'link_upgrade'  => 'https://yaycommerce.com/downloads/yaymail-addon-for-woo-sell-services/',
				),
				'YITH_WC_Recover_Abandoned_Cart'           => array(
					'plugin_name'   => 'YITH WooCommerce Recover Abandoned Cart Premium',
					'template_name' => array(
						'ywrac_email',
						'ywrac_email_recovered_cart',
					),
					'link_upgrade'  => 'https://yaycommerce.com/yaymail-woocommerce-email-customizer/#yaymail-addon-for-yith-woo-recover-abandoned-cart/',
				),
			);

				$list_plugin_for_pro = array();

			if ( class_exists( 'WC_Shipment_Tracking_Actions' ) || class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) || class_exists( 'AST_PRO_Install' ) ) {
				$list_plugin_for_pro[] = 'WC_Shipment_Tracking';
			}
			if ( class_exists( 'AST_PRO_Install' ) ) {
				$list_plugin_for_pro[] = 'WC_Shipment_Tracking_AST_PRO';
			}
			if ( class_exists( 'PH_Shipment_Tracking_API_Manager' ) ) {
				$list_plugin_for_pro[] = 'PH_Shipment_Tracking_API_Manager';
			}

			if ( class_exists( 'WC_Connect_Loader' ) ) {
				$list_plugin_for_pro[] = 'WooCommerce_Shipping_Tax';
			}

			if ( is_plugin_active( 'wc-chitchats-shipping-pro/wc-chitchats-shipping-pro.php' ) ) {
				$list_plugin_for_pro[] = 'Chitchats_Shipping_Shipments';
			}
			if ( class_exists( 'FooEvents' ) ) {
				$list_plugin_for_pro[] = 'FooEvents';
			}
			if ( class_exists( 'WC_Software' ) ) {
				$list_plugin_for_pro[] = 'WC_Software';
			}
			if ( class_exists( 'WC_Admin_Custom_Order_Fields' ) ) {
				$list_plugin_for_pro[] = 'WC_Admin_Custom_Order_Fields';
			}
			if ( class_exists( 'EventON' ) ) {
				$list_plugin_for_pro[] = 'EventON';
			}
			if ( function_exists( 'woocontracts_maile_ekle' ) ) {
				$list_plugin_for_pro[] = 'WC_Email_Sozlesmeler';
			}
			if ( class_exists( 'Woocommerce_German_Market' ) ) {
				$list_plugin_for_pro[] = 'Woocommerce_German_Market';
			}
			if ( class_exists( 'YITH_WooCommerce_Order_Tracking_Premium' ) ) {
				$list_plugin_for_pro[] = 'YITH_WooCommerce_Order_Tracking_Premium';
			}
			if ( class_exists( 'SitePress' ) ) {
				$list_plugin_for_pro[] = 'WPML';
			}
			if ( class_exists( 'Polylang' ) ) {
				$list_plugin_for_pro[] = 'Polylang';
			}
			if ( class_exists( 'TRP_Translate_Press' ) ) {
				$list_plugin_for_pro[] = 'TRP_Translate_Press';
			}
			if ( class_exists( 'Loco_Locale' ) ) {
				$list_plugin_for_pro[] = 'Loco';
			}
			if ( defined( 'WEGLOT_NAME' ) ) {
				$list_plugin_for_pro[] = 'WEGLOT';
			}
			$orderby        = 'name';
			$order_category = 'asc';
			$hide_empty     = false;

			$cat_args = array(
				'orderby'    => $orderby,
				'order'      => $order_category,
				'hide_empty' => $hide_empty,
			);

			$product_categories      = get_terms( 'product_cat', $cat_args );
			$les_promenades_fantomes = get_terms( 'les-promenades-fantomes', $cat_args );
			$promas_service          = get_terms( 'promas-service', $cat_args );
			$billing_country         = WC()->countries->countries;
			$arr_payment_methods     = array();
			$payment_methods         = WC()->payment_gateways->payment_gateways;
			foreach ( $payment_methods as $key => $item ) {
				if ( 'yes' == $item->enabled ) {
					$arr_payment_methods[] = array(
						'id'           => $item->id,
						'method_title' => $item->method_title,
					);
				}
			}

			$get_shipping_methods = WC()->shipping->get_shipping_methods();
			$data                 = array();
			foreach ( $get_shipping_methods as $shipping_method ) {
				$item = array(
					'id'           => $shipping_method->id,
					'method_title' => $shipping_method->method_title,
				);

				$data[] = $item;
			}

			$shipping_methods = $data;

			$data_coupon_codes = array();
			if ( is_plugin_active( 'yaymail-addon-conditional-logic/yaymail-conditional-logic.php' ) ) {
				global $wpdb;
				$get_coupon_codes = $wpdb->get_col( "SELECT post_name FROM $wpdb->posts WHERE post_type = 'shop_coupon' AND post_status = 'publish' ORDER BY post_name ASC LIMIT 100" );
				foreach ( $get_coupon_codes as $coupon_codes ) {
					$item = array(
						'id'           => $coupon_codes,
						'coupon_codes' => $coupon_codes,
					);

					$data_coupon_codes[] = $item;
				}
			}
			$coupon_codes = $data_coupon_codes;

			$arrayShortCode = array();
			if ( class_exists( 'YITH_Barcode' ) ) {
				$arrayShortCode[] = array(
					'plugin'    => 'YITH Barcode',
					'shortcode' => array(
						array( '[yaymail_yith_barcode]', 'Show Barcode' ),
					),
				);
			}

			$listShortCodeAddon = apply_filters( 'yaymail_list_shortcodes', $arrayShortCode );
			if ( class_exists( 'CWG_Instock_Notifier' ) ) {
				$listShortCodeAddon[] = array(
					'plugin'    => 'Back In Stock Notifier for WooCommerce',
					'shortcode' => array(
						array( '[yaymail_notifier_product_name]', 'Product name' ),
						array( '[yaymail_notifier_product_id]', 'Product ID' ),
						array( '[yaymail_notifier_product_link]', 'Product link' ),
						array( '[yaymail_notifier_shopname]', 'Shop name' ),
						array( '[yaymail_notifier_email_id]', 'Email ID' ),
						array( '[yaymail_notifier_subscriber_email]', 'Subscriber email' ),
						array( '[yaymail_notifier_subscriber_name]', 'Subscriber name' ),
						array( '[yaymail_notifier_cart_link]', 'Cart link' ),
						array( '[yaymail_notifier_only_product_name]', 'Only product name' ),
						array( '[yaymail_notifier_only_product_sku]', 'Only product sku' ),
						array( '[yaymail_notifier_only_product_image]', 'Only product image' ),
					),
				);
			}

			// support Custom Order Statuses for WooCommerce by nuggethon
			$woocos_custom_order_statuses = array();
			if ( class_exists( 'WOOCOS_Email_Manager' ) ) {
				$custom_order_statuses = json_decode( get_option( 'woocos_custom_order_statuses' ) );
				if ( $custom_order_statuses ) {
					foreach ( $custom_order_statuses as $order_status ) {
						array_push( $woocos_custom_order_statuses, $order_status->slug );
					}
				}
			}

			// support Custom Order Statuses for WooCommerce by nuggethon
			$bvcos_custom_order_statuses = array();
			if ( class_exists( 'Bright_Plugins_COSW' ) ) {
				$arg            = array(
					'numberposts' => -1,
					'post_type'   => 'order_status',
				);
				$postStatusList = get_posts( $arg );

				if ( $postStatusList ) {
					foreach ( $postStatusList as $post ) {
						$statusSlug = get_post_meta( $post->ID, 'status_slug', true );
						array_push( $bvcos_custom_order_statuses, $statusSlug );
					}
				}
			}

			$initial_categories = Products::get_all_categories();
			$initial_tags = Products::get_all_tags();

				wp_localize_script(
					$scriptId,
					'yaymail_data',
					array(
						'orders'                     => $order,
						'imgUrl'                     => YAYMAIL_PLUGIN_URL . 'assets/dist/images',
						'nonce'                      => wp_create_nonce( 'email-nonce' ),
						'defaultDataElement'         => $element->defaultDataElement,
						'home_url'                   => home_url(),
						'settings'                   => $settings,
						'admin_url'                  => get_admin_url(),
						'yaymail_plugin_url'         => YAYMAIL_PLUGIN_URL,
						'wc_emails'                  => $this->emails,
						'default_email_test'         => $default_email_test,
						'template'                   => $req_template,
						'set_default_logo'           => $setDefaultLogo,
						'set_default_footer'         => $setDefaultFooter,
						'list_plugin_for_pro'        => $list_plugin_for_pro,
						'plugins'                    => apply_filters( 'yaymail_plugins', array() ),
						'list_email_supported'       => $list_email_supported,
						'list_languages'             => $listLanguages,
						'active_language'            => $active_language,
						'product_categories'         => $product_categories,
						'les_promenades_fantomes'    => $les_promenades_fantomes,
						'promas_service'             => $promas_service,
						'billing_country'            => $billing_country,
						'payment_methods'            => $arr_payment_methods,
						'custom_statuses'            => function_exists( 'alg_get_custom_order_statuses_from_cpt' ) ? alg_get_custom_order_statuses_from_cpt() : array(), // Support Custom Order Status for WooCommerce
						'link_detail_smtp'           => self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=yaysmtp&section=description&TB_iframe=true&width=600&height=800' ),
						'yaymail_automatewoo_active' => ( is_plugin_active( 'yaymail-addon-for-automatewoo/yaymail-automatewoo.php' ) || is_plugin_active( 'email-customizer-automatewoo/yaymail-automatewoo.php' ) ) ? true : false,
						'yaymail_dokan_active'       => is_plugin_active( 'yaymail-addon-for-dokan/yaymail-premium-addon-dokan.php' ) ? true : false,
						'yaysmtp_active'             => $this->check_plugin_installed( 'yaysmtp/yay-smtp.php' ) || $this->check_plugin_installed( 'yaysmtp-pro/yay-smtp.php' ) ? true : false,
						'yaysmtp_setting'            => admin_url( 'admin.php?page=yaysmtp' ),
						'list_shortcode_addon'       => $listShortCodeAddon,
						'shipping_methods'           => $shipping_methods,
						'coupon_codes'               => $coupon_codes,
						'i18n'                       => I18n::jsTranslate(),
						'is_hiden_direction'         => $this->is_hiden_direction(),
						'woocos_custom_statuses'     => $woocos_custom_order_statuses,
						'bvos_custom_statuses'       => $bvcos_custom_order_statuses,
						'product_placeholder_image'  => \wc_placeholder_img_src(),
						'initial_categories' 		 => array_slice( $initial_categories, 0, 1 ),
						'initial_tags' 		 		 => array_slice( $initial_tags, 0, 1 ),
					)
				);
				do_action( 'yaymail_enqueue_script_conditional_logic' );
				do_action( 'yaymail_before_enqueue_dependence' );
		}
		wp_enqueue_script( 'yaymail-notice', YAYMAIL_PLUGIN_URL . 'assets/admin/js/notice.js', array( 'jquery' ), YAYMAIL_VERSION, false );
		wp_localize_script(
			'yaymail-notice',
			'yaymail_notice',
			array(
				'admin_ajax' => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'yaymail_nonce' ),
			)
		);
	}
	public function getPageId() {
		if ( null == $this->pageId ) {
			$this->pageId = YAYMAIL_PREFIX . '-settings';
		}

		return $this->pageId;
	}
	public function check_plugin_installed( $plugin_slug ) {
		$installed_plugins = get_plugins();
		return array_key_exists( $plugin_slug, $installed_plugins ) || in_array( $plugin_slug, $installed_plugins, true );
	}

	public function is_hiden_direction() {
		if ( class_exists( 'Polylang' ) || class_exists( 'SitePress' ) || class_exists( 'TRP_Translate_Press' ) || class_exists( 'Loco_Locale' ) ) {
			return true;
		}
		return false;
	}
}
