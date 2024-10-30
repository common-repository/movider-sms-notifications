<?php

defined( 'ABSPATH' ) or exit;


class WMSN_WC_Movider_SMS {



	/** version number */
	const VERSION = '1.0.0';

	/** @var WMSN_WC_Movider_SMS single instance of this plugin */
	protected static $instance;

	/** plugin id */
	const PLUGIN_ID = 'movider_sms';

	/** plugin text domain */
	const TEXT_DOMAIN = 'woo-movider-sms-notifications';

	/** @var \WMSN_WC_Movider_SMS_Admin instance */
	protected $admin;

	/** @var \WMSN_WC_Movider_SMS_AJAX instance */
	protected $ajax;

	/** @var \WMSN_WC_Movider_SMS_API instance */
	private $api;


	/**
	 * Sets up main plugin class.
	 *
	 * @since 1.0
	 */
	public function __construct() {
		
		// Load classes
		$this->includes();

		// Add opt-in checkbox to checkout
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'add_opt_in_checkbox' ) );

		// Process opt-in checkbox after order is processed
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'process_opt_in_checkbox' ) );

		// GDPR compliance: delete customer opt in for receiving SMS updates, when order is anonymized
		add_action( 'woocommerce_privacy_remove_order_personal_data', array( $this, 'erase_opt_in' ) );

		// Add order status hooks, at priority 11 as Order Status Manager adds
		// custom statuses at 10
		add_action( 'init', array( $this, 'add_order_status_hooks' ), 11 );

	}


	/**
	 * Loads required classes
	 *
	 * @since 1.0
	 */
	private function includes() {
		
		require_once( plugin_dir_path( __FILE__ ) . '/includes/helper-functions.php' );
		
		require_once( plugin_dir_path( __FILE__ ) . '/includes/class-wc-movider-sms-notification.php' );
		
		require_once( plugin_dir_path( __FILE__ ) . '/includes/class-wc-movider-sms-ajax.php' );
		
		$this->wc_helper = new WMSN_Helper();

		$this->ajax = new WMSN_WC_Movider_SMS_AJAX();

		// load admin classes
		if ( is_admin() ) {
			$this->admin_includes();
		}
	}


	/**
	 * Loads admin classes
	 *
	 * @since 1.0
	 */
	private function admin_includes() {

		// admin
		require_once( plugin_dir_path( __FILE__ ) . '/includes/admin/class-wc-movider-sms-admin.php' );
		
		
		$this->admin = new WMSN_WC_Movider_SMS_Admin();
		
	}


	/**
	 * Return admin class instance
	 *
	 * @since 1.8.0
	 *
	 * @return \WMSN_WC_Movider_SMS_Admin
	 */
	public function get_admin_instance() {
		return $this->admin;
	}


	/**
	 * Return ajax class instance
	 *
	 * @since 1.8.0
	 *
	 * @return \WMSN_WC_Movider_SMS_AJAX
	 */
	public function get_ajax_instance() {
		return $this->ajax;
	}



	/**
	 * Add hooks for the opt-in checkbox and customer / admin order status changes
	 *
	 * @since 1.1
	 */
	public function add_order_status_hooks() {

		$statuses = wc_get_order_statuses();
		

		// Customer order status change hooks
		foreach ( array_keys( $statuses ) as $status ) {

			$status_slug = ( 'wc-' === substr( $status, 0, 3 ) ) ? substr( $status, 3 ) : $status;

			add_action( 'woocommerce_order_status_' . $status_slug, array( $this, 'send_customer_notification' ) );
		}

		// Admin new order hooks
		foreach ( array( 'pending_to_on-hold', 'pending_to_processing', 'pending_to_completed', 'failed_to_on-hold', 'failed_to_processing', 'failed_to_completed' ) as $status ) {

			add_action( 'woocommerce_order_status_' . $status, array( $this, 'send_admin_new_order_notification' ) );
		}
	}


	/**
	 * Send customer an SMS when their order status changes
	 *
	 * @since 1.1
	 */
	public function send_customer_notification( $order_id ) {

		$notification = new WMSN_WC_Movider_SMS_Notification( $order_id );

		$notification->send_automated_customer_notification();
	}


	/**
	 * Send admins an SMS when a new order is received
	 *
	 * @since 1.1
	 */
	public function send_admin_new_order_notification( $order_id ) {

		$notification = new WMSN_WC_Movider_SMS_Notification( $order_id );

		$notification->send_admin_notification();
	}


	/**
	 * Returns the Movider SMS API object
	 *
	 * @since 1.1
	 *
	 * @return \WMSN_WC_Movider_SMS_API the API object
	 */
	public function get_api() {

		if ( is_object( $this->api ) ) {
			return $this->api;
		}

		// Load API
		require_once( plugin_dir_path( __FILE__ ) . '/includes/class-wc-movider-sms-api.php' );

		$api_key = get_option( 'wc_movider_sms_account_apikey', '' );
		$api_secret  = get_option( 'wc_movider_sms_account_secretkey', '' );
		$from_number = get_option( 'wc_movider_sms_from_name', '' );

		$options = array();

		return $this->api = new WMSN_WC_Movider_SMS_API( $api_key, $api_secret, $from_number, $options );
	}


	/**
	 * Adds checkbox to checkout page for customer to opt-in to SMS notifications
	 *
	 * @since 1.0
	 */
	public function add_opt_in_checkbox() {

		// use previous value or default value when loading checkout page
		if ( ! empty( $_POST['wc_movider_sms_optin'] ) ) {
			$value = wc_clean( $_POST['wc_movider_sms_optin'] );
		} else {
			$value = ( 'checked' === get_option( 'wc_movider_sms_checkout_optin_checkbox_default', 'unchecked' ) ) ? 1 : 0;
		}

		/**
		 * Filters the optin label at checkout.
		 *
		 * @since 1.12.0
		 *
		 * @param string $label the checkout label
		 */
		$optin_label = apply_filters( 'wc_movider_sms_checkout_optin_label', get_option( 'wc_movider_sms_checkout_optin_checkbox_label', '' ) );

		if ( ! empty( $optin_label ) ) {

			// output checkbox
			woocommerce_form_field( 'wc_movider_sms_optin', array(
				'type'  => 'checkbox',
				'class' => array( 'form-row-wide' ),
				'label' => $optin_label,
			), $value );
		}
	}


	/**
	 * Save opt-in as order meta
	 *
	 * TODO: This method will later need to instantiate an order / use a WC Data method. {BR 2017-02-22}
	 *
	 * @since 1.0
	 *
	 * @param int $order_id order ID for order being processed
	 */
	public function process_opt_in_checkbox( $order_id ) {

		if ( ! empty( $_POST['wc_movider_sms_optin'] ) ) {
			update_post_meta( $order_id, '_wc_movider_sms_optin', 1 );
		}
	}


	/**
	 * Removes the SMS Notification opt in when an order is anonymized and personal data erased.
	 *
	 * @internal
	 *
	 * @since 1.10.1
	 *
	 * @param \WC_Order $order an order being erased by privacy request
	 */
	public function erase_opt_in( $order ) {

		if ( $order instanceof WC_Order ) {

			Framework\SV_WC_Order_Compatibility::delete_meta_data( $order, '_wc_movider_sms_optin' );
		}
	}


	/** Helper methods ******************************************************/


	/**
	 * Main Movider SMS Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.4.0
	 *
	 * @see wc_movider_sms()
	 * @return WMSN_WC_Movider_SMS
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.2
	 *
	 * @see Framework\SV_WC_Plugin::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {

		return __( 'Movider SMS Notifications', 'woo-movider-sms-notifications' );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 1.2
	 *
	 * @see Framework\SV_WC_Plugin::get_file()
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {

		return __FILE__;
	}


	/**
	 * Gets the URL to the settings page
	 *
	 * @since 1.2
	 *
	 * @see Framework\SV_WC_Plugin::is_plugin_settings()
	 * @param string $_ unused
	 * @return string URL to the settings page
	 */
	public function get_settings_url( $_ = '' ) {

		return admin_url( 'admin.php?page=wc-settings&tab=movider_sms' );
	}


	/**
	 * Gets the plugin documentation URL
	 *
	 * @since 1.5.0
	 *
	 * @see Framework\SV_WC_Plugin::get_documentation_url()
	 * @return string
	 */
	public function get_documentation_url() {

		return '';
	}


	/**
	 * Gets the plugin support URL
	 *
	 * @since 1.5.0
	 *
	 * @see Framework\SV_WC_Plugin::get_support_url()
	 * @return string
	 */
	public function get_support_url() {

		return '';
	}


	/**
	 * Returns true if on the plugin settings page
	 *
	 * @since 1.2
	 *
	 * @see Framework\SV_WC_Plugin::is_plugin_settings()
	 * @return boolean true if on the settings page
	 */
	public function is_plugin_settings() {

		return isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['tab'] ) && 'movider_sms' === $_GET['tab'];
	}


	/**
	 * Log messages to WooCommerce error log if logging is enabled
	 *
	 * /wp-content/woocommerce/logs/movider-sms.txt
	 *
	 * @since 1.1
	 *
	 * @param string $content message to log
	 * @param string $_ unused
	 */
	public function log( $content, $_ = null ) {

		if ( 'yes' === get_option( 'wc_movider_sms_log_errors' ) ) {

			error_log($content.PHP_EOL, 3, MOVIDER_PLUGIN_LOG_DIR.'movider_log.log'); 
		}
	}

	

}


/**
 * Returns the One True Instance of Movider SMS.
 *
 * @since 1.12.0
 *
 * @return WMSN_WC_Movider_SMS
 */
function wmsn_wc_movider_sms() {

	return WMSN_WC_Movider_SMS::instance();
}