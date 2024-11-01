<?php
/**
 * Plugin Name: WooCommerce Crypto Billings Gateway
 * Plugin URI: https://wordpress.org/plugins/woo-cryptobillings-gateway/
 * Description: Take Crypto payments on your store using Crypto Billings.
 * Author: Crypto Billings Team
 * Author URI: https://cryptobillings.com/
 * Version: 1.1.0
 * Requires at least: 4.4
 * Tested up to: 5.6.2
 * WC requires at least: 2.6.0
 * WC tested up to: 5.0.0
 * Text Domain: woocommerce-cryptobillings-gateway
 *
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce fallback notice.
 *
 * @return string
 */
function woocommerce_cryptobillings_missing_wc_notice()
{
    echo '<div class="error"><p><strong>' . sprintf(esc_html__('Crypto Billings requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-cryptobillings-gateway'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
}

add_action('plugins_loaded', 'woocommerce_cryptobillings_gateway_init');

function woocommerce_cryptobillings_gateway_init()
{
    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', 'woocommerce_cryptobillings_missing_wc_notice');
        return;
    }

    if (! class_exists('WC_Cryptobillings')) :
    /**
     * Required minimums and constants
     */
    define('WC_CRYPTOBILLINGS_VERSION', '1.1.0');
    define('WC_CRYPTOBILLINGS_MIN_PHP_VER', '5.6.0');
    define('WC_CRYPTOBILLINGS_MIN_WC_VER', '2.6.0');
    define('WC_CRYPTOBILLINGS_MAIN_FILE', __FILE__);
    define('WC_CRYPTOBILLINGS_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
    define('WC_CRYPTOBILLINGS_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

    class WC_Cryptobillings
    {

            /**
             * @var Singleton The reference the *Singleton* instance of this class
             */
        private static $instance;

        /**
         * @var Reference to logging class.
         */
        private static $log;

        /**
         * Returns the *Singleton* instance of this class.
         *
         * @return Singleton The *Singleton* instance.
         */
        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Private clone method to prevent cloning of the instance of the
         * *Singleton* instance.
         *
         * @return void
         */
        private function __clone()
        {
        }

        /**
         * Private unserialize method to prevent unserializing of the *Singleton*
         * instance.
         *
         * @return void
         */
        private function __wakeup()
        {
        }

        /**
         * Protected constructor to prevent creating a new instance of the
         * *Singleton* via the `new` operator from outside of this class.
         */
        private function __construct()
        {
            add_action('admin_init', array( $this, 'install' ));
            $this->init();
        }

        /**
         * Init the plugin after plugins_loaded so environment variables are set.
         *
         */
        public function init()
        {
            if (is_admin()) {
                require_once(dirname(__FILE__) . '/includes/admin/class-wc-cryptobillings-privacy.php');
            }

            require_once(dirname(__FILE__) . '/lib/vendor/autoload.php');
            include_once(dirname(__FILE__) . '/includes/class-wc-cryptobillings-api.php');
            require_once(dirname(__FILE__) . '/includes/class-wc-cryptobillings-gateway.php');

            if (is_admin()) {
                require_once(dirname(__FILE__) . '/includes/admin/class-wc-cryptobillings-admin-notices.php');
            }

            add_filter('woocommerce_payment_gateways', array( $this, 'add_gateways' ));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'plugin_action_links' ));

            if (version_compare(WC_VERSION, '3.4', '<')) {
                add_filter('woocommerce_get_sections_checkout', array( $this, 'filter_gateway_order_admin' ));
            }
        }

        /**
         * Updates the plugin version in db
         *
         */
        public function update_plugin_version()
        {
            delete_option('wc_cryptobillings_version');
            update_option('wc_cryptobillings_version', WC_CRYPTOBILLINGS_VERSION);
        }

        /**
         * Handles upgrade routines.
         *
         */
        public function install()
        {
            if (! is_plugin_active(plugin_basename(__FILE__))) {
                return;
            }

            if (! defined('IFRAME_REQUEST') && (WC_CRYPTOBILLINGS_VERSION !== get_option('wc_cryptobillings_version'))) {
                do_action('woocommerce_cryptobillings_updated');

                if (! defined('WC_CRYPTOBILLINGS_INSTALLING')) {
                    define('WC_CRYPTOBILLINGS_INSTALLING', true);
                }

                $this->update_plugin_version();
            }
        }

        /**
         * Adds plugin action links.
         *
         */
        public function plugin_action_links($links)
        {
            $plugin_links = array(
                    '<a href="admin.php?page=wc-settings&tab=checkout&section=cryptobillings">' . esc_html__('Settings', 'woocommerce-cryptobillings-gateway') . '</a>',
                    '<a href="https://cryptobillings.com">' . esc_html__('Website', 'woocommerce-cryptobillings-gateway') . '</a>',
                    '<a href="https://cryptobillings.com/files/crypto-billings-payment-gateway.pdf" target="_blank">' . esc_html__('Docs', 'woocommerce-cryptobillings-gateway') . '</a>',
                );
            return array_merge($plugin_links, $links);
        }

        /**
         * Add the gateways to WooCommerce.
         *
         */
        public function add_gateways($methods)
        {
            $methods[] = 'WC_Cryptobillings_Gateway';
            return $methods;
        }

        /**
         * Modifies the order of the gateways displayed in admin.
         *
         */
        public function filter_gateway_order_admin($sections)
        {
            unset($sections['cryptobillings']);
            $sections['cryptobillings'] = 'Crypto Billings';
            return $sections;
        }
    }

    WC_Cryptobillings::get_instance();
    endif;
}
