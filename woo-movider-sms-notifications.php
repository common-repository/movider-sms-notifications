<?php
/**
 * Plugin Name: Movider SMS Notifications
 * Plugin URI: https://movider.co
 * Description: Send SMS order notifications to admins and customers for your WooCommerce store. Powered by Movider :)
 * Version: 1.0
 * Text Domain: woo-movider-sms-notifications
 * Domain Path: /i18n/languages/
 * WC requires at least: 2.6.14
 * WC tested up to: 3.5.5
 */

defined( 'ABSPATH' ) or exit;

define('MOVIDER_PLUGIN_PATH', plugin_dir_path( __FILE__ ));

define( 'MOVIDER_PLUGIN', __FILE__ );

define( 'MOVIDER_PLUGIN_URL',  plugin_dir_url( __FILE__ ));

define( 'MOVIDER_PLUGIN_ASSETS',  plugins_url( '/assets/', __FILE__ ));

define( 'MOVIDER_PLUGIN_LOG_DIR',  MOVIDER_PLUGIN_PATH.'logs/');


add_action( 'admin_init', 'wmsn_required_plugins' );

function wmsn_required_plugins()
{
    if ( is_admin() && current_user_can( 'activate_plugins' ) &&  !is_plugin_active( 'woocommerce/woocommerce.php' )   )
    {
        add_action( 'admin_notices', 'wmsn_required_plugins_notice' );

        deactivate_plugins( plugin_basename( __FILE__ ) ); 

        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
    }
}

function wmsn_required_plugins_notice()
{
?>
	<div class="error">
		<p>
			<?php _e("Sorry, the plugin Movider SMS Notifications requires woocommerce plugin to be installed and active.","woo-movider-sms-notifications") ?>
		</p>
	</div>
<?php
}

add_filter( 'plugin_row_meta', 'wmsn_plugin_row_meta', 10, 2 );
function wmsn_plugin_row_meta($links, $file)
{
	if ( plugin_basename( __FILE__ ) == $file ) {
        $row_meta = array(
          'docs'    => '<a href="' . esc_url( menu_page_url('movider', false) ) . '" target="_blank" aria-label="' . esc_attr__( 'Movider Guide', 'woo-movider-sms-notifications' ) . '" style="color:green;">' . esc_html__( 'Movider Document', 'woo-movider-sms-notifications' ) . '</a>'
        );
 
        return array_merge( $links, $row_meta );
    }
    return (array) $links;
}

/**
 * The plugin loader class.
 *
 * @since 1.0
 */
class WMSN_WC_Movider_SMS_Loader {


	/** minimum PHP version required by this plugin */
	const MINIMUM_PHP_VERSION = '5.3.0';

	/** minimum WordPress version required by this plugin */
	const MINIMUM_WP_VERSION = '4.4';

	/** minimum WooCommerce version required by this plugin */
	const MINIMUM_WC_VERSION = '2.6';

	/** SkyVerge plugin framework version used by this plugin */
	const FRAMEWORK_VERSION = '5.3.1';

	/** the plugin name, for displaying notices */
	const PLUGIN_NAME = 'Movider SMS Notifications';


	/** @var WMSN_WC_Movider_SMS_Loader single instance of this class */
	private static $instance;

	/** @var array the admin notices to add */
	private $notices = array();


	/**
	 * Constructs the class.
	 *
	 * @since 1.0
	 */
	protected function __construct() {

		register_activation_hook( __FILE__, array( $this, 'callback_activation_check' ) );

		add_action( 'admin_init', array( $this, 'callback_check_environment' ) );
		add_action( 'admin_init', array( $this, 'callback_add_plugin_notices' ) );

		add_action( 'admin_notices', array( $this, 'callback_admin_notices' ), 15 );

		// if the environment check fails, initialize the plugin
		if ( $this->is_environment_compatible() ) {
			add_action( 'plugins_loaded', array( $this, 'callback_init_plugin' ) );
		}
	}


	/**
	 * Cloning instances is forbidden due to singleton pattern.
	 *
	 * @since 1.0
	 */
	public function __clone() {

		_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot clone instances of %s.', get_class( $this ) ), '1.0.0' );
	}


	/**
	 * Unserializing instances is forbidden due to singleton pattern.
	 *
	 * @since 1.0
	 */
	public function __wakeup() {

		_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot unserialize instances of %s.', get_class( $this ) ), '1.0.0' );
	}


	/**
	 * Initializes the plugin.
	 *
	 * @since 1.0
	 */
	public function callback_init_plugin() {

		if ( ! $this->plugins_compatible() ) {
			return;
		}
		
		
		require_once( plugin_dir_path( __FILE__ ) . 'class-wc-movider-sms.php' );

		// fire it up!
		wmsn_wc_movider_sms();
	}


