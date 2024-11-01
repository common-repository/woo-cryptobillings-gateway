<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * WC_Cryptobillings_Gateway class.
 *
 */
use lexerom\cryptobillings\Payment;
use lexerom\cryptobillings\Item;
use lexerom\cryptobillings\AddressInfo;
use lexerom\cryptobillings\ShopInfo;

class WC_Cryptobillings_Gateway extends WC_Payment_Gateway
{

    /**
     * API access key
     *
     * @var string
     */
    public $api_key;

    /**
     * Supported Coins
     *
     * @var object
     */
    public $supported_coins;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id                 = 'cryptobillings';
        $this->method_title       = __('Crypto Billings', 'woocommerce-cryptobillings-gateway');
        /* translators: 1) link to Cryptobillings register page 2) link to Cryptobillings api keys page */
        $this->method_description = sprintf(__('Crypto Billings works by adding crypto payment fields on the checkout and then sending the details to a secure server for payment. <a href="%1$s" target="_blank">Sign up</a> for a Cryptobillings account, and <a href="%2$s" target="_blank">get your Cryptobillings API keys</a>.', 'woocommerce-cryptobillings-gateway'), 'https://cryptobillings.com/user/register', 'https://cryptobillings.com/user/profile');
        $this->has_fields         = false;

        $this->supported_coins = array(
            // 'GROW' => array(
                // 'name' => 'Grow',
                // 'icon' => plugins_url('assets/images/coins/GROW.png',WC_CRYPTOBILLINGS_MAIN_FILE),
                // 'default'=> true,
            // ),
            'DOPE' => array(
                'name' => 'Dopecoin',
                'icon' => plugins_url('assets/images/coins/DOPE.png', WC_CRYPTOBILLINGS_MAIN_FILE),
                'default'=> true,
            ),
            'BTC' => array(
                'name' => 'Bitcoin',
                'icon' => plugins_url('assets/images/coins/BTC.png', WC_CRYPTOBILLINGS_MAIN_FILE),
            ),
            'BCH' => array(
                'name' => 'Bitcoin Cash',
                'icon' => plugins_url('assets/images/coins/BCH.png', WC_CRYPTOBILLINGS_MAIN_FILE),
            ),
            'ETH' => array(
                'name' => 'Ethereum',
                'icon' => plugins_url('assets/images/coins/ETH.png', WC_CRYPTOBILLINGS_MAIN_FILE),
            ),
        );

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Get setting values.
        $this->title                       = $this->get_option('title');
        $this->description                 = $this->get_option('description');
        $this->instructions                = $this->get_option('instructions');
        $this->enabled                     = $this->get_option('enabled');
        $this->api_key                     = $this->get_option('api_key');
        $this->coin_list                   = $this->get_option('coin_list');
        $this->successUrl 				   = WC()->api_request_url(strtolower(get_class($this)).'_success') ;
        $this->cancelUrl  				   = WC()->api_request_url(strtolower(get_class($this)).'_cancel') ;
        $this->notifyUrl  				   = WC()->api_request_url(strtolower(get_class($this)).'_notify') ;

        WC_Cryptobillings_API::set_api_key($this->api_key);

        $this->allowed_coins = WC_Cryptobillings_API::get_allowed_coins($this->supported_coins, $this->coin_list);

