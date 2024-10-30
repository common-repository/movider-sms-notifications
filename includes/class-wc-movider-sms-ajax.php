<?php

defined( 'ABSPATH' ) or exit;

/**
 * Movider SMS AJAX class
 *
 * Handles all AJAX actions
 *
 * @since 1.0
 */
class WMSN_WC_Movider_SMS_AJAX {


	/**
	 * Adds required wp_ajax_* hooks
	 *
	 * @since  1.0
	 */
	public function __construct() {

		add_action( 'wp_ajax_woocommerce_movider_sms_send_test_sms', array( $this, 'send_test_sms' ) );

		// Process 'Toggle automated updates' meta-box action
		add_action( 'wp_ajax_wc_movider_sms_toggle_order_updates', array( $this, 'toggle_order_updates' ) );

		// Process 'Send an SMS' meta-box action
		add_action( 'wp_ajax_wc_movider_sms_send_order_sms', array( $this, 'send_order_sms' ) );
	}

	/**
	 * Handle test SMS AJAX call
	 *
	 * @since  1.0
	 */
	public function send_test_sms() {
		
		
		
		$this->verify_request( $_POST['security'], 'wc_movider_sms_send_test_sms' );

		// sanitize input
		$mobile_number = sanitize_text_field(trim( $_POST['mobile_number'] ));
		$message       = sanitize_text_field( $_POST['message'] );

		try {

			$response = wmsn_wc_movider_sms()->get_api()->send( $mobile_number, $message );

			exit( __( 'Test message sent successfully', 'woo-movider-sms-notifications' ) );

		} catch ( Exception $e ) {

			die( sprintf( __( 'Error sending SMS: %s', 'woo-movider-sms-notifications' ), $e->getMessage() ) );
		}
	}


	/**
	 * Toggle automated SMS messages from the edit order page
	 *
	 * @since 1.6.0
	 */
	public function toggle_order_updates() {

		$this->verify_request( $_POST['security'], 'wc_movider_sms_toggle_order_updates' );

		$order_id = ( is_numeric( $_POST['order_id'] ) ) ? absint( $_POST['order_id'] ) : null;

		if ( ! $order_id ) {
			return;
		}

		$current_status = get_post_meta( $order_id , '_wc_movider_sms_optin', true );
		

		if ( empty( $current_status ) ) { 
			update_post_meta( $order_id , '_wc_movider_sms_optin', 1 );
		} else {
			delete_post_meta( $order_id , '_wc_movider_sms_optin' );
		}
		
		exit();
	}


	/**
	 * Send an SMS from the edit order page
	 *
	 * @since 1.1.4
	 */
	public function send_order_sms() {

		$this->verify_request( $_POST['security'], 'wc_movider_sms_send_order_sms' );

		// sanitize message
		$message = sanitize_text_field( $_POST[ 'message' ] );

		$order_id = ( is_numeric( $_POST['order_id'] ) ) ? absint( $_POST['order_id'] ) : null;

		if ( ! $order_id ) {
			return;
		}

		$notification = new WMSN_WC_Movider_SMS_Notification( $order_id );

		// send the SMS
		$notification->send_manual_customer_notification( $message );

		exit( __( 'Message Sent', 'woo-movider-sms-notifications' ) );
	}


	/**
	 * Verifies AJAX request is valid
	 *
	 * @since  1.0
	 * @param string $nonce
	 * @param string $action
	 * @return void|bool
	 */
	private function verify_request( $nonce, $action ) {

		if( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'woo-movider-sms-notifications' ) );
		}

		if( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( __( 'You have taken too long, please go back and try again.', 'woo-movider-sms-notifications' ) );
		}

		return true;
	}


}