	/**
	 * Gets the framework version in namespace form.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	protected function get_framework_version_namespace() {

		return 'v' . str_replace( '.', '_', $this->get_framework_version() );
	}


	/**
	 * Gets the framework version used by this plugin.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	protected function get_framework_version() {

		return self::FRAMEWORK_VERSION;
	}


	/**
	 * Checks the server environment and other factors and deactivates plugins as necessary.
	 *
	 *
	 * @since 1.0
	 */
	public function callback_activation_check() {

		if ( ! $this->is_environment_compatible() ) {

			$this->deactivate_plugin();

			wp_die( self::PLUGIN_NAME . ' could not be activated. ' . $this->get_environment_message() );
		}
	}

	/**
	 * Checks the environment on loading WordPress, just in case the environment changes after activation.
	 *
	 * @since 1.0
	 */
	public function callback_check_environment() {

		if ( ! $this->is_environment_compatible() && is_plugin_active( plugin_basename( __FILE__ ) ) ) {

			$this->deactivate_plugin();

			$this->add_admin_notice( 'bad_environment', 'error', self::PLUGIN_NAME . ' has been deactivated. ' . $this->get_environment_message() );
		}
	}


	/**
	 * Adds notices for out-of-date WordPress and/or WooCommerce versions.
	 *
	 * @since 1.0
	 */
	public function callback_add_plugin_notices() {

		if ( ! $this->is_wp_compatible() ) {

			$this->add_admin_notice( 'update_wordpress', 'error', sprintf(
				'%s requires WordPress version %s or higher. Please %supdate WordPress &raquo;%s',
				'<strong>' . self::PLUGIN_NAME . '</strong>',
				self::MINIMUM_WP_VERSION,
				'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">', '</a>'
			) );
		}

		if ( ! $this->is_wc_compatible() ) {

			$this->add_admin_notice( 'update_woocommerce', 'error', sprintf(
				'%1$s requires WooCommerce version %2$s or higher. Please %3$supdate WooCommerce%4$s to the latest version, or %5$sdownload the minimum required version &raquo;%6$s',
				'<strong>' . self::PLUGIN_NAME . '</strong>',
				self::MINIMUM_WC_VERSION,
				'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">', '</a>',
				'<a href="' . esc_url( 'https://downloads.wordpress.org/plugin/woocommerce.' . self::MINIMUM_WC_VERSION . '.zip' ) . '">', '</a>'
			) );
		}
	}


	/**
	 * Determines if the required plugins are compatible.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	protected function plugins_compatible() {

		return $this->is_wp_compatible() && $this->is_wc_compatible();
	}


	/**
	 * Determines if the WordPress compatible.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	protected function is_wp_compatible() {

		if ( ! self::MINIMUM_WP_VERSION ) {
			return true;
		}

		return version_compare( get_bloginfo( 'version' ), self::MINIMUM_WP_VERSION, '>=' );
	}


	/**
	 * Determines if the WooCommerce compatible.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	protected function is_wc_compatible() {

		if ( ! self::MINIMUM_WC_VERSION ) {
			return true;
		}

		return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, self::MINIMUM_WC_VERSION, '>=' );
	}


	/**
	 * Deactivates the plugin.
	 *
	 * @since 1.0
	 */
	protected function deactivate_plugin() {

		deactivate_plugins( plugin_basename( __FILE__ ) );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}


	/**
	 * Adds an admin notice to be displayed.
	 *
	 * @since 1.0
	 *
	 * @param string $slug the slug for the notice
	 * @param string $class the css class for the notice
	 * @param string $message the notice message
	 */
	public function add_admin_notice( $slug, $class, $message ) {

		$this->notices[ $slug ] = array(
			'class'   => $class,
			'message' => $message
		);
	}


	/**
	 * Displays any admin notices added with WMSN_WC_Movider_SMS_Loader::add_admin_notice()
	 *
	 * @since 1.0
	 */
	public function callback_admin_notices() {

		foreach ( (array) $this->notices as $notice_key => $notice ) {

			?>
			<div class="<?php echo esc_attr( $notice['class'] ); ?>">
				<p>
					<?php echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) ); ?>
				</p>
			</div>
			<?php
		}
	}


	/**
	 * Determines if the server environment is compatible with this plugin.
	 *
	 * Override this method to add checks for more than just the PHP version.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	protected function is_environment_compatible() {

		return version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=' );
	}


	/**
	 * Gets the message for display when the environment is incompatible with this plugin.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	protected function get_environment_message() {

		$message = sprintf( 'The minimum PHP version required for this plugin is %1$s. You are running %2$s.', self::MINIMUM_PHP_VERSION, PHP_VERSION );

		return $message;
	}


	/**
	 * Gets the main WMSN_WC_Movider_SMS_Loader instance.
	 *
	 * Ensures only one instance can be loaded.
	 *
	 * @since 1.0
	 *
	 * @return WMSN_WC_Movider_SMS_Loader
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
	
	
	public function get_plugin_url() {

		if ( $this->plugin_url ) {
			return $this->plugin_url;
		}

		return $this->plugin_url = untrailingslashit( plugins_url( '/', $this->get_file() ) );
	}


}

// fire it up!
WMSN_WC_Movider_SMS_Loader::instance();
