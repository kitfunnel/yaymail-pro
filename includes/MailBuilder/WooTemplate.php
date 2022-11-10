<?php

namespace YayMail\MailBuilder;

use YayMail\Page\Source\CustomPostType;
use YayMail\Ajax;
use YayMail\MailBuilder\PIPTemplate;

defined( 'ABSPATH' ) || exit;
/**
 * Settings Page
 */
class WooTemplate {


	protected static $instance = null;
	private $templateAccount;
	private $templateSubscription;
	private $automatewoo_info                    = null;
	private $automatewoo_referrals_email_content = null;
	private $trackShipArgs                       = null;
	public static function getInstance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
			self::$instance->doHooks();
		}

		return self::$instance;
	}

	private function doHooks() {
		$this->templateAccount         = array( 'customer_new_account', 'customer_new_account_activation', 'customer_reset_password' );
		$this->templateGermanizedForWC = array( 'sab_simple_invoice', 'sab_cancellation_invoice', 'sab_packing_slip', 'sab_document_admin', 'sab_document' );
		add_filter( 'storeabill_get_template', array( $this, 'storeabill_get_template' ), 100, 5 );
		add_filter( 'wc_get_template', array( $this, 'getTemplateMail' ), 100, 5 );
		add_filter( 'fue_before_sending_email', array( $this, 'getFollowUpTemplates' ), 100, 3 );
		if ( class_exists( 'CWG_Instock_Notifier' ) ) {
			$postID_notifier_instock_mail = CustomPostType::postIDByTemplate( 'notifier_instock_mail' );
			if ( get_post_meta( $postID_notifier_instock_mail, '_yaymail_status', true ) ) {
				add_filter( 'cwginstock_message', array( $this, 'cwginstock_message' ), 100, 2 );
				add_action( 'admin_action_cwginstock-sendmail', array( $this, 'action_remove_header_footer' ), 9 );
				add_action( 'cwginstock_notify_process', array( $this, 'action_remove_header_footer' ), 9 );
				add_action( 'cwginstocknotifier_handle_action_send_mail', array( $this, 'action_remove_header_footer' ), 9 );
			}

			$postID_notifier_subscribe_mail = CustomPostType::postIDByTemplate( 'notifier_subscribe_mail' );
			if ( get_post_meta( $postID_notifier_subscribe_mail, '_yaymail_status', true ) ) {
				add_filter( 'cwgsubscribe_message', array( $this, 'cwgsubscribe_message' ), 100, 2 );
				add_action( 'cwginstock_after_insert_subscriber', array( $this, 'action_remove_header_footer' ), 9 );
			}
		}

		// change german market template dir
		$this->yaymail_get_german_market_templates();

		// Support custom order status
		if ( 'yes' === get_option( 'alg_orders_custom_statuses_emails_enabled', 'no' ) ) {
			add_action( 'woocommerce_order_status_changed', array( $this, 'send_email_on_order_status' ), 10, 4 );
		}
		if ( class_exists( 'WC_PIP_Loader' ) && class_exists( 'YayMailWooPrintInvoices\\templateDefault\\DefaultInvoice' ) && class_exists( 'YayMailWooPrintInvoices\\templateDefault\\DefaultPickList' ) ) {
			PIPTemplate::handle_trigger();
		}
		add_filter( 'retrieve_password_message', array( $this, 'admin_reset_password' ), 100, 4 );
		add_action( 'automatewoo_before_action_run', array( $this, 'automatewoo_before_action_run' ), 10 );
		add_filter( 'automatewoo/referrals/invite_email/mailer', array( $this, 'automatewoo_invite_email' ), 100, 2 );

		if ( class_exists( 'WCFM' ) ) {
			$WCFMWooFM_Template = CustomPostType::postIDByTemplate( 'WCFMWooFM_Template' );
			if ( get_post_meta( $WCFMWooFM_Template, '_yaymail_status', true ) ) {
				global $WCFM;
				remove_action( 'wcfm_email_content_wrapper', array( $WCFM, 'wcfm_email_content_wrapper' ), 10 );
				add_filter( 'wcfm_email_content_wrapper', array( &$this, 'wcfm_email_content_wrapper' ), 1, 2 );
			}
		}

	}

	public function wcfm_email_content_wrapper( $content_body, $email_heading ) {
		$template       = 'WCFMWooFM_Template';
		$postID         = CustomPostType::postIDByTemplate( 'WCFMWooFM_Template' );
		$templateActive = file_exists( YAYMAIL_PLUGIN_PATH . 'views/templates/single-follow-up-mail-template.php' ) ? YAYMAIL_PLUGIN_PATH . 'views/templates/single-follow-up-mail-template.php' : false;
		$args           = array(
			'order'         => null,
			'content_body'  => $content_body,
			'email_heading' => $email_heading,
		);
		ob_start();
		include $templateActive;
		$template_body = ob_get_contents();
		ob_end_clean();
		return $template_body;
	}
	public function automatewoo_invite_email( $mailer, $invite_email ) {
		if ( is_plugin_active( 'yaymail-addon-for-automatewoo/yaymail-automatewoo.php' ) || is_plugin_active( 'email-customizer-automatewoo/yaymail-automatewoo.php' ) ) {
			$template        = 'AutomateWoo_Referrals_Email';
			$postID          = CustomPostType::postIDByTemplate( $template );
			$template_status = get_post_meta( $postID, '_yaymail_status', true );
			if ( $template_status ) {
				$templateActive = file_exists( YAYMAIL_PLUGIN_PATH . 'views/templates/single-follow-up-mail-template.php' ) ? YAYMAIL_PLUGIN_PATH . 'views/templates/single-follow-up-mail-template.php' : false;
				ob_start();
				include $templateActive;
				$template_body = ob_get_contents();
				ob_end_clean();
				if ( '' !== $template_body ) {
					$template_body                             = $invite_email->replace_variables( $template_body );
					$this->automatewoo_referrals_email_content = $template_body;
				}
				add_filter(
					'woocommerce_mail_content',
					function( $email_content ) {
						return $this->automatewoo_referrals_email_content;
					},
					100,
					1
				);
			}
		}
		return $mailer;
	}


	public function automatewoo_before_action_run( $action ) {
		if ( is_plugin_active( 'yaymail-addon-for-automatewoo/yaymail-automatewoo.php' ) || is_plugin_active( 'yaymail-addon/yaymail-automatewoo.php' ) || is_plugin_active( 'email-customizer-automatewoo/yaymail-automatewoo.php' ) ) {
			$action_content         = $action->get_option_raw( 'email_content' );
			$this->automatewoo_info = $action;
			$template               = 'AutomateWoo_' . $action->workflow->get_id();
			$postID                 = CustomPostType::postIDByTemplate( $template );
			$template_status        = get_post_meta( $postID, '_yaymail_status', true );
			if ( $template_status ) {
				add_filter(
					'woocommerce_mail_content',
					function( $html ) {
						$workflow       = $this->automatewoo_info->workflow;
						$this->workflow = $workflow;
						$template       = 'AutomateWoo_' . $workflow->get_id();
						$postID         = CustomPostType::postIDByTemplate( $template );
						$templateActive = file_exists( YAYMAIL_PLUGIN_PATH . 'views/templates/single-follow-up-mail-template.php' ) ? YAYMAIL_PLUGIN_PATH . 'views/templates/single-follow-up-mail-template.php' : false;
						$raw_data       = $workflow->data_layer()->get_raw_data();
						$args           = array(
							'order'    => isset( $raw_data['order'] ) ? $raw_data['order'] : null,
							'subscription' => isset( $raw_data['subscription'] ) ? $raw_data['subscription'] : null,
							'email'    => '',
							'workflow' => $workflow,
						);
						ob_start();
						include $templateActive;
						$template_body = ob_get_contents();
						ob_end_clean();
						if ( '' !== $template_body ) {
							$template_body = $workflow->variable_processor()->process_field( $template_body, true );
							if ( $workflow->is_tracking_enabled() ) {
								$email         = new \AutomateWoo\Workflow_Email( $workflow, '', '', $template_body );
								$get_mailer    = $email->get_mailer();
								$template_body = $get_mailer->inject_tracking_pixel( $template_body );
								$template_body = $get_mailer->replace_urls_in_content( $template_body );
							}
							return $template_body;
						}
						return $html;
					},
					10
				);
			}
		}
	}

	public function send_email_on_order_status( $order_id, $status_from, $status_to, $order ) {
		$postID          = CustomPostType::postIDByTemplate( 'wc-' . $status_to );
		$template_status = get_post_meta( $postID, '_yaymail_status', true );
		if ( $template_status ) {
			global $wp_filter;
			$action = isset( $wp_filter['woocommerce_order_status_changed'] ) ? $wp_filter['woocommerce_order_status_changed']->callbacks : array();
			if ( ! empty( $action ) ) {

				foreach ( $action as $key => $value ) {
					foreach ( $value as $key1 => $value1 ) {
						if ( is_array( $value1['function'] ) && isset( $value1['function']['1'] ) ) {
							if ( 'send_email_on_order_status_changed' === $value1['function']['1'] ) {
								remove_action( 'woocommerce_order_status_changed', $key1, PHP_INT_MAX, 4 );
							}
						}
					}
				}
			}

			$alg_orders_custom_statuses_array = function_exists( 'alg_get_custom_order_statuses_from_cpt' ) ? alg_get_custom_order_statuses_from_cpt() : array();
			$emails_statuses                  = get_option( 'alg_orders_custom_statuses_emails_statuses', array() );
			if ( in_array( 'wc-' . $status_to, $emails_statuses, true ) || ( empty( $emails_statuses ) && in_array( 'wc-' . $status_to, array_keys( $alg_orders_custom_statuses_array ), true ) ) ) {
				// Options.
				$email_address = get_option( 'alg_orders_custom_statuses_emails_address', '' );
				$email_subject = get_option(
					'alg_orders_custom_statuses_emails_subject',
					// translators: WC Order Number, New Status & Date on which the order was placed.
					sprintf( __( '[%1$s] Order #%2$s status changed to %3$s - %4$s', 'custom-order-statuses-woocommerce' ), '{site_title}', '{order_number}', '{status_to}', '{order_date}' )
				);

				$woo_statuses        = wc_get_order_statuses();
				$replace_status_from = isset( $alg_orders_custom_statuses_array[ 'wc-' . $status_from ] ) ? $alg_orders_custom_statuses_array[ 'wc-' . $status_from ] : $woo_statuses[ 'wc-' . $status_from ];
				$replace_status_to   = isset( $alg_orders_custom_statuses_array[ 'wc-' . $status_to ] ) ? $alg_orders_custom_statuses_array[ 'wc-' . $status_to ] : $woo_statuses[ 'wc-' . $status_to ];

				// Replaced values.
				$replaced_values = array(
					'{order_id}'     => $order_id,
					'{order_number}' => $order->get_order_number(),
					'{order_date}'    => gmdate( get_option( 'date_format' ), strtotime( $order->get_date_created() ) ), // phpcs:ignore
					'{site_title}'   => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
					'{status_from}'  => $replace_status_from,
					'{status_to}'    => $replace_status_to,
					'{first_name}'   => $order->get_billing_first_name(),
					'{last_name}'    => $order->get_billing_last_name(),
				);

				$email_replaced_values = array(
					'{customer_email}' => $order->get_billing_email(),
					'{admin_email}'    => get_option( 'admin_email' ),
				);
				// Final processing.
				$email_address = ( '' === $email_address ? get_option( 'admin_email' ) : str_replace( array_keys( $email_replaced_values ), $email_replaced_values, $email_address ) );
				$email_subject = do_shortcode( str_replace( array_keys( $replaced_values ), $replaced_values, $email_subject ) );

				// Content
				$args = array(
					'custom_order_status' => true,
					'order'               => $order,
					'sent_to_admin'       => false,
					'status_from'         => $status_from,
				);
				ob_start();
				include YAYMAIL_PLUGIN_PATH . 'views/templates/single-mail-template.php';
				$html = ob_get_contents();
				ob_end_clean();
				wc_mail( $email_address, $email_subject, $html );
			}
		}

	}

	public function yaymail_get_german_market_templates() {
		if ( class_exists( 'Woocommerce_German_Market' ) ) {
			add_filter(
				'wgm_locate_template',
				function( $template, $template_name, $template_path ) {
					if ( 'emails/customer-confirm-order.php' === $template_name ) {
						$postID          = CustomPostType::postIDByTemplate( 'wgm_confirm_order_email' );
						$template_status = get_post_meta( $postID, '_yaymail_status', true );
						if ( $template_status ) {
							return YAYMAIL_PLUGIN_PATH . 'views/templates/emails/customer-confirm-order.php';
						}
					} elseif ( 'double-opt-in-customer-registration.php' === $template_name ) {
						$postID          = CustomPostType::postIDByTemplate( 'wgm_double_opt_in_customer_registration' );
						$template_status = get_post_meta( $postID, '_yaymail_status', true );
						if ( $template_status ) {
							return YAYMAIL_PLUGIN_PATH . 'views/templates/emails/double-opt-in-customer-registration.php';
						}
					} elseif ( 'emails/sepa-mandate.php' === $template_name ) {
						$postID          = CustomPostType::postIDByTemplate( 'wgm_sepa' );
						$template_status = get_post_meta( $postID, '_yaymail_status', true );
						if ( $template_status ) {
							return YAYMAIL_PLUGIN_PATH . 'views/templates/emails/sepa.php';
						}
					}
					return $template;
				},
				100,
				3
			);
		}
	}

	public function cwgsubscribe_message( $message, $subscriber_id ) {
		$custom_shortcode = new Shortcodes( 'notifier_subscribe_mail', '', false );
		$custom_shortcode->setOrderId( 0, true );
		$custom_shortcode->shortCodesOrderDefined( false, array( 'subscriber_id' => $subscriber_id ) );
		$Ajax   = Ajax::getInstance();
		$postID = CustomPostType::postIDByTemplate( 'notifier_subscribe_mail' );
		$html   = $Ajax->getHtmlByElements( $postID, array( 'order' => 'SampleOrder' ) );
		// Replace shortcode cannot do_shortcode
		$reg  = '/\[yaymail.*?\]/m';
		$html = preg_replace( $reg, '', $html );
		$html = html_entity_decode( $html, ENT_QUOTES, 'UTF-8' );
		return $html;
	}

	public function cwginstock_message( $message, $subscriber_id ) {
		$custom_shortcode = new Shortcodes( 'notifier_instock_mail', '', false );
		$custom_shortcode->setOrderId( 0, true );
		$custom_shortcode->shortCodesOrderDefined( false, array( 'subscriber_id' => $subscriber_id ) );
		$Ajax   = Ajax::getInstance();
		$postID = CustomPostType::postIDByTemplate( 'notifier_instock_mail' );
		$html   = $Ajax->getHtmlByElements( $postID, array( 'order' => 'SampleOrder' ) );
		// Replace shortcode cannot do_shortcode
		$reg  = '/\[yaymail.*?\]/m';
		$html = preg_replace( $reg, '', $html );
		$html = html_entity_decode( $html, ENT_QUOTES, 'UTF-8' );
		return $html;
	}
	public function action_remove_header_footer() {
		$emails = \WC_Emails::instance();
		remove_action( 'woocommerce_email_header', array( $emails, 'email_header' ) );
		remove_action( 'woocommerce_email_footer', array( $emails, 'email_footer' ) );
	}

	public function storeabill_get_template( $located, $template_name, $args, $template_path, $default_path ) {
		$this_template  = false;
		$templateActive = file_exists( YAYMAIL_PLUGIN_PATH . 'views/templates/single-mail-template.php' ) ? YAYMAIL_PLUGIN_PATH . 'views/templates/single-mail-template.php' : false;
		$template       = isset( $args['email'] ) && isset( $args['email']->id ) && ! empty( $args['email']->id ) ? $args['email']->id : false;

		if ( $template ) {
			if ( CustomPostType::postIDByTemplate( $template ) ) {
				$postID = CustomPostType::postIDByTemplate( $template );
				if ( get_post_meta( $postID, '_yaymail_status', true ) && ! empty( get_post_meta( $postID, '_yaymail_elements', true ) ) ) {
					if ( in_array( $template, $this->templateGermanizedForWC ) ) { // template mail with account
						$this_template = $templateActive;
					}
				}
			}
		}
		$this_template = $this_template ? $this_template : $located;
		return $this_template;
	}

	private function __construct() {}
	// define the woocommerce_new_order callback

	public function admin_reset_password( $message, $key, $user_login, $user_data ) {
		$this_template  = false;
		$templateActive = file_exists( YAYMAIL_PLUGIN_PATH . 'views/templates/single-mail-template.php' ) ? YAYMAIL_PLUGIN_PATH . 'views/templates/single-mail-template.php' : false;
		$template       = 'customer_reset_password';
		$email          = array(
			'id'         => 'customer_reset_password',
			'user_login' => $user_login,
			'user_id'    => $user_data->ID,
			'user_email' => $user_data->data->user_email,
			'user_data'  => $user_data,
			'key'        => $key,
		);
		$args           = array(
			'email'         => (object) $email,
			'sent_to_admin' => false,
			'reset_key'     => $key,
		);
		$holder_order   = isset( $args['order'] ) ? $args['order'] : null;
		if ( CustomPostType::postIDByTemplate( $template, $holder_order ) ) {
			$postID = CustomPostType::postIDByTemplate( $template, $holder_order );
			if ( get_post_meta( $postID, '_yaymail_status', true ) && ! empty( get_post_meta( $postID, '_yaymail_elements', true ) ) ) {
				if ( isset( $args['order'] ) || in_array( $template, $this->templateAccount ) ) { // template mail with order
					$this_template = $templateActive;
				}
			}
		}
		$template_path = '';
		$template_name = 'emails/customer-reset-password.php';
		if ( false !== $this_template ) {
			ob_start();
			include $this_template;
			$message = ob_get_contents();
			ob_end_clean();
			$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
			// translators: none.
			$title = sprintf( __( '[%s] Password Reset', 'yaymail' ), $site_name );
			wc_mail( $email['user_email'], $title, $message );
			$message = false;
		}
		return $message;
	}

	// support addon TrackShip for WooCommerce
	public function woocommerce_mail_content( $html ) {
		$trackshipArgs  = $this->trackShipArgs;
		$template       = 'trackship_' . $trackshipArgs['new_status'];
		$postID         = CustomPostType::postIDByTemplate( $template );
		$templateActive = file_exists( YAYMAIL_PLUGIN_PATH . 'views/templates/single-follow-up-mail-template.php' ) ? YAYMAIL_PLUGIN_PATH . 'views/templates/single-follow-up-mail-template.php' : false;
		$args           = array(
			'order_id'             => isset( $trackshipArgs['order_id'] ) ? $trackshipArgs['order_id'] : '0',
			'show_shipment_status' => isset( $trackshipArgs['show_shipment_status'] ) ? $trackshipArgs['show_shipment_status'] : false,
			'new_status'           => isset( $trackshipArgs['new_status'] ) ? $trackshipArgs['new_status'] : '',
			'tracking_items'       => isset( $trackshipArgs['tracking_items'] ) ? $trackshipArgs['tracking_items'] : array(),
			'shipment_status'      => isset( $trackshipArgs['shipment_status'] ) ? $trackshipArgs['shipment_status'] : array(),
		);

		ob_start();
		include $templateActive;
		$template_body = ob_get_contents();
		ob_end_clean();
		if ( '' !== $template_body ) {
			return $template_body;
		}
		return $html;

	}

	public function getTemplateMail( $located, $template_name, $args, $template_path, $default_path ) {
		// support addon TrackShip for WooCommerce
		if ( 'emails/tracking-info.php' == $template_name ) {
			$this->trackShipArgs = $args;
			if ( isset( $args['new_status'] ) ) {
				$template        = 'trackship_' . $args['new_status'];
				$postID          = CustomPostType::postIDByTemplate( $template );
				$template_status = get_post_meta( $postID, '_yaymail_status', true );
				if ( $template_status ) {
					add_filter( 'woocommerce_mail_content', array( $this, 'woocommerce_mail_content' ), 100 );
				}
			}
		}
		$this_template  = false;
		$templateActive = file_exists( YAYMAIL_PLUGIN_PATH . 'views/templates/single-mail-template.php' ) ? YAYMAIL_PLUGIN_PATH . 'views/templates/single-mail-template.php' : false;
		if ( isset( $args['yith_wc_email'] ) && isset( $args['yith_wc_email']->id ) && ! empty( $args['yith_wc_email']->id ) ) {
			// Get Email ID in yith-woocommerce-multi-vendor-premium
			$template = $args['yith_wc_email']->id;
		} else {
			$template = isset( $args['email'] ) && isset( $args['email']->id ) && ! empty( $args['email']->id ) ? $args['email']->id : false;
			if ( 'dokan-wholesale/' == $template_path ) {
				$template = 'Dokan_Email_Wholesale_Register';
			}
			if ( 'new-user-registration.php' == $template_name ) {
				$template = 'wp_crowdfunding_new_user';
			}
			if ( 'campaign-accepted.php' == $template_name ) {
				$template = 'wp_crowdfunding_campaign_accept';
			}
			if ( 'submit-campaign.php' == $template_name ) {
				$template = 'wp_crowdfunding_submit_campaign';
			}
			if ( 'campaign-updated.php' == $template_name ) {
				$template = 'wp_crowdfunding_campaign_update_email';
			}
			if ( 'new-backed.php' == $template_name ) {
				$template = 'wp_crowdfunding_new_backed';
			}
			if ( 'campaign-target-reached.php' == $template_name ) {
				$template = 'wp_crowdfunding_target_reached_email';
			}
			if ( 'withdraw-request.php' == $template_name ) {
				$template = 'wp_crowdfunding_withdraw_request';
			}
			if ( class_exists( 'WC_Smart_Coupons' ) ) {
				if ( isset( $args['email'] ) && strpos( $located, plugin_dir_path( WC_SC_PLUGIN_FILE ) ) !== false ) {
					$templateName = str_replace( plugin_dir_path( WC_SC_PLUGIN_FILE ) . 'templates/', '', $located );
					if ( 'email.php' == $templateName ) {
						$template   = 'wc_sc_email_coupon';
						$args['id'] = 'wc_sc_email_coupon';
					}
					if ( 'combined-email.php' == $templateName ) {
						$template   = 'wc_sc_combined_email_coupon';
						$args['id'] = 'wc_sc_combined_email_coupon';
					}
					if ( 'acknowledgement-email.php' == $templateName ) {
						$template   = 'wc_sc_acknowledgement_email';
						$args['id'] = 'wc_sc_acknowledgement_email';
					}
				}
			}
			if ( 'emails/waitlist-mailout.php' == $template_name ) {
				$template = 'woocommerce_waitlist_mailout';
			}
			if ( 'emails/waitlist-left.php' == $template_name ) {
				$template = 'woocommerce_waitlist_left_email';
			}
			if ( 'emails/waitlist-joined.php' == $template_name ) {
				$template = 'woocommerce_waitlist_joined_email';
			}
			if ( 'emails/waitlist-new-signup.php' == $template_name ) {
				$template = 'woocommerce_waitlist_signup_email';
			}
			if ( 'emails/dokan-admin-new-booking.php' == $template_name ) {
				$template = 'Dokan_Email_Booking_New';
			}
			if ( 'emails/dokan-customer-booking-cancelled.php' == $template_name ) {
				$template = 'Dokan_Email_Booking_Cancelled_NEW';
			}
		}

		if ( isset( $args['email'] ) && isset( $args['email']->id ) && false !== strpos( get_class( $args['email'] ), 'ORDDD_Email_Delivery_Reminder' ) ) {
			$template .= '_customer';
		}

		// support Custom Order Statuses for WooCommerce by nuggethon
		if ( class_exists( 'WOOCOS_Email_Manager' ) ) {
			if ( isset( $args['order'] ) && null != $args['order']->data['status'] && str_contains( $default_path, 'custom-order-statuses-for-woocommerce' ) ) {
				$order_status = $args['order']->data['status'];
				$template     = 'woocos-' . $order_status;
			}
		}

		// can't load tempalte email-delivery-date.php because it will has error when check out, with plugin WooCommerce Order Delivery
		if ( $template && 'emails/email-delivery-date.php' != $template_name && 'emails/email-order-details.php' != $template_name ) {
			// Yith Stripe

			if ( 'emails/expiring-card-email.php' === $template_name ) {
				$template = 'expiring_card';
			}
			if ( 'emails/renew-needs-action-email.php' === $template_name ) {
				$template = 'renew_needs_action';
			}

			if ( 'emails/admin-notify-approved.php' === $template_name ) {
				$template = 'admin_notify_approved';
			}
			if ( 'customer_partially_refunded_order' == $template ) {
				$template = 'customer_refunded_order';
			}
			$holder_order = isset( $args['order'] ) ? $args['order'] : null;
			if ( CustomPostType::postIDByTemplate( $template, $holder_order ) ) {
				$postID = CustomPostType::postIDByTemplate( $template, $holder_order );
				if ( get_post_meta( $postID, '_yaymail_status', true ) && ! empty( get_post_meta( $postID, '_yaymail_elements', true ) ) ) {
					if ( isset( $args['order'] ) || in_array( $template, $this->templateAccount ) ) { // template mail with order
						$this_template = $templateActive;
					} else {
						$checkHasTempalte = apply_filters( 'yaymail_addon_defined_template', false, $template );
						if ( $checkHasTempalte ) { // template mail with account
							$this_template = $templateActive;
						}
					}
				}
			}
		}
		$this_template = $this_template ? $this_template : $located;
		return $this_template;
	}

	public function getFollowUpTemplates( $email_data, $email, $queue_item ) {
		if ( has_filter( 'yaymail_follow_up_shortcode' ) ) {
			$templateActive  = file_exists( YAYMAIL_PLUGIN_PATH . 'views/templates/single-follow-up-mail-template.php' ) ? YAYMAIL_PLUGIN_PATH . 'views/templates/single-follow-up-mail-template.php' : false;
			$template        = 'follow_up_email_' . $email->id;
			$postID          = CustomPostType::postIDByTemplate( $template );
			$template_status = get_post_meta( $postID, '_yaymail_status', true );
			$args            = array(
				'email_data' => $email_data,
				'email'      => $email,
				'queue_item' => $queue_item,
			);
			if ( $template_status ) {
				ob_start();
				include $templateActive;
				$template_body = ob_get_contents();
				ob_end_clean();
				$email_data['message'] = $template_body;
			}
		}
		return $email_data;
	}
}
