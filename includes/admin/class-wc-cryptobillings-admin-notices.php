<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class that represents admin notices.
 *
 */
class WC_Cryptobillings_Admin_Notices {
	/**
	 * Notices (array)
	 * @var array
	 */
	public $notices = array();

	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_loaded', array( $this, 'hide_notices' ) );
	}

	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication).
	 *
	 */
	public function add_admin_notice( $slug, $class, $message, $dismissible = false ) {
		$this->notices[ $slug ] = array(
			'class'       => $class,
			'message'     => $message,
			'dismissible' => $dismissible,
		);
	}

	/**
	 * Display any notices we've collected thus far.
	 *
	 */
	public function admin_notices() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$this->cryptobillings_check_environment();

		foreach ( (array) $this->notices as $notice_key => $notice ) {
			echo '<div class="' . esc_attr( $notice['class'] ) . '" style="position:relative;">';

			if ( $notice['dismissible'] ) {
			?>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc-cryptobillings-hide-notice', $notice_key ), 'WC_Cryptobillings_hide_notices_nonce', '_WC_Cryptobillings_notice_nonce' ) ); ?>" class="woocommerce-message-close notice-dismiss" style="position:absolute;right:1px;padding:9px;text-decoration:none;"></a>
			<?php
			}

			echo '<p>';
			echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
			echo '</p></div>';
		}
	}

	/**
	 * The backup sanity check, in case the plugin is activated in a weird way,
	 * or the environment changes after activation. Also handles upgrade routines.
	 *
	 */
	public function cryptobillings_check_environment() {
		$show_keys_notice   = get_option( 'wc_cryptobillings_show_keys_notice' );
		$show_ssl_notice    = get_option( 'wc_cryptobillings_show_ssl_notice' );
		$show_phpver_notice = get_option( 'wc_cryptobillings_show_phpver_notice' );
		$show_wcver_notice  = get_option( 'wc_cryptobillings_show_wcver_notice' );
		$show_curl_notice   = get_option( 'wc_cryptobillings_show_curl_notice' );
		$options            = get_option( 'woocommerce_cryptobillings_settings' );
		$testmode           = ( isset( $options['testmode'] ) && 'yes' === $options['testmode'] ) ? true : false;
		$test_api_key       = isset( $options['test_api_key'] ) ? $options['test_api_key'] : '';
		$api_key            = isset( $options['api_key'] ) ? $options['api_key'] : '';

		if ( isset( $options['enabled'] ) && 'yes' === $options['enabled'] ) {
			if ( empty( $show_phpver_notice ) ) {
				if ( version_compare( phpversion(), WC_CRYPTOBILLINGS_MIN_PHP_VER, '<' ) ) {
					/* translators: 1) int version 2) int version */
					$message = __( 'WooCommerce Crypto Billings - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-cryptobillings-gateway' );

					$this->add_admin_notice( 'phpver', 'error', sprintf( $message, WC_CRYPTOBILLINGS_MIN_PHP_VER, phpversion() ), true );

					return;
				}
			}

			if ( empty( $show_wcver_notice ) ) {
				if ( version_compare( WC_VERSION, WC_CRYPTOBILLINGS_MIN_WC_VER, '<' ) ) {
					/* translators: 1) int version 2) int version */
					$message = __( 'WooCommerce Crypto Billings - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-cryptobillings-gateway' );

					$this->add_admin_notice( 'wcver', 'notice notice-warning', sprintf( $message, WC_CRYPTOBILLINGS_MIN_WC_VER, WC_VERSION ), true );

					return;
				}
			}

			if ( empty( $show_curl_notice ) ) {
				if ( ! function_exists( 'curl_init' ) ) {
					$this->add_admin_notice( 'curl', 'notice notice-warning', __( 'WooCommerce Crypto Billings - cURL is not installed.', 'woocommerce-cryptobillings-gateway' ), true );
				}
			}

			if ( empty( $show_keys_notice ) ) {
				$key = WC_Cryptobillings_API::get_api_key();

				if ( empty( $key ) && ! ( isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 'cryptobillings' === $_GET['section'] ) ) {
					$setting_link = $this->get_setting_link();
					/* translators: 1) link */
					$this->add_admin_notice( 'keys', 'notice notice-warning', sprintf( __( 'Crypto Billings is almost ready. To get started, <a href="%s">set your Crypto Billings API key</a>.', 'woocommerce-cryptobillings-gateway' ), $setting_link ), true );
				}
			}

			if ( empty( $show_ssl_notice ) ) {
				// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected.
				if ( ! wc_checkout_is_https() ) {
					/* translators: 1) link */
					$this->add_admin_notice( 'ssl', 'notice notice-warning', sprintf( __( 'Crypto Billings is enabled, but an SSL certificate is not detected. Your checkout may not be secure! Please ensure your server has a valid <a href="%1$s" target="_blank">SSL certificate</a>', 'woocommerce-cryptobillings-gateway' ), 'https://en.wikipedia.org/wiki/Transport_Layer_Security' ), true );
				}
			}
		}
	}

	/**
	 * Hides any admin notices.
	 *
	 */
	public function hide_notices() {
		if ( isset( $_GET['wc-cryptobillings-hide-notice'] ) && isset( $_GET['_WC_Cryptobillings_notice_nonce'] ) ) {
			if ( ! wp_verify_nonce( $_GET['_WC_Cryptobillings_notice_nonce'], 'WC_Cryptobillings_hide_notices_nonce' ) ) {
				wp_die( __( 'Action failed. Please refresh the page and retry.', 'woocommerce-cryptobillings-gateway' ) );
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-cryptobillings-gateway' ) );
			}

			$notice = wc_clean( $_GET['wc-cryptobillings-hide-notice'] );

			switch ( $notice ) {
				case 'phpver':
					update_option( 'WC_Cryptobillings_show_phpver_notice', 'no' );
					break;
				case 'wcver':
					update_option( 'WC_Cryptobillings_show_wcver_notice', 'no' );
					break;
				case 'curl':
					update_option( 'WC_Cryptobillings_show_curl_notice', 'no' );
					break;
				case 'keys':
					update_option( 'WC_Cryptobillings_show_keys_notice', 'no' );
					break;
				case 'ssl':
					update_option( 'WC_Cryptobillings_show_ssl_notice', 'no' );
					break;
			}
		}
	}

	/**
	 * Get setting link.
	 *
	 * @return string Setting link
	 */
	public function get_setting_link() {
		$use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;

		$section_slug = $use_id_as_section ? 'cryptobillings' : strtolower( 'WC_Cryptobillings_Gateway' );

		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
	}
}

new WC_Cryptobillings_Admin_Notices();
