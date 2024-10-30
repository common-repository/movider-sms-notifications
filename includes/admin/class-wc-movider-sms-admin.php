<?php
defined( 'ABSPATH' ) or exit;

/**
 * Movider SMS Admin class
 *
 * Loads admin settings page and adds related hooks / filters
 *
 * @since 1.0
 */
class WMSN_WC_Movider_SMS_Admin {


	/** @var string id of tab on WooCommerce Settings page */
	public static $tab_id = 'movider_sms';


	/**
	 * Setup admin class
	 *
	 * @since  1.0
	 */
	public function __construct() {

		/** General Admin Hooks */
		
		
		// Add SMS tab
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab'  ), 100 );

		// Show SMS settings page
		add_action( 'woocommerce_settings_movider_sms', array( $this, 'display_settings' ) );

		// Add admin notices
		add_action( 'admin_notices', array( $this, 'display_notices' ) );

		// Load the scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_and_styles' ) );

		// save SMS settings
		add_action( 'woocommerce_update_options_' . self::$tab_id, array( $this, 'process_settings' ) );

		// Add custom 'wc_movider_sms_link' form field type
		add_action( 'woocommerce_admin_field_wc_movider_sms_link', array( $this, 'add_link_field' ) );

		// add 'Movider SMS Notifications' item to admin bar menu
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu_item' ), 100 );

		/** Order Admin Hooks */

		// Add 'Send an SMS' meta-box on Order page to send SMS to customer
		add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ) );
		/*Add option page in settings*/
		add_action('admin_menu', array( $this, 'wmsn_add_guide_page' ));
	}


	/**
	 * Add SMS tab to WooCommerce Settings after 'Email' tab
	 *
	 * @since 1.0
	 * @param array $settings_tabs tabs array sans 'SMS' tab
	 * @return array $settings_tabs now with 100% more 'SMS' tab!
	 */
	public function add_settings_tab( $settings_tabs ) {

		$new_settings_tabs = array();

		foreach ( $settings_tabs as $tab_id => $tab_title ) {

			$new_settings_tabs[ $tab_id ] = $tab_title;

			// Add our tab after 'Email' tab
			if ( 'email' === $tab_id ) {
				$new_settings_tabs[ self::$tab_id ] = __( 'Movider SMS', 'woo-movider-sms-notifications' );
			}
		}

		return $new_settings_tabs;
	}


	/**
	 * Outputs sections for the Movider SMS settings tab.
	 *
	 * @since 1.12.0
	 */
	public function display_sections() {
		global $current_section;

		$sections = $this->get_sections();

		if ( empty( $sections ) || 1 === sizeof( $sections ) ) {

			return;
		}

		echo '<ul class="subsubsub">';

		$section_ids = array_keys( $sections );

		foreach ( $sections as $id => $label ) {

			echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=movider_sms&section=' . sanitize_title( $id ) ) ) . '" class="' . ( $current_section === $id ? 'current' : '' ) . '">' . esc_html( $label ) . '</a> ' . ( end( $section_ids ) === $id ? '' : '|' ) . ' </li>';
		}

		echo '</ul><br class="clear" />';
	}


	/**
	 * Show SMS settings page
	 *
	 * @see woocommerce_admin_fields()
	 * @uses WMSN_WC_Movider_SMS_Admin::get_settings() to get settings array
	 * @uses WMSN_WC_Movider_SMS_Admin::display_send_test_sms_form() to output 'send test SMS' form
	 * @since 1.0
	 */
	public function display_settings() {
		global $current_section;

		// default to orders section
		if ( ! $current_section ) {
			$current_section = 'general';
		}

		$this->display_sections();

		// display general settings only on General tab
		$settings = ( 'general' === $current_section ) ? self::get_settings() : array();

		/**
		 * Allow actors to change the settings to be displayed.
		 *
		 * @since 1.12.0
		 *
		 * @param array $sections
		 */
		$settings = apply_filters( 'wc_movider_sms_settings', $settings );

		// output settings
		if ( ! empty ( $settings ) ) {

			woocommerce_admin_fields( $settings );
		}
	}


	/**
	 * Display admin notices.
	 *
	 * @internal
	 *
	 * @since 1.11.0
	 */
	public function display_notices() {

		//wmsn_wc_movider_sms()->get_message_handler()->show_messages();
	}




	/**
	 * Load the scripts and styles.
	 *
	 * TODO: Look for a replacement for the screen ID check here post WC 3.1+. {BR 2017-02-22}
	 *
	 * @since 1.6.0
	 */
	public function enqueue_scripts_and_styles($hook) {

		if($hook == 'settings_page_movider')
		{
			wp_enqueue_style( 'wc-movider-guide-font', 'https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css', '', '1.0' );
			wp_enqueue_style( 'wc-movider-guide-admin', MOVIDER_PLUGIN_ASSETS. '/css/admin/wmsn-movider-guide.css', '', '1.0' );
			wp_enqueue_style( 'wc-movider-guide-bootstrap-admin', MOVIDER_PLUGIN_ASSETS. '/css/admin/wmsn-bootstrap.min.css', '', '1.0' );
			wp_enqueue_script( 'wc-movider-guide-bootstrap-admin-js', MOVIDER_PLUGIN_ASSETS . '/js/admin/wmsn-bootstrap.min.js', array(), '', true );
		}

		$screen = get_current_screen();
		// Only enqueue the scripts and styles on the settings page, order edit screen, and product edit screen
		if ( $screen && 'shop_order' !== $screen->id && 'product' !== $screen->id && ! wmsn_wc_movider_sms()->is_plugin_settings() ) {
			return;
		}

		wp_enqueue_script( 'wc-movider-sms-admin', MOVIDER_PLUGIN_ASSETS . '/js/admin/wc-movider-sms-admin.js', array(), '', true );

		wp_localize_script( 'wc-movider-sms-admin', 'wc_movider_sms_admin', array(

			// Settings screen
			'test_sms_error_message' => __( 'Please make sure you have entered a mobile phone number and test message.', 'woo-movider-sms-notifications' ),
			'test_sms_nonce'         => wp_create_nonce( 'wc_movider_sms_send_test_sms' ),

			// Edit order screen
			'edit_order_id'              => get_the_ID(),
			'toggle_order_updates_nonce' => wp_create_nonce( 'wc_movider_sms_toggle_order_updates' ),
			'send_order_sms_nonce'       => wp_create_nonce( 'wc_movider_sms_send_order_sms' ),

			// General
			'assets_url' => MOVIDER_PLUGIN_ASSETS . '/images/admin/ajax-loader.gif' ,
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
		) );

		wp_enqueue_style( 'wc-movider-sms-notifications-admin', MOVIDER_PLUGIN_ASSETS. '/css/admin/wc-movider-sms-notifications.min.css', '', '1.0' );

	}


	/**
	 * Add 'Send an SMS' meta-box to Orders page
	 *
	 * @since 1.0
	 */
	public function add_order_meta_box() {

		add_meta_box(
			'wc_movider_sms_order_meta_box',
			__( 'Movider SMS Messages', 'woo-movider-sms-notifications' ),
		 	array( $this, 'display_order_meta_box' ),
			'shop_order',
			'side',
			'default'
		);
	}


	/**
	 * Display the 'Send an SMS' meta-box on the Orders page
	 *
	 * TODO Instantiate an order here instead to update meta value post WC 3.1+ {BR 2017-02-22}
	 *
	 * @since 1.0
	 */
	public function display_order_meta_box( $post ) {

		$optin = get_post_meta( $post->ID, '_wc_movider_sms_optin', true ); 
		
		
		?>

		<p style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid #eee;">
			<input id="wc_movider_sms_toggle_order_updates" type="checkbox" <?php checked( 1, $optin ); ?> />
			<label for="wc_movider_sms_toggle_order_updates"><?php _e( 'Send automated order updates.', 'woo-movider-sms-notifications' ); ?></label>
		</p>

		<?php $default_message = apply_filters( 'wc_movider_sms_notifications_default_admin_sms_message', '' ); ?>

		<p><?php _e( 'Send SMS Message:', 'woo-movider-sms-notifications' ); ?></p>
		<p><textarea type="text" name="wc_movider_sms_order_message" id="wc_movider_sms_order_message" class="input-text" style="width: 100%;" rows="4" value="<?php echo esc_attr( $default_message ); ?>"></textarea></p>
		<p><a class="button tips" id="wc_movider_sms_order_send_message" data-tip="<?php _e( 'Send an SMS to the billing phone number for this order.', 'woo-movider-sms-notifications' ); ?>"><?php _e( 'Send SMS', 'woo-movider-sms-notifications' ); ?></a>
		<span id="wc_movider_sms_order_message_char_count" style="color: green; float: right; font-size: 16px;">0</span></p>

		<?php
	}


	/**
	 * Update options on SMS settings page
	 *
	 * @see woocommerce_update_options()
	 * @uses WMSN_WC_Movider_SMS_Admin::get_settings() to get settings array
	 * @since 1.0
	 */
	public function process_settings() {

		if ( isset( $_GET['section'] ) && 'general' !== $_GET['section'] ) {
			return;
		}

		woocommerce_update_options( self::get_settings() );
	}


	/**
	 * Get sections
	 *
	 * @since 1.12.0
	 *
	 * @return array
	 */
	public function get_sections() {

		$sections = array(
			'general' => __( 'General', 'woo-movider-sms-notifications' ),
		);

		/**
		 * Allow actors to change the sections for the Movider SMS settings tab.
		 *
		 * @since 1.12.0
		 *
		 * @param array $sections
		 */
		return apply_filters( 'wc_movider_sms_sections', $sections );
	}


	/**
	 * Build array of plugin settings in format needed to use WC admin settings API
	 *
	 * @see woocommerce_admin_fields()
	 * @see woocommerce_update_options()
	 * @since 1.0
	 * @return array settings
	 */
	public static function get_settings() {

		$settings = array(
		
		 array(
				'name' => __( 'General Settings', 'woo-movider-sms-notifications' ),
				'type' => 'title'
			),
  
			array(
				'id'       => 'wc_movider_sms_checkout_optin_checkbox_label',
				'name'     => __( 'Opt-in Checkbox Label', 'woo-movider-sms-notifications' ),
				'desc_tip' => __( 'Label for the Opt-in checkbox on the Checkout page. Leave blank to disable the opt-in and force ALL customers to receive SMS updates.', 'woo-movider-sms-notifications' ),
				'css'      => 'min-width: 275px;',
				'default'  => __( 'Please send me order updates via text message. Make sure you have selected country correctly and do NOT put country code in your mobile phone.', 'woo-movider-sms-notifications' ),
				'type'     => 'text'
			),

			array(
				'id'       => 'wc_movider_sms_checkout_optin_checkbox_default',
				'name'     => __( 'Opt-in Checkbox Default', 'woo-movider-sms-notifications' ),
				'desc_tip' => __( 'Default status for the Opt-in checkbox on the Checkout page.', 'woo-movider-sms-notifications' ),
				'std'      => 'checked',
				'default'  => 'checked',
				'type'     => 'select',
				'options'  => array(
					'checked'   => __( 'Checked', 'woo-movider-sms-notifications' ),
					'unchecked' => __( 'Unchecked', 'woo-movider-sms-notifications' )	
				)
			),


			array( 'type' => 'sectionend' ),

			
			array(
				'name' => __( 'Admin Notifications', 'woo-movider-sms-notifications' ),
				'type' => 'title'
			),

			array(
				'id'      => 'wc_movider_sms_enable_admin_sms',
				'name'    => __( 'Enable new order SMS admin notifications.', 'woo-movider-sms-notifications' ),
				'default' => 'no',
				'type'    => 'checkbox'
			),

			array(
				'id'          => 'wc_movider_sms_admin_sms_recipients',
				'name'        => __( 'Admin Mobile Number', 'woo-movider-sms-notifications' ),
				'desc_tip'    => __( 'Enter the mobile number (starting with the country code) where the New Order SMS should be sent. Send to multiple recipients by separating numbers with commas.', 'woo-movider-sms-notifications' ),
				'placeholder' => '6678901234',
				'type'        => 'text'
			),

			array(
				'id'       => 'wc_movider_sms_admin_sms_template',
				'name'     => __( 'Admin SMS Message', 'woo-movider-sms-notifications' ),
				/* translators: %1$s is <code>, %2$s is </code> */
				'desc' => sprintf( __( 'Use these tags to customize your message: %1$s%%shop_name%%%2$s, %1$s%%order_id%%%2$s, %1$s%%order_count%%%2$s, %1$s%%order_amount%%%2$s, %1$s%%order_status%%%2$s, %1$s%%billing_name%%%2$s, %1$s%%shipping_name%%%2$s, and %1$s%%shipping_method%%%2$s. Remember that SMS messages are limited to 160 characters.', 'woo-movider-sms-notifications' ), '<code>', '</code>' ),
				'css'      => 'min-width:500px;',
				'default'  => __( '%shop_name% : You have a new order (%order_id%) for %order_amount%!', 'woo-movider-sms-notifications' ),
				'type'     => 'textarea'
			),

			array( 'type' => 'sectionend' ),

			array(
				'name' => __( 'Customer Notifications', 'woo-movider-sms-notifications' ),
				'type' => 'title'
			),
		);

		$order_statuses = wc_get_order_statuses();

		$settings[] = array(
			'id'                => 'wc_movider_sms_send_sms_order_statuses',
			'name'              => __( 'Order statuses to send SMS notifications for', 'woo-movider-sms-notifications' ),
			'desc_tip'          => __( 'Orders with these statuses will have SMS notifications sent.', 'woo-movider-sms-notifications' ),
			'type'              => 'multiselect',
			'options'           => $order_statuses,
			'default'           => array_keys( $order_statuses ),
			'class'             => 'wc-enhanced-select',
			'css'               => 'min-width: 250px',
			'custom_attributes' => array(
				'data-placeholder' => __( 'Select statuses to automatically send notifications', 'woo-movider-sms-notifications' ),
			),
		);

		$settings[] = array(
			'id'       => 'wc_movider_sms_default_sms_template',
			'name'     => __( 'Default Customer SMS Message', 'woo-movider-sms-notifications' ),
			/* translators: %1$s is <code>, %2$s is </code> */
			'desc' => sprintf( __( 'Use these tags to customize your message: %1$s%%shop_name%%%2$s, %1$s%%order_id%%%2$s, %1$s%%order_count%%%2$s, %1$s%%order_amount%%%2$s, %1$s%%order_status%%%2$s, %1$s%%billing_name%%%2$s, %1$s%%billing_first%%%2$s, %1$s%%billing_last%%%2$s, %1$s%%shipping_name%%%2$s, and %1$s%%shipping_method%%%2$s. Remember that SMS messages are limited to 160 characters.', 'woo-movider-sms-notifications' ), '<code>', '</code>' ),
			'css'      => 'min-width:500px;',
			'default'  => __( '%shop_name% : Your order (%order_id%) is now %order_status%.', 'woo-movider-sms-notifications' ),
			'type'     => 'textarea'
		);

		// Display a textarea setting for each available order status
		foreach( $order_statuses as $slug => $label ) {

			$slug = 'wc-' === substr( $slug, 0, 3 ) ? substr( $slug, 3 ) : $slug;

			$settings[] = array(
				'id'       => 'wc_movider_sms_' . $slug . '_sms_template',
				'name'     => sprintf( __( '%s SMS Message', 'woo-movider-sms-notifications' ), $label ),
				'desc_tip' => sprintf( __( 'Add a custom SMS message for %s orders or leave blank to use the default message above.', 'woo-movider-sms-notifications' ), $slug ),
				'css'      => 'min-width:500px;',
				'type'     => 'textarea'
			);
		}

		// Continue adding settings as usual
		$settings = array_merge( $settings, array(
	
			array( 'type' => 'sectionend' ),

			array(
				'name' => __( 'Connection Settings', 'woo-movider-sms-notifications' ),
				'type' => 'title',
			),

			array(
				'id'       => 'wc_movider_sms_account_apikey',
				'name'     => __( 'API Key', 'woo-movider-sms-notifications' ),
				'desc_tip' => __( 'Log into your Movider Account to find your API Key.', 'woo-movider-sms-notifications' ),
				'type'     => 'text',
			),

			array(
				'id'       => 'wc_movider_sms_account_secretkey',
				'name'     => __( 'API Secret', 'woo-movider-sms-notifications' ),
				'desc_tip' => __( 'Log into your Movider Account to find your API Secret.', 'woo-movider-sms-notifications' ),
				'type'     => 'text',
			),

			array(
				'id'       => 'wc_movider_sms_from_name',
				'name'     => __( 'From (Sender ID)', 'woo-movider-sms-notifications' ),
				'desc_tip' => __( 'The sender ID or number the message should be sent from. You can choose from default sender IDs which are MOVIDER, MOVIDEROTP, MVDVERIFY and MVDSMS.', 'woo-movider-sms-notifications' ),
				'type'     => 'text',
			),

			array(
				'id'       => 'wc_movider_sms_log_errors',
				'name'     => __( 'Log Errors', 'woo-movider-sms-notifications' ),
				'desc' => __( 'Enable this to log Movider API errors to the WooCommerce log. Use this if you are having issues sending SMS.', 'woo-movider-sms-notifications' ),
				
					'desc_tip' => sprintf( __( 'Log Path - '.MOVIDER_PLUGIN_URL.'logs/movider_log.log' ), '<code>', '</code>' ),
				
				'default'  => 'no',
				'type'     => 'checkbox',
			),

			array( 'type' => 'sectionend' ),

			array(
				'name' => __( 'Send Test SMS', 'woo-movider-sms-notifications' ),
				'type' => 'title',
			),

			array(
				'id'       => 'wc_movider_sms_test_mobile_number',
				'name'     => __( 'Mobile Number', 'woo-movider-sms-notifications' ),
				'desc_tip' => __( 'Enter the mobile number (starting with the country code) where the test SMS should be send. Note that if you are using a trial Movider account, this number must be verified first.', 'woo-movider-sms-notifications' ),
				'type'     => 'text',
			),

			array(
				'id'       => 'wc_movider_sms_test_message',
				'name'     => __( 'Message', 'woo-movider-sms-notifications' ),
				'desc_tip' => __( 'Enter the test message to be sent. Remember that SMS messages are limited to 160 characters.', 'woo-movider-sms-notifications' ),
				'type'     => 'textarea',
				'css'      => 'min-width: 500px;',
			),

			array(
				'name'  => __( 'Send', 'woo-movider-sms-notifications' ),
				'href'  => '#',
				'class' => 'wc_movider_sms_test_sms_button' . ' button',
				'type'  => 'wc_movider_sms_link',
			),

			array( 'type' => 'sectionend', 'id' => 'wc_movider_sms_send_test_section' ),

		) );

		return $settings;
	}


	/**
	 * Add custom woocommerce admin form field via woocommerce_admin_field_* action
	 *
	 * @since 1.0
	 * @param array $field associative array of field parameters
	 */
	public function add_link_field( $field ) {

		if ( isset( $field['name'] ) && isset( $field['class'] ) && isset( $field['href'] ) ) :

		?>
			<tr valign="top">
				<th scope="row" class="titledesc"></th>
				<td class="forminp">
					<a href="<?php echo esc_url( $field['href'] ); ?>" class="<?php echo esc_attr( $field['class'] ); ?>"><?php echo wp_filter_kses( $field['name'] ); ?></a>
				</td>
			</tr>
		<?php

		endif;
	}


	/**
	 * Add the 'Movider SMS Notifications' admin menu bar item
	 *
	 * @since 1.1
	 */
	public function add_admin_bar_menu_item() {
		global $wp_admin_bar;

		// security check
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// add top-level menu
		$menu_args = array(
			'id'    => 'wc_movider_sms_admin_bar_menu',
			'title' => __( 'Movider SMS Notifications', 'woo-movider-sms-notifications' ),
			'href'  => false
		);

		// get SMS usage
		$sms_usage = $this->get_sms_usage();
		
		$available_amount = $sms_usage['currency'].' '.$sms_usage['amount'];
	
		// set message
					/* translators: %1$d - the number of SMS messages sent, %2$s - the total cost of the SMS messages sent */
		$message = sprintf( __( 'Your Balance : %s' ,'woo-movider-sms-notifications' ), $available_amount );

		// setup 'usage' item
		$sms_usage_item_args = array(
			'id' => 'wc_movider_sms_sms_usage_item',
			'title' => $message,
			'href' => false,
			'parent' => 'wc_movider_sms_admin_bar_menu'
		);

		// setup 'add funds' link
		$add_funds_item_args = array(
			'id'     => 'wc_movider_sms_add_funds_item',
			'title'  => __( 'Add Funds to Your Movider Account', 'woo-movider-sms-notifications' ),
			'href'   => 'https://dashboard.movider.co/payment',
			'meta'   => array( 'target' => '_blank' ),
			'parent' => 'wc_movider_sms_admin_bar_menu'
		);

		// add menu + items
		$wp_admin_bar->add_menu( $menu_args );
		$wp_admin_bar->add_menu( $sms_usage_item_args );
		$wp_admin_bar->add_menu( $add_funds_item_args );
	}


	/**
	 * Get SMS usage for today via Movider API and set as 15 minute transient
	 *
	 * @since 1.1
	 */
	private function get_sms_usage() {
		
		$usage = get_transient( 'wc_movider_sms_balance' );

		// get transient
		if ( false === ( $usage = get_transient( 'wc_movider_sms_balance' ) ) ) {

			// transient doesn't exist, fetch via Movider API
			try {

				// get SMS usage
				$response = wmsn_wc_movider_sms()->get_api()->get_sms_usage();
				
				$usage = array(
					'currency' => ( isset( $response['type'] ) ) ? $response['type'] : 0,
					'amount'  => ( isset( $response['amount'] ) ) ? $response['amount'] : 0
				);

				// set 15 minute transient
				set_transient( 'wc_movider_sms_balance', $usage, 60*15 );

				return $usage;

			} catch ( Exception $e ) {

				wmsn_wc_movider_sms()->log( $e->getMessage() );
				
				return array( 'currency' => 0, 'amount' => '0.00' );
			}

		} else {
				
			return $usage;
		}
	}

	public function wmsn_add_guide_page()
	{
		add_options_page('Movider', 'Movider', 'manage_options', 'movider', array($this,'wmsn_movider_options_page'));
	}
	public function wmsn_movider_options_page()
	{
		require_once 'wmsn-guide-template.php';
	}

}


// fire it up!
//$admin = new WMSN_WC_Movider_SMS_Admin();