        // Hooks.
        add_action('wp_enqueue_scripts', array( $this, 'payment_scripts' ));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));

        add_action('woocommerce_checkout_update_order_meta', array( $this,'coin_payment_update_order_meta'));

        add_action('woocommerce_admin_order_data_after_billing_address', array( $this, 'coin_checkout_field_display_admin_order_meta', 10, 1 ));

        add_action('woocommerce_api_' . strtolower(get_class($this)).'_success', array( $this, 'cryptobillings_success_return_handler' ));
        add_action('woocommerce_api_' . strtolower(get_class($this)).'_notify', array( $this, 'cryptobillings_notify_return_handler' ));
        add_action('woocommerce_api_' . strtolower(get_class($this)).'_cancel', array( $this, 'cryptobillings_cancel_return_handler' ));

        add_action('woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ));

        // Customer Emails
        add_action('woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3);
    }

    /**
     * Checks if keys are set.
     *
     */
    public function are_keys_set()
    {
        if (empty($this->api_key)) {
            return false;
        }
        return true;
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $options = array();

        foreach ($this->supported_coins as $coinSymbol => $coinData) {
            $options[$coinSymbol] = $coinData['name']." (".$coinSymbol.")";
        }

        $this->form_fields = require(dirname(__FILE__) . '/admin/cryptobillings-settings.php');
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {
        $user                 = wp_get_current_user();
        $total                = WC()->cart->total;
        $user_email           = '';
        $description          = ! empty($this->get_description()) ? $this->get_description() : '';
        $firstname            = '';
        $lastname             = '';

        $description = trim($description);

        echo apply_filters('wc_cryptobillings_description', wpautop(wp_kses_post($description)), $this->id);
        $this->elements_form();
    }

    /**
     * Renders the Cryptobillings elements form.
     *
     */
    public function elements_form()
    {
        $activeCoin = ''; ?>
			<div id="cryptobillings-payment-wrapper">
				<div id="coin-list">
					<label>
						<?= __('Select a coin to use', 'woocommerce-cryptobillings-gateway') ?>
					</label>
				</div>
				<div class="cryptobillings-coins-wrapper">
				<?php foreach ($this->allowed_coins as $key=>$coin) {
            $activeClass = '';
            if (isset($coin['default'])) {
                $activeClass = 'active-coin';
                $activeCoin = $key;
            } ?>
					<div class="coin-item-box">
						<a id="<?= $key ?>-wrapper" class="coin-item-click <?= $activeClass ?>" data-val="<?= $key ?>" href="javascript:void(0);" onclick="document.getElementById('coin-list-action').value=this.getAttribute('data-val');">
							<img class="coin-image" src="<?= $coin['icon'] ?>">
							<div class="coin-label">
								<!-- <?= $coin['name'] . ' ('. $key .')'; ?> -->
								<b><?= $key ?></b>
							</div>
						</a>
					</div>
				<?php
        } ?>
				</div>
				<div style="display: none;">
					<?php
                        woocommerce_form_field('coin_symbol', array(
                            'type'          => 'text',
                            'id' => 'coin-list-action'
                        ), $activeCoin); ?>
				</div>
			</div>
		<?php
    }

    /**
     * Payment_scripts function.
     *
     * Outputs scripts used for cryptobillings payment
     *
     */
    public function payment_scripts()
    {
        if (! is_cart() && ! is_checkout()) {
            return;
        }

        // If Cryptobillings is not enabled bail.
        if ('no' === $this->enabled) {
            return;
        }

        wp_register_style('cryptobillings_styles', plugins_url('assets/css/cryptobillings.css', WC_CRYPTOBILLINGS_MAIN_FILE), array(), WC_CRYPTOBILLINGS_VERSION);
        wp_enqueue_style('cryptobillings_styles');
        wp_register_script('cryptobillings_scripts', plugins_url('assets/js/cryptobillings.js', WC_CRYPTOBILLINGS_MAIN_FILE), array( 'jquery-payment' ), WC_CRYPTOBILLINGS_VERSION, true);
        wp_enqueue_script('cryptobillings_scripts');

        // If keys are not set bail.
        if (! $this->are_keys_set()) {
            wc_add_notice('Payment Gateway Failure: No Api Key has been set for Crypto Billings', 'error');
            return;
        }
    }

    /**
     * Handles the return from processing the payment.
     *
     */
    public function cryptobillings_success_return_handler()
    {

        // if ( ! wp_verify_nonce( $_GET['p1'], 'cryptobillings-checkout-process' ) ) {
        // return;
        // }

        if (isset($_GET['p1'],$_GET['p2'])) {
            $order_id = wc_clean($_GET['p1']);
            $order = wc_get_order($order_id);
            if ($order) {
                if (WC_Cryptobillings_API::check_request(WC_Cryptobillings_API::get_api_key(), get_woocommerce_currency(), $order->get_total(), $order_id, $_GET['p2'])) {
                    // Reduce stock levels
                    $order->reduce_order_stock();

                    // Remove cart
                    WC()->cart->empty_cart();

                    wp_redirect($order->get_checkout_order_received_url());
                    exit;
                }
            }
        }

        wp_redirect(get_home_url());


        exit;
    }

    /**
     * Handles the cancel return from crypto billings.
     *
     */
    public function cryptobillings_cancel_return_handler()
    {
        if (isset($_GET['p1'])) {
            $order_id = wc_clean($_GET['p1']);
            $order = wc_get_order($order_id);
            if ($order) {
                if (WC_Cryptobillings_API::check_request(WC_Cryptobillings_API::get_api_key(), get_woocommerce_currency(), $order->get_total(), $order_id, $_GET['p2'])) {
                    $order->update_status('cancelled', __('Crypto Billings payment cancelled', 'woocommerce-cryptobillings-gateway'));
                    wp_redirect(wc_get_cart_url());
                    exit;
                }
            }
        }

        wp_redirect(get_home_url());
    }

    /**
     * Handles the notification return from crypto billings.
     *
     */
    public function cryptobillings_notify_return_handler()
    {
        if (isset($_POST['p1'])) {
            $order_id = wc_clean($_POST['p1']);
            $payment_status = wc_clean($_POST['payment_status']);
            $order = wc_get_order($order_id);
            if ($order) {
                if (WC_Cryptobillings_API::check_request(WC_Cryptobillings_API::get_api_key(), get_woocommerce_currency(), $order->get_total(), $order_id, $_POST['p2'])) {
                    // 0-new, 1-pending,2-success,3-expired,4-cancelled
                    switch ($payment_status) {
                        case 0:
                        case 1:
                            $order->update_status('on-hold', __('Awaiting Crypto Billings payment', 'woocommerce-cryptobillings-gateway'));
                        break;
                        case 2:
                            $order->update_status('completed', __('Crypto Billings payment recieved', 'woocommerce-cryptobillings-gateway'));
                        break;
                        case 3:
                        case 4:
                        default:
                            $order->update_status('cancelled', __('Crypto Billings payment cancelled', 'woocommerce-cryptobillings-gateway'));
                        break;
                    }
                    exit;
                }
            }
        }
        wp_redirect(get_home_url());
    }

    /**
     * Update coin symbol
     *
     */

    public function coin_payment_update_order_meta($order_id)
    {
        if ($_POST['payment_method'] == 'cryptobillings') {
            update_post_meta($order_id, 'Coin Symbol', $_POST['coin_symbol']);
        }
    }

    public function coin_checkout_field_display_admin_order_meta($order)
    {
        echo '<p><strong>'.__('Coin Symbol').':</strong> ' . get_post_meta($order->id, 'Coin Symbol', true) . '</p>';
    }


    /**
     * Output for the order received page.
     */
    public function thankyou_page()
    {
        if ($this->instructions) {
            echo wpautop(wptexturize($this->instructions));
        }
    }


    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if ($this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status('on-hold')) {
            echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
        }
    }

    /**
     * Process the payment
     *
     * @return array|void
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $payment = new Payment(WC_Cryptobillings_API::get_api_key());

        $items = $order->get_items();
        $currency = get_woocommerce_currency();

        $itemArray = array();
        foreach ($order->get_items() as $item_key => $item_values) {
            $item_data = $item_values->get_data();
            $item = new Item();
            $item->description = $item_data['name'];
            $item->price = $item_data['subtotal'] / $item_data['quantity'];
            $item->currency = $currency;
            $item->quantity = $item_data['quantity'];
            $itemArray[] = $item;
        }

        $order_data = $order->get_data();

        //shipping price
        $item = new Item();
        $item->description = 'Shipping Fee';
        $item->price = $order_data['shipping_total'];
        $item->currency = $currency;
        $item->quantity = 1;
        $itemArray[] = $item;

        // BILLING INFORMATION:

        $billing = new AddressInfo();
        $billing->name = $order_data['billing']['first_name']. ' '.$order_data['billing']['last_name'];
        $billing->line1 = $order_data['billing']['address_1'];
        $billing->line2 = $order_data['billing']['address_2'];
        $billing->city = $order_data['billing']['city'];
        $billing->countryCode = $order_data['billing']['country'];
        $billing->postalCode = $order_data['billing']['postcode'];
        $billing->state = $order_data['billing']['state'];
        $billing->phone = $order_data['billing']['phone'];
        $billing->type = 'billing';

        // SHIPPING INFORMATION:

        $shipping = new AddressInfo();
        $shipping->name = $order_data['shipping']['first_name']. ' '.$order_data['shipping']['last_name'];
        $shipping->line1 = $order_data['shipping']['address_1'];
        $shipping->line2 = $order_data['shipping']['address_2'];
        $shipping->city = $order_data['shipping']['city'];
        $shipping->countryCode = $order_data['shipping']['country'];
        $shipping->postalCode = $order_data['shipping']['postcode'];
        $shipping->state = $order_data['shipping']['state'];
        $shipping->phone = $order_data['billing']['phone'];
        $shipping->type = 'shipping';

        // SHOP INFORMATION:

        $shopInfo = new ShopInfo();
        $shopInfo->shopUrl = get_home_url();
        $shopInfo->shopName = get_bloginfo('name');
        $shopInfo->shopEmail = get_bloginfo('admin_email');
        $shopInfo->customerEmail = $order_data['billing']['email'];

        $toCryptoCurrency = get_post_meta($order_id, 'Coin Symbol', true);

        $encrypt_key = WC_Cryptobillings_API::create_encrypt_key($this->api_key, $currency, $order->get_total(), $order_id);

        $gateway = $payment->createOrder($currency, $order->get_total(), $toCryptoCurrency, $this->instructions, $this->successUrl, $this->cancelUrl, $this->notifyUrl, $order_id, $encrypt_key, $itemArray, $shipping, $billing, $shopInfo);

        if (!isset($gateway->result->redirect_url)) {
            wc_add_notice('API Failure: '. $gateway->error, 'error');
            return array(
                'result'   => 'failure',
                'messages' =>  'API Failure: '. $gateway->error
            );
        }

        return array(
            'result' 	=> 'success',
            'redirect'	=> $gateway->result->redirect_url
        );
    }
}
